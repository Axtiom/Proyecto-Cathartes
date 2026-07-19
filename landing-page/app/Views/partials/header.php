<header class="site-header">
    <div class="contenedor site-header__inner">
        <?php View::partial('marca'); ?>
        <nav class="nav-principal" aria-label="Accesos">
            <a class="nav-link" href="<?= View::e(Config::get('enlaces.login')) ?>">Iniciar sesión</a>
            <a class="nav-link nav-link--cta" href="<?= View::e(Config::get('enlaces.registro')) ?>">Registrarse</a>
        </nav>
    </div>
</header>
