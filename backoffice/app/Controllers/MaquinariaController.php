<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Maquinaria.php';

/**
 * Maquinaria de las instalaciones: lista + alta, edición y borrado.
 * Mismas reglas que /maquinaria de la API de Gestión (validación en el modelo).
 */
class MaquinariaController extends Controller
{
    public function index(): void
    {
        $admin = Auth::exigir();
        $f = [
            'instalacion' => (int) ($_GET['instalacion'] ?? 0),
            'estado'      => (string) ($_GET['estado'] ?? ''),
            'q'           => (string) ($_GET['q'] ?? ''),
            'pagina'      => (int) ($_GET['pagina'] ?? 1),
        ];
        $this->vista('maquinaria', [
            'activo'        => 'maquinaria',
            'admin'         => $admin,
            'f'             => $f,
            'datos'         => Maquinaria::listar($f),
            'instalaciones' => Maquinaria::instalaciones(),
            'flash'         => $this->tomarFlash(),
            'csrf'          => Auth::csrf(),
        ]);
    }

    public function editar(): void
    {
        $admin = Auth::exigir();
        $m = Maquinaria::porId((int) ($_GET['id'] ?? 0));
        if ($m === null) {
            $this->flash('Máquina inexistente.', 'error');
            $this->redirigir('maquinaria');
        }
        $this->vista('maquina', [
            'activo'        => 'maquinaria',
            'admin'         => $admin,
            'm'             => $m,
            'instalaciones' => Maquinaria::instalaciones(),
            'flash'         => $this->tomarFlash(),
            'csrf'          => Auth::csrf(),
        ]);
    }

    public function crear(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $res = Maquinaria::crear($this->campos());
        $res['ok'] ? $this->flash('Máquina creada (#' . $res['id'] . ').')
                   : $this->flash('No se pudo crear: ' . $res['error'], 'error');
        $this->redirigir('maquinaria');
    }

    public function actualizar(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        $res = Maquinaria::actualizar($id, $this->campos());
        $res['ok'] ? $this->flash('Máquina #' . $id . ' actualizada.')
                   : $this->flash('No se pudo guardar: ' . $res['error'], 'error');
        $this->redirigir('maquinaria/editar?id=' . $id);
    }

    public function eliminar(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        $res = Maquinaria::eliminar($id);
        $res['ok'] ? $this->flash('Máquina #' . $id . ' eliminada.')
                   : $this->flash('No se pudo eliminar: ' . $res['error'], 'error');
        $this->redirigir('maquinaria');
    }

    private function campos(): array
    {
        return [
            'modelo'         => $_POST['modelo'] ?? '',
            'tipo'           => $_POST['tipo'] ?? '',
            'estado'         => $_POST['estado'] ?? 'funcional',
            'id_instalacion' => $_POST['id_instalacion'] ?? '',
        ];
    }
}
