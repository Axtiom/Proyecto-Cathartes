<?php
/**
 * Chrome del Backoffice (parte superior): <head>, sidebar y apertura del <main>.
 * Espera: $titulo (título de la pestaña), $activo (sección del sidebar a marcar),
 * $admin (datos del admin logueado). El texto fijo vive acá; los datos se
 * imprimen escapados con e().
 */
$titulo = $titulo ?? 'Backoffice · Cathartes';
$activo = $activo ?? '';
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
    <link rel="stylesheet" href="<?= e(ruta('css/admin.css')) ?>">
</head>
<body class="bo">
<div class="bo-shell">
    <?php require BASE_PATH . '/app/Views/partials/sidebar.php'; ?>
    <main class="bo-main">
