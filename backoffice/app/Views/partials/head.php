<?php
/**
 * Chrome del Backoffice (parte superior): <head>, sidebar y apertura del <main>.
 * Espera: $titulo (título de la pestaña), $activo (sección del sidebar a marcar),
 * $admin (datos del admin logueado). El texto fijo vive acá; los datos se
 * imprimen escapados con e().
 *
 * Opcional: $mapa = true en la vista (antes de este require) para que la pantalla
 * cargue la hoja de Leaflet. Los assets son LOCALES (public/assets/leaflet/), no
 * de CDN: cada una de las 7 propiedades se despliega sola y no depende de red
 * ajena. Los tiles sí salen a OpenStreetMap (es un servicio, no un asset).
 *
 * Opcional: $impresion = true para cargar print.css (salida a PDF por el diálogo
 * del navegador, Pantalla 10). Va con media="print", así que no toca la pantalla.
 */
$titulo    = $titulo ?? 'Backoffice · Cathartes';
$activo    = $activo ?? '';
$mapa      = $mapa ?? false;
$impresion = $impresion ?? false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700&family=DM+Sans:wght@300;400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <?php if ($mapa): ?><link rel="stylesheet" href="<?= e(ruta('assets/leaflet/leaflet.css')) ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(ruta('css/admin.css')) ?>">
    <?php if ($impresion): ?><link rel="stylesheet" media="print" href="<?= e(ruta('css/print.css')) ?>"><?php endif; ?>
</head>
<body class="bo">
<div class="bo-shell">
    <?php require BASE_PATH . '/app/Views/partials/sidebar.php'; ?>
    <main class="bo-main">
