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
        $periodo = array_key_exists($_GET['periodo'] ?? '', Panel::PERIODOS) ? (string) $_GET['periodo'] : 'mes';
        $this->vista('dashboard', [
            'activo'     => 'dashboard',
            'admin'      => $admin,
            'm'          => Panel::metricas(),
            'pendientes' => Panel::pendientes(8),
            'puntos'     => Panel::puntos(),
            'periodo'    => $periodo,
            'top'        => Panel::topDesbordados($periodo, 5),
            'sinCoords'  => Panel::sinCoordenadas(),
            'flash'      => $this->tomarFlash(),
            'csrf'       => Auth::csrf(),
        ]);
    }
}
