<?php
declare(strict_types=1);

/** @var Router $router */

// Salud del motor + BD
$router->get('/health', 'HealthController', 'index');

// Ruta del día de la cuadrilla autenticada (protegido: Bearer + rol Cuadrilla).
$router->get('/cuadrilla/ruta', 'CuadrillaController', 'ruta');

// ---------------------------------------------------------------------------
// Pendiente (fases siguientes, ya especificado en docs/contratos-api.md):
//   Cuadrilla — detalle de ruta con check de recorrido, incidencias asignadas,
//   marcar incidencia resuelta (solo líder), camión asignado. Todos protegidos.
// ---------------------------------------------------------------------------
