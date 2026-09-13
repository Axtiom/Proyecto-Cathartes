<?php
/**
 * 403 del Backoffice — autenticado pero sin rol administrador. No revela qué hay
 * del otro lado: mensaje genérico + link al inicio del rol real. Espera:
 * $inicioUrl (URL del inicio del rol del usuario).
 */
$inicioUrl = $inicioUrl ?? '#';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sin permiso · Backoffice Cathartes</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(ruta('css/admin.css')) ?>">
</head>
<body class="bo bo--centrado">
    <div class="bo-login" style="text-align:center">
        <p style="font-family:var(--fuente-titulo);font-size:64px;color:var(--rojo);margin:0;line-height:1">403</p>
        <h1>No tenés permiso</h1>
        <p class="bo-login__sub">El backoffice es exclusivo para administradores.</p>
        <a class="bo-btn bo-btn--pri" href="<?= e($inicioUrl) ?>">Ir a tu inicio</a>
    </div>
</body>
</html>
