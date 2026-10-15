<?php
/**
 * Editar máquina. Espera: $admin, $activo, $m, $instalaciones, $flash, $csrf.
 */
$titulo = 'Máquina #' . (int) $m['ID'] . ' · Backoffice';
require BASE_PATH . '/app/Views/partials/head.php';
?>

<div class="bo-encab">
    <div>
        <a class="bo-btn bo-btn--sm bo-btn--tenue" href="<?= e(ruta('maquinaria')) ?>">← Volver a maquinaria</a>
        <h1 style="margin-top:8px">Máquina #<?= (int) $m['ID'] ?> · <?= e($m['modelo']) ?></h1>
    </div>
</div>

<?php if ($flash): ?><div class="bo-aviso bo-aviso--<?= e($flash['tipo']) ?>"><?= e($flash['texto']) ?></div><?php endif; ?>

<div class="bo-detalle">
    <div class="bo-panel">
        <h2 class="bo-panel__tit">Datos</h2>
        <form method="post" action="<?= e(ruta('maquinaria/actualizar')) ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $m['ID'] ?>">
            <label class="bo-campo"><span>Modelo</span><input type="text" name="modelo" maxlength="100" value="<?= e($m['modelo']) ?>" required></label>
            <label class="bo-campo"><span>Tipo</span><input type="text" name="tipo" maxlength="50" value="<?= e($m['tipo']) ?>" required></label>
            <div class="bo-alta__fila">
                <label class="bo-campo"><span>Instalación</span>
                    <select name="id_instalacion">
                        <?php foreach ($instalaciones as $i): ?>
                            <option value="<?= (int) $i['ID'] ?>"<?= (int) $m['ID_instalacion'] === (int) $i['ID'] ? ' selected' : '' ?>><?= e($i['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="bo-campo"><span>Estado</span>
                    <select name="estado">
                        <?php foreach (Maquinaria::ESTADOS as $es): ?>
                            <option value="<?= e($es) ?>"<?= $m['estado'] === $es ? ' selected' : '' ?>><?= e(ucfirst($es)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <button type="submit" class="bo-btn bo-btn--pri" style="margin-top:10px">Guardar cambios</button>
        </form>
    </div>

    <div class="bo-panel">
        <h2 class="bo-panel__tit">Eliminar máquina</h2>
        <p class="bo-sub" style="font-size:13px;margin-bottom:12px">
            Se borra definitivamente. Ninguna otra tabla depende de la maquinaria, así que no deja
            datos huérfanos. Si solo está fuera de uso, conviene dejarla como «no funcional» o «en mantenimiento».
        </p>
        <form method="post" action="<?= e(ruta('maquinaria/eliminar')) ?>"
              onsubmit="return confirm('¿Eliminar definitivamente la máquina #<?= (int) $m['ID'] ?>?');">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $m['ID'] ?>">
            <button type="submit" class="bo-btn bo-btn--peligro">Eliminar</button>
        </form>
    </div>
</div>

<?php require BASE_PATH . '/app/Views/partials/foot.php'; ?>
