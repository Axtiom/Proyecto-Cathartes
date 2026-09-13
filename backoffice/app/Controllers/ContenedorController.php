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
            $this->flash('Contenedor inexistente.', 'error');
            $this->redirigir('contenedores');
        }
        $this->vista('contenedor', [
            'activo'      => 'contenedores',
            'admin'       => $admin,
            'c'           => $c,
            // Solo para mostrar a qué ruta pertenece; la reasignación se hace en
            // la pantalla de la ruta, no acá.
            'rutaActual'  => Contenedor::rutaDe((int) $c['ID_ruta']),
            'flash'       => $this->tomarFlash(),
            'csrf'        => Auth::csrf(),
        ]);
    }

    public function crear(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $res = Contenedor::crear($this->campos());
        $res['ok'] ? $this->flash('Contenedor creado (#' . $res['id'] . ').')
                   : $this->flash('No se pudo crear: ' . $res['error'], 'error');
        $this->redirigir('contenedores');
    }

    public function actualizar(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        // Sin `id_ruta`: el recorrido lo define la ruta (Pantalla 7). Aunque
        // alguien fuerce el POST con id_ruta, el Model no lo lee en la edición.
        $res = Contenedor::actualizar($id, $this->campos());
        $res['ok'] ? $this->flash('Contenedor #' . $id . ' actualizado.')
                   : $this->flash('No se pudo guardar: ' . $res['error'], 'error');
        $this->redirigir('contenedores/editar?id=' . $id);
    }

    public function baja(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        $res = Contenedor::baja($id, (string) ($_POST['motivo'] ?? ''));
        $res['ok'] ? $this->flash('Contenedor #' . $id . ' dado de baja.')
                   : $this->flash('No se pudo dar de baja: ' . $res['error'], 'error');
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
}
