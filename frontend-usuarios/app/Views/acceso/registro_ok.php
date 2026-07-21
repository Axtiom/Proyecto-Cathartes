<?php /** Confirmación de registro. */ ?>
<div class="tarjeta">
    <div class="marca">
        <?php View::partial('logo'); ?>
        <span class="marca__nombre">Cathartes</span>
    </div>
    <h1>¡Cuenta creada!</h1>
    <p class="sub">Tu cuenta quedó <b>pendiente de aprobación</b> del administrador municipal. Vas a poder ingresar una vez que la aprueben.</p>

    <div class="aviso">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
        <p>Te avisaremos por correo cuando tu cuenta esté activa.</p>
    </div>

    <a class="boton" href="/login" style="text-decoration:none">Ir a iniciar sesión</a>
</div>
