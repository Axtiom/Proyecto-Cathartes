<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Usuario.php';

/**
 * Guard del Backoffice (Fase 3.95 — sesión unificada).
 *
 * El backoffice NO tiene login propio ni sesión de auth: lee la MISMA cookie
 * HttpOnly que emite la API de Usuarios al validar el 2FA (`cathartes_sesion`,
 * token HMAC typ='sesion', firmado con el APP_TOKEN_SECRET compartido) y verifica.
 *
 * exigir() aplica, en orden:
 *   1) firma de la cookie (Token::validar 'sesion'). Sin cookie válida -> login.
 *   2) kill-switch: usuario.estado='activo'. Si no -> borra cookie y a login.
 *   3) guardia de rol: el ID debe estar en la tabla `administrador`. Si no -> 403.
 *
 * La sesión PHP ($_SESSION) se conserva SOLO para el token CSRF de los POST; no
 * guarda datos de autenticación.
 */
class Auth
{
    public const COOKIE = 'cathartes_sesion';

    public static function iniciar(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /** Exige admin autenticado. Devuelve ['id','nombre']. Corta el flujo si no. */
    public static function exigir(): array
    {
        $token   = $_COOKIE[self::COOKIE] ?? '';
        $payload = (is_string($token) && $token !== '') ? Token::validar($token, 'sesion') : null;
        if ($payload === null) {
            self::aLogin();
        }

        $id = (int) $payload['id'];
        $u  = Usuario::porId($id);
        if ($u === null || $u['estado'] !== 'activo') {
            self::borrarCookie();
            self::aLogin();
        }
        if (!Usuario::esAdmin($id)) {
            self::prohibido($id);   // 403: autenticado pero no es administrador
        }

        return ['id' => $id, 'nombre' => (string) $u['nombre']];
    }

    /** ¿Hay un admin válido? (para index sin cortar el flujo). */
    public static function adminValido(): bool
    {
        $token   = $_COOKIE[self::COOKIE] ?? '';
        $payload = (is_string($token) && $token !== '') ? Token::validar($token, 'sesion') : null;
        if ($payload === null) {
            return false;
        }
        $id = (int) $payload['id'];
        $u  = Usuario::porId($id);
        return $u !== null && $u['estado'] === 'activo' && Usuario::esAdmin($id);
    }

    public static function borrarCookie(): void
    {
        setcookie(self::COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    // ------------------------------------------------------------ CSRF ----
    public static function csrf(): string
    {
        self::iniciar();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function verificarCsrf(): void
    {
        self::iniciar();
        $t = $_POST['csrf'] ?? '';
        if (!is_string($t) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
            http_response_code(419);
            exit('Sesión vencida o token inválido. Volvé a intentar.');
        }
    }

    // ------------------------------------------------------ redirects ----
    private static function aLogin(): void
    {
        header('Location: ' . (string) Config::get('app.login_url', ''));
        exit;
    }

    /** 403 server-rendered: mensaje genérico + link al inicio del rol real. */
    private static function prohibido(int $id): void
    {
        $rol   = Usuario::rolDe($id);
        $front = rtrim((string) Config::get('app.frontend_url', ''), '/');
        $mapa  = [
            'operario'  => $front . '/operario.html',
            'cuadrilla' => $front . '/cuadrilla.html',
            'vecino'    => $front . '/inicio.html',
        ];
        $inicioUrl = $mapa[$rol] ?? (string) Config::get('app.login_url', '');

        http_response_code(403);
        require BASE_PATH . '/app/Views/pantallas/403.php';
        exit;
    }
}
