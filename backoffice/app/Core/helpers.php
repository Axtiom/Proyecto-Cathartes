<?php
declare(strict_types=1);

/**
 * Helpers globales, disponibles en Controllers y Vistas.
 *  - app_base(): prefijo del subdirectorio donde vive el front controller
 *    (deducido de SCRIPT_NAME, nada hardcodeado), para que los enlaces sirvan
 *    igual en subcarpeta (Apache local) o en la raíz de un vhost (Rocky).
 *  - ruta(): arma una URL absoluta de la app a partir de ese prefijo.
 *  - e(): atajo de htmlspecialchars — TODO dato de la base se imprime con esto.
 */

function app_base(): string
{
    $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    return ($base === '/' || $base === '.' || $base === '') ? '' : $base;
}

function ruta(string $p = ''): string
{
    return app_base() . '/' . ltrim($p, '/');
}

function e(mixed $s): string
{
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES, 'UTF-8');
}
