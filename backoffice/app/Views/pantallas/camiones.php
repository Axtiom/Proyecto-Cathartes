<?php
/**
 * Pantalla 5 — Camiones (lista + alta). Espera: $admin, $activo, $f, $datos,
 * $flash, $csrf.
 */
$titulo = 'Camiones · Backoffice Cathartes';
$mkq = function (array $ov = []) use ($f) {
    $p = array_merge($f, $ov);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 0);
    return '?' . http_build_query($p);
};
$badge = fn($e) => $e === 'funcional' ? 'ok' : ($e === 'en mantenimiento' ? 'rol' : 'baja');

require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <h1>Camiones</h1>
        <p class="bo-sub">Flota de recolección. Alta, edición y baja con fecha y motivo.</p>
    </div>
    <details class="bo-alta" id="bo-alta">
        <summary class="bo-btn bo-btn--pri">＋ Nuevo camión</summary>
        <div class="bo-alta__modal">
            <form method="post" action="<?= e(ruta('camiones/crear')) ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <h2 class="bo-alta__tit">Nuevo camión</h2>
                <div class="bo-alta__fila">
                    <label class="bo-campo"><span>Matrícula</span><input type="text" name="matricula" placeholder="ABC 1234" required></label>
                    <label class="bo-campo"><span>Capacidad (kg)</span><input type="number" name="capacidad" min="1" required></label>
                </div>
                <label class="bo-campo"><span>Modelo</span><input type="text" name="modelo" placeholder="Iveco Tector 2020" required></label>
                <label class="bo-campo"><span>Estado</span>
                    <select name="estado">
                        <option value="funcional">Funcional</option>
                        <option value="en mantenimiento">En mantenimiento</option>
                        <option value="no funcional">No funcional</option>
                    </select>
                </label>
                <div class="bo-alta__acc">
                    <button type="submit" class="bo-btn bo-btn--pri">Crear camión</button>
                    <button type="button" class="bo-btn" onclick="document.getElementById('bo-alta').removeAttribute('open')">Cancelar</button>
                </div>
            </form>
        </div>
    </details>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div><?php endif; ?>

<form class="bo-filtros" method="get" action="<?= e(ruta('camiones')) ?>">
    <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Buscar por matrícula o modelo…">
    <select name="estado">
        <option value="">Todos los estados</option>
        <?php foreach (['funcional', 'en mantenimiento', 'no funcional', 'dado de baja'] as $es): ?>
            <option value="<?= e($es) ?>"<?= $f['estado'] === $es ? ' selected' : '' ?>><?= e(ucfirst($es)) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="bo-btn">Filtrar</button>
    <a class="bo-btn bo-btn--tenue" href="<?= e(ruta('camiones')) ?>">Limpiar</a>
</form>

<?php if (!$datos['items']): ?>
    <div class="bo-vacio"><p class="bo-vacio__t">Sin camiones</p><p class="bo-vacio__s">No hay camiones con estos filtros.</p></div>
<?php else: ?>
    <div class="bo-tabla-wrap">
        <table class="bo-tabla">
            <thead><tr><th>ID</th><th>Matrícula</th><th>Modelo</th><th>Capacidad</th><th>Estado</th><th>Cuadrilla</th><th class="bo-tabla__acc"></th></tr></thead>
            <tbody>
                <?php foreach ($datos['items'] as $c): ?>
                    <tr>
                        <td class="bo-mono">#<?= (int) $c['ID'] ?></td>
                        <td class="bo-mono"><?= e($c['matricula']) ?></td>
                        <td><?= e($c['modelo']) ?></td>
                        <td><?= (int) $c['capacidad'] ?> kg</td>
                        <td><span class="bo-badge bo-badge--<?= $badge($c['estado']) ?>"><?= e($c['estado']) ?></span></td>
                        <td><?= $c['cuadrilla'] ? '<span class="bo-badge bo-badge--rol">' . e($c['cuadrilla']) . '</span>' : '<span class="bo-dim">libre</span>' ?></td>
                        <td class="bo-tabla__acc"><a class="bo-btn bo-btn--sm" href="<?= e(ruta('camiones/editar') . '?id=' . (int) $c['ID']) ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="bo-pag">
        <span><?= (int) $datos['total'] ?> camiones · página <?= (int) $datos['pagina'] ?> de <?= (int) $datos['paginas'] ?></span>
        <div class="bo-pag__btns">
            <?php if ($datos['pagina'] > 1): ?><a class="bo-btn bo-btn--sm" href="<?= e(ruta('camiones') . $mkq(['pagina' => $datos['pagina'] - 1])) ?>">Anterior</a><?php else: ?><span class="bo-btn bo-btn--sm bo-btn--off">Anterior</span><?php endif; ?>
            <?php if ($datos['pagina'] < $datos['paginas']): ?><a class="bo-btn bo-btn--sm" href="<?= e(ruta('camiones') . $mkq(['pagina' => $datos['pagina'] + 1])) ?>">Siguiente</a><?php else: ?><span class="bo-btn bo-btn--sm bo-btn--off">Siguiente</span><?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
