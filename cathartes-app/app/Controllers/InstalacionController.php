<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Instalacion.php';

/**
 * Pantalla 6 — Instalaciones. Alta y edición (no hay baja: `instalacion` no tiene
 * columna de estado y el MER está congelado, ver el Model). El detalle muestra las
 * rutas, los operarios y la maquinaria que dependen de la instalación.
 */
class InstalacionController extends Controller
{
    public function index(): void
    {
        $admin = Auth::exigir();
        $f = [
            'tipo'   => (string) ($_GET['tipo'] ?? ''),
            'q'      => (string) ($_GET['q'] ?? ''),
            'llenas' => (string) ($_GET['llenas'] ?? ''),
            'pagina' => (int) ($_GET['pagina'] ?? 1),
        ];
        $this->vista('instalaciones', [
            'activo'  => 'instalaciones',
            'admin'   => $admin,
            'f'       => $f,
            'datos'   => Instalacion::listar($f),
            'resumen' => Instalacion::resumen(),
            'flash'   => $this->tomarFlash(),
            'csrf'    => Auth::csrf(),
        ]);
    }

    public function editar(): void
    {
        $admin = Auth::exigir();
        $i = Instalacion::porId((int) ($_GET['id'] ?? 0));
        if ($i === null) {
            $this->flash('Instalación inexistente.', 'error');
            $this->redirigir('instalaciones');
        }
        $this->vista('instalacion', [
            'activo' => 'instalaciones',
            'admin'  => $admin,
            'i'      => $i,
            'dep'    => Instalacion::dependencias((int) $i['ID']),
            'flash'  => $this->tomarFlash(),
            'csrf'   => Auth::csrf(),
        ]);
    }

    public function crear(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $res = Instalacion::crear($this->campos());
        $res['ok'] ? $this->flash('Instalación creada (#' . $res['id'] . ').')
                   : $this->flash('No se pudo crear: ' . $res['error'], 'error');
        $this->redirigir('instalaciones');
    }

    public function actualizar(): void
    {
        Auth::exigir();
        Auth::verificarCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        $res = Instalacion::actualizar($id, $this->campos());
        $res['ok'] ? $this->flash('Instalación #' . $id . ' actualizada.')
                   : $this->flash('No se pudo guardar: ' . $res['error'], 'error');
        $this->redirigir('instalaciones/editar?id=' . $id);
    }

    private function campos(): array
    {
        return [
            'nombre'           => $_POST['nombre'] ?? '',
            'ubicacion'        => $_POST['ubicacion'] ?? '',
            'tipo'             => $_POST['tipo'] ?? '',
            'capacidad'        => $_POST['capacidad'] ?? 0,
            'ocupacion_actual' => $_POST['ocupacion_actual'] ?? 0,
            'latitud'          => $_POST['latitud'] ?? '',
            'longitud'         => $_POST['longitud'] ?? '',
        ];
    }
}
