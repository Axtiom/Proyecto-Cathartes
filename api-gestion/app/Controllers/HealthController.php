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
            echo json_encode(['ok' => true, 'db' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
}
