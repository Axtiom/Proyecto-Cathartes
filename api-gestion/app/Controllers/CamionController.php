<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Camion.php';

/**
 * Flota de camiones. CRUD completo, solo administrador (el rol se valida en el backend).
 *
 *   GET    /camiones               -> lista (filtros opcionales ?estado= y ?q=)
 *   GET    /camiones/detalle?id=N  -> un camión
 *   POST   /camiones               -> registra un camión
 *   PUT    /camiones?id=N          -> edita (parcial: lo que no se envía se conserva)
 *   DELETE /camiones?id=N          -> baja lógica; body {"motivo": "..."} obligatorio
 */
class CamionController extends Controller
{
    public function listar(): void
    {
        Auth::exigirRol('administrador');
        $items = Camion::listar(
            isset($_GET['estado']) ? (string) $_GET['estado'] : null,
            isset($_GET['q']) ? (string) $_GET['q'] : null
        );
        $this->json(['ok' => true, 'total' => count($items), 'items' => $items]);
    }

    public function detalle(): void
    {
        Auth::exigirRol('administrador');
        $id = $this->idQuery();
        $c  = $id > 0 ? Camion::porId($id) : null;
        if ($c === null) {
            $this->json(['ok' => false, 'error' => 'Camión inexistente'], 404);
            return;
        }
        $this->json(['ok' => true, 'camion' => $c]);
    }

    public function crear(): void
    {
        Auth::exigirRol('administrador');
        $r = Camion::crear($this->entrada());
        $this->resultado($r, 201, $r['ok'] ? ['id' => $r['id'], 'camion' => Camion::porId($r['id'])] : []);
    }

    public function actualizar(): void
    {
        Auth::exigirRol('administrador');
        $id = $this->idQuery();
        $r  = Camion::actualizar($id, $this->entrada());
        $this->resultado($r, 200, $r['ok'] ? ['camion' => Camion::porId($id)] : []);
    }

    public function baja(): void
    {
        Auth::exigirRol('administrador');
        $in = $this->entrada();
        $this->resultado(Camion::baja($this->idQuery(), (string) ($in['motivo'] ?? '')), 200, ['mensaje' => 'Camión dado de baja.']);
    }
}
