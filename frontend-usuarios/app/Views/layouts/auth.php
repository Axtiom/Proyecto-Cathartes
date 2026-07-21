<?php
/**
 * Layout de las pantallas de acceso (login / registro / 2FA / recuperar).
 *
 * @var string $contenido  card de la vista
 * @var string $titulo     título de la pestaña
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e(($titulo ?? 'Acceso') . ' · Cathartes') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700&family=DM+Sans:wght@300;400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/estilos.css">
</head>
<body>
    <div class="pantalla">
        <div class="pantalla__amb"></div>
        <div class="pantalla__glow pantalla__glow--verde"></div>
        <div class="pantalla__glow pantalla__glow--lavanda"></div>

        <?= $contenido ?>
    </div>
</body>
</html>
