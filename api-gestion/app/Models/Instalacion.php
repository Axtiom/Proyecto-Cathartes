<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Notificacion.php';

/**
 * Model de Instalaciones (vertederos y centros de acopio) de la API de Gestión.
 *
 * `instalacion` no tiene columna de estado (el MER está congelado), así que no
 * hay baja lógica. El borrado físico solo se permite si NO hay nada colgando
 * de la instalación (rutas, operarios, maquinaria): es lo que ya imponen las FK
 * con ON DELETE RESTRICT; acá se anticipa para devolver un 409 claro y no un 500.
 * `capacidad` y `ocupacion_actual` están en m³.
 */
class Instalacion
{
    public const TIPOS  = ['vertedero', 'centro de acopio'];
    public const UMBRAL = 80; // % de ocupación a partir del cual se alerta

    /** @return list<array> */
    public static function listar(?string $tipo = null, ?string $q = null): array
    {
        $where = []; $p = [];
        if ($tipo !== null && in_array($tipo, self::TIPOS, true)) { $where[] = 'i.tipo = ?'; $p[] = $tipo; }
        $q = trim((string) $q);
        if ($q !== '') { $where[] = '(i.nombre LIKE ? OR i.ubicacion LIKE ?)'; $p[] = "%$q%"; $p[] = "%$q%"; }
        $sqlW = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $st = Database::conexion()->prepare("SELECT * FROM instalacion i {$sqlW} ORDER BY i.nombre");
        $st->execute($p);
        return array_map([self::class, 'una'], $st->fetchAll());
    }

    public static function porId(int $id): ?array
    {
        $st = Database::conexion()->prepare('SELECT * FROM instalacion WHERE ID = ?');
        $st->execute([$id]);
        $f = $st->fetch();
        return $f ? self::una($f) : null;
    }

    /** Cantidad de rutas, operarios y maquinaria que dependen de la instalación. */
    public static function dependencias(int $id): array
    {
        $pdo = Database::conexion();
        $out = [];
        foreach (['rutas' => 'ruta', 'operarios' => 'operario', 'maquinaria' => 'maquinaria'] as $clave => $tabla) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM {$tabla} WHERE ID_instalacion = ?");
            $st->execute([$id]);
            $out[$clave] = (int) $st->fetchColumn();
        }
        return $out;
    }

    /** @return array{ok:bool,error?:string,id?:int} */
    public static function crear(array $d): array
    {
        $v = self::validar($d);
        if ($v !== null) return ['ok' => false, 'error' => $v];
        $pdo = Database::conexion();
        $pdo->prepare(
            'INSERT INTO instalacion (nombre, ubicacion, latitud, longitud, capacidad, ocupacion_actual, tipo)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            trim((string) $d['nombre']), trim((string) $d['ubicacion']),
            self::coord($d['latitud'] ?? ''), self::coord($d['longitud'] ?? ''),
            (int) $d['capacidad'], (int) ($d['ocupacion_actual'] ?? 0), $d['tipo'],
        ]);
        $nueva = (int) $pdo->lastInsertId();
        Notificacion::revisarUmbral($nueva, 0);   // una instalación que nace por encima del 80 % también alerta
        return ['ok' => true, 'id' => $nueva];
    }

    /** PUT parcial: lo que no viene se conserva. */
    public static function actualizar(int $id, array $d): array
    {
        $i = self::porId($id);
        if ($i === null) return ['ok' => false, 'error' => 'Instalación inexistente.', 'codigo' => 404];
        $d = array_merge([
            'nombre' => $i['nombre'], 'ubicacion' => $i['ubicacion'], 'latitud' => $i['latitud'],
            'longitud' => $i['longitud'], 'capacidad' => $i['capacidad'],
            'ocupacion_actual' => $i['ocupacion_actual'], 'tipo' => $i['tipo'],
        ], $d);
        $v = self::validar($d);
        if ($v !== null) return ['ok' => false, 'error' => $v];
        Database::conexion()->prepare(
            'UPDATE instalacion SET nombre = ?, ubicacion = ?, latitud = ?, longitud = ?,
                    capacidad = ?, ocupacion_actual = ?, tipo = ? WHERE ID = ?'
        )->execute([
            trim((string) $d['nombre']), trim((string) $d['ubicacion']),
            self::coord($d['latitud'] ?? ''), self::coord($d['longitud'] ?? ''),
            (int) $d['capacidad'], (int) $d['ocupacion_actual'], $d['tipo'], $id,
        ]);
        Notificacion::revisarUmbral($id, (int) $i['ocupacion_actual']);   // alerta si cruzó el 80 %
        return ['ok' => true];
    }

    /** Borrado físico, solo si no tiene dependencias. */
    public static function eliminar(int $id): array
    {
        if (self::porId($id) === null) return ['ok' => false, 'error' => 'Instalación inexistente.', 'codigo' => 404];
        $dep = self::dependencias($id);
        if (array_sum($dep) > 0) {
            return ['ok' => false, 'codigo' => 409, 'dependencias' => $dep,
                'error' => "No se puede eliminar: tiene {$dep['rutas']} ruta(s), {$dep['operarios']} operario(s) y {$dep['maquinaria']} máquina(s) asociadas."];
        }
        Database::conexion()->prepare('DELETE FROM instalacion WHERE ID = ?')->execute([$id]);
        return ['ok' => true];
    }

    public static function porcentaje(array $i): int
    {
        $cap = (int) ($i['capacidad'] ?? 0);
        return $cap > 0 ? (int) round((int) $i['ocupacion_actual'] * 100 / $cap) : 0;
    }

    private static function validar(array $d): ?string
    {
        $nombre = trim((string) ($d['nombre'] ?? ''));
        if ($nombre === '') return 'El nombre es obligatorio.';
        if (mb_strlen($nombre) > 100) return 'El nombre admite hasta 100 caracteres.';
        $ubi = trim((string) ($d['ubicacion'] ?? ''));
        if ($ubi === '') return 'La ubicación es obligatoria.';
        if (mb_strlen($ubi) > 200) return 'La ubicación admite hasta 200 caracteres.';
        if (!in_array($d['tipo'] ?? '', self::TIPOS, true)) return 'Tipo de instalación inválido (vertedero o centro de acopio).';
        if (!coordenada_valida($d['latitud'] ?? '', 90))   return 'La latitud tiene que ser un número entre -90 y 90, o quedar vacía.';
        if (!coordenada_valida($d['longitud'] ?? '', 180)) return 'La longitud tiene que ser un número entre -180 y 180, o quedar vacía.';
        $tope = number_format(MYSQL_INT_MAX, 0, ',', '.');
        if (!entero_en_rango($d['capacidad'] ?? '', 1, MYSQL_INT_MAX)) return "La capacidad tiene que ser un número entero entre 1 y {$tope} m³.";
        if (!entero_en_rango($d['ocupacion_actual'] ?? 0, 0, MYSQL_INT_MAX)) return "La ocupación tiene que ser un número entero entre 0 y {$tope} m³.";
        if ((int) ($d['ocupacion_actual'] ?? 0) > (int) $d['capacidad']) return 'La ocupación no puede superar la capacidad.';
        return null;
    }

    private static function coord(mixed $v): ?float
    {
        $v = trim((string) $v);
        return ($v === '' || !is_numeric($v)) ? null : (float) $v;
    }

    private static function una(array $f): array
    {
        $i = [
            'id' => (int) $f['ID'], 'nombre' => (string) $f['nombre'], 'ubicacion' => (string) $f['ubicacion'],
            'latitud' => $f['latitud'] !== null ? (float) $f['latitud'] : null,
            'longitud' => $f['longitud'] !== null ? (float) $f['longitud'] : null,
            'capacidad' => (int) $f['capacidad'], 'ocupacion_actual' => (int) $f['ocupacion_actual'],
            'tipo' => (string) $f['tipo'],
        ];
        $i['porcentaje'] = self::porcentaje($i);
        $i['alerta'] = $i['porcentaje'] >= self::UMBRAL;
        return $i;
    }
}
