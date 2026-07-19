<?php
/** @var array $tipos @var array $estadisticas @var array $hero @var array $rutas */
?>

<!-- ============ HERO ============ -->
<section class="seccion seccion--hero" id="hero">
    <div class="contenedor">
        <div class="hero">
            <span class="hero__eyebrow">Sistema activo · Montevideo, UY</span>
            <h1 class="hero__titulo">¿Contenedores desbordados o rotos?</h1>
            <p class="hero__sub">
                Reportalos y seguí su resolución en tiempo real. El sistema municipal
                coordina la respuesta operativa y te avisa cuando tu incidencia se atiende.
            </p>
            <div class="hero__acciones">
                <a class="btn btn--primario" href="<?= View::e(Config::get('enlaces.reportar')) ?>">
                    Reportar incidencia
                </a>
                <a class="btn btn--secundario" href="<?= View::e(Config::get('enlaces.rutas')) ?>">
                    Ver rutas de recolección
                </a>
            </div>
            <div class="hero__stats">
                <?php foreach ($hero as $s): ?>
                    <div class="hero__stat">
                        <div class="hero__stat-num"><?= View::e($s['valor']) ?></div>
                        <div class="hero__stat-lbl"><?= View::e($s['etiqueta']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ============ TIPOS DE INCIDENCIA ============ -->
<section class="seccion" id="tipos">
    <div class="contenedor">
        <?php View::partial('section-header', [
            'eyebrow' => 'Tipos de incidencia',
            'title'   => '¿Qué problema encontraste?',
        ]); ?>

        <div class="tipos-grid">
            <?php foreach ($tipos as $t): ?>
                <article class="tipo-card">
                    <div class="tipo-card__top">
                        <span class="tipo-card__num"><?= View::e($t['contador']) ?></span>
                        <span class="tipo-card__tag"><?= View::e($t['etiqueta']) ?></span>
                    </div>
                    <h3 class="tipo-card__titulo"><?= View::e($t['titulo']) ?></h3>
                    <p class="tipo-card__desc"><?= View::e($t['descripcion']) ?></p>
                    <a class="enlace-flecha" href="<?= View::e(Config::get('enlaces.reportar')) ?>">
                        Reportar este tipo <span aria-hidden="true">→</span>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ RUTAS DE RECOLECCIÓN ============ -->
<section class="seccion" id="rutas">
    <div class="contenedor rutas">
        <div class="rutas__info">
            <?php View::partial('section-header', [
                'eyebrow' => 'Mapa operativo',
                'title'   => 'Rutas de recolección',
            ]); ?>
            <p class="rutas__desc">
                Seguí en tiempo real a las cuadrillas y los vehículos que operan hoy en la
                ciudad. Consultá qué zonas ya fueron cubiertas y cuáles siguen en curso.
            </p>
            <div class="rutas__stats">
                <?php foreach ($rutas as $r): ?>
                    <div class="rutas__stat">
                        <div class="rutas__stat-num"><?= View::e($r['valor']) ?></div>
                        <div class="rutas__stat-lbl"><?= View::e($r['etiqueta']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <a class="enlace-flecha" href="<?= View::e(Config::get('enlaces.mapa')) ?>">
                Ver mapa completo <span aria-hidden="true">→</span>
            </a>
        </div>

        <!-- Panel de mapa — decorativo, 100% CSS (sin API real). Estado vacío. -->
        <div class="mapa" role="img" aria-label="Mapa operativo sin rutas activas por ahora">
            <div class="mapa__chip">
                <span class="mapa__chip-punto" aria-hidden="true"></span>
                Sin rutas activas
            </div>
        </div>
    </div>
</section>

<!-- ============ ESTADÍSTICAS PÚBLICAS ============ -->
<section class="seccion" id="estadisticas">
    <div class="contenedor">
        <?php View::partial('section-header', [
            'eyebrow' => 'Datos abiertos · actualizado hoy',
            'title'   => 'Estadísticas públicas',
        ]); ?>

        <div class="stats-grid">
            <?php foreach ($estadisticas as $e): ?>
                <article class="stat-card">
                    <span class="stat-card__anio"><?= View::e($e['anio']) ?></span>
                    <div class="stat-card__valor"><?= View::e($e['valor']) ?></div>
                    <div class="stat-card__unidad"><?= View::e($e['unidad']) ?></div>
                    <p class="stat-card__detalle"><?= View::e($e['detalle']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
