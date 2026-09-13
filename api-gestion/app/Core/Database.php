<?php
declare(strict_types=1);

/**
 * Conexión PDO singleton a `cathartes` (verbatim del esqueleto de api-usuarios).
 *
 * El Backoffice SÍ va directo a la base con PDO (permitido por la consigna para
 * administración masiva). Credenciales por .env, charset utf8mb4, errores como
 * excepciones, filas asociativas y prepared statements reales.
 *
 * Recordá: la columna de contraseña es `contrasena` (sin ñ — ver BUG-003) y en
 * toda query PDO va con backticks.
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function conexion(): PDO
    {
        if (self::$pdo === null) {
            $host    = Config::get('db.host', '127.0.0.1');
            $nombre  = Config::get('db.name', 'cathartes');
            $charset = Config::get('db.charset', 'utf8mb4');

            $dsn = "mysql:host={$host};dbname={$nombre};charset={$charset}";

            self::$pdo = new PDO(
                $dsn,
                (string) Config::get('db.user', ''),
                (string) Config::get('db.pass', ''),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        }

        return self::$pdo;
    }
}
