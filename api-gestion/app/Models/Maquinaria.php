<?php
declare(strict_types=1);

/**
 * Model de Maquinaria de las instalaciones (API de Gestión). CRUD básico.
 * Cada máquina pertenece a una instalación (ID_instalacion NOT NULL). Nada
 * referencia a `maquinaria`, así que el borrado físico es seguro.
 */
class Maquinaria
{
    public const ESTADOS = ['funcional', 'no funcional', 'en mantenimiento'];

    /** @return list<array> */
    public static function listar(?int $idInstalacion = null, ?string $estado = null): array
    {
        $where = []; $p = [];
        if ($idInstalacion !== null && $idInstalacion > 0) { $where[] = 'm.ID_instalacion = ?'; $p[] = $idInstalacion; }
        if ($estado !== null && in_array($estado, self::ESTADOS, true)) { $where[] = 'm.estado = ?'; $p[] = $estado; }
        $sqlW = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $st = Database::conexion()->prepare(
            "SELECT m.ID, m.modelo, m.tipo, m.estado, m.ID_instalacion, i.nombre AS instalacion
             FROM maquinaria m JOIN instalacion i ON i.ID = m.ID_instalacion {$sqlW} ORDER BY m.ID"
        );
        $st->execute($p);
        return array_map([self::class, 'una'], $st->fetchAll());
    }

    public static function porId(int $id): ?array
    {
        $st = Database::conexion()->prepare(
            'SELECT m.ID, m.modelo, m.tipo, m.estado, m.ID_instalacion, i.nombre AS instalacion
             FROM maquinaria m JOIN instalacion i ON i.ID = m.ID_instalacion WHERE m.ID = ?'
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
        $pdo->prepare('INSERT INTO maquinaria (modelo, tipo, estado, ID_instalacion) VALUES (?, ?, ?, ?)')
            ->execute([trim((string) $d['modelo']), trim((string) $d['tipo']), $d['estado'], (int) $d['id_instalacion']]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    }

    /** PUT parcial: lo que no viene se conserva. */
    public static function actualizar(int $id, array $d): array
    {
        $m = self::porId($id);
        if ($m === null) return ['ok' => false, 'error' => 'Máquina inexistente.', 'codigo' => 404];
        $d = array_merge(['modelo' => $m['modelo'], 'tipo' => $m['tipo'], 'estado' => $m['estado'], 'id_instalacion' => $m['id_instalacion']], $d);
        $v = self::validar($d);
        if ($v !== null) return ['ok' => false, 'error' => $v];
        Database::conexion()->prepare('UPDATE maquinaria SET modelo = ?, tipo = ?, estado = ?, ID_instalacion = ? WHERE ID = ?')
            ->execute([trim((string) $d['modelo']), trim((string) $d['tipo']), $d['estado'], (int) $d['id_instalacion'], $id]);
        return ['ok' => true];
    }

    public static function eliminar(int $id): array
    {
        if (self::porId($id) === null) return ['ok' => false, 'error' => 'Máquina inexistente.', 'codigo' => 404];
        Database::conexion()->prepare('DELETE FROM maquinaria WHERE ID = ?')->execute([$id]);
        return ['ok' => true];
    }

    private static function validar(array $d): ?string
    {
        $mod = trim((string) ($d['modelo'] ?? ''));
        if ($mod === '') return 'El modelo es obligatorio.';
        if (mb_strlen($mod) > 100) return 'El modelo admite hasta 100 caracteres.';
        $tipo = trim((string) ($d['tipo'] ?? ''));
        if ($tipo === '') return 'El tipo es obligatorio (por ejemplo: excavadora, compactadora).';
        if (mb_strlen($tipo) > 50) return 'El tipo admite hasta 50 caracteres.';
        if (!in_array($d['estado'] ?? '', self::ESTADOS, true)) return 'Estado inválido (funcional, no funcional o en mantenimiento).';
        if (!entero_en_rango($d['id_instalacion'] ?? '', 1)) return 'La instalación es obligatoria.';
        $st = Database::conexion()->prepare('SELECT 1 FROM instalacion WHERE ID = ?');
        $st->execute([(int) $d['id_instalacion']]);
        if (!$st->fetchColumn()) return 'La instalación no existe.';
        return null;
    }

    private static function una(array $f): array
    {
        return [
            'id' => (int) $f['ID'], 'modelo' => (string) $f['modelo'], 'tipo' => (string) $f['tipo'],
            'estado' => (string) $f['estado'], 'id_instalacion' => (int) $f['ID_instalacion'],
            'instalacion' => (string) $f['instalacion'],
        ];
    }
}
