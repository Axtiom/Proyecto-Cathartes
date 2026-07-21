<?php
/**
 * Página protegida mínima — prueba del circuito completo (token + kill-switch).
 * Placeholder hasta el dashboard real de cada rol.
 * @var array $usuario  @var string $csrf
 */
$usuario = $usuario ?? [];
?>
<div class="tarjeta">
    <div class="marca">
        <?php View::partial('logo'); ?>
        <span class="marca__nombre">Cathartes</span>
    </div>
    <h1>Hola, <?= View::e((string) ($usuario['nombre'] ?? '')) ?></h1>
    <p class="sub">Tu sesión está activa. Esta es una página de prueba hasta tener el panel de tu rol.</p>

    <div class="dato"><span>Rol</span><span><?= View::e((string) ($usuario['rol'] ?? '')) ?></span></div>
    <div class="dato"><span>Correo</span><span class="mono"><?= View::e((string) ($usuario['email'] ?? '')) ?></span></div>
    <div class="dato"><span>ID</span><span class="mono"><?= View::e((string) ($usuario['id'] ?? '')) ?></span></div>

    <form method="post" action="/logout" style="margin-top:18px">
        <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
        <button class="boton boton--tenue" type="submit">Cerrar sesión</button>
    </form>
</div>
