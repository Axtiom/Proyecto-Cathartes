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

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Mailer::enviar(
            (string) $u['email'],
            'Tu código de verificación · Cathartes',
            "Tu código es: {$codigo}\nVence en 5 minutos. Si no fuiste vos, ignorá este correo."
        );

        $preauth = Token::emitir2fa($id, $rol, $recordar, $codigo);

        $resp = [
            'ok'      => true,
            'need2fa' => true,
            'preauth' => $preauth,
            'destino' => self::enmascarar((string) $u['email']),
        ];
        // solo en dev (stub): devolver el código para poder probar sin mail real
        if (Config::get('mail.driver', 'stub') === 'stub') {
            $resp['debug_codigo'] = $codigo;
        }

        $this->json($resp);
    }

    // POST /verificar
    public function verificar(): void
    {
        $in = $this->entrada();
        $preauth = (string) ($in['preauth'] ?? '');
        $codigo  = (string) ($in['codigo'] ?? '');

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
        $u = Usuario::porId((int) $p['id']);
        if ($u === null || $u['estado'] !== 'activo') {
            $this->error('Tu cuenta todavía no fue aprobada.', 403);
            return;
        }

        // TODO(futuro): limitar intentos de código (rate limit). No implementado aún.
        $token = Token::emitirSesion((int) $p['id'], (string) $p['rol']);

        $this->json([
            'ok'       => true,
            'token'    => $token,
            'recordar' => (bool) ($p['recordar'] ?? false),
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

    // POST /logout — los tokens son stateless, no hay nada que revocar acá.
    public function logout(): void
    {
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
