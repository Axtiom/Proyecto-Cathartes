<?php
declare(strict_types=1);

/**
 * Rutas de la API de Gestión. JSON. Lectura pública (estadísticas,
 * contenedores) y el universo Vecino (reportar y seguir sus incidencias).
 *
 * @var Router $router
 */

$router->get('/health', 'HealthController', 'index');

// Estadísticas públicas (sin sesión) — alimenta la landing y la pantalla 2.
$router->get('/estadisticas/publicas', 'EstadisticasController', 'publicas');

// Contenedores (lectura pública/vecino)
$router->get('/contenedores', 'ContenedorController', 'activos');
$router->get('/contenedores/todos', 'ContenedorController', 'todos');
$router->get('/contenedores/publicos', 'ContenedorController', 'publicos'); // sin sesión (landing)

// Registro (solo administrador)
$router->post('/contenedores', 'ContenedorController', 'crear');
$router->get('/contenedores/detalle', 'ContenedorController', 'detalle');   // ?id=N
$router->put('/contenedores', 'ContenedorController', 'actualizar');         // ?id=N
$router->delete('/contenedores', 'ContenedorController', 'baja');            // ?id=N (baja lógica)
$router->get('/rutas', 'ContenedorController', 'rutas');

// Flota de camiones (solo administrador)
$router->get('/camiones', 'CamionController', 'listar');
$router->get('/camiones/detalle', 'CamionController', 'detalle');   // ?id=N
$router->post('/camiones', 'CamionController', 'crear');
$router->put('/camiones', 'CamionController', 'actualizar');                 // ?id=N
$router->delete('/camiones', 'CamionController', 'baja');                    // ?id=N (baja lógica)

// Instalaciones: vertederos y centros de acopio (solo administrador)
$router->get('/instalaciones', 'InstalacionController', 'listar');
$router->get('/instalaciones/detalle', 'InstalacionController', 'detalle');  // ?id=N
$router->post('/instalaciones', 'InstalacionController', 'crear');
$router->put('/instalaciones', 'InstalacionController', 'actualizar');       // ?id=N
$router->delete('/instalaciones', 'InstalacionController', 'eliminar');      // ?id=N

// Maquinaria de las instalaciones (solo administrador)
$router->get('/maquinaria', 'MaquinariaController', 'listar');
$router->get('/maquinaria/detalle', 'MaquinariaController', 'detalle');      // ?id=N
$router->post('/maquinaria', 'MaquinariaController', 'crear');
$router->put('/maquinaria', 'MaquinariaController', 'actualizar');           // ?id=N
$router->delete('/maquinaria', 'MaquinariaController', 'eliminar');          // ?id=N

// Universo Vecino (protegido + rol vecino, scopeado por sesión)
$router->get('/vecino/metricas', 'VecinoController', 'metricas');
$router->get('/vecino/incidencias', 'VecinoController', 'incidencias');
$router->post('/vecino/incidencias', 'VecinoController', 'reportar');
$router->get('/vecino/incidencia', 'VecinoController', 'incidencia');       // ?id=N
$router->get('/vecino/incidencia-foto', 'VecinoController', 'foto');       // ?id=N
