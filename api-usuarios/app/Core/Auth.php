<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Usuario.php';

/**
 * Guard de autenticación de la API + detección de rol.
 *
 * proteger() es el kill-switch: valida el Bearer como token de sesión y ADEMÁS
 * re-chequea usuario.estado='activo' contra la base. Si un admin hace
 * `UPDATE usuario SET estado='no activo'`, el siguiente request protegido de ese
 * usuario se cae solo (401). Corta la ejecución con 401 si algo falla.
 */
class Auth
{
    /** @return array{id:int,rol:string,nombre:string,email:string} */
    public static function proteger(): array
    {
        $token = self::bearer();
        if ($token === null) {
            self::rechazar();
        }

        $payload = Token::validar($token, 'sesion');
        if ($payload === null) {
            self::rechazar('Token inválido');
        }

        $id = (int) $payload['id'];
        $u  = Usuario::porId($id);
        if ($u === null || $u['estado'] !== 'activo') {
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
     * El rol se deduce de en qué tabla de subtipo está el ID (el MER no guarda
     * el rol como atributo). Orden: administrador > operario > cuadrilla > vecino.
     */
    public static function rolDe(int $id): string
    {
        $pdo = Database::conexion();
        foreach (['administrador', 'operario', 'cuadrilla', 'vecino'] as $rol) {
            $st = $pdo->prepare("SELECT 1 FROM {$rol} WHERE ID_usuario = ? LIMIT 1");
            $st->execute([$id]);
            if ($st->fetchColumn()) {
                return $rol;
            }
        }
        return 'vecino';
    }

    private static function bearer(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        if ($header === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $clave => $valor) {
                if (strcasecmp($clave, 'Authorization') === 0) {
                    $header = $valor;
                    break;
                }
            }
        }

        if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private static function rechazar(string $mensaje = 'No autorizado'): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $mensaje], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
