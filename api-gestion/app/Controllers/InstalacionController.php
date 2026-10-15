<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Instalacion.php';

/**
 * Instalaciones (vertederos y centros de acopio). CRUD completo, solo administrador.
 *
 *   GET    /instalaciones               -> lista (?tipo= ?q=); incluye % de ocupación y alerta (>= 80 %)
 *   GET    /instalaciones/detalle?id=N  -> una instalación con sus dependencias
 *   POST   /instalaciones               -> alta
 *   PUT    /instalaciones?id=N          -> edición (parcial)
 *   DELETE /instalaciones?id=N          -> borrado; 409 si tiene rutas, operarios o maquinaria
 */
class InstalacionController extends Controller
{
    public function listar(): void
    {
        Auth::exigirRol('administrador');
        $items = Instalacion::listar(
            isset($_GET['tipo']) ? (string) $_GET['tipo'] : null,
            isset($_GET['q']) ? (string) $_GET['q'] : null
        );
        $this->json(['ok' => true, 'total' => count($items), 'items' => $items]);
    }

    public function detalle(): void
    {
        Auth::exigirRol('administrador');
        $id = $this->idQuery();
        $i  = $id > 0 ? Instalacion::porId($id) : null;
        if ($i === null) {
            $this->json(['ok' => false, 'error' => 'Instalación inexistente'], 404);
            return;
        }
        $i['dependencias'] = Instalacion::dependencias($id);
        $this->json(['ok' => true, 'instalacion' => $i]);
    }

    public function crear(): void
    {
        Auth::exigirRol('administrador');
        $r = Instalacion::crear($this->entrada());
        $this->resultado($r, 201, $r['ok'] ? ['id' => $r['id'], 'instalacion' => Instalacion::porId($r['id'])] : []);
    }

    public function actualizar(): void
    {
        Auth::exigirRol('administrador');
        $id = $this->idQuery();
        $r  = Instalacion::actualizar($id, $this->entrada());
        $this->resultado($r, 200, $r['ok'] ? ['instalacion' => Instalacion::porId($id)] : []);
    }

    public function eliminar(): void
    {
        Auth::exigirRol('administrador');
        $this->resultado(Instalacion::eliminar($this->idQuery()), 200, ['mensaje' => 'Instalación eliminada.']);
    }
}
