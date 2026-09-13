<?php
declare(strict_types=1);

/**
 * Rutas de la API de Usuarios. JSON. Autenticación de un solo paso (sin
 * verificación en dos pasos): usuario y contraseña alcanzan para abrir sesión.
 *
 * @var Router $router
 */

$router->get('/health', 'HealthController', 'index');

$router->post('/registro', 'AccesoController', 'registro');
$router->post('/login', 'AccesoController', 'login');
$router->post('/recuperar', 'AccesoController', 'recuperar');
$router->post('/restablecer', 'AccesoController', 'restablecer');
$router->post('/logout', 'AccesoController', 'logout');

// Protegido (cookie + kill-switch). Prueba de sesión, reusado por el frontend.
$router->get('/yo', 'AccesoController', 'yo');

// Perfil (cualquier rol)
$router->get('/perfil', 'PerfilController', 'mostrar');
$router->post('/perfil', 'PerfilController', 'actualizar');
