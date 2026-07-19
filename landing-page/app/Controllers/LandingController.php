<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Estadisticas.php';

/**
 * Controlador de la landing pública.
 * Reúne los datos y renderiza la vista.
 */
class LandingController extends Controller
{
    public function index(): void
    {
        $this->view('landing.index', [
            'tipos'        => Estadisticas::tiposIncidencia(),
            'estadisticas' => Estadisticas::publicas(),
            'hero'         => Estadisticas::hero(),
            'rutas'        => Estadisticas::rutas(),
        ]);
    }
}
