<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Panel.php';

/**
 * Pantalla 1 — Dashboard. Solo lectura: agrega sobre lo que administran las otras
 * nueve pantallas. Es la última en construirse a propósito — recién ahora se sabe
 * que cada tabla se lee bien.
 */
class PanelController extends Controller
{
    public function index(): void
    {
        $admin = Auth::exigir();
        $this->vista('dashboard', [
            'activo'     => 'dashboard',
            'admin'      => $admin,
            'm'          => Panel::metricas(),
            'pendientes' => Panel::pendientes(8),
            'puntos'     => Panel::puntos(),
            'sinCoords'  => Panel::sinCoordenadas(),
            'flash'      => $this->tomarFlash(),
            'csrf'       => Auth::csrf(),
        ]);
    }
}
