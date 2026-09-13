<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Ruta.php';

/**
 * Pantalla 7 — Rutas. Alta, edición y asignación de cuadrilla (vía `toma`).
 * No hay acción de baja: se pasa `estado` a 'inactiva' desde el formulario.
 */
class RutaController extends Controller
{
    public function index(): void
    {
        $admin = Auth::exigir();
        $f = [
            'estado'      => (string) ($_GET['estado'] ?? ''),
            'zona'        => (string) ($_GET['zona'] ?? ''),
            'instalacion' => (int) ($_GET['instalacion'] ?? 0),
            'q'           => (string) ($_GET['q'] ?? ''),
            'pagina'      => (int) ($_GET['pagina'] ?? 1),
        ];
        $this->vista('rutas', [
            'activo'        => 'rutas',
            'admin'         => $admin,
            'f'             => $f,
            'datos'         => Ruta::listar($f),
            'zonas'         => Ruta::zonas(),
            'instalaciones' => Ruta::instalaciones(),
            'flash'         => $this->tomarFlash(),
            'csrf'          => Auth::csrf(),
        ]);
    }

    public function editar(): void
    {
        $admin = Auth::exigir();
        $r = Ruta::porId((int) ($_GET['id'] ?? 0));
        if ($r === null) {
            $this->flash('Ruta inexistente.', 'error');
            $this->redirigir('rutas');
        }
        $this->vista('ruta', [
            'activo'        => 'rutas',
            'admin'         => $admin,
            'r'             => $r,
            'instalaciones' => Ruta::instalaciones(),
            'cuadrillas'    => Ruta::cuadrillas(),
            'historial'     => Ruta::historial((int) $r['ID']),
            'contenedores'  => Ruta::contenedores((int) $r['ID']),
            'asignables'    => Ruta::contenedoresAsignables(),
            'otrasRutas'    => Ruta::otras((int) $r['ID']),
            'flash'         => $this->tomarFlash(),
            'csrf'          => Auth::csrf(),
        ]);
    }

    /** Define por qué contenedores pasa la ruta (checkboxes del detalle). */
    public function contenedores(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        $res = Ruta::asignarContenedores(
            $id,
            (array) ($_POST['contenedores'] ?? []),
            (int) ($_POST['ruta_destino'] ?? 0)
        );
        if ($res['ok']) {
            $partes = [];
            if ($res['sumados']) $partes[] = $res['sumados'] . ' agregado' . ($res['sumados'] === 1 ? '' : 's');
            if ($res['movidos']) $partes[] = $res['movidos'] . ' movido' . ($res['movidos'] === 1 ? '' : 's') . ' a otra ruta';
            $this->flash('Recorrido actualizado' . ($partes ? ' (' . implode(' · ', $partes) . ').' : ': sin cambios.'));
        } else {
            $this->flash('No se pudo guardar el recorrido: ' . $res['error'], 'error');
        }
        $this->redirigir('rutas/editar?id=' . $id);
    }

    public function crear(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $res = Ruta::crear($this->campos());
        $res['ok'] ? $this->flash('Ruta creada (#' . $res['id'] . ').')
                   : $this->flash('No se pudo crear: ' . $res['error'], 'error');
        $this->redirigir('rutas');
    }

    public function actualizar(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        $res = Ruta::actualizar($id, $this->campos());
        $res['ok'] ? $this->flash('Ruta #' . $id . ' actualizada.')
                   : $this->flash('No se pudo guardar: ' . $res['error'], 'error');
        $this->redirigir('rutas/editar?id=' . $id);
    }

    public function asignar(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        $cua = (int) ($_POST['id_cuadrilla'] ?? 0);
        $res = Ruta::asignarCuadrilla($id, $cua);
        $res['ok'] ? $this->flash('Cuadrilla asignada a la ruta #' . $id . '.')
                   : $this->flash('No se pudo asignar: ' . $res['error'], 'error');
        $this->redirigir('rutas/editar?id=' . $id);
    }

    private function campos(): array
    {
        return [
            'nombre'         => $_POST['nombre'] ?? '',
            'zona'           => $_POST['zona'] ?? '',
            'frecuencia'     => $_POST['frecuencia'] ?? '',
            'estado'         => $_POST['estado'] ?? '',
            'id_instalacion' => $_POST['id_instalacion'] ?? 0,
        ];
    }
}
