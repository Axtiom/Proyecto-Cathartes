<?php
/**
 * Gráfico de torta en SVG generado por el servidor. Sin librerías.
 *
 * Espera:
 *   $g_datos   array  etiqueta => valor (entero >= 0). El orden se respeta.
 *   $g_titulo  string opcional, para el aria-label.
 *
 * Los sectores se dibujan con `path` y arcos. Caso borde resuelto: si un solo
 * valor concentra el 100%, un arco de 360° con `A` no dibuja nada (el punto inicial
 * y el final coinciden), así que se usa un `circle`. Un total de 0 muestra la
 * leyenda en cero sin dibujar torta.
 *
 * Colores: tokens de la landing, en orden fijo, para que la misma categoría tenga
 * siempre el mismo color entre una carga y otra.
 */
$g_datos  = $g_datos ?? [];
$g_titulo = $g_titulo ?? 'Gráfico de torta';

$colores = ['#2FBF71', '#A9B4F0', '#6FB2FB', '#F5B54A', '#F58A8A', '#5E6690'];
$total   = array_sum(array_map('intval', $g_datos));
$cx = 110; $cy = 110; $r = 92;

/** Punto del borde del círculo para un ángulo dado (0 = arriba, sentido horario). */
$punto = function (float $frac) use ($cx, $cy, $r): array {
    $a = 2 * M_PI * $frac - M_PI / 2;
    return [round($cx + $r * cos($a), 2), round($cy + $r * sin($a), 2)];
};
?>
<div class="bo-torta">
    <svg class="bo-grafico bo-grafico--torta" viewBox="0 0 220 220" role="img"
         aria-label="<?= e($g_titulo) ?>" preserveAspectRatio="xMidYMid meet">
        <?php if ($total <= 0): ?>
            <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>" fill="none" stroke="#263156" stroke-width="2"/>
            <text x="<?= $cx ?>" y="<?= $cy + 4 ?>" text-anchor="middle" font-size="13" fill="#6E72A0">sin datos</text>
        <?php else: ?>
            <?php $acum = 0.0; $i = 0; ?>
            <?php foreach ($g_datos as $etiqueta => $valor): ?>
                <?php
                $valor = max(0, (int) $valor);
                if ($valor === 0) { $i++; continue; }
                $frac  = $valor / $total;
                $color = $colores[$i % count($colores)];
                ?>
                <?php if ($frac >= 1): ?>
                    <!-- 100% en un solo sector: un arco de 360° no dibuja nada. -->
                    <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>" fill="<?= $color ?>"/>
                <?php else: ?>
                    <?php
                    [$x1, $y1] = $punto($acum);
                    [$x2, $y2] = $punto($acum + $frac);
                    $grande = $frac > 0.5 ? 1 : 0;
                    ?>
                    <path d="M <?= $cx ?> <?= $cy ?> L <?= $x1 ?> <?= $y1 ?> A <?= $r ?> <?= $r ?> 0 <?= $grande ?> 1 <?= $x2 ?> <?= $y2 ?> Z"
                          fill="<?= $color ?>" stroke="#0B1020" stroke-width="1.5"/>
                <?php endif; ?>
                <?php $acum += $frac; $i++; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </svg>

    <ul class="bo-leyenda">
        <?php $i = 0; foreach ($g_datos as $etiqueta => $valor): ?>
            <?php $valor = max(0, (int) $valor); $pct = $total > 0 ? round($valor * 100 / $total) : 0; ?>
            <li>
                <span class="bo-leyenda__color" style="background:<?= $colores[$i % count($colores)] ?>"></span>
                <span class="bo-leyenda__txt"><?= e($etiqueta) ?></span>
                <span class="bo-leyenda__n"><?= $valor ?> · <?= $pct ?>%</span>
            </li>
            <?php $i++; ?>
        <?php endforeach; ?>
    </ul>
</div>
