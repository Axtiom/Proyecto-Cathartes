<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Contenedor.php';

/**
 * Pantalla 4 — Contenedores. ABM completo: alta (modal en la lista), edición y
 * baja con fecha + motivo (página de edición). El estado 'dado de baja' solo se
 * alcanza por la acción de baja, no editando el estado a mano.
 */
class ContenedorController extends Controller
{
    public function index(): void
    {
        $admin = Auth::exigir();
        $f = [
            'tipo'   => (string) ($_GET['tipo'] ?? ''),
            'estado' => (string) ($_GET['estado'] ?? ''),
            'ruta'   => (int) ($_GET['ruta'] ?? 0),
            'pagina' => (int) ($_GET['pagina'] ?? 1),
        ];
        $this->vista('contenedores', [
            'activo' => 'contenedores',
            'admin'  => $admin,
            'f'      => $f,
            'datos'  => Contenedor::listar($f),
            'rutas'  => Contenedor::rutas(),
            'flash'  => $this->tomarFlash(),
            'csrf'   => Auth::csrf(),
        ]);
    }

    public function editar(): void
    {
        $admin = Auth::exigir();
        $c = Contenedor::porId((int) ($_GET['id'] ?? 0));
        if ($c === null) {
            $this->flash('Contenedor inexistente.');
            $this->redirigir('contenedores');
        }
        $this->vista('contenedor', [
            'activo' => 'contenedores',
            'admin'  => $admin,
            'c'      => $c,
            'rutas'  => Contenedor::rutas(),
            'flash'  => $this->tomarFlash(),
            'csrf'   => Auth::csrf(),
        ]);
    }

    public function crear(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $res = Contenedor::crear($this->campos());
        $this->flash($res['ok'] ? ('Contenedor creado (#' . $res['id'] . ').') : ('No se pudo crear: ' . $res['error']));
        $this->redirigir('contenedores');
    }

    public function actualizar(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        $res = Contenedor::actualizar($id, $this->campos());
        $this->flash($res['ok'] ? ('Contenedor #' . $id . ' actualizado.') : ('No se pudo guardar: ' . $res['error']));
        $this->redirigir('contenedores/editar?id=' . $id);
    }

    public function baja(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        $res = Contenedor::baja($id, (string) ($_POST['motivo'] ?? ''));
        $this->flash($res['ok'] ? ('Contenedor #' . $id . ' dado de baja.') : ('No se pudo dar de baja: ' . $res['error']));
        $this->redirigir('contenedores/editar?id=' . $id);
    }

    private function campos(): array
    {
        return [
            'ubicacion' => $_POST['ubicacion'] ?? '',
            'tipo'      => $_POST['tipo'] ?? '',
            'estado'    => $_POST['estado'] ?? '',
            'id_ruta'   => $_POST['id_ruta'] ?? 0,
            'latitud'   => $_POST['latitud'] ?? '',
            'longitud'  => $_POST['longitud'] ?? '',
        ];
    }

    private function flash(string $m): void { $_SESSION['flash'] = $m; }
    private function tomarFlash(): ?string { $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m; }
}
