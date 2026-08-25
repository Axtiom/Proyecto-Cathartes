<?php
declare(strict_types=1);

/**
 * Acceso a datos de recolección (rutas tomadas por las cuadrillas), vía PDO.
 * Datos reales de `cathartes`, nunca demo. Sin datos → arreglo vacío.
 */
class Recoleccion
{
    /**
     * Rutas que toma una cuadrilla (tabla `toma`), con la instalación de destino.
     * @return list<array{id:int,nombre:string,frecuencia:string,instalacion:string,fecha:string}>
     */
    public static function rutasDeCuadrilla(int $idCuadrilla): array
    {
        $sql = 'SELECT r.ID AS id, r.nombre, r.frecuencia, ins.nombre AS instalacion, t.fecha
                FROM toma t
                JOIN ruta        r   ON r.ID  = t.ID_ruta
                JOIN instalacion ins ON ins.ID = r.ID_instalacion
                WHERE t.ID_cuadrilla = ?
                ORDER BY t.fecha DESC';
        $st = Database::conexion()->prepare($sql);
        $st->execute([$idCuadrilla]);

        $out = [];
        foreach ($st->fetchAll() as $f) {
            $out[] = [
                'id'          => (int) $f['id'],
                'nombre'      => (string) $f['nombre'],
                'frecuencia'  => (string) $f['frecuencia'],
                'instalacion' => (string) $f['instalacion'],
                'fecha'       => (string) $f['fecha'],
            ];
        }
        return $out;
    }
}
