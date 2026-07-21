<?php
/**
 * Restablecer contraseña. Recibe el token por la query (GET) y lo lleva en un
 * campo oculto al POST. La validación real (token vigente, largo) la hace la API.
 * @var string $csrf  @var array $errores  @var array $valores
 */
$errores = $errores ?? [];
$valores = $valores ?? [];
?>
<a class="volver" href="/login">
    <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>Volver a iniciar sesión
</a>
<form class="tarjeta" method="post" action="/restablecer">
    <div class="marca">
        <?php View::partial('logo'); ?>
        <span class="marca__nombre">Cathartes</span>
    </div>
    <h1>Elegí una nueva contraseña</h1>
    <p class="sub">Ingresá tu nueva contraseña dos veces para confirmarla.</p>

    <?php if (!empty($errores['general'])): ?>
        <div class="aviso aviso--error">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            <p><?= View::e($errores['general']) ?></p>
        </div>
    <?php endif; ?>

    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <input type="hidden" name="token" value="<?= View::e($valores['token'] ?? '') ?>">

    <div class="campo">
        <label for="password">Nueva contraseña</label>
        <div class="entrada">
            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" id="password" name="password" placeholder="••••••••••" autocomplete="new-password">
        </div>
    </div>

    <div class="campo">
        <label for="password_confirm">Confirmar contraseña</label>
        <div class="entrada">
            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" id="password_confirm" name="password_confirm" placeholder="••••••••••" autocomplete="new-password">
        </div>
    </div>

    <button class="boton" type="submit" style="margin-top:6px">Guardar contraseña</button>
    <div class="alterna">¿Ya la recordás? <a href="/login">Iniciá sesión</a></div>
</form>
