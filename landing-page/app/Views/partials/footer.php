<footer class="site-footer">
    <div class="contenedor site-footer__grid">
        <div class="site-footer__marca">
            <?php View::partial('marca'); ?>
            <p class="site-footer__desc">
                Sistema integral de gestión de residuos urbanos. Desarrollado por Axtiom.
            </p>
            <p class="site-footer__lugar"><?= View::e(Config::get('contacto.lugar')) ?></p>
        </div>

        <nav class="site-footer__col" aria-label="Sistema">
            <h3 class="site-footer__titulo">Sistema</h3>
            <a href="<?= View::e(Config::get('enlaces.login')) ?>">Iniciar sesión</a>
            <a href="<?= View::e(Config::get('enlaces.registro')) ?>">Registrarse</a>
            <a href="<?= View::e(Config::get('enlaces.reportar')) ?>">Reportar incidencia</a>
            <a href="<?= View::e(Config::get('enlaces.reportar')) ?>">Seguimiento de reportes</a>
        </nav>

        <nav class="site-footer__col" aria-label="Información">
            <h3 class="site-footer__titulo">Información</h3>
            <a href="<?= View::e(Config::get('enlaces.rutas')) ?>">Rutas de recolección</a>
            <a href="#estadisticas">Estadísticas públicas</a>
            <a href="#tipos">Tipos de incidencia</a>
            <a href="#">Preguntas frecuentes</a>
        </nav>

        <div class="site-footer__col">
            <h3 class="site-footer__titulo">Contacto</h3>
            <a href="#"><?= View::e(Config::get('contacto.telefono')) ?></a>
            <a href="mailto:<?= View::e(Config::get('contacto.email')) ?>"><?= View::e(Config::get('contacto.email')) ?></a>
            <a href="#"><?= View::e(Config::get('contacto.web')) ?></a>
        </div>
    </div>

    <div class="contenedor site-footer__base">
        <span>© 2026 Axtiom · Sistema de gestión de residuos urbanos</span>
        <span class="site-footer__legal">
            <a href="#">Privacidad</a>
            <a href="#">Accesibilidad</a>
            <a href="#">Datos abiertos</a>
        </span>
    </div>
</footer>
