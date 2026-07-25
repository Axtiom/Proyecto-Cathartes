<?php
declare(strict_types=1);

/**
 * Model de Camiones del Backoffice (PDO directo). ABM de flota.
 *
 * `matricula` es UNIQUE: se valida a mano para dar un error claro en vez de dejar
 * reventar la constraint. La baja no elimina: marca 'dado de baja' + fecha + motivo.
 * Un camión puede estar asignado a UNA cuadrilla (cuadrilla.ID_camion UNIQUE).
 */
class Camion
{
    private const POR_PAGINA = 25;
    public const ESTADOS = ['funcional', 'en mantenimiento', 'no funcional']; // operativos (sin baja)

    /** @return array{items:array,total:int,pagina:int,paginas:int} */
    public static function listar(array $o): array
    {
        $where = []; $p = [];
        if (in_array($o['estado'] ?? '', ['funcional', 'en mantenimiento', 'no funcional', 'dado de baja'], true)) { $where[] = 'c.estado = ?'; $p[] = $o['estado']; }
        $q = trim((string) ($o['q'] ?? ''));
        if ($q !== '') { $where[] = '(c.matricula LIKE ? OR c.modelo LIKE ?)'; $p[] = "%$q%"; $p[] = "%$q%"; }
        $sqlW = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $pdo = Database::conexion();
        $stT = $pdo->prepare("SELECT COUNT(*) FROM camion c {$sqlW}");
        $stT->execute($p);
        $total = (int) $stT->fetchColumn();

        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina  = min(max(1, (int) ($o['pagina'] ?? 1)), $paginas);
        $off     = ($pagina - 1) * self::POR_PAGINA;

        $st = $pdo->prepare(
            "SELECT c.ID, c.matricula, c.modelo, c.capacidad, c.estado, c.fecha_baja,
                    cu.nombre AS cuadrilla
             FROM camion c
             LEFT JOIN cuadrilla q ON q.ID_camion = c.ID
             LEFT JOIN usuario cu ON cu.ID = q.ID_usuario
             {$sqlW} ORDER BY c.ID ASC LIMIT {$off}, " . self::POR_PAGINA
        );
        $st->execute($p);
        return ['items' => $st->fetchAll(), 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    public static function porId(int $id): ?array
    {
        $st = Database::conexion()->prepare('SELECT * FROM camion WHERE ID = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Cuadrilla (nombre de equipo) que tiene asignado este camión, o null. */
    public static function cuadrillaDe(int $id): ?string
    {
        $st = Database::conexion()->prepare(
            'SELECT u.nombre FROM cuadrilla q JOIN usuario u ON u.ID = q.ID_usuario WHERE q.ID_camion = ?'
        );
        $st->execute([$id]);
        $n = $st->fetchColumn();
        return $n !== false ? (string) $n : null;
    }

    /** @return array{ok:bool,error?:string,id?:int} */
    public static function crear(array $d): array
    {
        $v = self::validar($d, 0);
        if ($v !== null) return ['ok' => false, 'error' => $v];
        Database::conexion()->prepare(
            'INSERT INTO camion (matricula, modelo, capacidad, estado) VALUES (?, ?, ?, ?)'
        )->execute([trim($d['matricula']), trim($d['modelo']), (int) $d['capacidad'], $d['estado']]);
        return ['ok' => true, 'id' => (int) Database::conexion()->lastInsertId()];
    }

    /** @return array{ok:bool,error?:string} */
    public static function actualizar(int $id, array $d): array
    {
        if (self::porId($id) === null) return ['ok' => false, 'error' => 'Camión inexistente.'];
        $v = self::validar($d, $id);
        if ($v !== null) return ['ok' => false, 'error' => $v];
        Database::conexion()->prepare(
            'UPDATE camion SET matricula = ?, modelo = ?, capacidad = ?, estado = ? WHERE ID = ?'
        )->execute([trim($d['matricula']), trim($d['modelo']), (int) $d['capacidad'], $d['estado'], $id]);
        return ['ok' => true];
    }

    public static function baja(int $id, string $motivo): array
    {
        if (self::porId($id) === null) return ['ok' => false, 'error' => 'Camión inexistente.'];
        if (trim($motivo) === '') return ['ok' => false, 'error' => 'El motivo de baja es obligatorio.'];
        Database::conexion()->prepare(
            "UPDATE camion SET estado='dado de baja', fecha_baja=NOW(), motivo_baja=? WHERE ID=?"
        )->execute([trim($motivo), $id]);
        return ['ok' => true];
    }

    private static function validar(array $d, int $idActual): ?string
    {
        $mat = trim((string) ($d['matricula'] ?? ''));
        if ($mat === '') return 'La matrícula es obligatoria.';
        if (trim((string) ($d['modelo'] ?? '')) === '') return 'El modelo es obligatorio.';
        if ((int) ($d['capacidad'] ?? 0) <= 0) return 'La capacidad debe ser un número mayor a 0.';
        if (!in_array($d['estado'] ?? '', self::ESTADOS, true)) return 'Estado inválido.';
        // matrícula UNIQUE (excluye el propio registro en edición)
        $st = Database::conexion()->prepare('SELECT 1 FROM camion WHERE matricula = ? AND ID <> ?');
        $st->execute([$mat, $idActual]);
        if ($st->fetchColumn()) return 'Ya existe un camión con esa matrícula.';
        return null;
    }
}
