<?php /** @var string $contenido */ ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e(Config::get('app.nombre', 'SiGeRU')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700&family=DM+Sans:wght@300;400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/estilos.css">
</head>
<body>
    <?php View::partial('header'); ?>

    <main>
        <?= $contenido ?>
    </main>

    <?php View::partial('footer'); ?>

    <?php View::partial('fab'); ?>
</body>
</html>
