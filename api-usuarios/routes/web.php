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

// Gestión de cuentas (solo administrador)
$router->get('/usuarios', 'UsuarioController', 'listar');            // ?rol= &estado= &q=
$router->get('/usuarios/detalle', 'UsuarioController', 'detalle');   // ?id=N
$router->post('/usuarios', 'UsuarioController', 'crear');
$router->put('/usuarios', 'UsuarioController', 'actualizar');        // ?id=N
$router->delete('/usuarios', 'UsuarioController', 'baja');           // ?id=N (baja lógica)
$router->post('/usuarios/aprobar', 'UsuarioController', 'aprobar');  // ?id=N
$router->post('/usuarios/rechazar', 'UsuarioController', 'rechazar');// ?id=N
