<?php
/**
 * Pantalla 9 — Editar recompensa + quita del catálogo.
 * Espera: $admin, $activo, $r, $canjes, $flash, $csrf.
 *
 * La quita es un DELETE real (no hay columna de estado) y solo procede si la
 * recompensa nunca se canjeó: `canje` la referencia con RESTRICT y el historial
 * manda. Con canjes, el panel muestra por qué no se puede y qué hacer en su lugar.
 */
$titulo   = 'Recompensa #' . (int) $r['ID'] . ' · Backoffice';
$tieneUso = count($canjes) > 0;
$gastado  = array_sum(array_map(fn($c) => (int) $c['puntos_gastados'], $canjes));
$num = fn($n) => number_format((int) $n, 0, ',', '.');
$fh  = fn($iso) => ($m = preg_match('/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', (string) $iso, $x)) ? "$x[3]/$x[2]/$x[1] $x[4]:$x[5]" : '—';

require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <a class="bo-btn bo-btn--sm bo-btn--tenue" href="<?= e(ruta('recompensas')) ?>">← Volver a recompensas</a>
        <h1 style="margin-top:8px">Recompensa #<?= (int) $r['ID'] ?> · <?= e($r['nombre']) ?></h1>
    </div>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div><?php endif; ?>

<div class="bo-detalle">
    <div class="bo-panel">
        <h2 class="bo-panel__tit">Datos</h2>
        <p class="bo-sub" style="font-size:13px;margin:0 0 12px">
            Cambiar el costo no toca los canjes ya hechos: `canje.puntos_gastados` guarda
            lo que se pagó en su momento.
        </p>
        <form method="post" action="<?= e(ruta('recompensas/actualizar')) ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $r['ID'] ?>">
            <label class="bo-campo"><span>Nombre</span><input type="text" name="nombre" maxlength="100" value="<?= e($r['nombre']) ?>" required></label>
            <label class="bo-campo"><span>Descripción <em>(opcional)</em></span><input type="text" name="descripcion" maxlength="255" value="<?= e((string) $r['descripcion']) ?>"></label>
            <label class="bo-campo"><span>Costo en puntos</span><input type="number" name="valor" min="1" value="<?= (int) $r['valor'] ?>" required></label>
            <button type="submit" class="bo-btn bo-btn--pri" style="margin-top:10px">Guardar cambios</button>
        </form>
    </div>

    <div class="bo-panel">
        <h2 class="bo-panel__tit">Quitar del catálogo</h2>
        <p class="bo-sub" style="font-size:13px;margin:0 0 12px">
            Una recompensa está activa mientras esté en el catálogo: no hay interruptor.
            Quitarla la saca de lo que el vecino puede canjear.
        </p>

        <?php if ($tieneUso): ?>
            <div class="bo-dato"><span>Canjes registrados</span><span><?= count($canjes) ?></span></div>
            <div class="bo-dato"><span>Puntos canjeados</span><span><?= e($num($gastado)) ?></span></div>
            <div class="bo-aviso bo-aviso--error" style="margin:12px 0 0">
                No se puede quitar: ya la canjearon. Borrarla se llevaría puesto el historial de
                canjes de esos vecinos. Si no querés que se siga canjeando, subile el costo o
                cambiale el nombre para retirarla de circulación.
            </div>
            <button type="button" class="bo-btn bo-btn--peligro bo-btn--bloqueado" style="margin-top:12px"
                    data-aviso="Esta recompensa tiene canjes registrados: quitarla rompería el historial. Cambiale el costo en su lugar.">
                Quitar del catálogo
            </button>
        <?php else: ?>
            <p class="bo-dim">Nunca se canjeó, así que se puede quitar sin perder historial.</p>
            <form method="post" action="<?= e(ruta('recompensas/eliminar')) ?>" style="margin-top:10px">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int) $r['ID'] ?>">
                <button type="submit" class="bo-btn bo-btn--peligro">Quitar del catálogo</button>
            </form>
        <?php endif; ?>

        <h3 class="bo-panel__tit" style="font-size:15px;margin:20px 0 6px">Canjes de esta recompensa (<?= count($canjes) ?>)</h3>
        <?php if (!$canjes): ?>
            <p class="bo-dim">Sin canjes.</p>
        <?php else: ?>
            <ul class="bo-lista">
                <?php foreach ($canjes as $c): ?>
                    <li>
                        <?= e($fh($c['fecha'])) ?> ·
                        <span class="bo-mono">#<?= (int) $c['ID_vecino'] ?></span> <?= e($c['nombre']) ?>
                        <span class="bo-badge bo-badge--rol"><?= e($num($c['puntos_gastados'])) ?> pts</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
