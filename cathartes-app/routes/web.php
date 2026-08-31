<?php
declare(strict_types=1);

/**
 * Rutas de la app unificada de Cathartes.
 *
 * Antes eran 4 archivos de rutas (uno por app). Se fusionaron acá:
 *   - Rutas SIN prefijo  -> pantallas del backoffice (HTML, GET renderiza /
 *     POST muta y redirige).
 *   - Rutas bajo /api/*  -> lo que antes eran api-usuarios, api-gestion y
 *     api-recoleccion (JSON), consumidas por fetch desde frontend-usuarios
 *     y landing-page.
 *
 * @var Router $router
 */

// ============================================================ BACKOFFICE ===
// Acceso (login unificado en frontend-usuarios; acá solo entrada y logout)
$router->get('/', 'AdminAccesoController', 'index');
$router->post('/logout', 'AdminAccesoController', 'logout');

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

// Recompensas (admin: catálogo + métricas de gamificación)
$router->get('/recompensas', 'RecompensaController', 'index');
$router->get('/recompensas/editar', 'RecompensaController', 'editar');
$router->post('/recompensas/crear', 'RecompensaController', 'crear');
$router->post('/recompensas/actualizar', 'RecompensaController', 'actualizar');
$router->post('/recompensas/eliminar', 'RecompensaController', 'eliminar');

// Informes (solo lectura; PDF por el diálogo de impresión)
$router->get('/informes', 'InformeController', 'index');

// ================================================================== API ===
// Salud del motor + BD
$router->get('/api/health', 'HealthController', 'index');

// Autenticación (login de un solo paso, sin verificación en dos pasos)
$router->post('/api/registro', 'AccesoController', 'registro');
$router->post('/api/login', 'AccesoController', 'login');
$router->post('/api/recuperar', 'AccesoController', 'recuperar');
$router->post('/api/restablecer', 'AccesoController', 'restablecer');
$router->post('/api/logout', 'AccesoController', 'logout');

// Protegido (cookie + kill-switch). Prueba de sesión, reusado por el frontend.
$router->get('/api/yo', 'AccesoController', 'yo');

// Protegido — saldo de puntos del vecino en sesión (badge del header, F12).
$router->get('/api/saldo', 'AccesoController', 'saldo');

// Gamificación del vecino
$router->get('/api/recompensas', 'RecompensaVecinoController', 'catalogo');
$router->post('/api/canjes', 'RecompensaVecinoController', 'canjear');
$router->get('/api/movimientos', 'RecompensaVecinoController', 'movimientos');
$router->get('/api/mis-canjes', 'RecompensaVecinoController', 'misCanjes');

// Perfil (cualquier rol)
$router->get('/api/perfil', 'PerfilController', 'mostrar');
$router->post('/api/perfil', 'PerfilController', 'actualizar');

// Estadísticas públicas (sin sesión) — alimenta la landing y la pantalla 2.
$router->get('/api/estadisticas/publicas', 'EstadisticasController', 'publicas');

// Contenedores (lectura pública/vecino)
$router->get('/api/contenedores', 'ContenedorPublicoController', 'activos');
$router->get('/api/contenedores/todos', 'ContenedorPublicoController', 'todos');
$router->get('/api/contenedores/publicos', 'ContenedorPublicoController', 'publicos'); // sin sesión (landing)

// Universo Vecino (protegido + rol vecino, scopeado por token)
$router->get('/api/vecino/metricas', 'VecinoController', 'metricas');
$router->get('/api/vecino/incidencias', 'VecinoController', 'incidencias');
$router->post('/api/vecino/incidencias', 'VecinoController', 'reportar');
$router->get('/api/vecino/incidencia', 'VecinoController', 'incidencia');       // ?id=N
$router->get('/api/vecino/incidencia-foto', 'VecinoController', 'foto');       // ?id=N

// Ruta del día de la cuadrilla autenticada (protegido: cookie + rol Cuadrilla)
$router->get('/api/cuadrilla/ruta', 'CuadrillaAppController', 'ruta');
