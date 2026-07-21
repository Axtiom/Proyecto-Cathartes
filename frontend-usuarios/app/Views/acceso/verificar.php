<?php
/**
 * Verificación en dos pasos (2FA). 6 recuadros reales sincronizados en el campo
 * oculto name="codigo". El `preauth` viaja server-side en la sesión del frontend.
 * @var string $csrf  @var array $errores  @var ?string $flash
 */
$errores = $errores ?? [];
?>
<a class="volver" href="/login">
    <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>Volver
</a>
<form class="tarjeta" method="post" action="/verificar">
    <div class="marca">
        <?php View::partial('logo'); ?>
        <span class="marca__nombre">Cathartes</span>
    </div>
    <h1>Verificación en dos pasos</h1>
    <p class="sub">Ingresá el código de 6 dígitos que te enviamos por correo.</p>

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

    <div class="otp" role="group" aria-label="Código de verificación de 6 dígitos">
        <input class="otp__caja" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" aria-label="Dígito 1">
        <input class="otp__caja" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="Dígito 2">
        <input class="otp__caja" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="Dígito 3">
        <input class="otp__caja" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="Dígito 4">
        <input class="otp__caja" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="Dígito 5">
        <input class="otp__caja" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="Dígito 6">
    </div>
    <input type="hidden" name="codigo" id="codigo">

    <button class="boton" type="submit">Verificar</button>
    <div class="resend">¿No te llegó? <a href="/login">Volver a intentar</a></div>
</form>

<script>
(function () {
    var cajas  = Array.prototype.slice.call(document.querySelectorAll('.otp__caja'));
    var oculto = document.getElementById('codigo');
    if (!cajas.length || !oculto) return;

    function sincronizar() {
        oculto.value = cajas.map(function (c) { return c.value; }).join('');
        cajas.forEach(function (c) {
            c.classList.toggle('otp__caja--lleno', c.value !== '');
        });
    }

    cajas.forEach(function (caja, i) {
        caja.addEventListener('input', function () {
            caja.value = caja.value.replace(/\D/g, '').slice(0, 1);
            if (caja.value && i < cajas.length - 1) cajas[i + 1].focus();
            sincronizar();
        });
        caja.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !caja.value && i > 0) cajas[i - 1].focus();
            else if (e.key === 'ArrowLeft' && i > 0) cajas[i - 1].focus();
            else if (e.key === 'ArrowRight' && i < cajas.length - 1) cajas[i + 1].focus();
        });
        caja.addEventListener('paste', function (e) {
            e.preventDefault();
            var texto = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
            for (var j = 0; j < cajas.length; j++) cajas[j].value = texto[j] || '';
            cajas[Math.min(texto.length, cajas.length - 1)].focus();
            sincronizar();
        });
    });

    cajas[0].focus();
})();
</script>
