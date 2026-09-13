<?php
declare(strict_types=1);

/**
 * Rutas del Backoffice. HTML server-rendered: GET dibuja pantalla, POST muta
 * y redirige (patrón post-redirect-get).
 *
 * @var Router $router
 */

// Acceso (login unificado en frontend-usuarios; acá solo entrada y logout)
$router->get('/', 'AccesoController', 'index');
$router->post('/logout', 'AccesoController', 'logout');

// Dashboard
$router->get('/dashboard', 'PanelController', 'index');

// Salud (sin auth)
$router->get('/health', 'HealthController', 'index');

// Usuarios
$router->get('/usuarios', 'UsuarioController', 'index');
$router->post('/usuarios/aprobar', 'UsuarioController', 'aprobar');
$router->post('/usuarios/rechazar', 'UsuarioController', 'rechazar');
$router->post('/usuarios/crear', 'UsuarioController', 'crear');

// Incidencias
$router->get('/incidencias', 'IncidenciaController', 'index');
$router->get('/incidencias/ver', 'IncidenciaController', 'ver');
$router->get('/incidencias/foto', 'IncidenciaController', 'foto');
$router->post('/incidencias/validar', 'IncidenciaController', 'validar');
$router->post('/incidencias/asignar', 'IncidenciaController', 'asignar');

// Contenedores (ABM)
$router->get('/contenedores', 'ContenedorController', 'index');
$router->get('/contenedores/editar', 'ContenedorController', 'editar');
$router->post('/contenedores/crear', 'ContenedorController', 'crear');
$router->post('/contenedores/actualizar', 'ContenedorController', 'actualizar');
$router->post('/contenedores/baja', 'ContenedorController', 'baja');

// Camiones (ABM de flota)
$router->get('/camiones', 'CamionController', 'index');
$router->get('/camiones/editar', 'CamionController', 'editar');
$router->post('/camiones/crear', 'CamionController', 'crear');
$router->post('/camiones/actualizar', 'CamionController', 'actualizar');
$router->post('/camiones/baja', 'CamionController', 'baja');

// Instalaciones
$router->get('/instalaciones', 'InstalacionController', 'index');
$router->get('/instalaciones/editar', 'InstalacionController', 'editar');
$router->post('/instalaciones/crear', 'InstalacionController', 'crear');
$router->post('/instalaciones/actualizar', 'InstalacionController', 'actualizar');

// Rutas
$router->get('/rutas', 'RutaController', 'index');
$router->get('/rutas/editar', 'RutaController', 'editar');
$router->post('/rutas/crear', 'RutaController', 'crear');
$router->post('/rutas/actualizar', 'RutaController', 'actualizar');
$router->post('/rutas/asignar', 'RutaController', 'asignar');
$router->post('/rutas/contenedores', 'RutaController', 'contenedores');

// Cuadrillas (admin: camión + estado operativo)
$router->get('/cuadrillas', 'CuadrillaController', 'index');
$router->get('/cuadrillas/editar', 'CuadrillaController', 'editar');
$router->post('/cuadrillas/actualizar', 'CuadrillaController', 'actualizar');

// Informes (solo lectura; PDF por el diálogo de impresión)
$router->get('/informes', 'InformeController', 'index');
