<?php
declare(strict_types=1);

/** @var Router $router */

// Salud del motor + BD
$router->get('/health', 'HealthController', 'index');

// Autenticación
$router->post('/registro',    'AccesoController', 'registro');
$router->post('/login',       'AccesoController', 'login');
$router->post('/verificar',   'AccesoController', 'verificar');
$router->post('/recuperar',   'AccesoController', 'recuperar');
$router->post('/restablecer', 'AccesoController', 'restablecer');
$router->post('/logout',      'AccesoController', 'logout');

// Protegido (Bearer + kill-switch). Prueba de sesión y reusado por el frontend.
$router->get('/yo', 'AccesoController', 'yo');
