<?php
/**
 * Pantalla 2 — Detalle de incidencia. Muestra la foto del reporte y permite
 * validar (dispara +150 pts por trigger) y asignar una cuadrilla (crea una
 * intervencion). Espera: $admin, $activo, $inc, $cuadrillas, $flash, $csrf.
 */
$titulo = 'Incidencia #' . (int) $inc['ID_incidencia'] . ' · Backoffice';
$fh = fn($iso) => ($m = preg_match('/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', (string) $iso, $x)) ? "$x[3]/$x[2]/$x[1] $x[4]:$x[5]" : '—';
$iv = $inc['intervencion'];

require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <a class="bo-btn bo-btn--sm bo-btn--tenue" href="<?= e(ruta('incidencias')) ?>">← Volver a incidencias</a>
        <h1 style="margin-top:8px">Incidencia #<?= (int) $inc['ID_incidencia'] ?></h1>
    </div>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--ok"><?= e($flash) ?></div><?php endif; ?>

<div class="bo-detalle">
    <!-- Columna izquierda: foto + datos -->
    <div>
        <?php if ($inc['foto'] !== null && $inc['foto'] !== ''): ?>
            <img class="bo-foto" src="<?= e(ruta('incidencias/foto') . '?id=' . (int) $inc['ID_incidencia']) ?>" alt="Foto del reporte">
        <?php else: ?>
            <div class="bo-foto bo-foto--sin">Sin foto adjunta</div>
        <?php endif; ?>

        <div class="bo-panel">
            <div class="bo-dato"><span>Tipo</span><span><?= e($inc['tipo']) ?></span></div>
            <div class="bo-dato"><span>Estado</span><span class="bo-badge bo-badge--<?= $inc['estado'] === 'finalizada' ? 'ok' : 'rol' ?>"><?= e($inc['estado']) ?></span></div>
            <div class="bo-dato"><span>Validada</span><span class="bo-badge bo-badge--<?= $inc['validada'] === 'si' ? 'ok' : 'baja' ?>"><?= $inc['validada'] === 'si' ? 'Sí (+150 pts)' : 'No' ?></span></div>
            <div class="bo-dato"><span>Fecha</span><span><?= e($fh($inc['fecha_ing'])) ?></span></div>
            <div class="bo-dato"><span>Contenedor</span><span>#<?= (int) $inc['id_contenedor'] ?> · <?= e($inc['ubicacion']) ?></span></div>
            <div class="bo-dato"><span>Zona · Ruta</span><span><?= e($inc['zona']) ?> · <?= e($inc['ruta']) ?></span></div>
            <div class="bo-dato"><span>Vecino</span><span><?= e($inc['vecino']) ?></span></div>
            <div class="bo-dato"><span>Descripción</span><span><?= e($inc['descripcion'] ?: '—') ?></span></div>
        </div>
    </div>

    <!-- Columna derecha: acciones + intervención -->
    <div>
        <div class="bo-panel">
            <h2 class="bo-panel__tit">Validación</h2>
            <?php if ($inc['validada'] === 'si'): ?>
                <p class="bo-dim">Ya validada. Se acreditaron 150 puntos al vecino.</p>
            <?php else: ?>
                <p class="bo-sub">Al validar, el vecino recibe 150 puntos (regla de gamificación).</p>
                <form method="post" action="<?= e(ruta('incidencias/validar')) ?>">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $inc['ID_incidencia'] ?>">
                    <button type="submit" class="bo-btn bo-btn--pri">Validar reporte</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="bo-panel" style="margin-top:16px">
            <h2 class="bo-panel__tit">Cuadrilla asignada</h2>
            <?php if ($iv !== null): ?>
                <div class="bo-dato"><span>Cuadrilla</span><span class="bo-badge bo-badge--rol"><?= e($iv['cuadrilla']) ?></span></div>
                <div class="bo-dato"><span>Inicio</span><span><?= e($fh($iv['fecha_inicio'])) ?></span></div>
                <div class="bo-dato"><span>Fin</span><span><?= $iv['fecha_fin'] ? e($fh($iv['fecha_fin'])) : '<span class="bo-dim">en curso</span>' ?></span></div>
                <div class="bo-dato"><span>Tarea finalizada</span><span><?= $iv['tarea_finalizada'] === 'si' ? 'Sí' : 'No' ?></span></div>
            <?php else: ?>
                <p class="bo-sub">Todavía sin cuadrilla. Asigná una para crear la intervención.</p>
                <form method="post" action="<?= e(ruta('incidencias/asignar')) ?>">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $inc['ID_incidencia'] ?>">
                    <label class="bo-campo"><span>Cuadrilla</span>
                        <select name="id_cuadrilla" required>
                            <option value="">Elegí una cuadrilla…</option>
                            <?php foreach ($cuadrillas as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"><?= e($c['nombre']) ?> (<?= e($c['estado']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="bo-btn bo-btn--pri" style="margin-top:10px">Asignar cuadrilla</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
