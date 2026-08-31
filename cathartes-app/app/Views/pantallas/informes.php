<?php
/**
 * Pantalla 10 — Informes. Server-rendered; dato con e().
 * Espera: $admin, $activo, $cual, $p (período), $incidencias, $porMes, $ocupacion,
 * $flota, $rendimiento, $inconsistencias, $flash, $csrf.
 *
 * Cada informe es una <section class="bo-informe"> con su propio encabezado (nombre,
 * período y fecha de emisión), porque en el PDF cada sección tiene que poder leerse
 * sola. El chrome (sidebar, filtros, botones) lo oculta print.css.
 *
 * Los informes 2 y 3 son foto del estado actual: lo dicen en su encabezado para que
 * la ausencia de período no parezca un olvido.
 */
$titulo   = 'Informes · Backoffice Cathartes';
$impresion = true; // carga print.css desde head.php
$num  = fn($n) => number_format((int) $n, 0, ',', '.');
$hoy  = date('d/m/Y H:i');
$ver  = fn(string $s) => $cual === '' || $cual === $s;

// Query string que preserva el período al cambiar de informe.
$conPeriodo = function (string $informe) use ($p) {
    $q = array_filter([
        'informe' => $informe,
        'desde'   => $p['desde'],
        'hasta'   => $p['hasta'],
    ], fn($v) => $v !== '');
    return $q ? ('?' . http_build_query($q)) : '';
};

require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab bo-no-print">
    <div>
        <h1>Informes</h1>
        <p class="bo-sub">Consultas agregadas sobre datos reales. «Exportar a PDF» abre el diálogo de impresión: guardá como PDF desde ahí.</p>
    </div>
    <button type="button" class="bo-btn bo-btn--pri" onclick="window.print()">Exportar a PDF</button>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?> bo-no-print"><?= e($flash['texto']) ?></div><?php endif; ?>

<!-- Qué informe se muestra -->
<nav class="bo-tabs bo-no-print">
    <a class="bo-tab<?= $cual === '' ? ' bo-tab--activo' : '' ?>" href="<?= e(ruta('informes') . $conPeriodo('')) ?>">Los cuatro</a>
    <?php foreach (InformeController::INFORMES as $slug => $nombre): ?>
        <a class="bo-tab<?= $cual === $slug ? ' bo-tab--activo' : '' ?>"
           href="<?= e(ruta('informes') . $conPeriodo($slug)) ?>"><?= e($nombre) ?></a>
    <?php endforeach; ?>
</nav>

<!-- Período: aplica a Incidencias y Rendimiento -->
<form class="bo-filtros bo-no-print" method="get" action="<?= e(ruta('informes')) ?>">
    <?php if ($cual !== ''): ?><input type="hidden" name="informe" value="<?= e($cual) ?>"><?php endif; ?>
    <label class="bo-campo bo-campo--inline"><span>Desde</span><input type="date" name="desde" value="<?= e($p['desde']) ?>"></label>
    <label class="bo-campo bo-campo--inline"><span>Hasta</span><input type="date" name="hasta" value="<?= e($p['hasta']) ?>"></label>
    <button type="submit" class="bo-btn">Aplicar período</button>
    <a class="bo-btn bo-btn--tenue" href="<?= e(ruta('informes') . ($cual !== '' ? '?informe=' . urlencode($cual) : '')) ?>">Todo el histórico</a>
    <span class="bo-dim">El período afecta a Incidencias y Rendimiento por cuadrilla.</span>
</form>

<!-- Encabezado que solo se ve en el PDF: identifica el documento impreso. -->
<div class="bo-print-encab">
    <strong>Cathartes</strong>
    <span>Backoffice de Administración — <?= e($cual === '' ? 'Informes' : InformeController::INFORMES[$cual]) ?></span>
    <span>Período: <?= e($p['texto']) ?> · Emitido el <?= e($hoy) ?> por <?= e($admin['nombre'] ?? '') ?></span>
</div>

<?php /* ============================================================ 1 ==== */ ?>
<?php if ($ver('incidencias')): ?>
<section class="bo-informe">
    <header class="bo-informe__cab">
        <h2 class="bo-panel__tit">1 · Incidencias</h2>
        <p class="bo-sub">Período: <?= e($p['texto']) ?> · emitido el <?= e($hoy) ?></p>
    </header>

    <div class="bo-metricas">
        <div class="bo-metrica">
            <span class="bo-metrica__n"><?= (int) $incidencias['total'] ?></span>
            <span class="bo-metrica__t">Reportes del período</span>
        </div>
        <div class="bo-metrica<?= $incidencias['sin_validar'] > 0 ? ' bo-metrica--alerta' : '' ?>">
            <span class="bo-metrica__n"><?= (int) $incidencias['sin_validar'] ?></span>
            <span class="bo-metrica__t">Sin validar</span>
        </div>
        <div class="bo-metrica">
            <span class="bo-metrica__n"><?= (int) $incidencias['en_curso'] ?></span>
            <span class="bo-metrica__t">En curso</span>
        </div>
        <div class="bo-metrica">
            <span class="bo-metrica__n"><?= e(duracion_humana($incidencias['resolucion'])) ?></span>
            <span class="bo-metrica__t">Promedio hasta la resolución (n=<?= (int) $incidencias['resueltas_n'] ?>)</span>
        </div>
    </div>

    <div class="bo-tabla-wrap">
        <table class="bo-tabla">
            <thead><tr><th>Desglose</th><th>Cantidad</th><th>Sobre el total</th></tr></thead>
            <tbody>
                <?php foreach ($incidencias['por_tipo'] as $tipo => $n): ?>
                    <tr>
                        <td>Tipo: <?= e($tipo) ?></td>
                        <td class="bo-mono"><?= (int) $n ?></td>
                        <td class="bo-mono"><?= $incidencias['total'] > 0 ? round($n * 100 / $incidencias['total']) : 0 ?>%</td>
                    </tr>
                <?php endforeach; ?>
                <tr><td>Estado: en curso</td><td class="bo-mono"><?= (int) $incidencias['en_curso'] ?></td><td class="bo-mono"><?= $incidencias['total'] > 0 ? round($incidencias['en_curso'] * 100 / $incidencias['total']) : 0 ?>%</td></tr>
                <tr><td>Estado: finalizada</td><td class="bo-mono"><?= (int) $incidencias['finalizadas'] ?></td><td class="bo-mono"><?= $incidencias['total'] > 0 ? round($incidencias['finalizadas'] * 100 / $incidencias['total']) : 0 ?>%</td></tr>
                <tr><td>Validadas</td><td class="bo-mono"><?= (int) $incidencias['validadas'] ?></td><td class="bo-mono"><?= $incidencias['total'] > 0 ? round($incidencias['validadas'] * 100 / $incidencias['total']) : 0 ?>%</td></tr>
                <tr><td>Sin validar</td><td class="bo-mono"><?= (int) $incidencias['sin_validar'] ?></td><td class="bo-mono"><?= $incidencias['total'] > 0 ? round($incidencias['sin_validar'] * 100 / $incidencias['total']) : 0 ?>%</td></tr>
            </tbody>
        </table>
    </div>

    <div class="bo-graficos">
        <div>
            <h3 class="bo-panel__tit" style="font-size:15px">Incidencias por mes</h3>
            <p class="bo-sub" style="font-size:12px;margin:0 0 8px">Últimos 6 meses. Un mes sin reportes se muestra en cero, no se omite.</p>
            <?php $g_titulo = 'Incidencias por mes'; $g_datos = $porMes; require BASE_PATH . '/app/Views/partials/grafico-barras.php'; ?>
        </div>
        <div>
            <h3 class="bo-panel__tit" style="font-size:15px">Distribución por tipo</h3>
            <p class="bo-sub" style="font-size:12px;margin:0 0 8px">Sobre los reportes del período.</p>
            <?php $g_titulo = 'Distribución de incidencias por tipo'; $g_datos = $incidencias['por_tipo']; require BASE_PATH . '/app/Views/partials/grafico-torta.php'; ?>
        </div>
    </div>

    <p class="bo-informe__pie">
        El tiempo promedio hasta la resolución se calcula únicamente sobre incidencias con una
        intervención finalizada. La cantidad de casos (n) se indica junto al promedio. El gráfico
        de barras cubre los últimos 6 meses e ignora el período seleccionado.
    </p>
</section>
<?php endif; ?>

<?php /* ============================================================ 2 ==== */ ?>
<?php if ($ver('ocupacion')): ?>
<section class="bo-informe">
    <header class="bo-informe__cab">
        <h2 class="bo-panel__tit">2 · Ocupación de instalaciones</h2>
        <p class="bo-sub">Foto del estado actual — este informe no lleva período. Emitido el <?= e($hoy) ?>.</p>
    </header>

    <?php if (!$ocupacion): ?>
        <div class="bo-vacio"><p class="bo-vacio__t">Sin instalaciones</p><p class="bo-vacio__s">No hay instalaciones cargadas.</p></div>
    <?php else: ?>
        <div class="bo-tabla-wrap">
            <table class="bo-tabla">
                <thead><tr><th>ID</th><th>Instalación</th><th>Tipo</th><th>Ocupación</th><th>Capacidad</th><th>Libre</th><th>%</th></tr></thead>
                <tbody>
                    <?php foreach ($ocupacion as $i): ?>
                        <tr<?= $i['alta'] ? ' class="bo-fila--alerta"' : '' ?>>
                            <td class="bo-mono">#<?= (int) $i['ID'] ?></td>
                            <td><?= e($i['nombre']) ?></td>
                            <td><?= e($i['tipo']) ?></td>
                            <td class="bo-mono"><?= e($num($i['ocupacion_actual'])) ?> m³</td>
                            <td class="bo-mono"><?= e($num($i['capacidad'])) ?> m³</td>
                            <td class="bo-mono"><?= e($num($i['libre'])) ?> m³</td>
                            <td class="bo-mono">
                                <?= (int) $i['pct'] ?>%
                                <?php if ($i['alta']): ?><span class="bo-badge bo-badge--baja">≥ <?= Informe::UMBRAL_OCUPACION ?>%</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="bo-informe__pie">
            Ordenado de mayor a menor ocupación. Se resaltan las instalaciones que alcanzaron o
            superaron el <?= Informe::UMBRAL_OCUPACION ?>% de su capacidad.
        </p>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php /* ============================================================ 3 ==== */ ?>
<?php if ($ver('flota')): ?>
<section class="bo-informe">
    <header class="bo-informe__cab">
        <h2 class="bo-panel__tit">3 · Flota</h2>
        <p class="bo-sub">Foto del estado actual — este informe no lleva período. Emitido el <?= e($hoy) ?>.</p>
    </header>

    <div class="bo-tabla-wrap">
        <table class="bo-tabla">
            <thead><tr><th>Estado</th><th>Camiones</th><th>Capacidad sumada</th><th>Sobre la flota operativa</th></tr></thead>
            <tbody>
                <?php foreach ($flota['por_estado'] as $estado => $d): ?>
                    <tr>
                        <td><?= e(ucfirst($estado)) ?></td>
                        <td class="bo-mono"><?= (int) $d['n'] ?></td>
                        <td class="bo-mono"><?= e($num($d['capacidad'])) ?></td>
                        <td class="bo-mono"><?= $flota['operativos'] > 0 ? round($d['n'] * 100 / $flota['operativos']) : 0 ?>%</td>
                    </tr>
                <?php endforeach; ?>
                <tr><td><strong>Flota operativa</strong></td><td class="bo-mono"><strong><?= (int) $flota['operativos'] ?></strong></td><td></td><td class="bo-mono">100%</td></tr>
            </tbody>
        </table>
    </div>

    <h3 class="bo-panel__tit" style="font-size:15px;margin-top:18px">Dados de baja (<?= count($flota['baja']) ?>)</h3>
    <?php if (!$flota['baja']): ?>
        <p class="bo-dim">Ningún camión dado de baja.</p>
    <?php else: ?>
        <div class="bo-tabla-wrap">
            <table class="bo-tabla">
                <thead><tr><th>ID</th><th>Matrícula</th><th>Modelo</th><th>Capacidad</th><th>Fecha de baja</th><th>Motivo</th></tr></thead>
                <tbody>
                    <?php foreach ($flota['baja'] as $c): ?>
                        <tr>
                            <td class="bo-mono">#<?= (int) $c['ID'] ?></td>
                            <td class="bo-mono"><?= e($c['matricula']) ?></td>
                            <td><?= e($c['modelo']) ?></td>
                            <td class="bo-mono"><?= e($num($c['capacidad'])) ?></td>
                            <td><?= e(($m = preg_match('/(\d{4})-(\d{2})-(\d{2})/', (string) $c['fecha_baja'], $x)) ? "$x[3]/$x[2]/$x[1]" : '—') ?></td>
                            <td><?= e($c['motivo_baja'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p class="bo-informe__pie">
        Los camiones dados de baja se listan aparte y no cuentan en la flota operativa ni en los
        porcentajes: el sistema no los elimina, los retira de servicio con fecha y motivo.
    </p>
</section>
<?php endif; ?>

<?php /* ============================================================ 4 ==== */ ?>
<?php if ($ver('rendimiento')): ?>
<section class="bo-informe">
    <header class="bo-informe__cab">
        <h2 class="bo-panel__tit">4 · Rendimiento por cuadrilla</h2>
        <p class="bo-sub">Período: <?= e($p['texto']) ?> · emitido el <?= e($hoy) ?></p>
    </header>

    <?php if ($inconsistencias): ?>
        <div class="bo-aviso bo-aviso--error">
            <strong>Datos inconsistentes en <code>intervencion</code>:</strong>
            <?= count($inconsistencias) ?> fila<?= count($inconsistencias) === 1 ? '' : 's' ?> contradictoria<?= count($inconsistencias) === 1 ? '' : 's' ?>.
            Los promedios de abajo las excluyen, pero conviene corregirlas.
            <ul class="bo-lista" style="margin-top:8px">
                <?php foreach ($inconsistencias as $x): ?>
                    <li><span class="bo-mono">intervención #<?= (int) $x['ID_intervencion'] ?></span> — <?= e($x['motivo']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$rendimiento): ?>
        <div class="bo-vacio"><p class="bo-vacio__t">Sin cuadrillas</p><p class="bo-vacio__s">No hay cuadrillas cargadas. Se dan de alta desde Usuarios.</p></div>
    <?php else: ?>
        <div class="bo-tabla-wrap">
            <table class="bo-tabla">
                <thead><tr><th>ID</th><th>Cuadrilla</th><th>Asignadas</th><th>Finalizadas</th><th>Abiertas</th><th>Tasa de cierre</th><th>Tiempo promedio</th></tr></thead>
                <tbody>
                    <?php foreach ($rendimiento as $c): ?>
                        <tr>
                            <td class="bo-mono">#<?= (int) $c['id'] ?></td>
                            <td>
                                <?= e($c['nombre']) ?>
                                <?php if ($c['operativo'] !== 'en servicio' || $c['cuenta'] !== 'activo'): ?>
                                    <span class="bo-badge bo-badge--baja"><?= e($c['cuenta'] !== 'activo' ? 'cuenta ' . $c['cuenta'] : $c['operativo']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="bo-mono"><?= (int) $c['asignadas'] ?></td>
                            <td class="bo-mono"><?= (int) $c['finalizadas'] ?></td>
                            <td class="bo-mono"><?= (int) $c['abiertas'] ?></td>
                            <td class="bo-mono"><?= $c['tasa'] === null ? '—' : (int) $c['tasa'] . '%' ?></td>
                            <td class="bo-mono">
                                <?= e(duracion_humana($c['prom'])) ?>
                                <span class="bo-dim">(n=<?= (int) $c['prom_n'] ?>)</span>
                            </td>
                        </tr>
                        <?php if (!$c['cuadra']): ?>
                            <tr><td colspan="7" class="bo-dim">
                                Inconsistencia en esta fila: asignadas (<?= (int) $c['asignadas'] ?>) no es igual a
                                finalizadas + abiertas (<?= (int) $c['finalizadas'] + (int) $c['abiertas'] ?>).
                            </td></tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p class="bo-informe__pie">
        El tiempo promedio se calcula únicamente sobre intervenciones finalizadas. La cantidad de
        casos (n) se indica junto a cada promedio.
    </p>
    <p class="bo-informe__pie">
        La tasa de cierre es un ratio histórico (finalizadas sobre asignadas); «Abiertas» es carga
        de trabajo actual. Una cuadrilla sin intervenciones en el período aparece en cero, no se
        omite.
    </p>
</section>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
