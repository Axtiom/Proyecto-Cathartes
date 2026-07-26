<?php
/**
 * Pantalla 7 — Editar ruta: datos, recorrido (por qué contenedores pasa), mapa y
 * cuadrilla a cargo. Espera: $admin, $activo, $r, $instalaciones, $cuadrillas,
 * $historial, $contenedores, $asignables, $otrasRutas, $flash, $csrf.
 *
 * `toma` es historial: la asignación vigente es la primera fila de $historial y
 * asignar agrega una fila nueva (ver app/Models/Ruta.php).
 *
 * El recorrido se define acá y no en la pantalla de Contenedores: la ruta es la
 * que decide por dónde pasa. Ojo con el esquema: `contenedor.ID_ruta` es NOT NULL,
 * así que desmarcar un contenedor no lo deja "sin ruta" — hay que mandarlo a otra.
 */
$titulo  = 'Ruta #' . (int) $r['ID'] . ' · Backoffice';
$mapa    = true; // carga leaflet.css desde head.php
$activa  = $r['estado'] === 'activa';
$vigente = $historial[0] ?? null;
$fh = fn($iso) => ($m = preg_match('/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', (string) $iso, $x)) ? "$x[3]/$x[2]/$x[1] $x[4]:$x[5]" : '—';
$badgeCont = fn($e) => $e === 'funcional' ? 'ok' : ($e === 'desbordado' ? 'rol' : 'baja');

// IDs de los contenedores que hoy son de esta ruta (para marcar los checkboxes).
$deLaRuta = array_map(fn($c) => (int) $c['ID'], $contenedores);

// Puntos del mapa: los contenedores del recorrido + la instalación de destino.
// Se arma acá (servidor) y viaja como JSON en un <script type="application/json">,
// que el navegador NO ejecuta: es dato, no código.
$puntos = [];
foreach ($contenedores as $c) {
    if ($c['latitud'] === null || $c['longitud'] === null) continue;
    $puntos[] = [
        'id' => (int) $c['ID'], 'tipo' => 'contenedor', 'estado' => $c['estado'],
        'latitud' => (float) $c['latitud'], 'longitud' => (float) $c['longitud'],
        'titulo' => $c['ubicacion'], 'ubicacion' => $c['tipo'],
    ];
}
if ($r['instalacion_lat'] !== null && $r['instalacion_lng'] !== null) {
    $puntos[] = [
        'id' => (int) $r['ID_instalacion'], 'tipo' => 'instalacion', 'estado' => $r['instalacion_tipo'],
        'latitud' => (float) $r['instalacion_lat'], 'longitud' => (float) $r['instalacion_lng'],
        'titulo' => $r['instalacion'], 'ubicacion' => 'destino de descarga',
    ];
}
$sinCoords = count($contenedores) - count(array_filter($puntos, fn($p) => $p['tipo'] === 'contenedor'));

require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <a class="bo-btn bo-btn--sm bo-btn--tenue" href="<?= e(ruta('rutas')) ?>">← Volver a rutas</a>
        <h1 style="margin-top:8px">Ruta #<?= (int) $r['ID'] ?> · <?= e($r['nombre']) ?></h1>
    </div>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div><?php endif; ?>

<?php if (!$activa): ?>
    <div class="bo-aviso bo-aviso--error">
        Ruta inactiva: no se le puede asignar cuadrilla. Pasala a <em>activa</em> y guardá para volver a operarla.
    </div>
<?php endif; ?>

<!-- Mapa del recorrido -->
<div class="bo-panel" style="margin-bottom:20px">
    <h2 class="bo-panel__tit">Mapa del recorrido</h2>
    <p class="bo-sub" style="font-size:13px;margin:0 0 12px">
        Los contenedores del recorrido, coloreados por estado, y la instalación de destino
        (el punto más grande). Un contenedor sin coordenadas cargadas no se puede dibujar.
    </p>
    <?php if ($sinCoords > 0): ?>
        <div class="bo-aviso bo-aviso--error" style="margin-bottom:12px">
            <?= (int) $sinCoords ?> contenedor<?= $sinCoords === 1 ? '' : 'es' ?> del recorrido no tiene<?= $sinCoords === 1 ? '' : 'n' ?>
            latitud y longitud, así que no aparece<?= $sinCoords === 1 ? '' : 'n' ?> en el mapa. Se cargan desde su ficha en Contenedores.
        </div>
    <?php endif; ?>
    <div id="bo-mapa"></div>
</div>

<div class="bo-detalle">
    <div class="bo-panel">
        <h2 class="bo-panel__tit">Datos</h2>
        <form method="post" action="<?= e(ruta('rutas/actualizar')) ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $r['ID'] ?>">
            <label class="bo-campo"><span>Nombre</span><input type="text" name="nombre" value="<?= e($r['nombre']) ?>" required></label>
            <div class="bo-alta__fila">
                <label class="bo-campo"><span>Zona</span><input type="text" name="zona" value="<?= e($r['zona']) ?>" required></label>
                <label class="bo-campo"><span>Estado</span>
                    <select name="estado">
                        <?php foreach (Ruta::ESTADOS as $es): ?>
                            <option value="<?= e($es) ?>"<?= $r['estado'] === $es ? ' selected' : '' ?>><?= e(ucfirst($es)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label class="bo-campo"><span>Frecuencia</span><input type="text" name="frecuencia" value="<?= e($r['frecuencia']) ?>" required></label>
            <label class="bo-campo"><span>Instalación de destino</span>
                <select name="id_instalacion" required>
                    <?php foreach ($instalaciones as $i): ?>
                        <option value="<?= (int) $i['ID'] ?>"<?= (int) $r['ID_instalacion'] === (int) $i['ID'] ? ' selected' : '' ?>><?= e($i['nombre'] . ' · ' . $i['tipo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="bo-btn bo-btn--pri" style="margin-top:10px">Guardar cambios</button>
        </form>
    </div>

    <div class="bo-panel">
        <h2 class="bo-panel__tit">Cuadrilla a cargo</h2>
        <div class="bo-dato">
            <span>Asignación vigente</span>
            <span><?= $vigente ? '<span class="bo-badge bo-badge--rol">' . e($vigente['nombre']) . '</span>' : '<span class="bo-dim">sin asignar</span>' ?></span>
        </div>
        <?php if ($vigente): ?>
            <div class="bo-dato"><span>Desde</span><span><?= e($fh($vigente['fecha'])) ?></span></div>
            <div class="bo-dato">
                <span>Estado operativo</span>
                <span class="bo-badge bo-badge--<?= $vigente['operativo'] === 'en servicio' ? 'ok' : 'baja' ?>"><?= e($vigente['operativo']) ?></span>
            </div>
        <?php endif; ?>

        <p class="bo-sub" style="font-size:13px;margin:14px 0 8px">
            Asignar deja registro: la toma anterior no se borra, queda como historial de la ruta.
            Solo aparecen cuadrillas con la cuenta activa y en servicio.
        </p>

        <?php if (!$cuadrillas): ?>
            <p class="bo-dim">No hay cuadrillas activas y en servicio para asignar.</p>
        <?php elseif (!$activa): ?>
            <button type="button" class="bo-btn bo-btn--bloqueado"
                    data-aviso="La ruta está inactiva: activala y guardá antes de asignarle una cuadrilla.">
                Asignar cuadrilla
            </button>
        <?php else: ?>
            <form method="post" action="<?= e(ruta('rutas/asignar')) ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int) $r['ID'] ?>">
                <label class="bo-campo"><span>Cuadrilla</span>
                    <select name="id_cuadrilla" required>
                        <option value="">Elegí una cuadrilla…</option>
                        <?php foreach ($cuadrillas as $c): ?>
                            <option value="<?= (int) $c['ID'] ?>">#<?= (int) $c['ID'] ?> · <?= e($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="bo-btn bo-btn--pri" style="margin-top:10px">Asignar cuadrilla</button>
            </form>
        <?php endif; ?>

        <h3 class="bo-panel__tit" style="font-size:15px;margin:20px 0 6px">Historial de tomas (<?= count($historial) ?>)</h3>
        <?php if (!$historial): ?>
            <p class="bo-dim">Sin tomas registradas.</p>
        <?php else: ?>
            <ul class="bo-lista">
                <?php foreach ($historial as $n => $h): ?>
                    <li>
                        <?= e($fh($h['fecha'])) ?> · <?= e($h['nombre']) ?>
                        <?php if ($n === 0): ?><span class="bo-badge bo-badge--ok">vigente</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- Recorrido: por qué contenedores pasa la ruta -->
<div class="bo-panel" style="margin-top:20px">
    <h2 class="bo-panel__tit">Por qué contenedores pasa (<?= count($contenedores) ?>)</h2>
    <p class="bo-sub" style="font-size:13px;margin:0 0 4px">
        Marcá los contenedores del recorrido. Los que están en otra ruta se pueden traer acá:
        se los saca de la ruta anterior. Los dados de baja no figuran.
    </p>
    <p class="bo-sub" style="font-size:13px;margin:0 0 14px">
        Un contenedor pertenece siempre a una ruta, así que desmarcar no lo deja libre:
        hay que indicar a qué ruta pasa.
    </p>

    <?php if (!$asignables): ?>
        <p class="bo-dim">No hay contenedores en operación. Se dan de alta desde la pantalla de Contenedores.</p>
    <?php else: ?>
        <form method="post" action="<?= e(ruta('rutas/contenedores')) ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $r['ID'] ?>">

            <div class="bo-checklist">
                <?php foreach ($asignables as $c): ?>
                    <?php $mio = in_array((int) $c['ID'], $deLaRuta, true); ?>
                    <label class="bo-check">
                        <input type="checkbox" name="contenedores[]" value="<?= (int) $c['ID'] ?>"<?= $mio ? ' checked' : '' ?>>
                        <span class="bo-check__cuerpo">
                            <span class="bo-mono">#<?= (int) $c['ID'] ?></span>
                            <?= e($c['ubicacion']) ?>
                            <span class="bo-badge bo-badge--<?= $badgeCont($c['estado']) ?>"><?= e($c['estado']) ?></span>
                            <span class="bo-dim"><?= e($c['tipo']) ?></span>
                            <?php if (!$mio): ?>
                                <span class="bo-dim">· hoy en <?= e($c['zona'] . ' · ' . $c['ruta']) ?></span>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <?php if ($otrasRutas): ?>
                <label class="bo-campo" style="margin-top:14px">
                    <span>Los que desmarques pasan a</span>
                    <select name="ruta_destino">
                        <option value="">— (solo si desmarcás alguno) —</option>
                        <?php foreach ($otrasRutas as $o): ?>
                            <option value="<?= (int) $o['ID'] ?>"><?= e($o['zona'] . ' · ' . $o['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php else: ?>
                <p class="bo-dim" style="margin-top:14px">
                    Es la única ruta del sistema: no hay a dónde mover un contenedor, así que por ahora
                    solo se pueden agregar. Creá otra ruta para poder sacarlos.
                </p>
            <?php endif; ?>

            <button type="submit" class="bo-btn bo-btn--pri" style="margin-top:12px">Guardar recorrido</button>
        </form>
    <?php endif; ?>
</div>

<!-- Puntos del mapa: DATO, no código (el navegador no ejecuta application/json). -->
<script type="application/json" id="bo-mapa-datos"><?= json_encode($puntos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= e(ruta('assets/leaflet/leaflet.js')) ?>"></script>
<script src="<?= e(ruta('js/mapa.js')) ?>"></script>
<script>
// Único JS de la pantalla: lee los puntos del bloque JSON y se los pasa al módulo
// Cathartes.Mapa (el mismo de la landing y del frontend de usuarios). Si Leaflet
// no cargó, el módulo muestra un aviso en el div en vez de romper la pantalla.
(function () {
    var datos = document.getElementById('bo-mapa-datos');
    if (!datos || !window.Cathartes || !window.Cathartes.Mapa) return;
    var puntos = [];
    try { puntos = JSON.parse(datos.textContent); } catch (e) { puntos = []; }
    window.Cathartes.Mapa.crear({ contenedor: '#bo-mapa', marcadores: puntos, alto: '380px' });
})();
</script>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
