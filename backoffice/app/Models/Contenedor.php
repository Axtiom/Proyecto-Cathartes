<?php
declare(strict_types=1);

/**
 * Model de Contenedores del Backoffice (PDO directo a `cathartes`). ABM completo.
 *
 * La baja NO borra: marca estado='dado de baja' y registra fecha_baja + motivo_baja
 * (política de borrado del proyecto: el sistema da de baja, no elimina).
 * Los estados operativos (funcional / no funcional / desbordado) se editan en el
 * formulario; 'dado de baja' se alcanza solo por la acción de baja.
 */
class Contenedor
{
    private const POR_PAGINA = 25;
    public const TIPOS   = ['basura', 'reciclable'];
    public const ESTADOS = ['funcional', 'no funcional', 'desbordado']; // operativos (sin baja)

    /** @return array{items:array,total:int,pagina:int,paginas:int} */
    public static function listar(array $o): array
    {
        $where = []; $p = [];
        if (in_array($o['tipo'] ?? '', self::TIPOS, true)) { $where[] = 'c.tipo = ?'; $p[] = $o['tipo']; }
        if (in_array($o['estado'] ?? '', ['funcional', 'no funcional', 'desbordado', 'dado de baja'], true)) { $where[] = 'c.estado = ?'; $p[] = $o['estado']; }
        if ((int) ($o['ruta'] ?? 0) > 0) { $where[] = 'c.ID_ruta = ?'; $p[] = (int) $o['ruta']; }
        $sqlW = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $pdo = Database::conexion();
        $stT = $pdo->prepare("SELECT COUNT(*) FROM contenedor c {$sqlW}");
        $stT->execute($p);
        $total = (int) $stT->fetchColumn();

        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina  = min(max(1, (int) ($o['pagina'] ?? 1)), $paginas);
        $off     = ($pagina - 1) * self::POR_PAGINA;

        $st = $pdo->prepare(
            "SELECT c.ID, c.ubicacion, c.tipo, c.estado, c.fecha_baja, r.nombre AS ruta, r.zona
             FROM contenedor c JOIN ruta r ON r.ID = c.ID_ruta
             {$sqlW} ORDER BY c.ID ASC LIMIT {$off}, " . self::POR_PAGINA
        );
        $st->execute($p);
        return ['items' => $st->fetchAll(), 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    public static function porId(int $id): ?array
    {
        $st = Database::conexion()->prepare('SELECT * FROM contenedor WHERE ID = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function rutas(): array
    {
        return Database::conexion()->query(
            "SELECT ID, nombre, zona FROM ruta ORDER BY zona, nombre"
        )->fetchAll();
    }

    /** @return array{ok:bool,error?:string,id?:int} */
    public static function crear(array $d): array
    {
        $v = self::validar($d);
        if ($v !== null) return ['ok' => false, 'error' => $v];
        $st = Database::conexion()->prepare(
            'INSERT INTO contenedor (ubicacion, latitud, longitud, tipo, estado, ID_ruta)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            trim($d['ubicacion']), self::coord($d['latitud'] ?? ''), self::coord($d['longitud'] ?? ''),
            $d['tipo'], $d['estado'], (int) $d['id_ruta'],
        ]);
        return ['ok' => true, 'id' => (int) Database::conexion()->lastInsertId()];
    }

    /** @return array{ok:bool,error?:string} */
    public static function actualizar(int $id, array $d): array
    {
        if (self::porId($id) === null) return ['ok' => false, 'error' => 'Contenedor inexistente.'];
        $v = self::validar($d);
        if ($v !== null) return ['ok' => false, 'error' => $v];
        $st = Database::conexion()->prepare(
            'UPDATE contenedor SET ubicacion = ?, latitud = ?, longitud = ?, tipo = ?, estado = ?, ID_ruta = ? WHERE ID = ?'
        );
        $st->execute([
            trim($d['ubicacion']), self::coord($d['latitud'] ?? ''), self::coord($d['longitud'] ?? ''),
            $d['tipo'], $d['estado'], (int) $d['id_ruta'], $id,
        ]);
        return ['ok' => true];
    }

    /** Baja: no elimina; marca estado='dado de baja' + fecha_baja + motivo. */
    public static function baja(int $id, string $motivo): array
    {
        if (self::porId($id) === null) return ['ok' => false, 'error' => 'Contenedor inexistente.'];
        if (trim($motivo) === '') return ['ok' => false, 'error' => 'El motivo de baja es obligatorio.'];
        Database::conexion()->prepare(
            "UPDATE contenedor SET estado='dado de baja', fecha_baja=NOW(), motivo_baja=? WHERE ID=?"
        )->execute([trim($motivo), $id]);
        return ['ok' => true];
    }

    private static function validar(array $d): ?string
    {
        if (trim((string) ($d['ubicacion'] ?? '')) === '') return 'La ubicación es obligatoria.';
        if (!in_array($d['tipo'] ?? '', self::TIPOS, true)) return 'Tipo de residuo inválido.';
        if (!in_array($d['estado'] ?? '', self::ESTADOS, true)) return 'Estado inválido.';
        if ((int) ($d['id_ruta'] ?? 0) <= 0) return 'La ruta es obligatoria.';
        $st = Database::conexion()->prepare('SELECT 1 FROM ruta WHERE ID = ?');
        $st->execute([(int) $d['id_ruta']]);
        if (!$st->fetchColumn()) return 'La ruta no existe.';
        return null;
    }

    /** Coordenada opcional: '' -> null; si no, número. */
    private static function coord($v): ?float
    {
        $v = trim((string) $v);
        return ($v === '' || !is_numeric($v)) ? null : (float) $v;
    }
}
