<?php
/**
 * Pantalla 1 — Dashboard. Server-rendered; dato con e().
 * Espera: $admin, $activo, $m (métricas), $pendientes, $puntos, $sinCoords,
 * $flash, $csrf.
 *
 * Dos bloques de tarjetas a propósito: arriba lo que REQUIERE ACCIÓN (cada una
 * linkea a la pantalla donde se resuelve, y se pinta en ámbar si hay algo), abajo
 * el estado de la operación (informativo, sin link). Después el mapa con filtros.
 */
$titulo = 'Dashboard · Backoffice Cathartes';
$mapa   = true; // carga leaflet.css desde head.php
$fh  = fn($iso) => ($m = preg_match('/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', (string) $iso, $x)) ? "$x[3]/$x[2]/$x[1] $x[4]:$x[5]" : '—';

// Tarjetas accionables: [número, texto, ruta, query]. Se pintan en ámbar cuando
// el número es mayor a cero, porque ahí sí hay trabajo pendiente.
$acciones = [
    [$m['usuarios_pendientes'],  'Usuarios esperando aprobación', 'usuarios',      '?pestana=pendientes'],
    [$m['sin_validar'],          'Incidencias sin validar',       'incidencias',   '?validada=no'],
    [$m['desbordados'],          'Contenedores desbordados',      'contenedores',  '?estado=desbordado'],
    [$m['instalaciones_llenas'], 'Instalaciones al ' . Panel::UMBRAL_INSTALACION . '% o más', 'instalaciones', '?llenas=si'],
];

require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <h1>Dashboard</h1>
        <p class="bo-sub">Estado operativo del sistema. Las tarjetas de arriba llevan a lo que hay para resolver.</p>
    </div>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div><?php endif; ?>

<h2 class="bo-panel__tit">Requiere acción</h2>
<div class="bo-metricas">
    <?php foreach ($acciones as [$n, $texto, $seccion, $qs]): ?>
        <a class="bo-metrica bo-metrica--link<?= (int) $n > 0 ? ' bo-metrica--alerta' : '' ?>"
           href="<?= e(ruta($seccion) . $qs) ?>">
            <span class="bo-metrica__n"><?= (int) $n ?></span>
            <span class="bo-metrica__t"><?= e($texto) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<h2 class="bo-panel__tit" style="margin-top:22px">Operación</h2>
<div class="bo-metricas">
    <div class="bo-metrica">
        <span class="bo-metrica__n"><?= (int) $m['en_curso'] ?></span>
        <span class="bo-metrica__t">Incidencias en curso<?= (int) $m['sin_asignar'] > 0 ? ' · ' . (int) $m['sin_asignar'] . ' sin cuadrilla' : '' ?></span>
    </div>
    <div class="bo-metrica">
        <span class="bo-metrica__n"><?= (int) $m['cuadrillas'] ?> / <?= (int) $m['cuadrillas_tot'] ?></span>
        <span class="bo-metrica__t">Cuadrillas en servicio</span>
    </div>
    <div class="bo-metrica">
        <span class="bo-metrica__n"><?= (int) $m['camiones_ok'] ?> / <?= (int) $m['camiones_tot'] ?></span>
        <span class="bo-metrica__t">Camiones funcionales</span>
    </div>
    <div class="bo-metrica">
        <span class="bo-metrica__n"><?= (int) $m['rutas_activas'] ?> / <?= (int) $m['rutas_tot'] ?></span>
        <span class="bo-metrica__t">Rutas activas</span>
    </div>
</div>

<div class="bo-metricas" style="margin-top:12px">
    <div class="bo-metrica">
        <span class="bo-metrica__n"><?= (int) $m['contenedores'] ?></span>
        <span class="bo-metrica__t">Contenedores en operación</span>
    </div>
</div>

<!-- Mapa operativo con filtros -->
<div class="bo-panel" style="margin-top:22px">
    <h2 class="bo-panel__tit">Mapa operativo</h2>
    <p class="bo-sub" style="font-size:13px;margin:0 0 12px">
        Contenedores coloreados por estado, instalaciones (los puntos más grandes) e incidencias abiertas
        (el halo alrededor del contenedor: ámbar si falta validarla, rojo si ya está en curso).
        Los filtros de arriba del mapa recortan lo que se ve sin recargar la página.
    </p>
    <?php if ($sinCoords > 0): ?>
        <div class="bo-aviso bo-aviso--error" style="margin-bottom:12px">
            <?= (int) $sinCoords ?> punto<?= $sinCoords === 1 ? '' : 's' ?> sin latitud y longitud cargadas,
            así que no aparece<?= $sinCoords === 1 ? '' : 'n' ?> en el mapa. Se completan desde la ficha de
            cada contenedor o instalación.
        </div>
    <?php endif; ?>
    <div id="bo-mapa"></div>
</div>

<!-- Top de contenedores desbordados -->
<div class="bo-panel" style="margin-top:20px">
    <h2 class="bo-panel__tit">Contenedores que más se desbordan</h2>
    <p class="bo-sub" style="font-size:13px;margin:0 0 12px">
        Cantidad de reportes de desborde por contenedor. Es donde conviene reforzar la frecuencia de recolección o instalar otro contenedor.
    </p>
    <nav class="bo-tabs">
        <?php foreach (Panel::PERIODOS as $slug => $texto): ?>
            <a class="bo-tab<?= $periodo === $slug ? ' bo-tab--activo' : '' ?>" href="<?= e(ruta('dashboard') . '?periodo=' . $slug) ?>"><?= e($texto) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if (!$top): ?>
        <div class="bo-vacio"><p class="bo-vacio__t">Sin desbordes reportados</p><p class="bo-vacio__s">No hay reportes de desborde en este período.</p></div>
    <?php else: $max = max(array_column($top, 'veces')); ?>
        <div class="bo-tabla-wrap">
            <table class="bo-tabla">
                <thead><tr><th>#</th><th>Contenedor</th><th>Zona</th><th>Estado actual</th><th style="width:34%">Reportes</th><th class="bo-tabla__acc"></th></tr></thead>
                <tbody>
                    <?php foreach ($top as $n => $t): ?>
                        <tr>
                            <td class="bo-mono"><?= $n + 1 ?></td>
                            <td><?= e($t['ubicacion']) ?> <span class="bo-dim bo-mono">#<?= (int) $t['id'] ?></span></td>
                            <td><?= e($t['zona']) ?></td>
                            <td><span class="bo-badge bo-badge--<?= $t['estado'] === 'funcional' ? 'ok' : ($t['estado'] === 'desbordado' ? 'rol' : 'baja') ?>"><?= e($t['estado']) ?></span></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <div style="flex:1;height:8px;border-radius:4px;background:var(--superficie2)">
                                        <div style="height:8px;border-radius:4px;background:var(--ambar);width:<?= (int) round($t['veces'] * 100 / $max) ?>%"></div>
                                    </div>
                                    <b class="bo-mono"><?= (int) $t['veces'] ?></b>
                                </div>
                            </td>
                            <td class="bo-tabla__acc"><a class="bo-btn bo-btn--sm" href="<?= e(ruta('contenedores/editar') . '?id=' . (int) $t['id']) ?>">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Cola de trabajo -->
<div class="bo-panel" style="margin-top:20px">
    <h2 class="bo-panel__tit">Incidencias esperando</h2>
    <p class="bo-sub" style="font-size:13px;margin:0 0 12px">
        Sin validar primero, y de las más viejas a las más nuevas: una incidencia vieja
        sin validar es peor que una de hoy.
    </p>
    <?php if (!$pendientes): ?>
        <div class="bo-vacio">
            <p class="bo-vacio__t">Nada pendiente</p>
            <p class="bo-vacio__s">No hay incidencias sin validar ni en curso.</p>
        </div>
    <?php else: ?>
        <div class="bo-tabla-wrap">
            <table class="bo-tabla">
                <thead><tr><th>ID</th><th>Ingresó</th><th>Tipo</th><th>Contenedor</th><th>Vecino</th><th>Validada</th><th>Cuadrilla</th><th class="bo-tabla__acc"></th></tr></thead>
                <tbody>
                    <?php foreach ($pendientes as $p): ?>
                        <tr>
                            <td class="bo-mono">#<?= (int) $p['ID_incidencia'] ?></td>
                            <td><?= e($fh($p['fecha_ing'])) ?></td>
                            <td><?= e($p['tipo']) ?></td>
                            <td><?= e($p['ubicacion']) ?></td>
                            <td><?= e($p['vecino']) ?></td>
                            <td><span class="bo-badge bo-badge--<?= $p['validada'] === 'si' ? 'ok' : 'baja' ?>"><?= $p['validada'] === 'si' ? 'sí' : 'no' ?></span></td>
                            <td><?= $p['asignada'] ? '<span class="bo-badge bo-badge--ok">asignada</span>' : '<span class="bo-dim">sin asignar</span>' ?></td>
                            <td class="bo-tabla__acc"><a class="bo-btn bo-btn--sm" href="<?= e(ruta('incidencias/ver') . '?id=' . (int) $p['ID_incidencia']) ?>">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <a class="bo-btn bo-btn--sm" style="margin-top:12px" href="<?= e(ruta('incidencias')) ?>">Ver todas las incidencias</a>
    <?php endif; ?>
</div>

<!-- Puntos del mapa: DATO, no código (el navegador no ejecuta application/json). -->
<script type="application/json" id="bo-mapa-datos"><?= json_encode($puntos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= e(ruta('assets/leaflet/leaflet.js')) ?>"></script>
<script src="<?= e(ruta('js/mapa.js')) ?>"></script>
<script>
// Lee los puntos del bloque JSON y los pasa al módulo Cathartes.Mapa (el mismo de
// la landing y del frontend). Los filtros son predicados sobre el marcador: el
// módulo dibuja los chips y rehace la capa, acá solo se declara el criterio.
(function () {
    var datos = document.getElementById('bo-mapa-datos');
    if (!datos || !window.Cathartes || !window.Cathartes.Mapa) return;
    var puntos = [];
    try { puntos = JSON.parse(datos.textContent); } catch (e) { puntos = []; }

    window.Cathartes.Mapa.crear({
        contenedor: '#bo-mapa',
        marcadores: puntos,
        alto: '440px',
        filtros: [
            { etiqueta: 'Todo',           predicado: function () { return true; } },
            { etiqueta: 'Desbordados',    predicado: function (m) { return m.estado === 'desbordado'; } },
            { etiqueta: 'No funcionales', predicado: function (m) { return m.estado === 'no funcional'; } },
            { etiqueta: 'Instalaciones',  predicado: function (m) { return m.tipo === 'instalacion'; } },
            { etiqueta: 'Incidencias abiertas', predicado: function (m) { return m.tipo === 'incidencia'; } }
        ]
    });
})();
</script>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
