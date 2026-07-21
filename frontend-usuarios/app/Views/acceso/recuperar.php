<?php
/**
 * Recuperar contraseña. La respuesta es siempre genérica (no revela si el
 * correo existe): el POST redirige a la vista recuperar_ok.
 * @var string $csrf  @var array $errores  @var array $valores
 */
$errores = $errores ?? [];
$valores = $valores ?? [];
?>
<a class="volver" href="/login">
    <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>Volver a iniciar sesión
</a>
<form class="tarjeta" method="post" action="/recuperar">
    <div class="marca">
        <?php View::partial('logo'); ?>
        <span class="marca__nombre">Cathartes</span>
    </div>
    <h1>Recuperar contraseña</h1>
    <p class="sub">Ingresá tu correo y te enviaremos un enlace para restablecer tu contraseña.</p>

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

    <button class="boton" type="submit" style="margin-top:6px">Enviar enlace de recuperación</button>
    <div class="alterna">¿Te acordaste? <a href="/login">Volver a iniciar sesión</a></div>
</form>
