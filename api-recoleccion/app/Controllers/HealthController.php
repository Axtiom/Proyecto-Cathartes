<?php
declare(strict_types=1);

/**
 * Chequeo de salud: verifica que el motor y la conexión a `cathartes`
 * respondan. GET /health -> {"ok": true, "db": true|false}.
 */
class HealthController extends Controller
{
    public function index(): void
    {
        try {
            Database::conexion()->query('SELECT 1');
            $this->json(['ok' => true, 'db' => true]);
        } catch (Throwable $e) {
            $this->json(['ok' => true, 'db' => false, 'error' => $e->getMessage()], 200);
        }
    }
}
