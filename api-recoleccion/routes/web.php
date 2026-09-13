<?php
declare(strict_types=1);

/**
 * Rutas de la API de Recolección. JSON. Lo que necesita la cuadrilla en
 * territorio: la ruta del día.
 *
 * @var Router $router
 */

$router->get('/health', 'HealthController', 'index');

// Ruta del día de la cuadrilla autenticada (protegido: cookie + rol Cuadrilla)
$router->get('/cuadrilla/ruta', 'CuadrillaController', 'ruta');
