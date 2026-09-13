<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Camion.php';

/**
 * Pantalla 5 — Camiones. ABM de flota: alta (modal), edición y baja con fecha +
 * motivo. Matrícula UNIQUE validada. El estado 'dado de baja' solo por la baja.
 */
class CamionController extends Controller
{
    public function index(): void
    {
        $admin = Auth::exigir();
        $f = [
            'estado' => (string) ($_GET['estado'] ?? ''),
            'q'      => (string) ($_GET['q'] ?? ''),
            'pagina' => (int) ($_GET['pagina'] ?? 1),
        ];
        $this->vista('camiones', [
            'activo' => 'camiones',
            'admin'  => $admin,
            'f'      => $f,
            'datos'  => Camion::listar($f),
            'flash'  => $this->tomarFlash(),
            'csrf'   => Auth::csrf(),
        ]);
    }

    public function editar(): void
    {
        $admin = Auth::exigir();
        $c = Camion::porId((int) ($_GET['id'] ?? 0));
        if ($c === null) {
            $this->flash('Camión inexistente.', 'error');
            $this->redirigir('camiones');
        }
        $this->vista('camion', [
            'activo'    => 'camiones',
            'admin'     => $admin,
            'c'         => $c,
            'cuadrilla' => Camion::cuadrillaDe((int) $c['ID']),
            'flash'     => $this->tomarFlash(),
            'csrf'      => Auth::csrf(),
        ]);
    }

    public function crear(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $res = Camion::crear($this->campos());
        $res['ok'] ? $this->flash('Camión creado (#' . $res['id'] . ').')
                   : $this->flash('No se pudo crear: ' . $res['error'], 'error');
        $this->redirigir('camiones');
    }

    public function actualizar(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        $res = Camion::actualizar($id, $this->campos());
        $res['ok'] ? $this->flash('Camión #' . $id . ' actualizado.')
                   : $this->flash('No se pudo guardar: ' . $res['error'], 'error');
        $this->redirigir('camiones/editar?id=' . $id);
    }

    public function baja(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        $res = Camion::baja($id, (string) ($_POST['motivo'] ?? ''));
        $res['ok'] ? $this->flash('Camión #' . $id . ' dado de baja.')
                   : $this->flash('No se pudo dar de baja: ' . $res['error'], 'error');
        $this->redirigir('camiones/editar?id=' . $id);
    }

    private function campos(): array
    {
        return [
            'matricula' => $_POST['matricula'] ?? '',
            'modelo'    => $_POST['modelo'] ?? '',
            'capacidad' => $_POST['capacidad'] ?? 0,
            'estado'    => $_POST['estado'] ?? '',
        ];
    }
}
