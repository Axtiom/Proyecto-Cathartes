<?php /** @var string $contenido */ ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e(Config::get('app.nombre', 'SiGeRU')) ?></title>
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
