<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Estadisticas.php';

/**
 * Estadísticas públicas de incidencias (sin sesión).
 *
 * GET /estadisticas/publicas -> incidencias por tipo, por mes y por zona.
 * Datos reales de `cathartes` (Model Estadisticas). Público a propósito: lo
 * consumen la landing y la pantalla 2 sin token.
 */
class EstadisticasController extends Controller
{
    public function publicas(): void
    {
        try {
            $this->json([
                'ok'       => true,
                'por_tipo' => Estadisticas::porTipo(),
                'por_mes'  => Estadisticas::porMes(),
                'por_zona' => Estadisticas::porZona(),
            ]);
        } catch (Throwable $e) {
            error_log('[' . static::class . '] ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Error interno del servidor'], 500);
        }
    }
}
