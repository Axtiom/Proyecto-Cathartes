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

// Protegido — saldo de puntos del vecino en sesión (badge del header, F12).
$router->get('/saldo', 'AccesoController', 'saldo');

// --- Gamificación del vecino (Fase 2 · Tanda B) ---
$router->get('/recompensas', 'RecompensaController', 'catalogo');
$router->post('/canjes',      'RecompensaController', 'canjear');
$router->get('/movimientos',  'RecompensaController', 'movimientos');
$router->get('/mis-canjes',   'RecompensaController', 'misCanjes');

// --- Perfil (Fase 2 · Tanda B; cualquier rol) ---
$router->get('/perfil',  'PerfilController', 'mostrar');
$router->post('/perfil', 'PerfilController', 'actualizar');
