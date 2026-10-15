<?php
declare(strict_types=1);

/**
 * Model de Camiones de la API de Gestión (PDO directo a `cathartes`).
 * Lectura y registro de la flota. Edición y baja siguen en el Backoffice.
 * `matricula` es UNIQUE: se valida a mano para devolver un error claro.
 */
class Camion
{
    public const ESTADOS = ['funcional', 'en mantenimiento', 'no funcional']; // operativos (sin baja)
    private const TODOS  = ['funcional', 'en mantenimiento', 'no funcional', 'dado de baja'];

    /** @return list<array> */
    public static function listar(?string $estado = null, ?string $q = null): array
    {
        $where = []; $p = [];
        if ($estado !== null && in_array($estado, self::TODOS, true)) { $where[] = 'c.estado = ?'; $p[] = $estado; }
        $q = trim((string) $q);
        if ($q !== '') { $where[] = '(c.matricula LIKE ? OR c.modelo LIKE ?)'; $p[] = "%$q%"; $p[] = "%$q%"; }
        $sqlW = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $st = Database::conexion()->prepare(
            "SELECT c.ID, c.matricula, c.modelo, c.capacidad, c.estado, c.fecha_baja, c.motivo_baja,
                    u.nombre AS cuadrilla
             FROM camion c
             LEFT JOIN cuadrilla q ON q.ID_camion = c.ID
             LEFT JOIN usuario u ON u.ID = q.ID_usuario
             {$sqlW} ORDER BY c.ID"
        );
        $st->execute($p);
        return array_map([self::class, 'una'], $st->fetchAll());
    }

    public static function porId(int $id): ?array
    {
        $st = Database::conexion()->prepare(
            'SELECT c.ID, c.matricula, c.modelo, c.capacidad, c.estado, c.fecha_baja, c.motivo_baja, u.nombre AS cuadrilla
             FROM camion c
             LEFT JOIN cuadrilla q ON q.ID_camion = c.ID
             LEFT JOIN usuario u ON u.ID = q.ID_usuario
             WHERE c.ID = ?'
        );
        $st->execute([$id]);
        $f = $st->fetch();
        return $f ? self::una($f) : null;
    }

    /** @return array{ok:bool,error?:string,id?:int} */
    public static function crear(array $d): array
    {
        $d['estado'] = $d['estado'] ?? 'funcional';
        $v = self::validar($d);
        if ($v !== null) return ['ok' => false, 'error' => $v];
        $pdo = Database::conexion();
        $pdo->prepare('INSERT INTO camion (matricula, modelo, capacidad, estado) VALUES (?, ?, ?, ?)')
            ->execute([trim((string) $d['matricula']), trim((string) $d['modelo']), (int) $d['capacidad'], $d['estado']]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    }

    /**
     * Edita matrícula, modelo, capacidad y estado (PUT parcial: lo que no viene se conserva).
     * @return array{ok:bool,error?:string,codigo?:int}
     */
    public static function actualizar(int $id, array $d): array
    {
        $c = self::porId($id);
        if ($c === null) return ['ok' => false, 'error' => 'Camión inexistente.', 'codigo' => 404];
        if ($c['estado'] === 'dado de baja') return ['ok' => false, 'error' => 'El camión está dado de baja y no se puede editar.', 'codigo' => 409];
        $d = array_merge(['matricula' => $c['matricula'], 'modelo' => $c['modelo'], 'capacidad' => $c['capacidad'], 'estado' => $c['estado']], $d);
        $v = self::validar($d, $id);
        if ($v !== null) return ['ok' => false, 'error' => $v];
        Database::conexion()->prepare('UPDATE camion SET matricula = ?, modelo = ?, capacidad = ?, estado = ? WHERE ID = ?')
            ->execute([trim((string) $d['matricula']), trim((string) $d['modelo']), (int) $d['capacidad'], $d['estado'], $id]);
        return ['ok' => true];
    }

    /** Baja lógica con fecha y motivo obligatorio. */
    public static function baja(int $id, string $motivo): array
    {
        $c = self::porId($id);
        if ($c === null) return ['ok' => false, 'error' => 'Camión inexistente.', 'codigo' => 404];
        if ($c['estado'] === 'dado de baja') return ['ok' => false, 'error' => 'El camión ya está dado de baja.', 'codigo' => 409];
        $motivo = trim($motivo);
        if ($motivo === '') return ['ok' => false, 'error' => 'El motivo de baja es obligatorio.'];
        if (mb_strlen($motivo) > 255) return ['ok' => false, 'error' => 'El motivo admite hasta 255 caracteres.'];
        Database::conexion()->prepare("UPDATE camion SET estado='dado de baja', fecha_baja=NOW(), motivo_baja=? WHERE ID=?")
            ->execute([$motivo, $id]);
        return ['ok' => true];
    }

    private static function validar(array $d, int $idActual = 0): ?string
    {
        $mat = trim((string) ($d['matricula'] ?? ''));
        if ($mat === '') return 'La matrícula es obligatoria.';
        if (mb_strlen($mat) > 20) return 'La matrícula admite hasta 20 caracteres.';
        $mod = trim((string) ($d['modelo'] ?? ''));
        if ($mod === '') return 'El modelo es obligatorio.';
        if (mb_strlen($mod) > 100) return 'El modelo admite hasta 100 caracteres.';
        if (!entero_en_rango($d['capacidad'] ?? '', 1, MYSQL_INT_MAX)) {
            return 'La capacidad debe ser un número entero entre 1 y ' . number_format(MYSQL_INT_MAX, 0, ',', '.') . '.';
        }
        if (!in_array($d['estado'], self::ESTADOS, true)) return 'Estado inválido.';
        $st = Database::conexion()->prepare('SELECT 1 FROM camion WHERE matricula = ? AND ID <> ?');
        $st->execute([$mat, $idActual]);
        if ($st->fetchColumn()) return 'Ya existe un camión con esa matrícula.';
        return null;
    }

    private static function una(array $f): array
    {
        return [
            'id'          => (int) $f['ID'],
            'matricula'   => (string) $f['matricula'],
            'modelo'      => (string) $f['modelo'],
            'capacidad'   => (int) $f['capacidad'],
            'estado'      => (string) $f['estado'],
            'cuadrilla'   => $f['cuadrilla'] !== null ? (string) $f['cuadrilla'] : null,
            'fecha_baja'  => $f['fecha_baja'],
            'motivo_baja' => $f['motivo_baja'],
        ];
    }
}
