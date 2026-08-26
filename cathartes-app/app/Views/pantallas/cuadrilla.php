<?php
/**
 * Pantalla 8 — Editar cuadrilla. Espera: $admin, $activo, $c, $camiones,
 * $historial, $intervenciones, $flash, $csrf.
 *
 * Acá se edita SOLO lo del subtipo: camión y estado operativo. La cuenta
 * (nombre, email, baja) se administra en Usuarios; la ruta, en Rutas.
 */
$titulo   = 'Cuadrilla #' . (int) $c['ID'] . ' · Backoffice';
$vigente  = $historial[0] ?? null;
$enServicio = $c['operativo'] === 'en servicio';
$fh = fn($iso) => ($m = preg_match('/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', (string) $iso, $x)) ? "$x[3]/$x[2]/$x[1] $x[4]:$x[5]" : '—';

require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <a class="bo-btn bo-btn--sm bo-btn--tenue" href="<?= e(ruta('cuadrillas')) ?>">← Volver a cuadrillas</a>
        <h1 style="margin-top:8px">Cuadrilla #<?= (int) $c['ID'] ?> · <?= e($c['nombre']) ?></h1>
    </div>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div><?php endif; ?>

<?php if ($c['cuenta'] !== 'activo'): ?>
    <div class="bo-aviso bo-aviso--error">
        La cuenta de esta cuadrilla está <strong>no activa</strong>: no puede iniciar sesión ni operar,
        aunque su estado operativo diga otra cosa. Se reactiva desde Usuarios.
    </div>
<?php endif; ?>

<div class="bo-detalle">
    <div class="bo-panel">
        <h2 class="bo-panel__tit">Operación</h2>
        <p class="bo-sub" style="font-size:13px;margin:0 0 12px">
            Camión y estado operativo. Un camión no puede estar en dos cuadrillas a la vez,
            así que la lista trae los libres más el que ya tiene esta.
        </p>
        <form method="post" action="<?= e(ruta('cuadrillas/actualizar')) ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $c['ID'] ?>">
            <label class="bo-campo"><span>Camión</span>
                <select name="id_camion">
                    <option value="">Sin camión</option>
                    <?php foreach ($camiones as $ca): ?>
                        <option value="<?= (int) $ca['ID'] ?>"<?= (int) $c['ID_camion'] === (int) $ca['ID'] ? ' selected' : '' ?>>
                            <?= e($ca['matricula'] . ' · ' . $ca['modelo'] . ' · ' . $ca['estado']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="bo-campo"><span>Estado operativo</span>
                <select name="estado">
                    <?php foreach (Cuadrilla::ESTADOS as $es): ?>
                        <option value="<?= e($es) ?>"<?= $c['operativo'] === $es ? ' selected' : '' ?>><?= e(ucfirst($es)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="bo-btn bo-btn--pri" style="margin-top:10px">Guardar cambios</button>
        </form>

        <h2 class="bo-panel__tit" style="margin-top:22px">Cuenta</h2>
        <div class="bo-dato"><span>Email</span><span class="bo-mono"><?= e($c['email']) ?></span></div>
        <div class="bo-dato">
            <span>Estado de cuenta</span>
            <span class="bo-badge bo-badge--<?= $c['cuenta'] === 'activo' ? 'ok' : 'baja' ?>"><?= e($c['cuenta']) ?></span>
        </div>
        <p class="bo-sub" style="font-size:13px;margin:10px 0 0">
            El nombre del equipo y la baja de la cuenta se administran en Usuarios: una cuadrilla
            es un usuario, no una entidad aparte.
        </p>
        <a class="bo-btn bo-btn--sm" style="margin-top:10px"
           href="<?= e(ruta('usuarios') . '?pestana=todos&q=' . urlencode((string) $c['email'])) ?>">Ver la cuenta en Usuarios</a>
    </div>

    <div class="bo-panel">
        <h2 class="bo-panel__tit">Ruta vigente</h2>
        <div class="bo-dato">
            <span>Recorrido</span>
            <span><?= $vigente ? '<span class="bo-badge bo-badge--rol">' . e($vigente['zona'] . ' · ' . $vigente['nombre']) . '</span>' : '<span class="bo-dim">sin ruta</span>' ?></span>
        </div>
        <?php if ($vigente): ?>
            <div class="bo-dato"><span>Desde</span><span><?= e($fh($vigente['fecha'])) ?></span></div>
            <div class="bo-dato">
                <span>Estado de la ruta</span>
                <span class="bo-badge bo-badge--<?= $vigente['estado'] === 'activa' ? 'ok' : 'baja' ?>"><?= e($vigente['estado']) ?></span>
            </div>
        <?php endif; ?>
        <p class="bo-sub" style="font-size:13px;margin:10px 0 0">
            La ruta se asigna desde la pantalla de la ruta, que es donde se define el recorrido.
            Cada asignación queda registrada con su fecha.
        </p>
        <?php if ($vigente): ?>
            <a class="bo-btn bo-btn--sm" style="margin-top:10px"
               href="<?= e(ruta('rutas/editar') . '?id=' . (int) $vigente['ID_ruta']) ?>">Ir a la ruta vigente</a>
        <?php else: ?>
            <a class="bo-btn bo-btn--sm" style="margin-top:10px" href="<?= e(ruta('rutas')) ?>">Elegir una ruta</a>
        <?php endif; ?>

        <h3 class="bo-panel__tit" style="font-size:15px;margin:20px 0 6px">Historial de rutas (<?= count($historial) ?>)</h3>
        <?php if (!$historial): ?>
            <p class="bo-dim">Sin rutas tomadas.</p>
        <?php else: ?>
            <ul class="bo-lista">
                <?php foreach ($historial as $n => $h): ?>
                    <li>
                        <?= e($fh($h['fecha'])) ?> · <?= e($h['zona'] . ' · ' . $h['nombre']) ?>
                        <?php if ($n === 0): ?><span class="bo-badge bo-badge--ok">vigente</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h3 class="bo-panel__tit" style="font-size:15px;margin:20px 0 6px">Incidencias asignadas (<?= count($intervenciones) ?>)</h3>
        <?php if (!$intervenciones): ?>
            <p class="bo-dim">Sin incidencias asignadas. Se asignan desde la pantalla de Incidencias.</p>
        <?php else: ?>
            <ul class="bo-lista">
                <?php foreach ($intervenciones as $i): ?>
                    <li>
                        <span class="bo-mono">#<?= (int) $i['ID_incidencia'] ?></span>
                        <?= e($i['tipo']) ?>
                        <span class="bo-badge bo-badge--<?= $i['tarea_finalizada'] === 'si' ? 'ok' : 'rol' ?>"><?= $i['tarea_finalizada'] === 'si' ? 'finalizada' : 'abierta' ?></span>
                        <span class="bo-dim">desde <?= e($fh($i['fecha_inicio'])) ?></span>
                        <a class="bo-btn bo-btn--sm" href="<?= e(ruta('incidencias/ver') . '?id=' . (int) $i['ID_incidencia']) ?>">Ver</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
