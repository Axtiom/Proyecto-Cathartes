<?php
/**
 * Pantalla 8 — Cuadrillas (lista). Server-rendered; dato con e().
 * Espera: $admin, $activo, $f, $datos, $resumen, $flash, $csrf.
 *
 * No hay botón de alta: una cuadrilla ES un usuario (el rol se deduce de la tabla
 * de subtipo), así que se crea desde Usuarios. Acá se administran el camión y el
 * estado operativo.
 *
 * Dos columnas de estado a propósito: «Cuenta» es `usuario.estado` y «Operativo»
 * es `cuadrilla.estado`. Son ejes distintos.
 */
$titulo = 'Cuadrillas · Backoffice Cathartes';
$mkq = function (array $ov = []) use ($f) {
    $p = array_merge($f, $ov);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 0);
    return '?' . http_build_query($p);
};

require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <h1>Cuadrillas</h1>
        <p class="bo-sub">Equipos de recolección: camión asignado, ruta vigente y estado operativo.</p>
    </div>
    <a class="bo-btn" href="<?= e(ruta('usuarios') . '?pestana=todos&rol=cuadrilla') ?>">Ver cuentas en Usuarios</a>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div><?php endif; ?>

<div class="bo-metricas">
    <div class="bo-metrica">
        <span class="bo-metrica__n"><?= (int) $resumen['total'] ?></span>
        <span class="bo-metrica__t">Cuadrillas</span>
    </div>
    <div class="bo-metrica">
        <span class="bo-metrica__n"><?= (int) $resumen['en_servicio'] ?></span>
        <span class="bo-metrica__t">En servicio</span>
    </div>
    <div class="bo-metrica<?= $resumen['sin_camion'] > 0 ? ' bo-metrica--alerta' : '' ?>">
        <span class="bo-metrica__n"><?= (int) $resumen['sin_camion'] ?></span>
        <span class="bo-metrica__t">Sin camión</span>
    </div>
    <div class="bo-metrica<?= $resumen['cuenta_inactiva'] > 0 ? ' bo-metrica--alerta' : '' ?>">
        <span class="bo-metrica__n"><?= (int) $resumen['cuenta_inactiva'] ?></span>
        <span class="bo-metrica__t">Con la cuenta inactiva</span>
    </div>
</div>

<form class="bo-filtros" method="get" action="<?= e(ruta('cuadrillas')) ?>">
    <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Buscar por nombre de equipo o email…">
    <select name="estado">
        <option value="">Todo estado operativo</option>
        <?php foreach (Cuadrilla::ESTADOS as $es): ?>
            <option value="<?= e($es) ?>"<?= $f['estado'] === $es ? ' selected' : '' ?>><?= e(ucfirst($es)) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="cuenta">
        <option value="">Toda cuenta</option>
        <option value="activo"<?= $f['cuenta'] === 'activo' ? ' selected' : '' ?>>Cuenta activa</option>
        <option value="no activo"<?= $f['cuenta'] === 'no activo' ? ' selected' : '' ?>>Cuenta no activa</option>
    </select>
    <select name="camion">
        <option value="">Con o sin camión</option>
        <option value="con"<?= $f['camion'] === 'con' ? ' selected' : '' ?>>Con camión</option>
        <option value="sin"<?= $f['camion'] === 'sin' ? ' selected' : '' ?>>Sin camión</option>
    </select>
    <button type="submit" class="bo-btn">Filtrar</button>
    <a class="bo-btn bo-btn--tenue" href="<?= e(ruta('cuadrillas')) ?>">Limpiar</a>
</form>

<?php if (!$datos['items']): ?>
    <div class="bo-vacio">
        <p class="bo-vacio__t">Sin cuadrillas</p>
        <p class="bo-vacio__s">No hay cuadrillas con estos filtros. Se dan de alta desde Usuarios, eligiendo el rol Cuadrilla.</p>
    </div>
<?php else: ?>
    <div class="bo-tabla-wrap">
        <table class="bo-tabla">
            <thead><tr><th>ID</th><th>Equipo</th><th>Camión</th><th>Ruta vigente</th><th>Tareas abiertas</th><th>Operativo</th><th>Cuenta</th><th class="bo-tabla__acc"></th></tr></thead>
            <tbody>
                <?php foreach ($datos['items'] as $c): ?>
                    <tr>
                        <td class="bo-mono">#<?= (int) $c['ID'] ?></td>
                        <td><?= e($c['nombre']) ?></td>
                        <td>
                            <?php if ($c['ID_camion'] === null): ?>
                                <span class="bo-dim">sin camión</span>
                            <?php else: ?>
                                <span class="bo-mono"><?= e($c['matricula']) ?></span>
                                <span class="bo-badge bo-badge--<?= $c['camion_estado'] === 'funcional' ? 'ok' : 'baja' ?>"><?= e($c['camion_estado']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= $c['ruta'] ? e($c['ruta']) : '<span class="bo-dim">sin ruta</span>' ?></td>
                        <td class="bo-mono"><?= (int) $c['abiertas'] ?></td>
                        <td><span class="bo-badge bo-badge--<?= $c['operativo'] === 'en servicio' ? 'ok' : 'baja' ?>"><?= e($c['operativo']) ?></span></td>
                        <td><span class="bo-badge bo-badge--<?= $c['cuenta'] === 'activo' ? 'ok' : 'baja' ?>"><?= e($c['cuenta']) ?></span></td>
                        <td class="bo-tabla__acc"><a class="bo-btn bo-btn--sm" href="<?= e(ruta('cuadrillas/editar') . '?id=' . (int) $c['ID']) ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="bo-pag">
        <span><?= (int) $datos['total'] ?> cuadrillas · página <?= (int) $datos['pagina'] ?> de <?= (int) $datos['paginas'] ?></span>
        <div class="bo-pag__btns">
            <?php if ($datos['pagina'] > 1): ?><a class="bo-btn bo-btn--sm" href="<?= e(ruta('cuadrillas') . $mkq(['pagina' => $datos['pagina'] - 1])) ?>">Anterior</a><?php else: ?><span class="bo-btn bo-btn--sm bo-btn--off">Anterior</span><?php endif; ?>
            <?php if ($datos['pagina'] < $datos['paginas']): ?><a class="bo-btn bo-btn--sm" href="<?= e(ruta('cuadrillas') . $mkq(['pagina' => $datos['pagina'] + 1])) ?>">Siguiente</a><?php else: ?><span class="bo-btn bo-btn--sm bo-btn--off">Siguiente</span><?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
