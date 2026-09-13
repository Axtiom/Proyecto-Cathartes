<?php
declare(strict_types=1);

/**
 * Acceso a datos agregados de incidencias, vía PDO (datos reales de `cathartes`,
 * nunca demo). Alimenta las estadísticas públicas.
 *
 * Recicla la lógica que vivía en el viejo landing-page/app/Models/Estadisticas.php
 * (PHP-MVC del frontend), ahora del lado API como manda la letra del proyecto.
 *
 * Sin datos → arreglos vacíos (la vista muestra estado vacío, no datos falsos).
 */
class Estadisticas
{
    /** Incidencias agrupadas por tipo. @return list<array{tipo:string,cantidad:int}> */
    public static function porTipo(): array
    {
        $sql = 'SELECT tipo, COUNT(*) AS cantidad
                FROM incidencia_historial
                GROUP BY tipo
                ORDER BY cantidad DESC';
        return self::filas($sql, static function (array $f): array {
            return ['tipo' => (string) $f['tipo'], 'cantidad' => (int) $f['cantidad']];
        });
    }

    /** Incidencias por mes (YYYY-MM). @return list<array{mes:string,cantidad:int}> */
    public static function porMes(): array
    {
        $sql = "SELECT DATE_FORMAT(fecha_ing, '%Y-%m') AS mes, COUNT(*) AS cantidad
                FROM incidencia_historial
                GROUP BY mes
                ORDER BY mes";
        return self::filas($sql, static function (array $f): array {
            return ['mes' => (string) $f['mes'], 'cantidad' => (int) $f['cantidad']];
        });
    }

    /**
     * Incidencias por zona. No hay columna "zona" en el MER: se deriva de la ruta
     * que cubre el contenedor donde se reportó la incidencia (ruta.nombre).
     * @return list<array{zona:string,cantidad:int}>
     */
    public static function porZona(): array
    {
        $sql = 'SELECT r.nombre AS zona, COUNT(*) AS cantidad
                FROM incidencia_historial i
                JOIN contenedor c ON c.ID = i.ID_contenedor
                JOIN ruta       r ON r.ID = c.ID_ruta
                GROUP BY r.nombre
                ORDER BY cantidad DESC';
        return self::filas($sql, static function (array $f): array {
            return ['zona' => (string) $f['zona'], 'cantidad' => (int) $f['cantidad']];
        });
    }

    /** @param callable(array):array $map @return list<array> */
    private static function filas(string $sql, callable $map): array
    {
        $st = Database::conexion()->query($sql);
        $out = [];
        foreach ($st->fetchAll() as $fila) {
            $out[] = $map($fila);
        }
        return $out;
    }
}
