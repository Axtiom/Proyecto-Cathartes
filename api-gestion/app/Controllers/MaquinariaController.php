<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Maquinaria.php';

/**
 * Maquinaria de las instalaciones. CRUD básico, solo administrador.
 *
 *   GET    /maquinaria               -> lista (?instalacion=ID ?estado=)
 *   GET    /maquinaria/detalle?id=N  -> una máquina
 *   POST   /maquinaria               -> alta (modelo, tipo, estado, id_instalacion)
 *   PUT    /maquinaria?id=N          -> edición (parcial)
 *   DELETE /maquinaria?id=N          -> borrado
 */
class MaquinariaController extends Controller
{
    public function listar(): void
    {
        Auth::exigirRol('administrador');
        $inst  = (isset($_GET['instalacion']) && ctype_digit((string) $_GET['instalacion'])) ? (int) $_GET['instalacion'] : null;
        $items = Maquinaria::listar($inst, isset($_GET['estado']) ? (string) $_GET['estado'] : null);
        $this->json(['ok' => true, 'total' => count($items), 'items' => $items]);
    }

    public function detalle(): void
    {
        Auth::exigirRol('administrador');
        $id = $this->idQuery();
        $m  = $id > 0 ? Maquinaria::porId($id) : null;
        if ($m === null) {
            $this->json(['ok' => false, 'error' => 'Máquina inexistente'], 404);
            return;
        }
        $this->json(['ok' => true, 'maquina' => $m]);
    }

    public function crear(): void
    {
        Auth::exigirRol('administrador');
        $r = Maquinaria::crear($this->entrada());
        $this->resultado($r, 201, $r['ok'] ? ['id' => $r['id'], 'maquina' => Maquinaria::porId($r['id'])] : []);
    }

    public function actualizar(): void
    {
        Auth::exigirRol('administrador');
        $id = $this->idQuery();
        $r  = Maquinaria::actualizar($id, $this->entrada());
        $this->resultado($r, 200, $r['ok'] ? ['maquina' => Maquinaria::porId($id)] : []);
    }

    public function eliminar(): void
    {
        Auth::exigirRol('administrador');
        $this->resultado(Maquinaria::eliminar($this->idQuery()), 200, ['mensaje' => 'Máquina eliminada.']);
    }
}
