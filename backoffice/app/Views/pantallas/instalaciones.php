<?php
/**
 * Pantalla 6 — Instalaciones (lista + alta). Server-rendered; dato con e().
 * Espera: $admin, $activo, $f, $datos, $resumen, $flash, $csrf.
 *
 * No hay acción de baja: `instalacion` no tiene columna de estado y el MER está
 * congelado (ver app/Models/Instalacion.php).
 */
$titulo = 'Instalaciones · Backoffice Cathartes';
$mkq = function (array $ov = []) use ($f) {
    $p = array_merge($f, $ov);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 0);
    return '?' . http_build_query($p);
};
// Color de la barra según el llenado. El umbral de alerta del proyecto es 80 %.
$nivel = fn(int $pc) => $pc >= Instalacion::UMBRAL ? 'alta' : ($pc >= 60 ? 'media' : 'baja');

require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <h1>Instalaciones</h1>
        <p class="bo-sub">Vertederos y centros de acopio: capacidad, ocupación en m³ y tipo.</p>
    </div>
    <details class="bo-alta" id="bo-alta">
        <summary class="bo-btn bo-btn--pri">＋ Nueva instalación</summary>
        <div class="bo-alta__modal">
            <form method="post" action="<?= e(ruta('instalaciones/crear')) ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <h2 class="bo-alta__tit">Nueva instalación</h2>
                <label class="bo-campo"><span>Nombre</span><input type="text" name="nombre" placeholder="Centro de Acopio Buceo" required></label>
                <label class="bo-campo"><span>Ubicación</span><input type="text" name="ubicacion" placeholder="Av. Rivera 3900, Buceo, Montevideo" required></label>
                <label class="bo-campo"><span>Tipo</span>
                    <select name="tipo">
                        <option value="centro de acopio">Centro de acopio</option>
                        <option value="vertedero">Vertedero</option>
                    </select>
                </label>
                <div class="bo-alta__fila">
                    <label class="bo-campo"><span>Capacidad (m³)</span><input type="number" name="capacidad" min="1" required></label>
                    <label class="bo-campo"><span>Ocupación actual (m³)</span><input type="number" name="ocupacion_actual" min="0" value="0" required></label>
                </div>
                <div class="bo-alta__fila">
                    <label class="bo-campo"><span>Latitud <em>(opcional)</em></span><input type="text" name="latitud" placeholder="-34.9075"></label>
                    <label class="bo-campo"><span>Longitud <em>(opcional)</em></span><input type="text" name="longitud" placeholder="-56.1385"></label>
                </div>
                <p class="bo-alta__nota">El tipo determina qué residuo maneja la instalación. La ocupación no puede superar la capacidad.</p>
                <div class="bo-alta__acc">
                    <button type="submit" class="bo-btn bo-btn--pri">Crear instalación</button>
                    <button type="button" class="bo-btn" onclick="document.getElementById('bo-alta').removeAttribute('open')">Cancelar</button>
                </div>
            </form>
        </div>
    </details>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div><?php endif; ?>

<div class="bo-metricas">
    <div class="bo-metrica">
        <span class="bo-metrica__n"><?= (int) $resumen['total'] ?></span>
        <span class="bo-metrica__t">Instalaciones</span>
    </div>
    <div class="bo-metrica">
        <span class="bo-metrica__n"><?= number_format($resumen['capacidad'], 0, ',', '.') ?></span>
        <span class="bo-metrica__t">Capacidad total (m³)</span>
    </div>
    <div class="bo-metrica">
        <span class="bo-metrica__n"><?= number_format($resumen['ocupacion'], 0, ',', '.') ?></span>
        <span class="bo-metrica__t">Ocupación total (m³)</span>
    </div>
    <div class="bo-metrica<?= $resumen['llenas'] > 0 ? ' bo-metrica--alerta' : '' ?>">
        <span class="bo-metrica__n"><?= (int) $resumen['llenas'] ?></span>
        <span class="bo-metrica__t">Al <?= Instalacion::UMBRAL ?>% o más</span>
    </div>
</div>

<form class="bo-filtros" method="get" action="<?= e(ruta('instalaciones')) ?>">
    <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Buscar por nombre o ubicación…">
    <select name="tipo">
        <option value="">Todos los tipos</option>
        <?php foreach (Instalacion::TIPOS as $t): ?>
            <option value="<?= e($t) ?>"<?= $f['tipo'] === $t ? ' selected' : '' ?>><?= e(ucfirst($t)) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="llenas">
        <option value="">Cualquier ocupación</option>
        <option value="si"<?= $f['llenas'] === 'si' ? ' selected' : '' ?>>Solo al <?= Instalacion::UMBRAL ?>% o más</option>
    </select>
    <button type="submit" class="bo-btn">Filtrar</button>
    <a class="bo-btn bo-btn--tenue" href="<?= e(ruta('instalaciones')) ?>">Limpiar</a>
</form>

<?php if (!$datos['items']): ?>
    <div class="bo-vacio"><p class="bo-vacio__t">Sin instalaciones</p><p class="bo-vacio__s">No hay instalaciones con estos filtros.</p></div>
<?php else: ?>
    <div class="bo-tabla-wrap">
        <table class="bo-tabla">
            <thead><tr><th>ID</th><th>Nombre</th><th>Ubicación</th><th>Tipo</th><th>Ocupación</th><th>Rutas</th><th>Operarios</th><th class="bo-tabla__acc"></th></tr></thead>
            <tbody>
                <?php foreach ($datos['items'] as $i): ?>
                    <?php $pc = Instalacion::porcentaje($i); ?>
                    <tr>
                        <td class="bo-mono">#<?= (int) $i['ID'] ?></td>
                        <td><?= e($i['nombre']) ?></td>
                        <td><?= e($i['ubicacion']) ?></td>
                        <td><span class="bo-badge bo-badge--rol"><?= e($i['tipo']) ?></span></td>
                        <td class="bo-ocupacion">
                            <span class="bo-ocupacion__n"><?= (int) $i['ocupacion_actual'] ?> / <?= (int) $i['capacidad'] ?> m³ · <?= $pc ?>%</span>
                            <span class="bo-barra bo-barra--<?= $nivel($pc) ?>"><span class="bo-barra__llena" style="width:<?= min(100, $pc) ?>%"></span></span>
                        </td>
                        <td class="bo-mono"><?= (int) $i['rutas'] ?></td>
                        <td class="bo-mono"><?= (int) $i['operarios'] ?></td>
                        <td class="bo-tabla__acc"><a class="bo-btn bo-btn--sm" href="<?= e(ruta('instalaciones/editar') . '?id=' . (int) $i['ID']) ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="bo-pag">
        <span><?= (int) $datos['total'] ?> instalaciones · página <?= (int) $datos['pagina'] ?> de <?= (int) $datos['paginas'] ?></span>
        <div class="bo-pag__btns">
            <?php if ($datos['pagina'] > 1): ?><a class="bo-btn bo-btn--sm" href="<?= e(ruta('instalaciones') . $mkq(['pagina' => $datos['pagina'] - 1])) ?>">Anterior</a><?php else: ?><span class="bo-btn bo-btn--sm bo-btn--off">Anterior</span><?php endif; ?>
            <?php if ($datos['pagina'] < $datos['paginas']): ?><a class="bo-btn bo-btn--sm" href="<?= e(ruta('instalaciones') . $mkq(['pagina' => $datos['pagina'] + 1])) ?>">Siguiente</a><?php else: ?><span class="bo-btn bo-btn--sm bo-btn--off">Siguiente</span><?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
