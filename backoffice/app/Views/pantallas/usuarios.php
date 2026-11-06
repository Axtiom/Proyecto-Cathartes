<?php
/**
 * Pantalla 3 — Usuarios. Server-rendered: toda la estructura y el texto fijo
 * están acá; el Controller solo pasó los datos. Dato de la base impreso con e()
 * (htmlspecialchars). Sin JavaScript.
 *
 * Espera: $admin, $activo, $pestana, $rol, $q, $datos (items,total,pagina,
 * paginas,porPagina), $conteos, $flash, $csrf.
 */
$titulo = 'Usuarios · Backoffice Cathartes';

// Helper local: arma un query string preservando los filtros actuales, con
// overrides. Se usa en las solapas, la paginación y el "volver" de las acciones.
$mkq = function (array $ov = []) use ($pestana, $rol, $q, $datos) {
    $p = array_merge(['pestana' => $pestana, 'rol' => $rol, 'q' => $q, 'pagina' => $datos['pagina']], $ov);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($p);
};
$volver = $mkq();

$solapas = [
    'pendientes' => 'Pendientes',
    'activos'    => 'Activos',
    'baja'       => 'Dados de baja',
    'todos'      => 'Todos',
];
$roles = ['administrador' => 'Administrador', 'operario' => 'Operario', 'cuadrilla' => 'Cuadrilla', 'vecino' => 'Vecino'];

// RN4: dar de baja a un administrador es potestad exclusiva del administrador
// jefe. Si el admin de sesión no lo es, la fila de otro administrador se muestra
// con el botón inerte + aviso, en vez de mandar el POST y comerse un 403.
$bloqueadoRN4 = fn(array $u) => $u['rol'] === 'administrador' && !$esJefe;

require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <h1>Usuarios</h1>
        <p class="bo-sub">Aprobá o rechazá solicitudes y gestioná las cuentas del sistema.</p>
    </div>
    <details class="bo-alta" id="bo-alta">
        <summary class="bo-btn bo-btn--pri">＋ Alta manual</summary>
        <div class="bo-alta__modal">
            <form method="post" action="<?= e(ruta('usuarios/crear')) ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="volver" value="<?= e($volver) ?>">
                <h2 class="bo-alta__tit">Alta de usuario</h2>

                <label class="bo-campo"><span>Rol</span>
                    <select name="rol" id="bo-rol">
                        <option value="vecino">Vecino</option>
                        <option value="operario">Operario</option>
                        <option value="cuadrilla">Cuadrilla</option>
                        <?php if ($esJefe): ?><option value="administrador">Administrador</option><?php endif; ?>
                    </select>
                </label>

                <div class="bo-alta__fila">
                    <label class="bo-campo"><span>Nombre</span><input type="text" name="nombre" required></label>
                    <label class="bo-campo"><span>Apellido</span><input type="text" name="apellido"></label>
                </div>
                <div class="bo-alta__fila">
                    <label class="bo-campo"><span>Correo electrónico</span><input type="email" name="email" required></label>
                    <label class="bo-campo"><span>Teléfono</span><input type="text" name="telefono" placeholder="09X XXX XXX" required></label>
                </div>
                <label class="bo-campo"><span>Contraseña (mín. 8)</span><input type="password" name="password" required></label>

                <!-- Campos por subtipo. Cada bloque arranca oculto y deshabilitado: el JS
                     habilita solo el del rol elegido, así un campo que no corresponde ni
                     siquiera se manda. Igual el server lo rechaza si llega (BUG-010). -->
                <div class="bo-campo" data-rol="operario" hidden>
                    <span>Instalación <em>(obligatoria)</em></span>
                    <select name="id_instalacion" disabled>
                        <option value="">Elegí una instalación…</option>
                        <?php foreach ($instalaciones as $i): ?>
                            <option value="<?= (int) $i['ID'] ?>"><?= e($i['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bo-campo" data-rol="cuadrilla" hidden>
                    <span>Camión <em>(opcional)</em></span>
                    <select name="id_camion" disabled>
                        <option value="">Sin camión (asignar después)</option>
                        <?php foreach ($camiones as $c): ?>
                            <option value="<?= (int) $c['ID'] ?>"><?= e($c['matricula'] . ' · ' . $c['modelo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$camiones): ?><small class="bo-alta__hint">No hay camiones libres; se asigna después desde Cuadrillas.</small><?php endif; ?>
                </div>
                <?php if ($esJefe): ?>
                <label class="bo-campo" data-rol="administrador" hidden>
                    <span>¿Jefe?</span>
                    <select name="jefe" disabled>
                        <option value="no">No</option>
                        <option value="si">Sí (puede gestionar administradores)</option>
                    </select>
                </label>
                <?php endif; ?>

                <p class="bo-alta__nota">Nombre y apellido se guardan juntos en <code>usuario.nombre</code>. El usuario entra <strong>activo</strong>.</p>
                <div class="bo-alta__acc">
                    <button type="submit" class="bo-btn bo-btn--pri">Crear usuario</button>
                    <button type="button" class="bo-btn" onclick="document.getElementById('bo-alta').removeAttribute('open')">Cancelar</button>
                </div>
            </form>
        </div>
    </details>
</div>

<?php if ($flash): ?>
    <div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div>
<?php endif; ?>

<!-- Solapas -->
<nav class="bo-tabs">
    <?php foreach ($solapas as $slug => $texto): ?>
        <a class="bo-tab<?= $pestana === $slug ? ' bo-tab--activo' : '' ?>"
           href="<?= e(ruta('usuarios') . $mkq(['pestana' => $slug, 'pagina' => 1])) ?>">
            <?= e($texto) ?> <span class="bo-tab__n"><?= (int) ($conteos[$slug] ?? 0) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<!-- Filtros (GET) -->
<form class="bo-filtros" method="get" action="<?= e(ruta('usuarios')) ?>">
    <input type="hidden" name="pestana" value="<?= e($pestana) ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre o email…">
    <select name="rol">
        <option value="">Todos los roles</option>
        <?php foreach ($roles as $slug => $texto): ?>
            <option value="<?= e($slug) ?>"<?= $rol === $slug ? ' selected' : '' ?>><?= e($texto) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="bo-btn">Filtrar</button>
    <?php if ($q !== '' || $rol !== ''): ?>
        <a class="bo-btn bo-btn--tenue" href="<?= e(ruta('usuarios') . '?pestana=' . e($pestana)) ?>">Limpiar</a>
    <?php endif; ?>
</form>

<?php if (!$datos['items']): ?>
    <div class="bo-vacio">
        <p class="bo-vacio__t">Sin usuarios</p>
        <p class="bo-vacio__s">No hay usuarios que coincidan con esta vista.</p>
    </div>
<?php else: ?>
    <div class="bo-tabla-wrap">
        <table class="bo-tabla">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Estado de cuenta</th>
                    <th>Estado operativo</th>
                    <th class="bo-tabla__acc">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($datos['items'] as $u): ?>
                    <?php
                    $esActivo = $u['estado'] === 'activo';
                    $esYo     = (int) $u['ID'] === (int) $admin['id'];
                    $badgeCuenta = $esActivo ? 'ok' : 'baja';
                    ?>
                    <tr>
                        <td class="bo-mono">#<?= (int) $u['ID'] ?></td>
                        <td><?= e($u['nombre']) ?></td>
                        <td class="bo-mono"><?= e($u['email']) ?></td>
                        <td><span class="bo-badge bo-badge--rol"><?= e($u['rol']) ?></span></td>
                        <td><span class="bo-badge bo-badge--<?= $badgeCuenta ?>"><?= e($u['estado']) ?></span></td>
                        <td>
                            <?php if ($u['rol'] === 'cuadrilla' && $u['estado_operativo'] !== null): ?>
                                <span class="bo-badge bo-badge--<?= $u['estado_operativo'] === 'en servicio' ? 'ok' : 'baja' ?>"><?= e($u['estado_operativo']) ?></span>
                            <?php else: ?>
                                <span class="bo-dim">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="bo-tabla__acc">
                            <a class="bo-btn bo-btn--sm" href="<?= e(ruta('usuarios/editar?id=' . (int) $u['ID'])) ?>"><?= $esYo ? 'Mi cuenta' : 'Editar' ?></a>
                            <?php if ($esYo): ?>
                                <span class="bo-dim">tu cuenta</span>
                            <?php else: ?>
                                <?php if (!$esActivo): ?>
                                    <form method="post" action="<?= e(ruta('usuarios/aprobar')) ?>" class="bo-inline">
                                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $u['ID'] ?>">
                                        <input type="hidden" name="volver" value="<?= e($volver) ?>">
                                        <button type="submit" class="bo-btn bo-btn--ok bo-btn--sm">Aprobar</button>
                                    </form>
                                <?php endif; ?>
                                <?php $texto = $esActivo ? 'Dar de baja' : 'Rechazar'; ?>
                                <?php if ($bloqueadoRN4($u)): ?>
                                    <!-- RN4: este admin no es jefe, así que no puede dar de baja a otro
                                         administrador. El botón no manda nada y avisa en el momento; el
                                         403 del servidor sigue estando por si alguien fuerza el POST. -->
                                    <button type="button" class="bo-btn bo-btn--sm bo-btn--peligro bo-btn--bloqueado"
                                            data-aviso="Solo un administrador jefe puede dar de baja a otro administrador (RN4).">
                                        <?= e($texto) ?>
                                    </button>
                                <?php else: ?>
                                    <form method="post" action="<?= e(ruta('usuarios/rechazar')) ?>" class="bo-inline">
                                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $u['ID'] ?>">
                                        <input type="hidden" name="volver" value="<?= e($volver) ?>">
                                        <button type="submit" class="bo-btn bo-btn--peligro bo-btn--sm"><?= e($texto) ?></button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <div class="bo-pag">
        <span><?= (int) $datos['total'] ?> usuarios · página <?= (int) $datos['pagina'] ?> de <?= (int) $datos['paginas'] ?></span>
        <div class="bo-pag__btns">
            <?php if ($datos['pagina'] > 1): ?>
                <a class="bo-btn bo-btn--sm" href="<?= e(ruta('usuarios') . $mkq(['pagina' => $datos['pagina'] - 1])) ?>">Anterior</a>
            <?php else: ?>
                <span class="bo-btn bo-btn--sm bo-btn--off">Anterior</span>
            <?php endif; ?>
            <?php if ($datos['pagina'] < $datos['paginas']): ?>
                <a class="bo-btn bo-btn--sm" href="<?= e(ruta('usuarios') . $mkq(['pagina' => $datos['pagina'] + 1])) ?>">Siguiente</a>
            <?php else: ?>
                <span class="bo-btn bo-btn--sm bo-btn--off">Siguiente</span>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
// Muestra/oculta los campos por subtipo según el rol elegido (solo UX; el server
// valida igual). Toda la estructura está en el HTML; el JS solo togglea `hidden`
// y `disabled` — un control deshabilitado no se manda en el POST, así el campo de
// otro rol no viaja ni por accidente.
(function () {
    var sel = document.getElementById('bo-rol');
    if (!sel) return;
    function sync() {
        var r = sel.value;
        document.querySelectorAll('#bo-alta [data-rol]').forEach(function (el) {
            var suyo = el.getAttribute('data-rol') === r;
            el.hidden = !suyo;
            el.querySelectorAll('select, input, textarea').forEach(function (c) {
                c.disabled = !suyo;
            });
        });
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
