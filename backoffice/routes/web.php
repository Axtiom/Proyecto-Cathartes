<?php
declare(strict_types=1);

/**
 * Rutas del Backoffice. Convención: GET renderiza vista; POST muta y redirige
 * (patrón POST-Redirect-GET). Los nombres de ruta no llevan el prefijo del
 * subdirectorio: el Router lo recorta solo.
 */

// Acceso (login unificado en el frontend; acá solo entrada y logout)
$router->get('/',        'AccesoController', 'index');

// Pantalla 1 — Dashboard (solo lectura: agrega sobre las otras nueve)
$router->get('/dashboard', 'PanelController', 'index');
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

// Pantalla 6 — Instalaciones (alta y edición; sin baja, ver el Model)
$router->get('/instalaciones',            'InstalacionController', 'index');
$router->get('/instalaciones/editar',     'InstalacionController', 'editar');
$router->post('/instalaciones/crear',      'InstalacionController', 'crear');
$router->post('/instalaciones/actualizar', 'InstalacionController', 'actualizar');

// Pantalla 7 — Rutas (alta, edición y asignación de cuadrilla vía `toma`)
$router->get('/rutas',            'RutaController', 'index');
$router->get('/rutas/editar',     'RutaController', 'editar');
$router->post('/rutas/crear',      'RutaController', 'crear');
$router->post('/rutas/actualizar', 'RutaController', 'actualizar');
$router->post('/rutas/asignar',    'RutaController', 'asignar');
$router->post('/rutas/contenedores', 'RutaController', 'contenedores');

// Pantalla 8 — Cuadrillas (camión + estado operativo; el alta es de USUARIO)
$router->get('/cuadrillas',            'CuadrillaController', 'index');
$router->get('/cuadrillas/editar',     'CuadrillaController', 'editar');
$router->post('/cuadrillas/actualizar', 'CuadrillaController', 'actualizar');

// Pantalla 9 — Recompensas (catálogo + métricas de gamificación)
$router->get('/recompensas',            'RecompensaController', 'index');
$router->get('/recompensas/editar',     'RecompensaController', 'editar');
$router->post('/recompensas/crear',      'RecompensaController', 'crear');
$router->post('/recompensas/actualizar', 'RecompensaController', 'actualizar');
$router->post('/recompensas/eliminar',   'RecompensaController', 'eliminar');

// Pantalla 10 — Informes (solo lectura; PDF por el diálogo de impresión)
$router->get('/informes', 'InformeController', 'index');
