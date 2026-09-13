<?php
/**
 * Pantalla 7 — Rutas (lista + alta). Server-rendered; dato con e().
 * Espera: $admin, $activo, $f, $datos, $zonas, $instalaciones, $flash, $csrf.
 *
 * La columna «Cuadrilla» sale de la toma más reciente de `toma` (ver el Model).
 * No hay acción de baja: la ruta se pasa a 'inactiva' desde su formulario.
 */
$titulo = 'Rutas · Backoffice Cathartes';
$mkq = function (array $ov = []) use ($f) {
    $p = array_merge($f, $ov);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 0);
    return '?' . http_build_query($p);
};

require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <h1>Rutas</h1>
        <p class="bo-sub">Recorridos de recolección: zona, frecuencia, instalación de destino y cuadrilla a cargo.</p>
    </div>
    <details class="bo-alta" id="bo-alta">
        <summary class="bo-btn bo-btn--pri">＋ Nueva ruta</summary>
        <div class="bo-alta__modal">
            <form method="post" action="<?= e(ruta('rutas/crear')) ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <h2 class="bo-alta__tit">Nueva ruta</h2>
                <label class="bo-campo"><span>Nombre</span><input type="text" name="nombre" placeholder="Buceo — Pocitos" required></label>
                <div class="bo-alta__fila">
                    <label class="bo-campo"><span>Zona</span><input type="text" name="zona" list="bo-zonas" placeholder="Buceo" required></label>
                    <label class="bo-campo"><span>Estado</span>
                        <select name="estado">
                            <option value="activa">Activa</option>
                            <option value="inactiva">Inactiva</option>
                        </select>
                    </label>
                </div>
                <label class="bo-campo"><span>Frecuencia</span><input type="text" name="frecuencia" placeholder="diaria · lunes, miércoles y viernes" required></label>
                <label class="bo-campo"><span>Instalación de destino</span>
                    <select name="id_instalacion" required>
                        <option value="">Elegí una instalación…</option>
                        <?php foreach ($instalaciones as $i): ?>
                            <option value="<?= (int) $i['ID'] ?>"><?= e($i['nombre'] . ' · ' . $i['tipo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <p class="bo-alta__nota">La cuadrilla se asigna después, desde el detalle de la ruta.</p>
                <div class="bo-alta__acc">
                    <button type="submit" class="bo-btn bo-btn--pri">Crear ruta</button>
                    <button type="button" class="bo-btn" onclick="document.getElementById('bo-alta').removeAttribute('open')">Cancelar</button>
                </div>
            </form>
        </div>
    </details>
</div>

<!-- Zonas ya usadas, para autocompletar el campo de texto libre del alta y del filtro. -->
<datalist id="bo-zonas">
    <?php foreach ($zonas as $z): ?><option value="<?= e($z) ?>"></option><?php endforeach; ?>
</datalist>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div><?php endif; ?>

<form class="bo-filtros" method="get" action="<?= e(ruta('rutas')) ?>">
    <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Buscar por nombre o frecuencia…">
    <select name="zona">
        <option value="">Todas las zonas</option>
        <?php foreach ($zonas as $z): ?>
            <option value="<?= e($z) ?>"<?= $f['zona'] === $z ? ' selected' : '' ?>><?= e($z) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="estado">
        <option value="">Todos los estados</option>
        <?php foreach (Ruta::ESTADOS as $es): ?>
            <option value="<?= e($es) ?>"<?= $f['estado'] === $es ? ' selected' : '' ?>><?= e(ucfirst($es)) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="instalacion">
        <option value="">Todos los destinos</option>
        <?php foreach ($instalaciones as $i): ?>
            <option value="<?= (int) $i['ID'] ?>"<?= $f['instalacion'] === (int) $i['ID'] ? ' selected' : '' ?>><?= e($i['nombre']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="bo-btn">Filtrar</button>
    <a class="bo-btn bo-btn--tenue" href="<?= e(ruta('rutas')) ?>">Limpiar</a>
</form>

<?php if (!$datos['items']): ?>
    <div class="bo-vacio"><p class="bo-vacio__t">Sin rutas</p><p class="bo-vacio__s">No hay rutas con estos filtros.</p></div>
<?php else: ?>
    <div class="bo-tabla-wrap">
        <table class="bo-tabla">
            <thead><tr><th>ID</th><th>Nombre</th><th>Zona</th><th>Frecuencia</th><th>Destino</th><th>Contenedores</th><th>Cuadrilla</th><th>Estado</th><th class="bo-tabla__acc"></th></tr></thead>
            <tbody>
                <?php foreach ($datos['items'] as $r): ?>
                    <tr>
                        <td class="bo-mono">#<?= (int) $r['ID'] ?></td>
                        <td><?= e($r['nombre']) ?></td>
                        <td><?= e($r['zona']) ?></td>
                        <td><?= e($r['frecuencia']) ?></td>
                        <td><?= e($r['instalacion']) ?></td>
                        <td>
                            <span class="bo-mono"><?= (int) $r['contenedores'] ?></span>
                            <?php if ((int) $r['desbordados'] > 0): ?>
                                <span class="bo-badge bo-badge--rol"><?= (int) $r['desbordados'] ?> desbordado<?= (int) $r['desbordados'] === 1 ? '' : 's' ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= $r['cuadrilla'] ? '<span class="bo-badge bo-badge--rol">' . e($r['cuadrilla']) . '</span>' : '<span class="bo-dim">sin asignar</span>' ?></td>
                        <td><span class="bo-badge bo-badge--<?= $r['estado'] === 'activa' ? 'ok' : 'baja' ?>"><?= e($r['estado']) ?></span></td>
                        <td class="bo-tabla__acc"><a class="bo-btn bo-btn--sm" href="<?= e(ruta('rutas/editar') . '?id=' . (int) $r['ID']) ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="bo-pag">
        <span><?= (int) $datos['total'] ?> rutas · página <?= (int) $datos['pagina'] ?> de <?= (int) $datos['paginas'] ?></span>
        <div class="bo-pag__btns">
            <?php if ($datos['pagina'] > 1): ?><a class="bo-btn bo-btn--sm" href="<?= e(ruta('rutas') . $mkq(['pagina' => $datos['pagina'] - 1])) ?>">Anterior</a><?php else: ?><span class="bo-btn bo-btn--sm bo-btn--off">Anterior</span><?php endif; ?>
            <?php if ($datos['pagina'] < $datos['paginas']): ?><a class="bo-btn bo-btn--sm" href="<?= e(ruta('rutas') . $mkq(['pagina' => $datos['pagina'] + 1])) ?>">Siguiente</a><?php else: ?><span class="bo-btn bo-btn--sm bo-btn--off">Siguiente</span><?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
