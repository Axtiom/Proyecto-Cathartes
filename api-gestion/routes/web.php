<?php
declare(strict_types=1);

/** @var Router $router */

// Salud del motor + BD
$router->get('/health', 'HealthController', 'index');

// Estadísticas públicas (sin sesión) — alimenta la landing y la pantalla 2.
$router->get('/estadisticas/publicas', 'EstadisticasController', 'publicas');

// --- Contenedores (lectura; usuario autenticado) ---------------------------
$router->get('/contenedores',          'ContenedorController', 'activos');
$router->get('/contenedores/todos',    'ContenedorController', 'todos');
$router->get('/contenedores/publicos', 'ContenedorController', 'publicos');   // sin sesión (landing)

// --- Universo Vecino (Fase 2; protegido + rol vecino, scopeado por token) ---
$router->get('/vecino/metricas',        'VecinoController', 'metricas');
$router->get('/vecino/incidencias',     'VecinoController', 'incidencias');
$router->post('/vecino/incidencias',    'VecinoController', 'reportar');
$router->get('/vecino/incidencia',      'VecinoController', 'incidencia');       // ?id=N
$router->get('/vecino/incidencia-foto', 'VecinoController', 'foto');             // ?id=N

// ---------------------------------------------------------------------------
// Pendiente (fases siguientes, ya especificado en docs/contratos-api.md):
//   Backoffice y Operario — instalaciones, camiones, rutas, cuadrillas,
//   recompensas (admin), informes. Protegidos con rol Administrador/Operario.
// ---------------------------------------------------------------------------
