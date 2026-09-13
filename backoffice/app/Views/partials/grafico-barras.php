<?php
/**
 * Gráfico de barras en SVG generado por el servidor. Sin librerías: entra al PDF
 * del navegador sin depender de JavaScript, y el marcado se lee en el HTML.
 *
 * Espera:
 *   $g_datos   array  etiqueta => valor (entero >= 0). El orden se respeta.
 *   $g_titulo  string opcional, para el aria-label.
 *
 * Los meses en cero se dibujan igual (barra de altura mínima): un hueco en la
 * serie mentiría por omisión. Colores de los tokens de la landing.
 */
$g_datos  = $g_datos ?? [];
$g_titulo = $g_titulo ?? 'Gráfico de barras';

$anchoTotal = 640;
$altoTotal  = 220;
$margenIzq  = 38;
$margenAb   = 30;
$margenArr  = 14;
$areaAncho  = $anchoTotal - $margenIzq - 10;
$areaAlto   = $altoTotal - $margenArr - $margenAb;

$n   = max(1, count($g_datos));
$max = max(1, $g_datos ? (int) max($g_datos) : 1);
$paso = $areaAncho / $n;
$ancho = min(54, $paso * 0.6);
?>
<svg class="bo-grafico" viewBox="0 0 <?= $anchoTotal ?> <?= $altoTotal ?>" role="img"
     aria-label="<?= e($g_titulo) ?>" preserveAspectRatio="xMidYMid meet">
    <!-- Líneas de referencia: 0, mitad y máximo -->
    <?php foreach ([0, 1, 2] as $i): ?>
        <?php $v = (int) round($max * $i / 2); $y = $margenArr + $areaAlto - ($areaAlto * $i / 2); ?>
        <line x1="<?= $margenIzq ?>" y1="<?= round($y, 1) ?>" x2="<?= $margenIzq + $areaAncho ?>" y2="<?= round($y, 1) ?>"
              stroke="#263156" stroke-width="1"/>
        <text x="<?= $margenIzq - 6 ?>" y="<?= round($y + 4, 1) ?>" text-anchor="end"
              font-size="11" fill="#9AA6C4"><?= $v ?></text>
    <?php endforeach; ?>

    <?php $i = 0; foreach ($g_datos as $etiqueta => $valor): ?>
        <?php
        $valor = max(0, (int) $valor);
        $x = $margenIzq + $paso * $i + ($paso - $ancho) / 2;
        // Altura mínima de 2px para que un cero se vea como cero y no como nada.
        $alto = $valor > 0 ? max(2, $areaAlto * $valor / $max) : 2;
        $y = $margenArr + $areaAlto - $alto;
        ?>
        <rect x="<?= round($x, 1) ?>" y="<?= round($y, 1) ?>" width="<?= round($ancho, 1) ?>" height="<?= round($alto, 1) ?>"
              rx="3" fill="<?= $valor > 0 ? '#2FBF71' : '#263156' ?>"/>
        <?php if ($valor > 0): ?>
            <text x="<?= round($x + $ancho / 2, 1) ?>" y="<?= round($y - 4, 1) ?>" text-anchor="middle"
                  font-size="11" fill="#E6EAF5"><?= $valor ?></text>
        <?php endif; ?>
        <text x="<?= round($x + $ancho / 2, 1) ?>" y="<?= $altoTotal - 10 ?>" text-anchor="middle"
              font-size="11" fill="#9AA6C4"><?= e($etiqueta) ?></text>
        <?php $i++; ?>
    <?php endforeach; ?>
</svg>
