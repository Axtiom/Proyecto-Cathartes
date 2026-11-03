<?php
declare(strict_types=1);

/**
 * Rutas de la API de Recolección. JSON. Lo que necesitan la cuadrilla en territorio y el
 * operario de instalación. Cada una valida su rol en el backend (Auth::exigirRol).
 *
 * @var Router $router
 */
$router->get('/health', 'HealthController', 'index');

// Cuadrilla
$router->get('/cuadrilla/ruta', 'CuadrillaController', 'ruta');
$router->get('/cuadrilla/resumen', 'CuadrillaController', 'resumen');
$router->get('/cuadrilla/rutas', 'CuadrillaController', 'rutas');
$router->get('/cuadrilla/mapa', 'CuadrillaController', 'mapa');                       // ?ruta=ID
$router->get('/cuadrilla/intervenciones', 'CuadrillaController', 'intervenciones');
$router->get('/cuadrilla/intervencion', 'CuadrillaController', 'intervencion');       // ?id=N
$router->get('/cuadrilla/repuestos', 'CuadrillaController', 'repuestos');
$router->post('/cuadrilla/contenedor/verificar', 'CuadrillaController', 'verificar');
$router->post('/cuadrilla/contenedor/estado', 'CuadrillaController', 'estadoContenedor');
$router->post('/cuadrilla/intervencion/repuestos', 'CuadrillaController', 'usarRepuesto');
$router->post('/cuadrilla/intervencion/finalizar', 'CuadrillaController', 'finalizar');

// Operario de instalación
$router->get('/operario/resumen', 'OperarioController', 'resumen');
$router->get('/operario/instalacion', 'OperarioController', 'instalacion');
$router->post('/operario/ocupacion', 'OperarioController', 'ocupacion');
