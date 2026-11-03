<?php
/**
 * Notificaciones del administrador. Espera: $admin, $activo, $items, $sinLeer, $soloNuevas, $flash, $csrf.
 * Todo texto sale escapado con e(): el mensaje lo arma el sistema pero incluye datos que cargó un vecino.
 */
$titulo = 'Notificaciones · Backoffice Cathartes';
$fh = fn($f) => date('d/m/Y H:i', strtotime((string) $f));
require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <h1>Notificaciones</h1>
        <p class="bo-sub">Nuevas incidencias por validar y alertas de instalaciones al 80 % de su capacidad o más.</p>
    </div>
    <?php if ($sinLeer > 0): ?>
        <form method="post" action="<?= e(ruta('notificaciones/leer-todas')) ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <button type="submit" class="bo-btn">Marcar todas como leídas (<?= (int) $sinLeer ?>)</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div><?php endif; ?>

<nav class="bo-tabs">
    <a class="bo-tab<?= !$soloNuevas ? ' bo-tab--activo' : '' ?>" href="<?= e(ruta('notificaciones')) ?>">Todas</a>
    <a class="bo-tab<?= $soloNuevas ? ' bo-tab--activo' : '' ?>" href="<?= e(ruta('notificaciones') . '?ver=nuevas') ?>">Sin leer <span class="bo-tab__n"><?= (int) $sinLeer ?></span></a>
</nav>

<?php if (!$items): ?>
    <div class="bo-vacio">
        <p class="bo-vacio__t"><?= $soloNuevas ? 'Todo al día' : 'Sin notificaciones' ?></p>
        <p class="bo-vacio__s"><?= $soloNuevas ? 'No tenés avisos sin leer.' : 'Cuando haya una incidencia nueva o una instalación llena, te avisamos acá.' ?></p>
    </div>
<?php else: ?>
    <div class="bo-notif">
        <?php foreach ($items as $n): ?>
            <div class="bo-notif__item<?= $n['leida'] ? '' : ' bo-notif__item--nueva' ?>">
                <div>
                    <p class="bo-notif__msg"><?= e($n['mensaje']) ?></p>
                    <span class="bo-notif__meta">
                        <span class="bo-badge bo-badge--<?= $n['tipo'] === 'instalacion llena' ? 'rol' : 'ok' ?>"><?= e($n['tipo']) ?></span>
                        · <?= e($fh($n['fecha'])) ?><?= $n['leida'] ? '' : ' · <b>sin leer</b>' ?>
                    </span>
                </div>
                <div class="bo-notif__acc">
                    <?php if ($n['id_incidencia'] !== null): ?>
                        <a class="bo-btn bo-btn--sm" href="<?= e(ruta('incidencias/ver') . '?id=' . (int) $n['id_incidencia']) ?>">Ver incidencia</a>
                    <?php elseif ($n['tipo'] === 'instalacion llena'): ?>
                        <a class="bo-btn bo-btn--sm" href="<?= e(ruta('instalaciones')) ?>">Ver instalaciones</a>
                    <?php endif; ?>
                    <?php if (!$n['leida']): ?>
                        <form method="post" action="<?= e(ruta('notificaciones/leer')) ?>">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                            <button type="submit" class="bo-btn bo-btn--sm bo-btn--tenue">Marcar leída</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
