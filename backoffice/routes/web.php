<?php
declare(strict_types=1);

/**
 * Rutas del Backoffice. Convención: GET renderiza vista; POST muta y redirige
 * (patrón POST-Redirect-GET). Los nombres de ruta no llevan el prefijo del
 * subdirectorio: el Router lo recorta solo.
 */

// Acceso (login unificado en el frontend; acá solo entrada y logout)
$router->get('/',        'AccesoController', 'index');
$router->post('/logout', 'AccesoController', 'logout');

// Salud (sin auth)
$router->get('/health',  'HealthController', 'index');

// Pantalla 3 — Usuarios
$router->get('/usuarios',           'UsuarioController', 'index');
$router->post('/usuarios/aprobar',  'UsuarioController', 'aprobar');
$router->post('/usuarios/rechazar', 'UsuarioController', 'rechazar');
$router->post('/usuarios/crear',    'UsuarioController', 'crear');

// Pantalla 2 — Incidencias
$router->get('/incidencias',         'IncidenciaController', 'index');
$router->get('/incidencias/ver',     'IncidenciaController', 'ver');
$router->get('/incidencias/foto',    'IncidenciaController', 'foto');
$router->post('/incidencias/validar', 'IncidenciaController', 'validar');
$router->post('/incidencias/asignar', 'IncidenciaController', 'asignar');

// Pantalla 4 — Contenedores (ABM)
$router->get('/contenedores',            'ContenedorController', 'index');
$router->get('/contenedores/editar',     'ContenedorController', 'editar');
$router->post('/contenedores/crear',      'ContenedorController', 'crear');
$router->post('/contenedores/actualizar', 'ContenedorController', 'actualizar');
$router->post('/contenedores/baja',       'ContenedorController', 'baja');

// Pantalla 5 — Camiones (ABM de flota)
$router->get('/camiones',            'CamionController', 'index');
$router->get('/camiones/editar',     'CamionController', 'editar');
$router->post('/camiones/crear',      'CamionController', 'crear');
$router->post('/camiones/actualizar', 'CamionController', 'actualizar');
$router->post('/camiones/baja',       'CamionController', 'baja');
