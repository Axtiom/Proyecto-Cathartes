<?php
/**
 * Pantalla 9 — Recompensas (catálogo + métricas). Server-rendered; dato con e().
 * Espera: $admin, $activo, $f, $datos, $resumen, $recientes, $flash, $csrf.
 *
 * «Recompensa activa» = existe en el catálogo: no hay flag de activación. Sacarla
 * del catálogo solo se puede si nunca se canjeó (ver el Model).
 */
$titulo = 'Recompensas · Backoffice Cathartes';
$mkq = function (array $ov = []) use ($f) {
    $p = array_merge($f, $ov);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 0);
    return '?' . http_build_query($p);
};
$num = fn($n) => number_format((int) $n, 0, ',', '.');
$fh  = fn($iso) => ($m = preg_match('/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', (string) $iso, $x)) ? "$x[3]/$x[2]/$x[1] $x[4]:$x[5]" : '—';

require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <h1>Recompensas</h1>
        <p class="bo-sub">Catálogo de gamificación. El costo en puntos lo define el administrador; los 150 puntos por incidencia validada son fijos.</p>
    </div>
    <details class="bo-alta" id="bo-alta">
        <summary class="bo-btn bo-btn--pri">＋ Nueva recompensa</summary>
        <div class="bo-alta__modal">
            <form method="post" action="<?= e(ruta('recompensas/crear')) ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <h2 class="bo-alta__tit">Nueva recompensa</h2>
                <label class="bo-campo"><span>Nombre</span><input type="text" name="nombre" maxlength="100" placeholder="Marco «Reciclador»" required></label>
                <label class="bo-campo"><span>Descripción <em>(opcional)</em></span><input type="text" name="descripcion" maxlength="255" placeholder="Marco de perfil para vecinos recicladores."></label>
                <label class="bo-campo"><span>Costo en puntos</span><input type="number" name="valor" min="1" placeholder="300" required></label>
                <p class="bo-alta__nota">Crear una recompensa la deja disponible: estar en el catálogo <strong>es</strong> estar activa.</p>
                <div class="bo-alta__acc">
                    <button type="submit" class="bo-btn bo-btn--pri">Crear recompensa</button>
                    <button type="button" class="bo-btn" onclick="document.getElementById('bo-alta').removeAttribute('open')">Cancelar</button>
                </div>
            </form>
        </div>
    </details>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div><?php endif; ?>

<div class="bo-metricas">
    <div class="bo-metrica">
        <span class="bo-metrica__n"><?= (int) $resumen['catalogo'] ?></span>
        <span class="bo-metrica__t">En el catálogo</span>
    </div>
    <div class="bo-metrica">
        <span class="bo-metrica__n"><?= (int) $resumen['canjes_mes'] ?></span>
        <span class="bo-metrica__t">Canjes del mes</span>
    </div>
    <div class="bo-metrica">
        <span class="bo-metrica__n"><?= e($num($resumen['puntos_mes'])) ?></span>
        <span class="bo-metrica__t">Puntos canjeados este mes</span>
    </div>
    <div class="bo-metrica">
        <span class="bo-metrica__n"><?= e($num($resumen['otorgados'])) ?></span>
        <span class="bo-metrica__t">Puntos otorgados (acumulado)</span>
    </div>
</div>

<!-- Balance de puntos: no hay tabla de movimientos, así que los otorgados se
     recalculan de la regla (150 por incidencia validada) y se contrastan con el
     saldo real de los vecinos. Si no cuadra, se vio acá. -->
<div class="bo-panel" style="margin-bottom:20px">
    <h2 class="bo-panel__tit">Balance de puntos</h2>
    <div class="bo-dato">
        <span>Otorgados</span>
        <span><?= e($num($resumen['otorgados'])) ?> <span class="bo-dim">= <?= (int) $resumen['validadas'] ?> incidencias validadas × <?= Recompensa::PUNTOS_POR_INCIDENCIA ?></span></span>
    </div>
    <div class="bo-dato">
        <span>Canjeados</span>
        <span><?= e($num($resumen['canjeados'])) ?> <span class="bo-dim">en <?= (int) $resumen['canjes_total'] ?> canje<?= (int) $resumen['canjes_total'] === 1 ? '' : 's' ?></span></span>
    </div>
    <div class="bo-dato">
        <span>Saldo en circulación</span>
        <span>
            <?= e($num($resumen['saldo'])) ?>
            <span class="bo-badge bo-badge--<?= $resumen['cuadra'] ? 'ok' : 'baja' ?>"><?= $resumen['cuadra'] ? 'cuadra' : 'no cuadra' ?></span>
        </span>
    </div>
    <?php if (!$resumen['cuadra']): ?>
        <div class="bo-aviso bo-aviso--error" style="margin:12px 0 0">
            Otorgados menos canjeados da <?= e($num($resumen['esperado'])) ?>, pero la suma de saldos es
            <?= e($num($resumen['saldo'])) ?>. La diferencia indica puntos tocados por fuera del trigger
            (por ejemplo, un <code>UPDATE</code> manual sobre <code>vecino.saldo</code>).
        </div>
    <?php endif; ?>
</div>

<form class="bo-filtros" method="get" action="<?= e(ruta('recompensas')) ?>">
    <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Buscar por nombre o descripción…">
    <select name="movimiento">
        <option value="">Todas</option>
        <option value="canjeadas"<?= $f['movimiento'] === 'canjeadas' ? ' selected' : '' ?>>Con canjes</option>
        <option value="sin canjes"<?= $f['movimiento'] === 'sin canjes' ? ' selected' : '' ?>>Sin canjes</option>
    </select>
    <select name="orden">
        <option value="">Ordenar por nombre</option>
        <option value="valor"<?= $f['orden'] === 'valor' ? ' selected' : '' ?>>Ordenar por costo</option>
    </select>
    <button type="submit" class="bo-btn">Filtrar</button>
    <a class="bo-btn bo-btn--tenue" href="<?= e(ruta('recompensas')) ?>">Limpiar</a>
</form>

<?php if (!$datos['items']): ?>
    <div class="bo-vacio">
        <p class="bo-vacio__t">Sin recompensas</p>
        <p class="bo-vacio__s">No hay recompensas con estos filtros. Un catálogo vacío deja a los vecinos sin nada que canjear.</p>
    </div>
<?php else: ?>
    <div class="bo-tabla-wrap">
        <table class="bo-tabla">
            <thead><tr><th>ID</th><th>Nombre</th><th>Descripción</th><th>Costo</th><th>Canjes</th><th>Puntos canjeados</th><th class="bo-tabla__acc"></th></tr></thead>
            <tbody>
                <?php foreach ($datos['items'] as $r): ?>
                    <tr>
                        <td class="bo-mono">#<?= (int) $r['ID'] ?></td>
                        <td><?= e($r['nombre']) ?></td>
                        <td><?= $r['descripcion'] !== null ? e($r['descripcion']) : '<span class="bo-dim">—</span>' ?></td>
                        <td class="bo-mono"><?= e($num($r['valor'])) ?> pts</td>
                        <td class="bo-mono"><?= (int) $r['canjes'] ?></td>
                        <td class="bo-mono"><?= e($num($r['puntos'])) ?></td>
                        <td class="bo-tabla__acc"><a class="bo-btn bo-btn--sm" href="<?= e(ruta('recompensas/editar') . '?id=' . (int) $r['ID']) ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="bo-pag">
        <span><?= (int) $datos['total'] ?> recompensas · página <?= (int) $datos['pagina'] ?> de <?= (int) $datos['paginas'] ?></span>
        <div class="bo-pag__btns">
            <?php if ($datos['pagina'] > 1): ?><a class="bo-btn bo-btn--sm" href="<?= e(ruta('recompensas') . $mkq(['pagina' => $datos['pagina'] - 1])) ?>">Anterior</a><?php else: ?><span class="bo-btn bo-btn--sm bo-btn--off">Anterior</span><?php endif; ?>
            <?php if ($datos['pagina'] < $datos['paginas']): ?><a class="bo-btn bo-btn--sm" href="<?= e(ruta('recompensas') . $mkq(['pagina' => $datos['pagina'] + 1])) ?>">Siguiente</a><?php else: ?><span class="bo-btn bo-btn--sm bo-btn--off">Siguiente</span><?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="bo-panel" style="margin-top:20px">
    <h2 class="bo-panel__tit">Últimos canjes</h2>
    <?php if (!$recientes): ?>
        <p class="bo-dim">Todavía no hay canjes registrados.</p>
    <?php else: ?>
        <ul class="bo-lista">
            <?php foreach ($recientes as $c): ?>
                <li>
                    <?= e($fh($c['fecha'])) ?> ·
                    <span class="bo-mono">#<?= (int) $c['ID_vecino'] ?></span> <?= e($c['nombre']) ?>
                    canjeó <strong><?= e($c['recompensa']) ?></strong>
                    <span class="bo-badge bo-badge--rol"><?= e($num($c['puntos_gastados'])) ?> pts</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
