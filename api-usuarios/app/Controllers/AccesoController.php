<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Usuario.php';

/**
 * Endpoints de autenticación de la API de Usuarios.
 *
 * Convención de respuestas: éxito {"ok":true, ...}; error
 * {"ok":false,"error":"...","campo":"opcional"}. Todo con prepared statements
 * (via el modelo Usuario) y `contrasena` con backticks. No se toca el MER.
 */
class AccesoController extends Controller
{
    private const MIN_PASS = 8;
    private const PREAUTH_COOKIE = 'cathartes_preauth';

    // POST /registro
    public function registro(): void
    {
        $in = $this->entrada();
        $nombre   = trim((string) ($in['nombre'] ?? ''));
        $apellido = trim((string) ($in['apellido'] ?? ''));
        $telefono = trim((string) ($in['telefono'] ?? ''));
        $email    = trim((string) ($in['email'] ?? ''));
        $pass     = (string) ($in['password'] ?? '');
        $passC    = (string) ($in['password_confirm'] ?? '');

        if ($nombre === '' || $apellido === '' || $telefono === '' || $email === '' || $pass === '') {
            $this->error('Faltan completar campos.', 400);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('El correo no es válido.', 400, 'email');
            return;
        }
        if (strlen($pass) < self::MIN_PASS) {
            $this->error('La contraseña debe tener al menos 8 caracteres.', 400, 'password');
            return;
        }
        if ($pass !== $passC) {
            $this->error('Las contraseñas no coinciden.', 400, 'password_confirm');
            return;
        }
        if (Usuario::existeEmail($email)) {
            $this->error('Ya existe una cuenta con ese correo.', 409, 'email');
            return;
        }

        $nombreCompleto = trim($nombre . ' ' . $apellido);
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        Usuario::crearVecino($nombreCompleto, $email, $hash, $telefono);

        $this->json([
            'ok'      => true,
            'mensaje' => 'Cuenta creada. Queda pendiente de aprobación del administrador.',
        ], 201);
    }

    // POST /login
    public function login(): void
    {
        $in = $this->entrada();
        $email    = trim((string) ($in['email'] ?? ''));
        $pass     = (string) ($in['password'] ?? '');
        $recordar = (bool) ($in['recordar'] ?? false);

        $u = Usuario::porEmail($email);
        if ($u === null || !password_verify($pass, (string) $u['hash'])) {
            // mismo mensaje para "no existe" y "clave mal": no revelar cuál
            $this->error('Credenciales inválidas.', 401);
            return;
        }
        if ($u['estado'] !== 'activo') {
            $this->error('Tu cuenta todavía no fue aprobada.', 403);
            return;
        }

        $id  = (int) $u['ID'];
        $rol = Auth::rolDe($id);
        if ($rol === null) {
            // Sin subtipo = estado inconsistente: no se lo deja pasar (se registra).
            error_log("[auth] usuario {$id} sin tabla de subtipo: acceso denegado en login");
            $this->error('Tu cuenta no tiene un rol asignado. Contactá al administrador.', 403);
            return;
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Mailer::enviar(
            (string) $u['email'],
            'Tu código de verificación · Cathartes',
            "Tu código es: {$codigo}\nVence en 5 minutos. Si no fuiste vos, ignorá este correo."
        );

        // El preauth (2FA, ~5 min) viaja en cookie HttpOnly: el JS ni lo toca.
        $preauth = Token::emitir2fa($id, $rol, $recordar, $codigo);
        self::setCookie(self::PREAUTH_COOKIE, $preauth, time() + 300);

        $resp = [
            'ok'      => true,
            'need2fa' => true,
            'destino' => self::enmascarar((string) $u['email']),
        ];
        // solo en dev (stub): devolver el código para poder probar sin mail real
        if (Config::get('mail.driver', 'stub') === 'stub') {
            $resp['debug_codigo'] = $codigo;
        }

        $this->json($resp);
    }

    // POST /verificar — valida el código 2FA, SETEA la cookie de sesión y devuelve
    // el rol + el destino (URL) al que el frontend debe redirigir. El preauth se
    // lee de la cookie HttpOnly (no del body): el JS no lo maneja.
    public function verificar(): void
    {
        $preauth = (string) ($_COOKIE[self::PREAUTH_COOKIE] ?? '');
        $codigo  = (string) ($this->entrada()['codigo'] ?? '');

        $p = Token::validar($preauth, '2fa');
        if ($p === null) {
            $this->error('Código expirado, volvé a ingresar.', 401);
            return;
        }
        if (!hash_equals((string) $p['code_hash'], hash('sha256', $codigo))) {
            $this->error('Código incorrecto.', 401);
            return;
        }

        // re-chequear estado fresco (pudo desactivarse entre login y verificación)
        $id = (int) $p['id'];
        $u  = Usuario::porId($id);
        if ($u === null || $u['estado'] !== 'activo') {
            $this->error('Tu cuenta todavía no fue aprobada.', 403);
            return;
        }
        // rol autoritativo al momento de verificar (el subtipo pudo cambiar).
        $rol = Auth::rolDe($id);
        if ($rol === null) {
            error_log("[auth] usuario {$id} sin tabla de subtipo: acceso denegado en verificar");
            $this->error('Tu cuenta no tiene un rol asignado. Contactá al administrador.', 403);
            return;
        }

        // TODO(futuro): limitar intentos de código (rate limit). No implementado aún.
        $recordar = (bool) ($p['recordar'] ?? false);
        $ttl = $recordar ? time() + (int) Config::get('token.ttl', 7776000) : 0; // 0 = cookie de sesión
        self::setCookie(Auth::COOKIE, Token::emitirSesion($id, $rol), $ttl);
        self::borrarCookie(self::PREAUTH_COOKIE);

        $this->json([
            'ok'      => true,
            'rol'     => $rol,
            'destino' => self::destino($rol),
        ]);
    }

    // POST /recuperar
    public function recuperar(): void
    {
        $in = $this->entrada();
        $email = trim((string) ($in['email'] ?? ''));

        $u = Usuario::porEmail($email);
        if ($u !== null) {
            $reset = Token::emitirReset((int) $u['ID']);
            $base  = rtrim((string) Config::get('app.frontend_url', 'http://127.0.0.1:8080'), '/');
            $url   = $base . '/restablecer?token=' . $reset;
            Mailer::enviar(
                (string) $u['email'],
                'Restablecer tu contraseña · Cathartes',
                "Para elegir una nueva contraseña entrá a:\n{$url}\nEl enlace vence en 1 hora."
            );
        }

        // siempre la misma respuesta: no revelar si el correo existe
        $this->json([
            'ok'      => true,
            'mensaje' => 'Si el correo existe, te enviamos un enlace.',
        ]);
    }

    // POST /restablecer
    public function restablecer(): void
    {
        $in = $this->entrada();
        $token = (string) ($in['token'] ?? '');
        $pass  = (string) ($in['password'] ?? '');
        $passC = (string) ($in['password_confirm'] ?? '');

        $r = Token::validar($token, 'reset');
        if ($r === null) {
            $this->error('Enlace inválido o vencido.', 400);
            return;
        }
        if (strlen($pass) < self::MIN_PASS) {
            $this->error('La contraseña debe tener al menos 8 caracteres.', 400, 'password');
            return;
        }
        if ($pass !== $passC) {
            $this->error('Las contraseñas no coinciden.', 400, 'password_confirm');
            return;
        }

        Usuario::actualizarPassword((int) $r['id'], password_hash($pass, PASSWORD_DEFAULT));
        $this->json(['ok' => true, 'mensaje' => 'Contraseña actualizada.']);
    }

    // POST /logout — borra la cookie de sesión (y la de preauth por las dudas).
    // Como la cookie es del host con Path=/, cerrar sesión en una app cierra en
    // todas (backoffice y frontend comparten la misma cookie).
    public function logout(): void
    {
        Auth::borrarCookie();
        self::borrarCookie(self::PREAUTH_COOKIE);
        $this->json(['ok' => true]);
    }

    // GET /yo (protegido) — prueba de token + kill-switch, reusado por el frontend.
    public function yo(): void
    {
        $ctx = Auth::proteger();
        $this->json([
            'ok'      => true,
            'usuario' => [
                'id'     => $ctx['id'],
                'nombre' => $ctx['nombre'],
                'email'  => $ctx['email'],
                'rol'    => $ctx['rol'],
            ],
        ]);
    }

    // GET /saldo (protegido) — puntos de gamificación del vecino en sesión.
    // Lo consume el badge del header del Frontend de Usuarios. saldo=null si el
    // usuario no es vecino (otros roles no tienen gamificación).
    public function saldo(): void
    {
        $ctx = Auth::proteger();
        $this->json([
            'ok'    => true,
            'saldo' => Usuario::saldoVecino((int) $ctx['id']),
        ]);
    }

    private function error(string $mensaje, int $codigo, ?string $campo = null): void
    {
        $resp = ['ok' => false, 'error' => $mensaje];
        if ($campo !== null) {
            $resp['campo'] = $campo;
        }
        $this->json($resp, $codigo);
    }

    // --- Cookies de sesión (Fase 3.95) --------------------------------------
    /** Setea una cookie HttpOnly, SameSite=Lax, Path=/, Secure según .env. */
    private static function setCookie(string $nombre, string $valor, int $expires): void
    {
        setcookie($nombre, $valor, [
            'expires'  => $expires,   // 0 = cookie de sesión de navegador
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (bool) Config::get('cookie.secure', false),
        ]);
    }

    private static function borrarCookie(string $nombre): void
    {
        setcookie($nombre, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /** URL de destino post-2FA según el rol (config-driven, nada hardcodeado). */
    private static function destino(string $rol): string
    {
        $front = rtrim((string) Config::get('app.frontend_url', ''), '/');
        switch ($rol) {
            case 'administrador': return (string) Config::get('app.backoffice_url', '');
            case 'operario':      return $front . '/operario.html';
            case 'cuadrilla':     return $front . '/cuadrilla.html';
            default:              return $front . '/inicio.html';   // vecino
        }
    }

    /** juan.perez@correo.com → j***@correo.com */
    private static function enmascarar(string $email): string
    {
        $at = strpos($email, '@');
        if ($at === false || $at < 1) {
            return '***';
        }
        return substr($email, 0, 1) . '***' . substr($email, $at);
    }
}
