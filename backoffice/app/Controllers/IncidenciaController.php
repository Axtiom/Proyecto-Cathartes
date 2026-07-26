<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Incidencia.php';

/**
 * Pantalla 2 — Incidencias. Validar reportes y asignar cuadrillas, con filtros
 * (estado, tipo, zona, período) y paginación. El detalle muestra la foto del
 * reporte. "Asignada" se infiere de la existencia de una intervencion.
 */
class IncidenciaController extends Controller
{
    public function index(): void
    {
        $admin = Auth::exigir();
        $filtros = [
            'estado'   => (string) ($_GET['estado'] ?? ''),
            'validada' => (string) ($_GET['validada'] ?? ''),
            'tipo'   => (string) ($_GET['tipo'] ?? ''),
            'zona'   => (string) ($_GET['zona'] ?? ''),
            'desde'  => (string) ($_GET['desde'] ?? ''),
            'hasta'  => (string) ($_GET['hasta'] ?? ''),
            'pagina' => (int) ($_GET['pagina'] ?? 1),
        ];
        $this->vista('incidencias', [
            'activo'  => 'incidencias',
            'admin'   => $admin,
            'f'       => $filtros,
            'datos'   => Incidencia::listar($filtros),
            'zonas'   => Incidencia::zonas(),
            'flash'   => $this->tomarFlash(),
            'csrf'    => Auth::csrf(),
        ]);
    }

    public function ver(): void
    {
        $admin = Auth::exigir();
        $id  = (int) ($_GET['id'] ?? 0);
        $inc = Incidencia::detalle($id);
        if ($inc === null) {
            $this->flash('No se encontró la incidencia.', 'error');
            $this->redirigir('incidencias');
        }
        $this->vista('incidencia', [
            'activo'     => 'incidencias',
            'admin'      => $admin,
            'inc'        => $inc,
            'cuadrillas' => Incidencia::cuadrillas(),
            'flash'      => $this->tomarFlash(),
            'csrf'       => Auth::csrf(),
        ]);
    }

    public function validar(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        Incidencia::validar($id)
            ? $this->flash('Incidencia #' . $id . ' validada. Se acreditaron 150 puntos al vecino.')
            : $this->flash('La incidencia ya estaba validada o no existe.', 'error');
        $this->redirigir('incidencias/ver?id=' . $id);
    }

    public function asignar(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        $cua = (int) ($_POST['id_cuadrilla'] ?? 0);
        $res = Incidencia::asignar($id, $cua);
        $res['ok'] ? $this->flash('Cuadrilla asignada a la incidencia #' . $id . '.')
                   : $this->flash('No se pudo asignar: ' . $res['error'], 'error');
        $this->redirigir('incidencias/ver?id=' . $id);
    }

    /** Sirve la foto del reporte leyéndola del storage de api-gestion (config). */
    public function foto(): void
    {
        Auth::exigir();
        $id      = (int) ($_GET['id'] ?? 0);
        $archivo = Incidencia::fotoArchivo($id);
        $dir     = rtrim((string) Config::get('uploads.incidencias', ''), '/\\');
        $ruta    = $archivo !== null ? $dir . DIRECTORY_SEPARATOR . $archivo : '';

        if ($archivo === null || $ruta === '' || !is_file($ruta)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Sin foto';
            return;
        }
        $mime = (strtolower(pathinfo($archivo, PATHINFO_EXTENSION)) === 'png') ? 'image/png' : 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: private, max-age=300');
        readfile($ruta);
    }
}
