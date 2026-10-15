<?php
declare(strict_types=1);

/**
 * Model de Maquinaria de las instalaciones (Backoffice). CRUD básico: alta, edición y borrado.
 * Cada máquina pertenece a una instalación (ID_instalacion NOT NULL). Ninguna otra tabla
 * referencia a `maquinaria`, por eso el borrado físico es seguro.
 */
class Maquinaria
{
    private const POR_PAGINA = 25;
    public const ESTADOS = ['funcional', 'no funcional', 'en mantenimiento'];

    /**
     * @param array{instalacion?:int,estado?:string,q?:string,pagina?:int} $o
     * @return array{items:array,total:int,pagina:int,paginas:int,porPagina:int}
     */
    public static function listar(array $o): array
    {
        $where = []; $p = [];
        if (!empty($o['instalacion'])) { $where[] = 'm.ID_instalacion = ?'; $p[] = (int) $o['instalacion']; }
        if (!empty($o['estado']) && in_array($o['estado'], self::ESTADOS, true)) { $where[] = 'm.estado = ?'; $p[] = $o['estado']; }
        $q = trim((string) ($o['q'] ?? ''));
        if ($q !== '') { $where[] = '(m.modelo LIKE ? OR m.tipo LIKE ?)'; $p[] = "%$q%"; $p[] = "%$q%"; }
        $sqlW = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $pdo = Database::conexion();
        $st = $pdo->prepare("SELECT COUNT(*) FROM maquinaria m {$sqlW}");
        $st->execute($p);
        $total   = (int) $st->fetchColumn();
        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina  = min(max(1, (int) ($o['pagina'] ?? 1)), $paginas);

        $st = $pdo->prepare(
            "SELECT m.ID, m.modelo, m.tipo, m.estado, m.ID_instalacion, i.nombre AS instalacion
             FROM maquinaria m JOIN instalacion i ON i.ID = m.ID_instalacion {$sqlW}
             ORDER BY m.ID LIMIT " . self::POR_PAGINA . ' OFFSET ' . (($pagina - 1) * self::POR_PAGINA)
        );
        $st->execute($p);
        return ['items' => $st->fetchAll(), 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas, 'porPagina' => self::POR_PAGINA];
    }

    public static function porId(int $id): ?array
    {
        $st = Database::conexion()->prepare(
            'SELECT m.ID, m.modelo, m.tipo, m.estado, m.ID_instalacion, i.nombre AS instalacion
             FROM maquinaria m JOIN instalacion i ON i.ID = m.ID_instalacion WHERE m.ID = ?'
        );
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** @return array{ok:bool,error?:string,id?:int} */
    public static function crear(array $d): array
    {
        $v = self::validar($d);
        if ($v !== null) return ['ok' => false, 'error' => $v];
        $pdo = Database::conexion();
        $pdo->prepare('INSERT INTO maquinaria (modelo, tipo, estado, ID_instalacion) VALUES (?, ?, ?, ?)')
            ->execute([trim((string) $d['modelo']), trim((string) $d['tipo']), $d['estado'], (int) $d['id_instalacion']]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    }

    public static function actualizar(int $id, array $d): array
    {
        if (self::porId($id) === null) return ['ok' => false, 'error' => 'Máquina inexistente.'];
        $v = self::validar($d);
        if ($v !== null) return ['ok' => false, 'error' => $v];
        Database::conexion()->prepare('UPDATE maquinaria SET modelo = ?, tipo = ?, estado = ?, ID_instalacion = ? WHERE ID = ?')
            ->execute([trim((string) $d['modelo']), trim((string) $d['tipo']), $d['estado'], (int) $d['id_instalacion'], $id]);
        return ['ok' => true];
    }

    public static function eliminar(int $id): array
    {
        if (self::porId($id) === null) return ['ok' => false, 'error' => 'Máquina inexistente.'];
        Database::conexion()->prepare('DELETE FROM maquinaria WHERE ID = ?')->execute([$id]);
        return ['ok' => true];
    }

    public static function instalaciones(): array
    {
        return Database::conexion()->query('SELECT ID, nombre FROM instalacion ORDER BY nombre')->fetchAll();
    }

    private static function validar(array $d): ?string
    {
        $mod = trim((string) ($d['modelo'] ?? ''));
        if ($mod === '') return 'El modelo es obligatorio.';
        if (mb_strlen($mod) > 100) return 'El modelo admite hasta 100 caracteres.';
        $tipo = trim((string) ($d['tipo'] ?? ''));
        if ($tipo === '') return 'El tipo es obligatorio (por ejemplo: excavadora, compactadora).';
        if (mb_strlen($tipo) > 50) return 'El tipo admite hasta 50 caracteres.';
        if (!in_array($d['estado'] ?? '', self::ESTADOS, true)) return 'Estado inválido.';
        if (!entero_en_rango($d['id_instalacion'] ?? '', 1)) return 'La instalación es obligatoria.';
        $st = Database::conexion()->prepare('SELECT 1 FROM instalacion WHERE ID = ?');
        $st->execute([(int) $d['id_instalacion']]);
        if (!$st->fetchColumn()) return 'La instalación no existe.';
        return null;
    }
}
