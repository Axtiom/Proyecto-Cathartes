<?php
declare(strict_types=1);

/**
 * Manejo de la sesión del Frontend de Usuarios.
 *
 * Guarda el token firmado que emite la API y lo manda en cada request como
 * Authorization: Bearer. Acá también viven:
 *   - el CSRF por sesión (token sincronizador para todos los POST),
 *   - el `preauth` del 2FA entre /login y /verificar,
 *   - la lógica de "Recordarme":
 *       tildado    -> cookie persistente (~90 días),
 *       destildado -> cookie de sesión de navegador (muere al cerrarlo).
 *     Sin timeout por inactividad: nunca se expulsa a un usuario en uso activo.
 */
class Sesion
{
    /** 90 días, igual que APP_TOKEN_TTL de la API (Recordarme). */
    private const TTL_RECORDAR = 7776000;

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0, // por defecto: cookie de sesión (se ajusta al recordar)
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    // ---------- CSRF ----------

    public static function csrf(): string
    {
        self::iniciar();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function csrfValido(?string $valor): bool
    {
        self::iniciar();
        return is_string($valor)
            && !empty($_SESSION['_csrf'])
            && hash_equals((string) $_SESSION['_csrf'], $valor);
    }

    // ---------- Token de sesión ----------

    public static function guardarToken(string $token, bool $recordar = false): void
    {
        self::iniciar();
        session_regenerate_id(true); // evita fijación de sesión al autenticar

        $_SESSION['token']    = $token;
        $_SESSION['recordar'] = $recordar;

        $expira = $recordar ? time() + self::TTL_RECORDAR : 0; // 0 = cookie de sesión
        setcookie(session_name(), session_id(), [
            'expires'  => $expira,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function token(): ?string
    {
        self::iniciar();
        return $_SESSION['token'] ?? null;
    }

    public static function autenticado(): bool
    {
        return self::token() !== null;
    }

    // ---------- Pre-auth 2FA ----------

    public static function guardarPreauth(string $preauth): void
    {
        self::iniciar();
        $_SESSION['preauth'] = $preauth;
    }

    public static function preauth(): ?string
    {
        self::iniciar();
        return $_SESSION['preauth'] ?? null;
    }

    public static function borrarPreauth(): void
    {
        self::iniciar();
        unset($_SESSION['preauth']);
    }

    // ---------- Mensajes flash (una sola lectura) ----------

    public static function flash(string $mensaje): void
    {
        self::iniciar();
        $_SESSION['_flash'] = $mensaje;
    }

    public static function tomarFlash(): ?string
    {
        self::iniciar();
        $m = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return $m;
    }

    // ---------- Logout ----------

    /**
     * Cierra la sesión: borra el token/preauth y baja la persistencia de la
     * cookie (vuelve a cookie de sesión). Conserva el CSRF para el próximo form.
     */
    public static function destruir(): void
    {
        self::iniciar();
        unset($_SESSION['token'], $_SESSION['recordar'], $_SESSION['preauth']);

        setcookie(session_name(), session_id(), [
            'expires'  => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_regenerate_id(true);
    }
}
