<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Contenedor.php';

/**
 * Contenedores (lectura). Protegido: cualquier usuario autenticado.
 *   GET /contenedores        -> activos (selector de /reportar, lista de /inicio)
 *   GET /contenedores/todos  -> todos (para el mapa, incluye dados de baja)
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
