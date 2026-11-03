<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Notificacion.php';

/**
 * Notificaciones de la persona con sesión (cualquier rol). Cada quien ve y marca SOLO las suyas:
 * el id de usuario sale de la sesión, nunca de un parámetro.
 *
 *   GET  /notificaciones                -> las últimas (?no_leidas=1 solo las pendientes, ?limite=N hasta 200)
 *   GET  /notificaciones/contador       -> {no_leidas: N}, para el globito de la campana
 *   POST /notificaciones/leer?id=N      -> marca una como leída (404 si no es suya)
 *   POST /notificaciones/leer-todas     -> marca todas las suyas
 */
class NotificacionController extends Controller
{
    private function yo(): int
    {
        return (int) Auth::proteger()['id'];
    }

    public function listar(): void
    {
        $yo = $this->yo();
        $solo   = ($_GET['no_leidas'] ?? '') === '1';
        $limite = isset($_GET['limite']) && ctype_digit((string) $_GET['limite']) ? (int) $_GET['limite'] : 50;
        $items = Notificacion::listar($yo, $solo, $limite);
        $this->json(['ok' => true, 'total' => count($items), 'no_leidas' => Notificacion::contarNoLeidas($yo), 'items' => $items]);
    }

    public function contador(): void
    {
        $this->json(['ok' => true, 'no_leidas' => Notificacion::contarNoLeidas($this->yo())]);
    }

    public function leer(): void
    {
        $yo = $this->yo();
        if (!Notificacion::marcarLeida($this->idQuery(), $yo)) {
            $this->json(['ok' => false, 'error' => 'Notificación inexistente'], 404);
            return;
        }
        $this->json(['ok' => true, 'no_leidas' => Notificacion::contarNoLeidas($yo)]);
    }

    public function leerTodas(): void
    {
        $yo = $this->yo();
        $n = Notificacion::marcarTodas($yo);
        $this->json(['ok' => true, 'marcadas' => $n, 'no_leidas' => 0]);
    }
}
