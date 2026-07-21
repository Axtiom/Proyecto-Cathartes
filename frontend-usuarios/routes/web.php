<?php
declare(strict_types=1);

/** @var Router $router */

// Pantallas de acceso: GET renderiza, POST cablea contra la API.
$router->get('/login',      'AccesoController', 'login');
$router->post('/login',     'AccesoController', 'loginPost');

$router->get('/registro',   'AccesoController', 'registro');
$router->post('/registro',  'AccesoController', 'registroPost');

$router->get('/verificar',  'AccesoController', 'verificar');
$router->post('/verificar', 'AccesoController', 'verificarPost');

$router->get('/recuperar',  'AccesoController', 'recuperar');
$router->post('/recuperar', 'AccesoController', 'recuperarPost');

$router->get('/restablecer',  'AccesoController', 'restablecer');
$router->post('/restablecer', 'AccesoController', 'restablecerPost');

// Página protegida (prueba del circuito) + logout
$router->get('/inicio',  'AccesoController', 'inicio');
$router->post('/logout', 'AccesoController', 'logout');
