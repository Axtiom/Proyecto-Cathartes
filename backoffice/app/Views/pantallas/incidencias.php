<?php
/**
 * Pantalla 2 — Incidencias (listado). Server-rendered: estructura y texto fijo
 * acá; dato de la base con e(). Filtros por estado, tipo, zona y período.
 * Espera: $admin, $activo, $f (filtros), $datos, $zonas, $flash, $csrf.
 */
$titulo = 'Incidencias · Backoffice Cathartes';

$mkq = function (array $ov = []) use ($f) {
    $p = array_merge($f, $ov);
    unset($p['pagina']); if (isset($ov['pagina'])) $p['pagina'] = $ov['pagina'];
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 0);
    return '?' . http_build_query($p);
};
$tipos = ['desbordado' => 'Desbordado', 'roto' => 'Roto', 'falta de recoleccion' => 'Falta de recolección'];
$estados = ['en curso' => 'En curso', 'finalizada' => 'Finalizada'];
$fechaCorta = fn($iso) => ($m = preg_match('/(\d{4})-(\d{2})-(\d{2})/', (string) $iso, $x)) ? "$x[3]/$x[2]/$x[1]" : '';

require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <h1>Incidencias</h1>
        <p class="bo-sub">Validá reportes y asigná cuadrillas. Tocá «Ver» para el detalle con la foto.</p>
    </div>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div><?php endif; ?>

<!-- Filtros (GET) -->
<form class="bo-filtros" method="get" action="<?= e(ruta('incidencias')) ?>">
    <select name="estado">
        <option value="">Todos los estados</option>
        <?php foreach ($estados as $k => $v): ?><option value="<?= e($k) ?>"<?= $f['estado'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select>
    <select name="validada">
        <option value="">Validadas y sin validar</option>
        <option value="no"<?= $f['validada'] === 'no' ? ' selected' : '' ?>>Sin validar</option>
        <option value="si"<?= $f['validada'] === 'si' ? ' selected' : '' ?>>Ya validadas</option>
    </select>
    <select name="tipo">
        <option value="">Todos los tipos</option>
        <?php foreach ($tipos as $k => $v): ?><option value="<?= e($k) ?>"<?= $f['tipo'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select>
    <select name="zona">
        <option value="">Todas las zonas</option>
        <?php foreach ($zonas as $z): ?><option value="<?= e($z) ?>"<?= $f['zona'] === $z ? ' selected' : '' ?>><?= e($z) ?></option><?php endforeach; ?>
    </select>
    <label class="bo-campo bo-campo--inline"><span>Desde</span><input type="date" name="desde" value="<?= e($f['desde']) ?>"></label>
    <label class="bo-campo bo-campo--inline"><span>Hasta</span><input type="date" name="hasta" value="<?= e($f['hasta']) ?>"></label>
    <button type="submit" class="bo-btn">Filtrar</button>
    <a class="bo-btn bo-btn--tenue" href="<?= e(ruta('incidencias')) ?>">Limpiar</a>
</form>

<?php if (!$datos['items']): ?>
    <div class="bo-vacio">
        <p class="bo-vacio__t">Sin incidencias</p>
        <p class="bo-vacio__s">No hay incidencias que coincidan con estos filtros.</p>
    </div>
<?php else: ?>
    <div class="bo-tabla-wrap">
        <table class="bo-tabla">
            <thead>
                <tr>
                    <th>Nº</th><th>Fecha</th><th>Tipo</th><th>Ubicación</th><th>Zona</th>
                    <th>Vecino</th><th>Validada</th><th>Estado</th><th>Cuadrilla</th><th class="bo-tabla__acc"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($datos['items'] as $i): ?>
                    <tr>
                        <td class="bo-mono">#<?= (int) $i['id'] ?></td>
                        <td><?= e($fechaCorta($i['fecha_ing'])) ?></td>
                        <td><?= e($i['tipo']) ?></td>
                        <td><?= e($i['ubicacion']) ?></td>
                        <td><?= e($i['zona']) ?></td>
                        <td><?= e($i['vecino']) ?></td>
                        <td><span class="bo-badge bo-badge--<?= $i['validada'] === 'si' ? 'ok' : 'baja' ?>"><?= $i['validada'] === 'si' ? 'Sí' : 'No' ?></span></td>
                        <td><span class="bo-badge bo-badge--<?= $i['estado'] === 'finalizada' ? 'ok' : 'rol' ?>"><?= e($i['estado']) ?></span></td>
                        <td><?= $i['asignada'] ? '<span class="bo-badge bo-badge--rol">' . e($i['cuadrilla']) . '</span>' : '<span class="bo-dim">sin asignar</span>' ?></td>
                        <td class="bo-tabla__acc"><a class="bo-btn bo-btn--sm" href="<?= e(ruta('incidencias/ver') . '?id=' . (int) $i['id']) ?>">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="bo-pag">
        <span><?= (int) $datos['total'] ?> incidencias · página <?= (int) $datos['pagina'] ?> de <?= (int) $datos['paginas'] ?></span>
        <div class="bo-pag__btns">
            <?php if ($datos['pagina'] > 1): ?>
                <a class="bo-btn bo-btn--sm" href="<?= e(ruta('incidencias') . $mkq(['pagina' => $datos['pagina'] - 1])) ?>">Anterior</a>
            <?php else: ?><span class="bo-btn bo-btn--sm bo-btn--off">Anterior</span><?php endif; ?>
            <?php if ($datos['pagina'] < $datos['paginas']): ?>
                <a class="bo-btn bo-btn--sm" href="<?= e(ruta('incidencias') . $mkq(['pagina' => $datos['pagina'] + 1])) ?>">Siguiente</a>
            <?php else: ?><span class="bo-btn bo-btn--sm bo-btn--off">Siguiente</span><?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
