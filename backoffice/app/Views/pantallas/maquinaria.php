<?php
/**
 * Maquinaria (lista + alta). Espera: $admin, $activo, $f, $datos, $instalaciones, $flash, $csrf.
 */
$titulo = 'Maquinaria · Backoffice Cathartes';
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
        <h1>Maquinaria</h1>
        <p class="bo-sub">Máquinas de los vertederos y centros de acopio. Alta, edición y borrado.</p>
    </div>
    <details class="bo-alta" id="bo-alta">
        <summary class="bo-btn bo-btn--pri">＋ Nueva máquina</summary>
        <div class="bo-alta__modal">
            <form method="post" action="<?= e(ruta('maquinaria/crear')) ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <h2 class="bo-alta__tit">Nueva máquina</h2>
                <label class="bo-campo"><span>Modelo</span><input type="text" name="modelo" maxlength="100" placeholder="Caterpillar D6" required></label>
                <label class="bo-campo"><span>Tipo</span><input type="text" name="tipo" maxlength="50" placeholder="bulldozer, compactadora, excavadora…" required></label>
                <div class="bo-alta__fila">
                    <label class="bo-campo"><span>Instalación</span>
                        <select name="id_instalacion" required>
                            <?php foreach ($instalaciones as $i): ?>
                                <option value="<?= (int) $i['ID'] ?>"><?= e($i['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="bo-campo"><span>Estado</span>
                        <select name="estado">
                            <?php foreach (Maquinaria::ESTADOS as $es): ?>
                                <option value="<?= e($es) ?>"><?= e(ucfirst($es)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="bo-alta__acc">
                    <button type="submit" class="bo-btn bo-btn--pri">Crear máquina</button>
                    <button type="button" class="bo-btn" onclick="document.getElementById('bo-alta').removeAttribute('open')">Cancelar</button>
                </div>
            </form>
        </div>
    </details>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div><?php endif; ?>

<form class="bo-filtros" method="get" action="<?= e(ruta('maquinaria')) ?>">
    <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Buscar por modelo o tipo…">
    <select name="instalacion">
        <option value="">Todas las instalaciones</option>
        <?php foreach ($instalaciones as $i): ?>
            <option value="<?= (int) $i['ID'] ?>"<?= (int) $f['instalacion'] === (int) $i['ID'] ? ' selected' : '' ?>><?= e($i['nombre']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="estado">
        <option value="">Todos los estados</option>
        <?php foreach (Maquinaria::ESTADOS as $es): ?>
            <option value="<?= e($es) ?>"<?= $f['estado'] === $es ? ' selected' : '' ?>><?= e(ucfirst($es)) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="bo-btn">Filtrar</button>
    <a class="bo-btn bo-btn--tenue" href="<?= e(ruta('maquinaria')) ?>">Limpiar</a>
</form>

<?php if (!$datos['items']): ?>
    <div class="bo-vacio"><p class="bo-vacio__t">Sin maquinaria</p><p class="bo-vacio__s">No hay máquinas con estos filtros.</p></div>
<?php else: ?>
    <div class="bo-tabla-wrap">
        <table class="bo-tabla">
            <thead><tr><th>ID</th><th>Modelo</th><th>Tipo</th><th>Instalación</th><th>Estado</th><th class="bo-tabla__acc"></th></tr></thead>
            <tbody>
                <?php foreach ($datos['items'] as $m): ?>
                    <tr>
                        <td class="bo-mono">#<?= (int) $m['ID'] ?></td>
                        <td><?= e($m['modelo']) ?></td>
                        <td><?= e($m['tipo']) ?></td>
                        <td><?= e($m['instalacion']) ?></td>
                        <td><span class="bo-badge bo-badge--<?= $badge($m['estado']) ?>"><?= e($m['estado']) ?></span></td>
                        <td class="bo-tabla__acc"><a class="bo-btn bo-btn--sm" href="<?= e(ruta('maquinaria/editar') . '?id=' . (int) $m['ID']) ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="bo-pag">
        <span><?= (int) $datos['total'] ?> máquinas · página <?= (int) $datos['pagina'] ?> de <?= (int) $datos['paginas'] ?></span>
        <div class="bo-pag__btns">
            <?php if ($datos['pagina'] > 1): ?><a class="bo-btn bo-btn--sm" href="<?= e(ruta('maquinaria') . $mkq(['pagina' => $datos['pagina'] - 1])) ?>">Anterior</a><?php else: ?><span class="bo-btn bo-btn--sm bo-btn--off">Anterior</span><?php endif; ?>
            <?php if ($datos['pagina'] < $datos['paginas']): ?><a class="bo-btn bo-btn--sm" href="<?= e(ruta('maquinaria') . $mkq(['pagina' => $datos['pagina'] + 1])) ?>">Siguiente</a><?php else: ?><span class="bo-btn bo-btn--sm bo-btn--off">Siguiente</span><?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
