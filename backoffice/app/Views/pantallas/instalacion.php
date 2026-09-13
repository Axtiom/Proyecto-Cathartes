<?php
/**
 * Pantalla 6 — Editar instalación. Espera: $admin, $activo, $i, $dep, $flash, $csrf.
 *
 * No hay panel de baja a propósito: `instalacion` no tiene columna de estado ni
 * `fecha_baja`, y el MER está congelado. El panel de la derecha muestra lo que
 * depende de la instalación, que es lo que las FK (ON DELETE RESTRICT) protegen.
 */
$titulo = 'Instalación #' . (int) $i['ID'] . ' · Backoffice';
$pc     = Instalacion::porcentaje($i);
$nivel  = $pc >= Instalacion::UMBRAL ? 'alta' : ($pc >= 60 ? 'media' : 'baja');

require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <a class="bo-btn bo-btn--sm bo-btn--tenue" href="<?= e(ruta('instalaciones')) ?>">← Volver a instalaciones</a>
        <h1 style="margin-top:8px">Instalación #<?= (int) $i['ID'] ?> · <?= e($i['nombre']) ?></h1>
    </div>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div><?php endif; ?>

<?php if ($pc >= Instalacion::UMBRAL): ?>
    <div class="bo-aviso bo-aviso--error">
        Ocupación al <?= $pc ?>%: alcanzó el umbral de alerta del <?= Instalacion::UMBRAL ?>%. Conviene planificar una descarga.
    </div>
<?php endif; ?>

<div class="bo-detalle">
    <div class="bo-panel">
        <h2 class="bo-panel__tit">Datos</h2>
        <div class="bo-dato">
            <span>Ocupación</span>
            <span class="bo-ocupacion">
                <span class="bo-ocupacion__n"><?= (int) $i['ocupacion_actual'] ?> / <?= (int) $i['capacidad'] ?> m³ · <?= $pc ?>%</span>
                <span class="bo-barra bo-barra--<?= $nivel ?>"><span class="bo-barra__llena" style="width:<?= min(100, $pc) ?>%"></span></span>
            </span>
        </div>
        <form method="post" action="<?= e(ruta('instalaciones/actualizar')) ?>" style="margin-top:8px">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $i['ID'] ?>">
            <label class="bo-campo"><span>Nombre</span><input type="text" name="nombre" value="<?= e($i['nombre']) ?>" required></label>
            <label class="bo-campo"><span>Ubicación</span><input type="text" name="ubicacion" value="<?= e($i['ubicacion']) ?>" required></label>
            <label class="bo-campo"><span>Tipo</span>
                <select name="tipo">
                    <?php foreach (Instalacion::TIPOS as $t): ?>
                        <option value="<?= e($t) ?>"<?= $i['tipo'] === $t ? ' selected' : '' ?>><?= e(ucfirst($t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="bo-alta__fila">
                <label class="bo-campo"><span>Capacidad (m³)</span><input type="number" name="capacidad" min="1" value="<?= (int) $i['capacidad'] ?>" required></label>
                <label class="bo-campo"><span>Ocupación actual (m³)</span><input type="number" name="ocupacion_actual" min="0" value="<?= (int) $i['ocupacion_actual'] ?>" required></label>
            </div>
            <div class="bo-alta__fila">
                <label class="bo-campo"><span>Latitud <em>(opcional)</em></span><input type="text" name="latitud" value="<?= $i['latitud'] === null ? '' : e((string) $i['latitud']) ?>" placeholder="-34.9075"></label>
                <label class="bo-campo"><span>Longitud <em>(opcional)</em></span><input type="text" name="longitud" value="<?= $i['longitud'] === null ? '' : e((string) $i['longitud']) ?>" placeholder="-56.1385"></label>
            </div>
            <button type="submit" class="bo-btn bo-btn--pri" style="margin-top:10px">Guardar cambios</button>
        </form>
    </div>

    <div class="bo-panel">
        <h2 class="bo-panel__tit">Depende de esta instalación</h2>
        <p class="bo-sub" style="font-size:13px;margin-bottom:12px">
            La instalación no se elimina: rutas, operarios y maquinaria la referencian y las
            claves foráneas lo impiden. Para retirarla de operación, reasigná lo que cuelga
            de ella y dejala sin rutas activas.
        </p>

        <div class="bo-dato"><span>Maquinaria</span><span><?= (int) $dep['maquinaria'] ?></span></div>

        <h3 class="bo-panel__tit" style="font-size:15px;margin:16px 0 6px">Rutas de destino (<?= count($dep['rutas']) ?>)</h3>
        <?php if (!$dep['rutas']): ?>
            <p class="bo-dim">Ninguna ruta descarga acá.</p>
        <?php else: ?>
            <ul class="bo-lista">
                <?php foreach ($dep['rutas'] as $r): ?>
                    <li>
                        <span class="bo-mono">#<?= (int) $r['ID'] ?></span>
                        <?= e($r['zona'] . ' · ' . $r['nombre']) ?>
                        <span class="bo-badge bo-badge--<?= $r['estado'] === 'activa' ? 'ok' : 'baja' ?>"><?= e($r['estado']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h3 class="bo-panel__tit" style="font-size:15px;margin:16px 0 6px">Operarios asignados (<?= count($dep['operarios']) ?>)</h3>
        <?php if (!$dep['operarios']): ?>
            <p class="bo-dim">Sin operarios asignados.</p>
        <?php else: ?>
            <ul class="bo-lista">
                <?php foreach ($dep['operarios'] as $o): ?>
                    <li>
                        <span class="bo-mono">#<?= (int) $o['ID'] ?></span>
                        <?= e($o['nombre']) ?>
                        <span class="bo-badge bo-badge--<?= $o['estado'] === 'activo' ? 'ok' : 'baja' ?>"><?= e($o['estado']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
