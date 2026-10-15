<?php
declare(strict_types=1);

/**
 * Model de Instalaciones del Backoffice (PDO directo a `cathartes`).
 *
 * Alta y edición, SIN baja: la tabla `instalacion` no tiene columna `estado`
 * (ni `fecha_baja`), y el MER está congelado — no se inventa una. Además las FK
 * de `ruta`, `operario` y `maquinaria` apuntan acá con ON DELETE RESTRICT, así
 * que una instalación con dependencias no se puede borrar por diseño. La pantalla
 * de detalle muestra esas dependencias para que se vea de dónde sale la
 * restricción.
 *
 * `capacidad` y `ocupacion_actual` son INT y se leen en m³ (unidad del proyecto).
 * El umbral de alerta de llenado es 80 %.
 */
class Instalacion
{
    private const POR_PAGINA = 25;
    public const TIPOS   = ['vertedero', 'centro de acopio'];
    public const UMBRAL  = 80; // % de ocupación a partir del cual se alerta

    /** @return array{items:array,total:int,pagina:int,paginas:int} */
    public static function listar(array $o): array
    {
        $where = []; $p = [];
        if (in_array($o['tipo'] ?? '', self::TIPOS, true)) {
            $where[] = 'i.tipo = ?';
            $p[] = $o['tipo'];
        }
        $q = trim((string) ($o['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(i.nombre LIKE ? OR i.ubicacion LIKE ?)';
            $p[] = '%' . $q . '%';
            $p[] = '%' . $q . '%';
        }
        // Solo las que ya pasaron el umbral de llenado.
        if (($o['llenas'] ?? '') === 'si') {
            $where[] = 'i.capacidad > 0 AND (i.ocupacion_actual * 100 / i.capacidad) >= ' . self::UMBRAL;
        }
        $sqlW = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $pdo = Database::conexion();
        $stT = $pdo->prepare("SELECT COUNT(*) FROM instalacion i {$sqlW}");
        $stT->execute($p);
        $total = (int) $stT->fetchColumn();

        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina  = min(max(1, (int) ($o['pagina'] ?? 1)), $paginas);
        $off     = ($pagina - 1) * self::POR_PAGINA;

        // Los contadores van por subconsulta y no por JOIN + GROUP BY: con tres
        // tablas hijas distintas, los JOIN se multiplican entre sí y los COUNT
        // salen inflados.
        $st = $pdo->prepare(
            "SELECT i.ID, i.nombre, i.ubicacion, i.capacidad, i.ocupacion_actual, i.tipo,
                    (SELECT COUNT(*) FROM ruta      r WHERE r.ID_instalacion = i.ID) AS rutas,
                    (SELECT COUNT(*) FROM operario  o WHERE o.ID_instalacion = i.ID) AS operarios
             FROM instalacion i
             {$sqlW} ORDER BY i.nombre ASC LIMIT {$off}, " . self::POR_PAGINA
        );
        $st->execute($p);
        return ['items' => $st->fetchAll(), 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    public static function porId(int $id): ?array
    {
        $st = Database::conexion()->prepare('SELECT * FROM instalacion WHERE ID = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /**
     * Lo que cuelga de la instalación. Es lo que impide borrarla (FK RESTRICT) y
     * lo que hace que un cambio de tipo o de capacidad tenga consecuencias.
     *
     * @return array{rutas:array,operarios:array,maquinaria:int}
     */
    public static function dependencias(int $id): array
    {
        $pdo = Database::conexion();

        $r = $pdo->prepare('SELECT ID, nombre, zona, estado FROM ruta WHERE ID_instalacion = ? ORDER BY nombre');
        $r->execute([$id]);

        $o = $pdo->prepare(
            'SELECT u.ID, u.nombre, u.estado
             FROM operario op JOIN usuario u ON u.ID = op.ID_usuario
             WHERE op.ID_instalacion = ? ORDER BY u.nombre'
        );
        $o->execute([$id]);

        $m = $pdo->prepare('SELECT COUNT(*) FROM maquinaria WHERE ID_instalacion = ?');
        $m->execute([$id]);

        return ['rutas' => $r->fetchAll(), 'operarios' => $o->fetchAll(), 'maquinaria' => (int) $m->fetchColumn()];
    }

    /** Totales de la flota de instalaciones, para la tira de resumen. */
    public static function resumen(): array
    {
        $f = Database::conexion()->query(
            'SELECT COUNT(*)                     AS total,
                    COALESCE(SUM(capacidad), 0)  AS capacidad,
                    COALESCE(SUM(ocupacion_actual), 0) AS ocupacion,
                    SUM(capacidad > 0 AND (ocupacion_actual * 100 / capacidad) >= ' . self::UMBRAL . ') AS llenas
             FROM instalacion'
        )->fetch() ?: [];
        return [
            'total'     => (int) ($f['total'] ?? 0),
            'capacidad' => (int) ($f['capacidad'] ?? 0),
            'ocupacion' => (int) ($f['ocupacion'] ?? 0),
            'llenas'    => (int) ($f['llenas'] ?? 0),
        ];
    }

    /** @return array{ok:bool,error?:string,id?:int} */
    public static function crear(array $d): array
    {
        $v = self::validar($d);
        if ($v !== null) return ['ok' => false, 'error' => $v];

        $pdo = Database::conexion();
        $st  = $pdo->prepare(
            'INSERT INTO instalacion (nombre, ubicacion, latitud, longitud, capacidad, ocupacion_actual, tipo)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            trim((string) $d['nombre']), trim((string) $d['ubicacion']),
            self::coord($d['latitud'] ?? ''), self::coord($d['longitud'] ?? ''),
            (int) $d['capacidad'], (int) $d['ocupacion_actual'], $d['tipo'],
        ]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    }

    /** @return array{ok:bool,error?:string} */
    public static function actualizar(int $id, array $d): array
    {
        if (self::porId($id) === null) return ['ok' => false, 'error' => 'Instalación inexistente.'];
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
        return ['ok' => true];
    }

    /** Porcentaje de llenado (0-100+). Con capacidad 0 devuelve 0, no divide. */
    /**
     * Borrado físico, solo si NADA depende de la instalación (rutas, operarios, maquinaria).
     * Es lo que ya imponen las FK con ON DELETE RESTRICT; acá se anticipa para dar un
     * mensaje claro en vez de un error de base de datos.
     */
    public static function eliminar(int $id): array
    {
        if (self::porId($id) === null) {
            return ['ok' => false, 'error' => 'Instalación inexistente.'];
        }
        $dep = self::dependencias($id);
        $r = count($dep['rutas']); $o = count($dep['operarios']); $m = $dep['maquinaria'];
        if ($r + $o + $m > 0) {
            return ['ok' => false, 'error' => "Tiene {$r} ruta(s), {$o} operario(s) y {$m} máquina(s) asociadas: reasignalas antes de eliminarla."];
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
        if (trim((string) ($d['nombre'] ?? '')) === '')    return 'El nombre es obligatorio.';
        if (trim((string) ($d['ubicacion'] ?? '')) === '') return 'La ubicación es obligatoria.';
        if (!in_array($d['tipo'] ?? '', self::TIPOS, true)) return 'Tipo de instalación inválido.';
        if (!coordenada_valida($d['latitud'] ?? '', 90))    return 'La latitud tiene que ser un número entre -90 y 90, o quedar vacía.';
        if (!coordenada_valida($d['longitud'] ?? '', 180))  return 'La longitud tiene que ser un número entre -180 y 180, o quedar vacía.';

        // Rango de la columna (INT), no del cast de PHP: ver entero_en_rango().
        $tope = number_format(MYSQL_INT_MAX, 0, ',', '.');
        if (!entero_en_rango($d['capacidad'] ?? '', 1, MYSQL_INT_MAX)) {
            return 'La capacidad tiene que ser un número entero entre 1 y ' . $tope . ' m³.';
        }
        if (!entero_en_rango($d['ocupacion_actual'] ?? '', 0, MYSQL_INT_MAX)) {
            return 'La ocupación tiene que ser un número entero entre 0 y ' . $tope . ' m³.';
        }
        $cap = (int) $d['capacidad'];
        $ocu = (int) $d['ocupacion_actual'];
        if ($ocu > $cap) return 'La ocupación (' . $ocu . ' m³) no puede superar la capacidad (' . $cap . ' m³).';
        return null;
    }

    /** Coordenada opcional: '' -> null; si no, número. */
    private static function coord($v): ?float
    {
        $v = trim((string) $v);
        return ($v === '' || !is_numeric($v)) ? null : (float) $v;
    }
}
