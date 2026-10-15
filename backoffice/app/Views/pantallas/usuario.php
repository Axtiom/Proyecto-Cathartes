<?php
/**
 * Editar cuenta. Espera: $admin, $activo, $u (Usuario::detalle), $esYo, $esJefe,
 * $instalaciones, $camiones (libres + el actual), $flash, $csrf.
 *
 * El rol no se cambia desde acá (una cuenta nace con un rol y lo conserva). Solo
 * aparecen los campos que corresponden a ese rol; igualmente el backend vuelve
 * a validar todo, así que ocultar un campo nunca es el control.
 */
$titulo  = 'Usuario #' . (int) $u['ID'] . ' · Backoffice';
$rol     = $u['rol'];
$soloLee = $rol === 'administrador' && !$esYo && !$esJefe;   // RN3: un admin común no edita a otro admin

require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <a class="bo-btn bo-btn--sm bo-btn--tenue" href="<?= e(ruta('usuarios')) ?>">← Volver a usuarios</a>
        <h1 style="margin-top:8px">Usuario #<?= (int) $u['ID'] ?> · <?= e($u['nombre']) ?></h1>
    </div>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div><?php endif; ?>
<?php if ($soloLee): ?>
    <div class="bo-aviso bo-aviso--error">Solo un administrador jefe puede modificar a otros administradores (RN3).</div>
<?php endif; ?>

<div class="bo-detalle">
    <div class="bo-panel">
        <h2 class="bo-panel__tit">Datos de la cuenta</h2>
        <div class="bo-dato"><span>Rol</span><span><span class="bo-badge bo-badge--rol"><?= e($rol) ?></span></span></div>
        <div class="bo-dato"><span>Estado</span><span><span class="bo-badge bo-badge--<?= $u['estado'] === 'activo' ? 'ok' : 'baja' ?>"><?= e($u['estado']) ?></span></span></div>

        <form method="post" action="<?= e(ruta('usuarios/actualizar')) ?>" style="margin-top:8px">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $u['ID'] ?>">
            <fieldset <?= $soloLee ? 'disabled' : '' ?> style="border:0;padding:0;margin:0">
                <label class="bo-campo"><span>Nombre</span><input type="text" name="nombre" maxlength="100" value="<?= e($u['nombre']) ?>" required></label>
                <label class="bo-campo"><span>Correo</span><input type="email" name="email" maxlength="150" value="<?= e($u['email']) ?>" required></label>
                <label class="bo-campo"><span>Teléfonos <em>(separados por coma)</em></span>
                    <input type="text" name="telefonos" value="<?= e(implode(', ', $u['telefonos'])) ?>" required>
                </label>
                <label class="bo-campo"><span>Contraseña nueva <em>(dejá vacío para no cambiarla)</em></span>
                    <input type="password" name="password" minlength="8" autocomplete="new-password">
                </label>

                <?php if ($rol === 'operario'): ?>
                    <label class="bo-campo"><span>Instalación asignada</span>
                        <select name="id_instalacion">
                            <?php foreach ($instalaciones as $i): ?>
                                <option value="<?= (int) $i['ID'] ?>"<?= (int) $u['id_instalacion'] === (int) $i['ID'] ? ' selected' : '' ?>><?= e($i['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php elseif ($rol === 'cuadrilla'): ?>
                    <label class="bo-campo"><span>Camión asignado</span>
                        <select name="id_camion">
                            <option value="0"<?= $u['id_camion'] === null ? ' selected' : '' ?>>— Sin camión —</option>
                            <?php foreach ($camiones as $c): ?>
                                <option value="<?= (int) $c['ID'] ?>"<?= (int) $u['id_camion'] === (int) $c['ID'] ? ' selected' : '' ?>><?= e($c['matricula'] . ' · ' . $c['modelo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="bo-campo"><span>Estado operativo</span>
                        <select name="estado_cuadrilla">
                            <?php foreach (['en servicio', 'fuera de servicio'] as $est): ?>
                                <option value="<?= e($est) ?>"<?= $u['estado_cuadrilla'] === $est ? ' selected' : '' ?>><?= e(ucfirst($est)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php elseif ($rol === 'administrador' && $esJefe): ?>
                    <label class="bo-campo"><span>Administrador jefe</span>
                        <select name="jefe">
                            <option value="no"<?= $u['jefe'] === 'no' ? ' selected' : '' ?>>No</option>
                            <option value="si"<?= $u['jefe'] === 'si' ? ' selected' : '' ?>>Sí</option>
                        </select>
                    </label>
                <?php endif; ?>

                <button type="submit" class="bo-btn bo-btn--pri" style="margin-top:10px">Guardar cambios</button>
            </fieldset>
        </form>
    </div>

    <div class="bo-panel">
        <h2 class="bo-panel__tit">Sobre esta pantalla</h2>
        <p class="bo-sub" style="font-size:13px">
            El rol de una cuenta no se cambia. Las cuentas no se borran: se dan de baja desde el
            listado (queda registrada la fecha y quién la hizo). Un administrador solo puede ser
            modificado por un administrador jefe o por él mismo, y el sistema nunca se queda sin
            administrador jefe activo.
        </p>
        <?php if ($esYo): ?><p class="bo-dim">Esta es tu propia cuenta.</p><?php endif; ?>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
