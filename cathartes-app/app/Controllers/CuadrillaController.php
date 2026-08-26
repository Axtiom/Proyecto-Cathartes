<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Cuadrilla.php';

/**
 * Pantalla 8 — Cuadrillas. Solo administra lo propio del subtipo: camión y estado
 * operativo. El alta y la baja de una cuadrilla son alta y baja de USUARIO
 * (Pantalla 3), porque el rol se deduce de la tabla de subtipo. La ruta se asigna
 * desde la Pantalla 7 (queda registrada en `toma`).
 */
class CuadrillaController extends Controller
{
    public function index(): void
    {
        $admin = Auth::exigir();
        $f = [
            'estado' => (string) ($_GET['estado'] ?? ''),
            'cuenta' => (string) ($_GET['cuenta'] ?? ''),
            'camion' => (string) ($_GET['camion'] ?? ''),
            'q'      => (string) ($_GET['q'] ?? ''),
            'pagina' => (int) ($_GET['pagina'] ?? 1),
        ];
        $this->vista('cuadrillas', [
            'activo'  => 'cuadrillas',
            'admin'   => $admin,
            'f'       => $f,
            'datos'   => Cuadrilla::listar($f),
            'resumen' => Cuadrilla::resumen(),
            'flash'   => $this->tomarFlash(),
            'csrf'    => Auth::csrf(),
        ]);
    }

    public function editar(): void
    {
        $admin = Auth::exigir();
        $c = Cuadrilla::porId((int) ($_GET['id'] ?? 0));
        if ($c === null) {
            $this->flash('Cuadrilla inexistente.', 'error');
            $this->redirigir('cuadrillas');
        }
        $this->vista('cuadrilla', [
            'activo'         => 'cuadrillas',
            'admin'          => $admin,
            'c'              => $c,
            'camiones'       => Cuadrilla::camionesDisponibles((int) $c['ID']),
            'historial'      => Cuadrilla::historial((int) $c['ID']),
            'intervenciones' => Cuadrilla::intervenciones((int) $c['ID']),
            'flash'          => $this->tomarFlash(),
            'csrf'           => Auth::csrf(),
        ]);
    }

    public function actualizar(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        $res = Cuadrilla::actualizar($id, [
            'estado'    => $_POST['estado'] ?? '',
            'id_camion' => $_POST['id_camion'] ?? 0,
        ]);
        $res['ok'] ? $this->flash('Cuadrilla #' . $id . ' actualizada.')
                   : $this->flash('No se pudo guardar: ' . $res['error'], 'error');
        $this->redirigir('cuadrillas/editar?id=' . $id);
    }
}
