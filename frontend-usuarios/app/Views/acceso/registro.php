<?php
/**
 * Registro (Crear cuenta). El registro público siempre crea un Vecino; el rol
 * no se elige. Conserva lo tipeado en error (menos las contraseñas).
 * @var string $csrf  @var array $errores  @var array $valores
 */
$errores = $errores ?? [];
$valores = $valores ?? [];
?>
<form class="tarjeta tarjeta--ancha" method="post" action="/registro">
    <div class="marca">
        <?php View::partial('logo'); ?>
        <span class="marca__nombre">Cathartes</span>
    </div>
    <h1>Crear cuenta</h1>
    <p class="sub">Registrate para reportar incidencias y seguir su resolución.</p>

    <?php if (!empty($errores['general'])): ?>
        <div class="aviso aviso--error">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            <p><?= View::e($errores['general']) ?></p>
        </div>
    <?php endif; ?>

    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

    <div class="fila-2">
        <div class="campo">
            <label for="nombre">Nombre</label>
            <div class="entrada"><input type="text" id="nombre" name="nombre" placeholder="Juan" autocomplete="given-name" value="<?= View::e($valores['nombre'] ?? '') ?>"></div>
        </div>
        <div class="campo">
            <label for="apellido">Apellido</label>
            <div class="entrada"><input type="text" id="apellido" name="apellido" placeholder="Pérez" autocomplete="family-name" value="<?= View::e($valores['apellido'] ?? '') ?>"></div>
        </div>
    </div>

    <div class="campo">
        <label for="telefono">Teléfono</label>
        <div class="entrada"><input type="tel" id="telefono" name="telefono" placeholder="099 123 456" autocomplete="tel" value="<?= View::e($valores['telefono'] ?? '') ?>"></div>
    </div>

    <div class="campo">
        <label for="email">Correo electrónico</label>
        <div class="entrada"><input type="email" id="email" name="email" placeholder="juan.perez@correo.com" autocomplete="email" value="<?= View::e($valores['email'] ?? '') ?>"></div>
    </div>

    <div class="fila-2">
        <div class="campo">
            <label for="password">Contraseña</label>
            <div class="entrada"><input type="password" id="password" name="password" placeholder="••••••••••" autocomplete="new-password"></div>
        </div>
        <div class="campo">
            <label for="password_confirm">Confirmar contraseña</label>
            <div class="entrada"><input type="password" id="password_confirm" name="password_confirm" placeholder="••••••••••" autocomplete="new-password"></div>
        </div>
    </div>

    <div class="aviso">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
        <p>Tu cuenta quedará <b>pendiente de aprobación</b> del administrador municipal antes de poder ingresar.</p>
    </div>

    <button class="boton" type="submit">Crear cuenta</button>
    <div class="alterna">¿Ya tenés cuenta? <a href="/login">Iniciá sesión</a></div>
</form>
