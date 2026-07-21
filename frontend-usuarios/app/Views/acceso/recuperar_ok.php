<?php /** Confirmación genérica de recuperación (no revela si el correo existe). */ ?>
<div class="tarjeta">
    <div class="marca">
        <?php View::partial('logo'); ?>
        <span class="marca__nombre">Cathartes</span>
    </div>
    <h1>Revisá tu correo</h1>
    <p class="sub">Si el correo que ingresaste está registrado, te enviamos un enlace para restablecer tu contraseña. El enlace vence en 1 hora.</p>

    <a class="boton" href="/login" style="text-decoration:none">Volver a iniciar sesión</a>
</div>
