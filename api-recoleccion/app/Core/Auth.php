<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Usuario.php';

/**
 * Guard de autenticación de la API + detección de rol.
 *
 * Fase 3.95 (sesión unificada): la sesión viaja en una COOKIE HttpOnly firmada
 * (`cathartes_sesion`), no más `Authorization: Bearer` desde localStorage. El
 * token es el mismo HMAC-SHA256 con `typ='sesion'`; la cookie la setea la API de
 * Usuarios al validar el 2FA. Acá NO se crea tabla de tokens: el esquema no cambia.
 *
 * proteger() aplica, en orden:
 *   1) firma de la cookie (Token::validar 'sesion'),
 *   2) kill-switch: re-consulta usuario.estado; si no es 'activo' -> 401 y BORRA
 *      la cookie (SameSite=Lax deja la sesión abierta hasta el próximo request).
 * La AUTORIZACIÓN POR ROL la hace cada endpoint: compara el rol devuelto (deducido
 * del subtipo) con el que exige y responde 403 si no coincide. guardia.js del
 * frontend es solo UX: el límite duro está acá.
 */
class Auth
{
    public const COOKIE = 'cathartes_sesion';

    /** @return array{id:int,rol:?string,nombre:string,email:string} */
    public static function proteger(): array
    {
        $token = self::cookieToken();
        if ($token === null) {
            self::rechazar();
        }

        $payload = Token::validar($token, 'sesion');
        if ($payload === null) {
            self::borrarCookie();
            self::rechazar('Sesión inválida');
        }

        $id = (int) $payload['id'];
        $u  = Usuario::porId($id);
        if ($u === null || $u['estado'] !== 'activo') {
            self::borrarCookie();
            self::rechazar('Sesión inválida');
        }

        return [
            'id'     => $id,
            'rol'    => self::rolDe($id),
            'nombre' => (string) $u['nombre'],
            'email'  => (string) $u['email'],
        ];
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

    /** Borra la cookie de sesión (para 401 por kill-switch / firma inválida). */
    public static function borrarCookie(): void
    {
        setcookie(self::COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function rechazar(string $mensaje = 'No autorizado'): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $mensaje], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
