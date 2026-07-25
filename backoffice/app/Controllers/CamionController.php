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
            $this->flash('Camión inexistente.');
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
        $this->flash($res['ok'] ? ('Camión creado (#' . $res['id'] . ').') : ('No se pudo crear: ' . $res['error']));
        $this->redirigir('camiones');
    }

    public function actualizar(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        $res = Camion::actualizar($id, $this->campos());
        $this->flash($res['ok'] ? ('Camión #' . $id . ' actualizado.') : ('No se pudo guardar: ' . $res['error']));
        $this->redirigir('camiones/editar?id=' . $id);
    }

    public function baja(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        $res = Camion::baja($id, (string) ($_POST['motivo'] ?? ''));
        $this->flash($res['ok'] ? ('Camión #' . $id . ' dado de baja.') : ('No se pudo dar de baja: ' . $res['error']));
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

    private function flash(string $m): void { $_SESSION['flash'] = $m; }
    private function tomarFlash(): ?string { $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m; }
}
