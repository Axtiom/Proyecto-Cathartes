<?php
declare(strict_types=1);

/**
 * Model de Contenedores de la API de Gestión (PDO directo a `cathartes`).
 *
 * Esta API es de solo lectura pública/vecino (mapa, selector de /reportar,
 * estadísticas). El ABM completo (alta, baja, verificación de estado) vive en
 * el Contenedor del Backoffice — apps separadas, cada una con el modelo que
 * le corresponde a su tarea.
 */
class Contenedor
{
    /**
     * Contenedores activos (todo lo que no está dado de baja). Para el selector
     * de /reportar y la lista de /inicio del vecino. Incluye la ruta.
     * @return list<array>
     */
    public static function activos(): array
    {
        $sql = "SELECT c.ID AS id, c.ubicacion, c.latitud, c.longitud, c.tipo, c.estado, r.nombre AS ruta
                FROM contenedor c
                JOIN ruta r ON r.ID = c.ID_ruta
                WHERE c.estado <> 'dado de baja'
                ORDER BY c.ID";
        return self::mapear(Database::conexion()->query($sql)->fetchAll());
    }

    /** Todos los contenedores (para el mapa; incluye dados de baja). @return list<array> */
    public static function todos(): array
    {
        $sql = "SELECT c.ID AS id, c.ubicacion, c.latitud, c.longitud, c.tipo, c.estado, r.nombre AS ruta
                FROM contenedor c
                JOIN ruta r ON r.ID = c.ID_ruta
                ORDER BY c.ID";
        return self::mapear(Database::conexion()->query($sql)->fetchAll());
    }

    /** Un contenedor, en el formato liviano que usan el vecino y el mapa. */
    public static function porId(int $id): ?array
    {
        $st = Database::conexion()->prepare(
            "SELECT c.ID AS id, c.ubicacion, c.latitud, c.longitud, c.tipo, c.estado, r.nombre AS ruta
             FROM contenedor c JOIN ruta r ON r.ID = c.ID_ruta WHERE c.ID = ?"
        );
        $st->execute([$id]);
        $fila = $st->fetch();
        return $fila ? self::una($fila) : null;
    }

    /** @return list<array> */
    private static function mapear(array $filas): array
    {
        return array_map([self::class, 'una'], $filas);
    }

    private static function una(array $f): array
    {
        return [
            'id'        => (int) $f['id'],
            'ubicacion' => (string) $f['ubicacion'],
            'latitud'   => $f['latitud']  !== null ? (float) $f['latitud']  : null,
            'longitud'  => $f['longitud'] !== null ? (float) $f['longitud'] : null,
            'tipo'      => (string) $f['tipo'],
            'estado'    => (string) $f['estado'],
            'ruta'      => (string) $f['ruta'],
        ];
    }
}
