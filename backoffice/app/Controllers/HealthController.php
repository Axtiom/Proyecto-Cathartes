<?php
declare(strict_types=1);

/** GET /health -> JSON {ok, db}. Verifica motor + conexión a `cathartes`. */
class HealthController extends Controller
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            Database::conexion()->query('SELECT 1');
            echo json_encode(['ok' => true, 'db' => true]);
        } catch (Throwable $e) {
            // El detalle (usuario, host, motivo) va al log del servidor; el endpoint es público
            // y nunca lo muestra. 503 para que un healthcheck (Docker, balanceador) lo detecte.
            error_log('[health] ' . $e->getMessage());
            http_response_code(503);
            echo json_encode(['ok' => false, 'db' => false], JSON_UNESCAPED_UNICODE);
        }
    }
}
