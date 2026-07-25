<?php
/**
 * Pantalla 4 — Editar contenedor + baja. Espera: $admin, $activo, $c, $rutas,
 * $flash, $csrf. El estado 'dado de baja' solo se alcanza por la acción de baja;
 * editar el estado a un valor operativo reactiva el contenedor.
 */
$titulo = 'Contenedor #' . (int) $c['ID'] . ' · Backoffice';
$deBaja = $c['estado'] === 'dado de baja';
$estadoSel = $deBaja ? 'funcional' : $c['estado'];
$fh = fn($iso) => ($m = preg_match('/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', (string) $iso, $x)) ? "$x[3]/$x[2]/$x[1] $x[4]:$x[5]" : '—';

require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <a class="bo-btn bo-btn--sm bo-btn--tenue" href="<?= e(ruta('contenedores')) ?>">← Volver a contenedores</a>
        <h1 style="margin-top:8px">Contenedor #<?= (int) $c['ID'] ?></h1>
    </div>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--ok"><?= e($flash) ?></div><?php endif; ?>

<div class="bo-detalle">
    <!-- Edición -->
    <div class="bo-panel">
        <h2 class="bo-panel__tit">Datos</h2>
        <?php if ($deBaja): ?>
            <div class="bo-aviso bo-aviso--error" style="margin-bottom:12px">Dado de baja el <?= e($fh($c['fecha_baja'])) ?>. Guardar los datos con un estado operativo lo reactiva.</div>
        <?php endif; ?>
        <form method="post" action="<?= e(ruta('contenedores/actualizar')) ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $c['ID'] ?>">
            <label class="bo-campo"><span>Ubicación</span><input type="text" name="ubicacion" value="<?= e($c['ubicacion']) ?>" required></label>
            <div class="bo-alta__fila">
                <label class="bo-campo"><span>Tipo</span>
                    <select name="tipo">
                        <option value="basura"<?= $c['tipo'] === 'basura' ? ' selected' : '' ?>>Basura</option>
                        <option value="reciclable"<?= $c['tipo'] === 'reciclable' ? ' selected' : '' ?>>Reciclable</option>
                    </select>
                </label>
                <label class="bo-campo"><span>Estado</span>
                    <select name="estado">
                        <?php foreach (['funcional', 'no funcional', 'desbordado'] as $es): ?>
                            <option value="<?= e($es) ?>"<?= $estadoSel === $es ? ' selected' : '' ?>><?= e(ucfirst($es)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label class="bo-campo"><span>Ruta</span>
                <select name="id_ruta" required>
                    <?php foreach ($rutas as $r): ?>
                        <option value="<?= (int) $r['ID'] ?>"<?= (int) $c['ID_ruta'] === (int) $r['ID'] ? ' selected' : '' ?>><?= e($r['zona'] . ' · ' . $r['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="bo-alta__fila">
                <label class="bo-campo"><span>Latitud</span><input type="text" name="latitud" value="<?= e($c['latitud']) ?>"></label>
                <label class="bo-campo"><span>Longitud</span><input type="text" name="longitud" value="<?= e($c['longitud']) ?>"></label>
            </div>
            <button type="submit" class="bo-btn bo-btn--pri" style="margin-top:6px">Guardar cambios</button>
        </form>
    </div>

    <!-- Baja -->
    <div class="bo-panel">
        <h2 class="bo-panel__tit">Baja</h2>
        <?php if ($deBaja): ?>
            <div class="bo-dato"><span>Estado</span><span class="bo-badge bo-badge--baja">dado de baja</span></div>
            <div class="bo-dato"><span>Fecha de baja</span><span><?= e($fh($c['fecha_baja'])) ?></span></div>
            <div class="bo-dato"><span>Motivo</span><span><?= e($c['motivo_baja'] ?: '—') ?></span></div>
        <?php else: ?>
            <p class="bo-sub">El contenedor no se elimina: la baja lo marca <em>dado de baja</em> y registra fecha y motivo.</p>
            <form method="post" action="<?= e(ruta('contenedores/baja')) ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int) $c['ID'] ?>">
                <label class="bo-campo"><span>Motivo de la baja</span>
                    <input type="text" name="motivo" placeholder="Ej: reemplazado por deterioro" required>
                </label>
                <button type="submit" class="bo-btn bo-btn--peligro" style="margin-top:10px">Dar de baja</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
