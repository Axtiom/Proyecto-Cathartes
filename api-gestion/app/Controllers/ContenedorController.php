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
 *   GET  /rutas                    -> rutas (solo administrador), para elegir al registrar
 *   POST /contenedores             -> registra un contenedor (solo administrador)
 *   GET  /contenedores/detalle?id= -> un contenedor completo (solo administrador)
 *   PUT  /contenedores?id=N        -> edita (parcial; la ruta no se cambia desde acá)
 *   DELETE /contenedores?id=N      -> baja lógica; body {"motivo": "..."} obligatorio
 */
class ContenedorController extends Controller
{
    public function activos(): void
    {
        Auth::proteger();
        try {
            $this->json(['ok' => true, 'items' => Contenedor::activos()]);
        } catch (Throwable $e) {
            error_log('[' . static::class . '] ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Error interno del servidor'], 500);
        }
    }

    public function todos(): void
    {
        Auth::proteger();
        try {
            $this->json(['ok' => true, 'items' => Contenedor::todos()]);
        } catch (Throwable $e) {
            error_log('[' . static::class . '] ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Error interno del servidor'], 500);
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
            error_log('[' . static::class . '] ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Error interno del servidor'], 500);
        }
    }

    /** Rutas disponibles. Solo administrador. */
    public function rutas(): void
    {
        Auth::exigirRol('administrador');
        $this->json(['ok' => true, 'items' => Contenedor::rutas()]);
    }

    /** Registro de un contenedor. Solo administrador. */
    public function crear(): void
    {
        Auth::exigirRol('administrador');
        $r = Contenedor::crear($this->entrada());
        $this->resultado($r, 201, $r['ok'] ? ['id' => $r['id'], 'contenedor' => Contenedor::porId($r['id'])] : []);
    }

    public function detalle(): void
    {
        Auth::exigirRol('administrador');
        $id = $this->idQuery();
        $c  = $id > 0 ? Contenedor::completo($id) : null;
        if ($c === null) {
            $this->json(['ok' => false, 'error' => 'Contenedor inexistente'], 404);
            return;
        }
        $this->json(['ok' => true, 'contenedor' => $c]);
    }

    public function actualizar(): void
    {
        Auth::exigirRol('administrador');
        $id = $this->idQuery();
        $r  = Contenedor::actualizar($id, $this->entrada());
        $this->resultado($r, 200, $r['ok'] ? ['contenedor' => Contenedor::completo($id)] : []);
    }

    public function baja(): void
    {
        Auth::exigirRol('administrador');
        $in = $this->entrada();
        $this->resultado(Contenedor::baja($this->idQuery(), (string) ($in['motivo'] ?? '')), 200, ['mensaje' => 'Contenedor dado de baja.']);
    }
}
