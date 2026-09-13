<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Contenedor.php';

/**
 * Contenedores — lectura para el Frontend de Usuarios y la Landing (antes la
 * API de Gestión). Usa los mismos datos que el ABM del backoffice; acá solo
 * lectura, en formato liviano.
 *
 *   GET /api/contenedores          -> activos, protegido (selector de reportar)
 *   GET /api/contenedores/todos    -> todos, protegido (mapa)
 *   GET /api/contenedores/publicos -> activos, SIN sesión (mapa de la landing)
 */
class ContenedorController extends Controller
{
    public function activos(): void
    {
        Auth::proteger();
        try {
            $this->json(['ok' => true, 'items' => Contenedor::activos()]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function todos(): void
    {
        Auth::proteger();
        try {
            $this->json(['ok' => true, 'items' => Contenedor::todos()]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Contenedores activos SIN sesión — para el mapa de la landing pública.
     * Mismos datos que activos() pero sin Auth (dato no sensible: ubicación y
     * estado de contenedores públicos en la vía).
     */
    public function publicos(): void
    {
        try {
            $this->json(['ok' => true, 'items' => Contenedor::activos()]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
