<?php
/**
 * Iniciar sesión. El checkbox "recordar" decide la duración de la sesión.
 * @var string $csrf  @var array $errores  @var array $valores  @var ?string $flash
 */
$errores = $errores ?? [];
$valores = $valores ?? [];
?>
<form class="tarjeta" method="post" action="/login">
    <div class="marca">
        <?php View::partial('logo'); ?>
        <span class="marca__nombre">Cathartes</span>
    </div>
    <h1>Iniciar sesión</h1>
    <p class="sub">Ingresá con tu cuenta para acceder al sistema.</p>

    <?php if (!empty($flash)): ?>
        <div class="aviso aviso--dev"><p><?= View::e($flash) ?></p></div>
    <?php endif; ?>
    <?php if (!empty($errores['general'])): ?>
        <div class="aviso aviso--error">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            <p><?= View::e($errores['general']) ?></p>
        </div>
    <?php endif; ?>

    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

    <div class="campo">
        <label for="email">Correo electrónico</label>
        <div class="entrada">
            <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg>
            <input type="email" id="email" name="email" placeholder="juan.perez@correo.com" autocomplete="email" value="<?= View::e($valores['email'] ?? '') ?>">
        </div>
    </div>

    <div class="campo">
        <label for="password">Contraseña</label>
        <div class="entrada">
            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" id="password" name="password" placeholder="••••••••••" autocomplete="current-password">
        </div>
    </div>

    <div class="fila-entre">
        <label class="recordar"><input type="checkbox" name="recordar" value="1"> Recordarme</label>
        <a class="enlace" href="/recuperar">¿Olvidaste tu contraseña?</a>
    </div>

    <button class="boton" type="submit">Ingresar</button>

    <div class="aviso" style="margin-top:20px;margin-bottom:0">
        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <p>Por seguridad, te pediremos un código de verificación en el siguiente paso.</p>
    </div>

    <div class="alterna">¿No tenés cuenta? <a href="/registro">Registrate</a></div>
</form>
