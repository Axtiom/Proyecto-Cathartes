<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Recompensa.php';

/**
 * Pantalla 9 — Recompensas. Catálogo administrable (alta, edición, quita) más las
 * métricas de gamificación. Sacar del catálogo es el equivalente a desactivar:
 * «recompensa activa» = existe en el catálogo (ver el Model).
 */
class RecompensaController extends Controller
{
    public function index(): void
    {
        $admin = Auth::exigir();
        $f = [
            'q'          => (string) ($_GET['q'] ?? ''),
            'movimiento' => (string) ($_GET['movimiento'] ?? ''),
            'orden'      => (string) ($_GET['orden'] ?? ''),
            'pagina'     => (int) ($_GET['pagina'] ?? 1),
        ];
        $this->vista('recompensas', [
            'activo'    => 'recompensas',
            'admin'     => $admin,
            'f'         => $f,
            'datos'     => Recompensa::listar($f),
            'resumen'   => Recompensa::resumen(),
            'recientes' => Recompensa::canjesRecientes(10),
            'flash'     => $this->tomarFlash(),
            'csrf'      => Auth::csrf(),
        ]);
    }

    public function editar(): void
    {
        $admin = Auth::exigir();
        $r = Recompensa::porId((int) ($_GET['id'] ?? 0));
        if ($r === null) {
            $this->flash('Recompensa inexistente.', 'error');
            $this->redirigir('recompensas');
        }
        $this->vista('recompensa', [
            'activo' => 'recompensas',
            'admin'  => $admin,
            'r'      => $r,
            'canjes' => Recompensa::canjes((int) $r['ID']),
            'flash'  => $this->tomarFlash(),
            'csrf'   => Auth::csrf(),
        ]);
    }

    public function crear(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $res = Recompensa::crear($this->campos());
        $res['ok'] ? $this->flash('Recompensa creada (#' . $res['id'] . ').')
                   : $this->flash('No se pudo crear: ' . $res['error'], 'error');
        $this->redirigir('recompensas');
    }

    public function actualizar(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        $res = Recompensa::actualizar($id, $this->campos());
        $res['ok'] ? $this->flash('Recompensa #' . $id . ' actualizada.')
                   : $this->flash('No se pudo guardar: ' . $res['error'], 'error');
        $this->redirigir('recompensas/editar?id=' . $id);
    }

    /** Quita del catálogo. POST, nunca GET: cambia estado. */
    public function eliminar(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        $res = Recompensa::eliminar($id);
        if ($res['ok']) {
            $this->flash('Recompensa #' . $id . ' sacada del catálogo.');
            $this->redirigir('recompensas');
        }
        $this->flash('No se pudo sacar del catálogo: ' . $res['error'], 'error');
        $this->redirigir('recompensas/editar?id=' . $id);
    }

    private function campos(): array
    {
        return [
            'nombre'      => $_POST['nombre'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? '',
            'valor'       => $_POST['valor'] ?? 0,
        ];
    }
}
