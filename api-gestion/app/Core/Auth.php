<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Usuario.php';

/**
 * Guard de autenticación de toda la app (antes repetido en las 4 apps
 * separadas; acá vive una sola vez).
 *
 * La sesión viaja en una COOKIE HttpOnly firmada (`cathartes_sesion`), no en
 * `Authorization: Bearer` ni en localStorage. El token es HMAC-SHA256 con
 * `typ='sesion'`, emitido por AccesoController al validar usuario/contraseña.
 *
 * proteger() — para los endpoints /api/* (JSON) — aplica, en orden:
 *   1) firma de la cookie (Token::validar 'sesion'),
 *   2) kill-switch: re-consulta usuario.estado; si no es 'activo' -> 401 y
 *      borra la cookie.
 * La AUTORIZACIÓN POR ROL la hace cada endpoint: compara el rol devuelto con
 * el que exige y responde 403 si no coincide.
 *
 * exigir() — para las pantallas del backoffice (HTML) — hace lo mismo y
 * además exige que el usuario esté en la tabla `administrador`; si no,
 * responde 403 server-rendered.
 *
 * La sesión PHP ($_SESSION) se usa solo para el token CSRF de los POST del
 * backoffice; no guarda datos de autenticación.
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

    // ------------------------------------------------------- guard API ----
    /** @return array{id:int,rol:?string,nombre:string,email:string} */
    public static function proteger(): array
    {
        $token = self::cookieToken();
        if ($token === null) {
            self::rechazarJson();
        }

        $payload = Token::validar($token, 'sesion');
        if ($payload === null) {
            self::borrarCookie();
            self::rechazarJson('Sesión inválida');
        }

        $id = (int) $payload['id'];
        $u  = Usuario::porId($id);
        if ($u === null || $u['estado'] !== 'activo') {
            self::borrarCookie();
            self::rechazarJson('Sesión inválida');
        }

        return [
            'id'     => $id,
            'rol'    => self::rolDe($id),
            'nombre' => (string) $u['nombre'],
            'email'  => (string) $u['email'],
        ];
    }

    // ------------------------------------------------- guard backoffice ----
    /** Exige admin autenticado. Devuelve ['id','nombre']. Corta el flujo si no. */
    public static function exigir(): array
    {
        $token   = self::cookieToken();
        $payload = $token !== null ? Token::validar($token, 'sesion') : null;
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
        $token   = self::cookieToken();
        $payload = $token !== null ? Token::validar($token, 'sesion') : null;
        if ($payload === null) {
            return false;
        }
        $id = (int) $payload['id'];
        $u  = Usuario::porId($id);
        return $u !== null && $u['estado'] === 'activo' && Usuario::esAdmin($id);
    }

    /**
     * Rol deducido de en qué tabla de subtipo está el ID (el MER no guarda el rol
     * como atributo). Orden: administrador > operario > cuadrilla > vecino.
     * Devuelve null si el ID no está en NINGÚN subtipo: estado inconsistente, no
     * se lo deja pasar (el llamador lo trata como acceso denegado).
     */
    public static function rolDe(int $id): ?string
    {
        $pdo = Database::conexion();
        foreach (['administrador', 'operario', 'cuadrilla', 'vecino'] as $rol) {
            $st = $pdo->prepare("SELECT 1 FROM {$rol} WHERE ID_usuario = ? LIMIT 1");
            $st->execute([$id]);
            if ($st->fetchColumn()) {
                return $rol;
            }
        }
        return null;
    }

    private static function cookieToken(): ?string
    {
        $t = $_COOKIE[self::COOKIE] ?? '';
        return (is_string($t) && $t !== '') ? $t : null;
    }

    /** Borra la cookie de sesión (para 401/403 por kill-switch o firma inválida). */
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
    private static function rechazarJson(string $mensaje = 'No autorizado'): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $mensaje], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function aLogin(): void
    {
        header('Location: ' . (string) Config::get('app.login_url', ''));
        exit;
    }

    /** 403 server-rendered: mensaje genérico + link al inicio del rol real. */
    private static function prohibido(int $id): void
    {
        $rol   = Usuario::rolDe($id);
        $mapa  = [
            'operario'  => hermana('frontend-usuarios', 'operario.html'),
            'cuadrilla' => hermana('frontend-usuarios', 'cuadrilla.html'),
            'vecino'    => hermana('frontend-usuarios', 'inicio.html'),
        ];
        $inicioUrl = $mapa[$rol] ?? (string) Config::get('app.login_url', '');

        http_response_code(403);
        require BASE_PATH . '/app/Views/pantallas/403.php';
        exit;
    }
}
