<?php
/**
 * Sidebar del Backoffice — partial PHP (no <template> + JS: acá renderiza el
 * servidor). Las 10 secciones están escritas a mano. Las que todavía no se
 * construyeron quedan como ítems deshabilitados, para que la estructura completa
 * se vea sin mandar a un 404. Al construir cada pantalla, se le pone el href.
 *
 * Espera: $activo (slug de la sección actual), $admin (['id','nombre']).
 */
$secciones = [
    ['slug' => 'dashboard',     'ruta' => 'dashboard',      'texto' => 'Dashboard'],
    ['slug' => 'incidencias',   'ruta' => 'incidencias',    'texto' => 'Incidencias'],
    ['slug' => 'usuarios',      'ruta' => 'usuarios',       'texto' => 'Usuarios'],
    ['slug' => 'contenedores',  'ruta' => 'contenedores',   'texto' => 'Contenedores'],
    ['slug' => 'camiones',      'ruta' => 'camiones',       'texto' => 'Camiones'],
    ['slug' => 'instalaciones', 'ruta' => 'instalaciones',  'texto' => 'Instalaciones'],
    ['slug' => 'maquinaria',    'ruta' => 'maquinaria',     'texto' => 'Maquinaria'],
    ['slug' => 'rutas',         'ruta' => 'rutas',          'texto' => 'Rutas'],
    ['slug' => 'cuadrillas',    'ruta' => 'cuadrillas',     'texto' => 'Cuadrillas'],
    ['slug' => 'informes',      'ruta' => 'informes',       'texto' => 'Informes'],
];
$admin = $admin ?? ['nombre' => ''];
?>
<aside class="bo-side">
    <div class="bo-side__marca">
        <span class="bo-side__logo" aria-hidden="true">
            <!-- Logo Cathartes. Mismo SVG que la landing y el frontend de usuarios
                 (fuente única de marca): el ala izquierda es la derecha espejada con
                 translate(96,0) scale(-1,1), y las cuatro circunferencias de abajo son
                 las ruedas del camión. -->
            <svg viewBox="0 0 96 96" width="24" height="24" aria-hidden="true">
                <circle cx="48" cy="48" r="45" fill="none" stroke="#2E4A54" stroke-width="2"/>
                <circle cx="48" cy="48" r="38.5" fill="none" stroke="#14B8A6" stroke-width="1.4" opacity="0.55"/>
                <g fill="#2FBF71">
                    <path d="M48 31 C 60 24 72 23 84 28 C 79 29 81 31 77 31 C 74 31 75 33 71 33 C 68 33 61 32 50 35 Z" transform="translate(96,0) scale(-1,1)"/>
                    <path d="M48 31 C 60 24 72 23 84 28 C 79 29 81 31 77 31 C 74 31 75 33 71 33 C 68 33 61 32 50 35 Z"/>
                    <path d="M48 20 C 51.5 27 52.5 30 51.5 34 C 51 39 50 43 48 47 C 46 43 45 39 44.5 34 C 43.5 30 44.5 27 48 20 Z"/>
                    <path d="M27 66 L27 57.5 Q27 56 28.5 56 L37 56 L40 50.5 Q41 49 42.5 49 L67 49 Q68.5 49 68.5 50.5 L68.5 66 Q68.5 67.5 67 67.5 L28.5 67.5 Q27 67.5 27 66 Z"/>
                </g>
                <circle cx="40" cy="68" r="4.3" fill="#2FBF71"/>
                <circle cx="40" cy="68" r="1.9" fill="#0B1620"/>
                <circle cx="60" cy="68" r="4.3" fill="#2FBF71"/>
                <circle cx="60" cy="68" r="1.9" fill="#0B1620"/>
            </svg>
        </span>
        <div>
            <strong>Cathartes</strong>
            <span class="bo-side__sub">Backoffice</span>
        </div>
    </div>

    <nav class="bo-nav">
        <?php foreach ($secciones as $s): ?>
            <?php if ($s['ruta'] === null): ?>
                <span class="bo-nav__item bo-nav__item--off" title="Próximamente"><?= e($s['texto']) ?></span>
            <?php else: ?>
                <a class="bo-nav__item<?= $activo === $s['slug'] ? ' bo-nav__item--activo' : '' ?>"
                   href="<?= e(ruta($s['ruta'])) ?>"><?= e($s['texto']) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="bo-side__pie">
        <div class="bo-side__admin">
            <span class="bo-side__avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($admin['nombre'] ?? '?', 0, 1))) ?></span>
            <div>
                <strong><?= e($admin['nombre'] ?? '') ?></strong>
                <span class="bo-side__sub">Administrador</span>
            </div>
        </div>
        <form method="post" action="<?= e(ruta('logout')) ?>">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
            <button type="submit" class="bo-side__salir">Cerrar sesión</button>
        </form>
    </div>
</aside>
