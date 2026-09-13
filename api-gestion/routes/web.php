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

// Universo Vecino (protegido + rol vecino, scopeado por sesión)
$router->get('/vecino/metricas', 'VecinoController', 'metricas');
$router->get('/vecino/incidencias', 'VecinoController', 'incidencias');
$router->post('/vecino/incidencias', 'VecinoController', 'reportar');
$router->get('/vecino/incidencia', 'VecinoController', 'incidencia');       // ?id=N
$router->get('/vecino/incidencia-foto', 'VecinoController', 'foto');       // ?id=N
