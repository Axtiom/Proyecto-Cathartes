<?php
/**
 * Encabezado de sección reutilizable: eyebrow (etiqueta corta) + H2.
 * Uso: View::partial('section-header', ['eyebrow' => '...', 'title' => '...']);
 *
 * @var string $eyebrow
 * @var string $title
 */
?>
<header class="seccion__head">
    <span class="eyebrow"><?= View::e($eyebrow) ?></span>
    <h2 class="seccion__titulo"><?= View::e($title) ?></h2>
</header>
