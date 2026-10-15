<?php
declare(strict_types=1);

/**
 * Deja la base de PRUEBA en un estado conocido: borra todas las tablas y vuelve a
 * cargar db/BD.sql (estructura) y db/seed.sql (datos). Se ignoran las sentencias
 * a nivel de base (DROP/CREATE DATABASE, USE) para no tocar nunca `cathartes`.
 *
 * Seguridad: se niega a correr si el nombre de la base no termina en "_test".
 */
final class BaseDatosPrueba
{
    public static function conectar(): PDO
    {
        $nombre = (string) getenv('DB_NAME');
        if (!str_ends_with($nombre, '_test')) {
            throw new RuntimeException("Por seguridad, los tests solo corren contra una base *_test (DB_NAME='{$nombre}').");
        }
        $pdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST') ?: '127.0.0.1', $nombre),
            (string) getenv('DB_USER'), (string) getenv('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    }

    public static function reiniciar(): void
    {
        $pdo = self::conectar();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM) as [$tabla]) {
            $pdo->exec("DROP TABLE `{$tabla}`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $raiz = dirname(__DIR__, 2) . '/db/';
        foreach (['BD.sql', 'seed.sql'] as $archivo) {
            $sql = file_get_contents($raiz . $archivo);
            $sql = preg_replace('/^\s*(DROP DATABASE|CREATE DATABASE|USE)\b[^;]*;/mi', '', $sql);
            // Varias sentencias en una sola llamada: PDO solo avisa del error de la primera,
            // así que se recorren los resultados con nextRowset() para que un fallo en
            // cualquier sentencia del script corte el test en lugar de pasar en silencio.
            $st = $pdo->prepare($sql);
            $st->execute();
            while ($st->nextRowset()) {
            }
        }
    }
}
