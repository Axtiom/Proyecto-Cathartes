<?php
/**
 * Pantalla 5 — Editar camión + baja. Espera: $admin, $activo, $c, $cuadrilla,
 * $flash, $csrf.
 */
$titulo = 'Camión #' . (int) $c['ID'] . ' · Backoffice';
$deBaja = $c['estado'] === 'dado de baja';
$estadoSel = $deBaja ? 'funcional' : $c['estado'];
$fh = fn($iso) => ($m = preg_match('/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', (string) $iso, $x)) ? "$x[3]/$x[2]/$x[1] $x[4]:$x[5]" : '—';

require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <a class="bo-btn bo-btn--sm bo-btn--tenue" href="<?= e(ruta('camiones')) ?>">← Volver a camiones</a>
        <h1 style="margin-top:8px">Camión #<?= (int) $c['ID'] ?> · <?= e($c['matricula']) ?></h1>
    </div>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div><?php endif; ?>

<div class="bo-detalle">
    <div class="bo-panel">
        <h2 class="bo-panel__tit">Datos</h2>
        <?php if ($deBaja): ?>
            <div class="bo-aviso bo-aviso--error" style="margin-bottom:12px">Dado de baja el <?= e($fh($c['fecha_baja'])) ?>. Guardar con un estado operativo lo reactiva.</div>
        <?php endif; ?>
        <div class="bo-dato"><span>Cuadrilla asignada</span><span><?= $cuadrilla ? '<span class="bo-badge bo-badge--rol">' . e($cuadrilla) . '</span>' : '<span class="bo-dim">libre</span>' ?></span></div>
        <form method="post" action="<?= e(ruta('camiones/actualizar')) ?>" style="margin-top:8px">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $c['ID'] ?>">
            <div class="bo-alta__fila">
                <label class="bo-campo"><span>Matrícula</span><input type="text" name="matricula" value="<?= e($c['matricula']) ?>" required></label>
                <label class="bo-campo"><span>Capacidad (kg)</span><input type="number" name="capacidad" min="1" value="<?= (int) $c['capacidad'] ?>" required></label>
            </div>
            <label class="bo-campo"><span>Modelo</span><input type="text" name="modelo" value="<?= e($c['modelo']) ?>" required></label>
            <label class="bo-campo"><span>Estado</span>
                <select name="estado">
                    <?php foreach (['funcional', 'en mantenimiento', 'no funcional'] as $es): ?>
                        <option value="<?= e($es) ?>"<?= $estadoSel === $es ? ' selected' : '' ?>><?= e(ucfirst($es)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="bo-btn bo-btn--pri" style="margin-top:6px">Guardar cambios</button>
        </form>
    </div>

    <div class="bo-panel">
        <h2 class="bo-panel__tit">Baja</h2>
        <?php if ($deBaja): ?>
            <div class="bo-dato"><span>Estado</span><span class="bo-badge bo-badge--baja">dado de baja</span></div>
            <div class="bo-dato"><span>Fecha de baja</span><span><?= e($fh($c['fecha_baja'])) ?></span></div>
            <div class="bo-dato"><span>Motivo</span><span><?= e($c['motivo_baja'] ?: '—') ?></span></div>
        <?php else: ?>
            <p class="bo-sub">El camión no se elimina: la baja lo marca <em>dado de baja</em> y registra fecha y motivo.</p>
            <form method="post" action="<?= e(ruta('camiones/baja')) ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int) $c['ID'] ?>">
                <label class="bo-campo"><span>Motivo de la baja</span><input type="text" name="motivo" placeholder="Ej: fin de vida útil" required></label>
                <button type="submit" class="bo-btn bo-btn--peligro" style="margin-top:10px">Dar de baja</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
