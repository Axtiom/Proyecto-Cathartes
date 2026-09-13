<?php
/**
 * Pantalla 4 — Contenedores (lista + alta). Server-rendered; dato con e().
 * Espera: $admin, $activo, $f, $datos, $rutas, $flash, $csrf.
 */
$titulo = 'Contenedores · Backoffice Cathartes';
$mkq = function (array $ov = []) use ($f) {
    $p = array_merge($f, $ov);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 0);
    return '?' . http_build_query($p);
};
$badge = fn($e) => $e === 'funcional' ? 'ok' : ($e === 'desbordado' ? 'rol' : 'baja');

require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <h1>Contenedores</h1>
        <p class="bo-sub">Alta, edición y baja de contenedores. La baja registra fecha y motivo.</p>
    </div>
    <details class="bo-alta" id="bo-alta">
        <summary class="bo-btn bo-btn--pri">＋ Nuevo contenedor</summary>
        <div class="bo-alta__modal">
            <form method="post" action="<?= e(ruta('contenedores/crear')) ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <h2 class="bo-alta__tit">Nuevo contenedor</h2>
                <label class="bo-campo"><span>Ubicación</span><input type="text" name="ubicacion" placeholder="Av. Rivera 3245, Buceo" required></label>
                <div class="bo-alta__fila">
                    <label class="bo-campo"><span>Tipo de residuo</span>
                        <select name="tipo"><option value="basura">Basura</option><option value="reciclable">Reciclable</option></select>
                    </label>
                    <label class="bo-campo"><span>Estado</span>
                        <select name="estado">
                            <option value="funcional">Funcional</option>
                            <option value="no funcional">No funcional</option>
                            <option value="desbordado">Desbordado</option>
                        </select>
                    </label>
                </div>
                <!-- La ruta se pide SOLO en el alta: `contenedor.ID_ruta` es NOT NULL,
                     un contenedor no puede nacer sin ruta. Después el recorrido se
                     administra desde la pantalla de la ruta. -->
                <label class="bo-campo"><span>Ruta inicial</span>
                    <select name="id_ruta" required>
                        <option value="">Elegí una ruta…</option>
                        <?php foreach ($rutas as $r): ?>
                            <option value="<?= (int) $r['ID'] ?>"><?= e($r['zona'] . ' · ' . $r['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <p class="bo-alta__nota">Después podés cambiar el recorrido desde <strong>Rutas</strong>: ahí se define por qué contenedores pasa cada una.</p>
                <div class="bo-alta__fila">
                    <label class="bo-campo"><span>Latitud <em>(opcional)</em></span><input type="text" name="latitud" placeholder="-34.9075"></label>
                    <label class="bo-campo"><span>Longitud <em>(opcional)</em></span><input type="text" name="longitud" placeholder="-56.1385"></label>
                </div>
                <div class="bo-alta__acc">
                    <button type="submit" class="bo-btn bo-btn--pri">Crear contenedor</button>
                    <button type="button" class="bo-btn" onclick="document.getElementById('bo-alta').removeAttribute('open')">Cancelar</button>
                </div>
            </form>
        </div>
    </details>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div><?php endif; ?>

<form class="bo-filtros" method="get" action="<?= e(ruta('contenedores')) ?>">
    <select name="tipo">
        <option value="">Todos los tipos</option>
        <option value="basura"<?= $f['tipo'] === 'basura' ? ' selected' : '' ?>>Basura</option>
        <option value="reciclable"<?= $f['tipo'] === 'reciclable' ? ' selected' : '' ?>>Reciclable</option>
    </select>
    <select name="estado">
        <option value="">Todos los estados</option>
        <?php foreach (['funcional', 'no funcional', 'desbordado', 'dado de baja'] as $es): ?>
            <option value="<?= e($es) ?>"<?= $f['estado'] === $es ? ' selected' : '' ?>><?= e(ucfirst($es)) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="ruta">
        <option value="">Todas las rutas</option>
        <?php foreach ($rutas as $r): ?>
            <option value="<?= (int) $r['ID'] ?>"<?= $f['ruta'] === (int) $r['ID'] ? ' selected' : '' ?>><?= e($r['zona'] . ' · ' . $r['nombre']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="bo-btn">Filtrar</button>
    <a class="bo-btn bo-btn--tenue" href="<?= e(ruta('contenedores')) ?>">Limpiar</a>
</form>

<?php if (!$datos['items']): ?>
    <div class="bo-vacio"><p class="bo-vacio__t">Sin contenedores</p><p class="bo-vacio__s">No hay contenedores con estos filtros.</p></div>
<?php else: ?>
    <div class="bo-tabla-wrap">
        <table class="bo-tabla">
            <thead><tr><th>ID</th><th>Ubicación</th><th>Tipo</th><th>Estado</th><th>Ruta</th><th class="bo-tabla__acc"></th></tr></thead>
            <tbody>
                <?php foreach ($datos['items'] as $c): ?>
                    <tr>
                        <td class="bo-mono">#<?= (int) $c['ID'] ?></td>
                        <td><?= e($c['ubicacion']) ?></td>
                        <td><?= e($c['tipo']) ?></td>
                        <td><span class="bo-badge bo-badge--<?= $badge($c['estado']) ?>"><?= e($c['estado']) ?></span></td>
                        <td><?= e($c['zona'] . ' · ' . $c['ruta']) ?></td>
                        <td class="bo-tabla__acc"><a class="bo-btn bo-btn--sm" href="<?= e(ruta('contenedores/editar') . '?id=' . (int) $c['ID']) ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="bo-pag">
        <span><?= (int) $datos['total'] ?> contenedores · página <?= (int) $datos['pagina'] ?> de <?= (int) $datos['paginas'] ?></span>
        <div class="bo-pag__btns">
            <?php if ($datos['pagina'] > 1): ?><a class="bo-btn bo-btn--sm" href="<?= e(ruta('contenedores') . $mkq(['pagina' => $datos['pagina'] - 1])) ?>">Anterior</a><?php else: ?><span class="bo-btn bo-btn--sm bo-btn--off">Anterior</span><?php endif; ?>
            <?php if ($datos['pagina'] < $datos['paginas']): ?><a class="bo-btn bo-btn--sm" href="<?= e(ruta('contenedores') . $mkq(['pagina' => $datos['pagina'] + 1])) ?>">Siguiente</a><?php else: ?><span class="bo-btn bo-btn--sm bo-btn--off">Siguiente</span><?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
