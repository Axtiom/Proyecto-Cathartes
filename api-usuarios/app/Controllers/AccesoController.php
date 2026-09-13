<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Usuario.php';

/**
 * Endpoints de autenticación (antes la API de Usuarios). Login de un solo
 * paso: usuario + contraseña alcanzan para abrir sesión, sin verificación en
 * dos pasos por correo.
 *
 * Convención de respuestas: éxito {"ok":true, ...}; error
 * {"ok":false,"error":"...","campo":"opcional"}. Todo con prepared statements
 * (via el modelo Usuario) y `contrasena` con backticks. No se toca el MER.
 */
class AccesoController extends Controller
{
    private const MIN_PASS = 8;

    // POST /api/registro
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

    // POST /api/login — válida usuario/contraseña y abre sesión directamente
    // (sin segundo factor): setea la cookie de sesión y devuelve el destino
    // según el rol, al que el frontend redirige.
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

        $ttl = $recordar ? time() + (int) Config::get('token.ttl', 7776000) : 0; // 0 = cookie de sesión
        self::setCookie(Auth::COOKIE, Token::emitirSesion($id, $rol), $ttl);

        $this->json([
            'ok'      => true,
            'rol'     => $rol,
            'destino' => self::destino($rol),
        ]);
    }

    // POST /api/recuperar
    public function recuperar(): void
    {
        $in = $this->entrada();
        $email = trim((string) ($in['email'] ?? ''));

        $u = Usuario::porEmail($email);
        if ($u !== null) {
            $reset = Token::emitirReset((int) $u['ID']);
            $url   = hermana('frontend-usuarios', 'restablecer?token=' . $reset);
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

    // POST /api/restablecer
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

    // POST /api/logout — borra la cookie de sesión. Como es del host con
    // Path=/, cerrar sesión acá cierra en todas las pantallas (backoffice
    // incluido: comparten la misma cookie).
    public function logout(): void
    {
        Auth::borrarCookie();
        $this->json(['ok' => true]);
    }

    // GET /api/yo (protegido) — prueba de sesión + kill-switch, reusado por el frontend.
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

    private function error(string $mensaje, int $codigo, ?string $campo = null): void
    {
        $resp = ['ok' => false, 'error' => $mensaje];
        if ($campo !== null) {
            $resp['campo'] = $campo;
        }
        $this->json($resp, $codigo);
    }

    // --- Cookie de sesión ----------------------------------------------------
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

    /** URL de destino post-login según el rol (config-driven, nada hardcodeado). */
    private static function destino(string $rol): string
    {
        switch ($rol) {
            case 'administrador': return hermana('backoffice', 'usuarios');
            case 'operario':      return hermana('frontend-usuarios', 'operario.html');
            case 'cuadrilla':     return hermana('frontend-usuarios', 'cuadrilla.html');
            default:              return hermana('frontend-usuarios', 'inicio.html'); // vecino
        }
    }
}
