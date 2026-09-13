<?php
declare(strict_types=1);

/**
 * Loader mínimo de .env (verbatim del esqueleto PHP-MVC de api-usuarios).
 *
 * Parsea el archivo `.env` de la app (CLAVE=valor por línea) y lo expone
 * vía Env::get(). El config/config.php arma su arreglo leyendo de acá, así
 * que el salto local -> producción es solo cambiar el .env (nada hardcodeado).
 */
class Env
{
    private static array $datos = [];
    private static bool $cargado = false;

    public static function cargar(string $archivo): void
    {
        self::$cargado = true;

        if (!is_file($archivo)) {
            return; // sin .env: se cae a los defaults de config.php
        }

        foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            $linea = trim($linea);
            if ($linea === '' || $linea[0] === '#') {
                continue;
            }
            if (!str_contains($linea, '=')) {
                continue;
            }
            [$clave, $valor] = explode('=', $linea, 2);
            $clave = trim($clave);
            $valor = trim($valor);

            if (strlen($valor) >= 2) {
                $ini = $valor[0];
                $fin = $valor[strlen($valor) - 1];
                if (($ini === '"' && $fin === '"') || ($ini === "'" && $fin === "'")) {
                    $valor = substr($valor, 1, -1);
                }
            }

            self::$datos[$clave] = $valor;
        }
    }

    public static function get(string $clave, mixed $default = null): mixed
    {
        return self::$datos[$clave] ?? $default;
    }
}
