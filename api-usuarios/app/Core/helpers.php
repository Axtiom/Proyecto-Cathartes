<?php
declare(strict_types=1);

/**
 * Helpers globales, disponibles en Controllers y Vistas.
 *  - app_base(): prefijo del subdirectorio donde vive el front controller
 *    (deducido de SCRIPT_NAME, nada hardcodeado), para que los enlaces sirvan
 *    igual en subcarpeta (Apache local) o en la raíz de un vhost (Rocky).
 *  - ruta(): arma una URL absoluta de la app a partir de ese prefijo.
 *  - e(): atajo de htmlspecialchars — TODO dato de la base se imprime con esto.
 *  - entero_en_rango(): valida un entero contra el rango real de la columna.
 */

/**
 * Máximo de un INT con signo en MySQL. Los INT del DDL (capacidades, valores en
 * puntos, ocupaciones) no aceptan más que esto: pasarse tira el error 1264
 * «Out of range value» y, sin validar antes, eso termina en un 500.
 * Ver BUG-013.
 */
const MYSQL_INT_MAX = 2147483647;

function app_base(): string
{
    $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    return ($base === '/' || $base === '.' || $base === '') ? '' : $base;
}

function ruta(string $p = ''): string
{
    return app_base() . '/' . ltrim($p, '/');
}

/**
 * URL a una carpeta hermana del proyecto (frontend-usuarios, landing-page).
 * Esta app vive en su propia carpeta con un `public/` adentro; sus hermanas
 * (las otras 3 apps y los 2 frontends) están un nivel arriba. Se calcula en
 * base a app_base() (que ya sale de
 * SCRIPT_NAME): nada de host ni carpeta hardcodeados, sirve igual si el
 * proyecto entero cuelga de una subcarpeta o de la raíz del vhost.
 */
function hermana(string $carpeta, string $resto = ''): string
{
    $raiz  = dirname(dirname(app_base())); // .../<esta-app>/public -> raíz del proyecto
    $resto = $resto !== '' ? '/' . ltrim($resto, '/') : '';
    return rtrim($raiz, '/') . '/' . trim($carpeta, '/') . $resto;
}

function e(mixed $s): string
{
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * ¿El valor es un entero dentro del rango que la columna soporta?
 *
 * Ojo con el cast: `(int) '99999999999'` en PHP de 64 bits NO desborda (da
 * 99999999999), así que el número llega intacto a MySQL, que sí lo rechaza. Hay
 * que comparar contra el límite de la COLUMNA, no confiar en el cast.
 */
function entero_en_rango(mixed $v, int $min = 0, int $max = MYSQL_INT_MAX): bool
{
    $s = trim((string) $v);
    if ($s === '' || !preg_match('/^-?\d+$/', $s)) return false;
    // Comparación como string cuando el número excede el int de PHP: bccomp no
    // está garantizado, pero un número de más dígitos que el máximo ya se pasa.
    $neg = $s[0] === '-';
    $abs = ltrim($neg ? substr($s, 1) : $s, '0');
    if (strlen($abs) > 19) return false; // fuera del int de PHP, seguro fuera de rango
    $n = (int) $s;
    return $n >= $min && $n <= $max;
}

/**
 * Duración en minutos a texto legible: «1 d 4 h», «4 h 30 min», «12 min».
 * Devuelve '—' si no hay dato (null): en los informes eso significa «no hay casos
 * finalizados para promediar», que no es lo mismo que cero.
 */
function duracion_humana(?int $minutos): string
{
    if ($minutos === null) return '—';
    if ($minutos < 1) return 'menos de 1 min';

    $d = intdiv($minutos, 1440);
    $h = intdiv($minutos % 1440, 60);
    $m = $minutos % 60;

    if ($d > 0) return $h > 0 ? "{$d} d {$h} h" : "{$d} d";
    if ($h > 0) return $m > 0 ? "{$h} h {$m} min" : "{$h} h";
    return "{$m} min";
}

/**
 * ¿Coordenada válida para una columna DECIMAL(9,6)? Vacía es válida (queda NULL).
 * Si viene, tiene que ser numérica y caer en el rango geográfico: una latitud de
 * 99999 no solo no existe, además desborda la columna (DECIMAL(9,6) llega hasta
 * 999.999999) y eso terminaba en un 500. Ver BUG-013.
 */
function coordenada_valida(mixed $v, float $limite): bool
{
    $s = trim((string) $v);
    if ($s === '') return true;
    if (!is_numeric($s)) return false;
    $n = (float) $s;
    return $n >= -$limite && $n <= $limite;
}
