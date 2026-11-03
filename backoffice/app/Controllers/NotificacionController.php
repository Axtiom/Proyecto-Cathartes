<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Notificacion.php';

/**
 * Notificaciones del administrador con sesión: las de nuevas incidencias por validar y las
 * alertas de instalaciones llenas. Cada administrador ve y marca SOLO las suyas.
 */
class NotificacionController extends Controller
{
    public function index(): void
    {
        $admin = Auth::exigir();
        $soloNuevas = ($_GET['ver'] ?? '') === 'nuevas';
        $this->vista('notificaciones', [
            'activo'     => 'notificaciones',
            'admin'      => $admin,
            'soloNuevas' => $soloNuevas,
            'items'      => Notificacion::listar((int) $admin['id'], $soloNuevas, 100),
            'sinLeer'    => Notificacion::contarNoLeidas((int) $admin['id']),
            'flash'      => $this->tomarFlash(),
            'csrf'       => Auth::csrf(),
        ]);
    }

    public function leer(): void
    {
        $admin = Auth::exigir();
        Auth::verificarCsrf();
        Notificacion::marcarLeida((int) ($_POST['id'] ?? 0), (int) $admin['id']);
        $this->redirigir('notificaciones');
    }

    public function leerTodas(): void
    {
        $admin = Auth::exigir();
        Auth::verificarCsrf();
        $n = Notificacion::marcarTodas((int) $admin['id']);
        $this->flash($n > 0 ? $n . ' notificación' . ($n === 1 ? '' : 'es') . ' marcada' . ($n === 1 ? '' : 's') . ' como leída' . ($n === 1 ? '' : 's') . '.' : 'No había notificaciones sin leer.');
        $this->redirigir('notificaciones');
    }
}
