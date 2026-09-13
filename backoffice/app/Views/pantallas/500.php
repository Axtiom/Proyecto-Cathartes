<?php
/**
 * 500 del Backoffice — error no capturado. Página autónoma: NO incluye el chrome
 * (head/sidebar), porque el sidebar toca la sesión y acá justamente no se puede
 * asumir que el estado de la app sea sano.
 *
 * Espera: $e (Throwable) y $depurar (bool). Sin APP_DEBUG no se imprime NADA del
 * error: ni mensaje, ni archivo, ni SQL. El detalle está en el log de PHP.
 */
$e       = $e ?? null;
$depurar = $depurar ?? false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error del servidor · Backoffice Cathartes</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700&family=DM+Sans:wght@300;400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(ruta('css/admin.css')) ?>">
</head>
<body class="bo bo--centrado">
    <div class="bo-login" style="text-align:center">
        <p style="font-family:var(--fuente-titulo);font-size:64px;color:var(--rojo);margin:0;line-height:1">500</p>
        <h1>Algo falló del lado del servidor</h1>
        <p class="bo-login__sub">La operación no se completó. El detalle quedó en el log del servidor.</p>
        <a class="bo-btn bo-btn--pri" href="<?= e(ruta('usuarios')) ?>">Volver al backoffice</a>

        <?php if ($depurar && $e !== null): ?>
            <!-- Solo con APP_DEBUG=true (dev). En producción este bloque no se imprime. -->
            <div class="bo-aviso bo-aviso--error" style="text-align:left;margin-top:18px">
                <strong><?= e(get_class($e)) ?></strong>
                <p style="margin:6px 0 0"><?= e($e->getMessage()) ?></p>
                <p class="bo-mono" style="margin:6px 0 0;font-size:12px"><?= e($e->getFile() . ':' . $e->getLine()) ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
