<?php
declare(strict_types=1);

/** Config por notación de puntos (verbatim del esqueleto de api-usuarios). */
class Config
{
    private static array $datos = [];

    public static function cargar(array $datos): void
    {
        self::$datos = $datos;
    }

    public static function get(string $clave, mixed $default = null): mixed
    {
        $valor = self::$datos;
        foreach (explode('.', $clave) as $parte) {
            if (!is_array($valor) || !array_key_exists($parte, $valor)) {
                return $default;
            }
            $valor = $valor[$parte];
        }
        return $valor;
    }
}
