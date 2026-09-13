<?php
declare(strict_types=1);

/**
 * Model de Cuadrillas del Backoffice (PDO directo a `cathartes`).
 *
 * SIN ALTA Y SIN BAJA ACÁ. Cuadrilla es **subtipo de Usuario**: el rol se deduce de
 * en qué tabla de subtipo está el ID, así que crear una cuadrilla es crear un
 * usuario (Pantalla 3 → alta multirol) y darla de baja es dar de baja la cuenta
 * (`usuario.estado`). Esta pantalla administra lo que es propio del subtipo:
 * el **camión** y el **estado operativo**.
 *
 * Dos ejes de estado que no hay que confundir:
 *   - `usuario.estado`   → estado de CUENTA ('activo' / 'no activo')
 *   - `cuadrilla.estado` → estado OPERATIVO ('en servicio' / 'fuera de servicio')
 *
 * El nombre del equipo es `usuario.nombre` (ej. «Cuadrilla Alfa»): no existe
 * `cuadrilla.nombre` ni `cuadrilla.integrantes`.
 *
 * `cuadrilla.ID_camion` es NULL-able y **UNIQUE**: una cuadrilla puede no tener
 * camión, y un camión no puede estar en dos cuadrillas.
 */
class Cuadrilla
{
    private const POR_PAGINA = 25;
    public const ESTADOS = ['en servicio', 'fuera de servicio'];

    /**
     * Ruta vigente de la cuadrilla. `toma` es historial (PK con fecha, sin columna
     * de estado), así que "la ruta actual" es la toma más reciente. Va como
     * subconsulta y no como JOIN: un JOIN duplicaría la cuadrilla por cada toma
     * pasada.
     */
    private const SQL_RUTA_ACTUAL =
        '(SELECT CONCAT(r.zona, " · ", r.nombre) FROM toma t JOIN ruta r ON r.ID = t.ID_ruta
           WHERE t.ID_cuadrilla = c.ID_usuario ORDER BY t.fecha DESC LIMIT 1)';

    /** @return array{items:array,total:int,pagina:int,paginas:int} */
    public static function listar(array $o): array
    {
        $where = []; $p = [];
        if (in_array($o['estado'] ?? '', self::ESTADOS, true)) {
            $where[] = 'c.estado = ?';
            $p[] = $o['estado'];
        }
        if (in_array($o['cuenta'] ?? '', ['activo', 'no activo'], true)) {
            $where[] = 'u.estado = ?';
            $p[] = $o['cuenta'];
        }
        if (($o['camion'] ?? '') === 'con')  { $where[] = 'c.ID_camion IS NOT NULL'; }
        if (($o['camion'] ?? '') === 'sin')  { $where[] = 'c.ID_camion IS NULL'; }
        $q = trim((string) ($o['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(u.nombre LIKE ? OR u.email LIKE ?)';
            $p[] = '%' . $q . '%';
            $p[] = '%' . $q . '%';
        }
        $sqlW = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $pdo = Database::conexion();
        $stT = $pdo->prepare("SELECT COUNT(*) FROM cuadrilla c JOIN usuario u ON u.ID = c.ID_usuario {$sqlW}");
        $stT->execute($p);
        $total = (int) $stT->fetchColumn();

        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina  = min(max(1, (int) ($o['pagina'] ?? 1)), $paginas);
        $off     = ($pagina - 1) * self::POR_PAGINA;

        $st = $pdo->prepare(
            "SELECT c.ID_usuario AS ID, u.nombre, u.email, u.estado AS cuenta,
                    c.estado AS operativo, c.ID_camion,
                    ca.matricula, ca.modelo, ca.estado AS camion_estado,
                    " . self::SQL_RUTA_ACTUAL . " AS ruta,
                    (SELECT COUNT(*) FROM intervencion i
                      WHERE i.ID_cuadrilla = c.ID_usuario AND i.tarea_finalizada = 'no') AS abiertas
             FROM cuadrilla c
             JOIN usuario u  ON u.ID = c.ID_usuario
             LEFT JOIN camion ca ON ca.ID = c.ID_camion
             {$sqlW} ORDER BY u.nombre LIMIT {$off}, " . self::POR_PAGINA
        );
        $st->execute($p);
        return ['items' => $st->fetchAll(), 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    public static function porId(int $id): ?array
    {
        $st = Database::conexion()->prepare(
            "SELECT c.ID_usuario AS ID, u.nombre, u.email, u.estado AS cuenta,
                    c.estado AS operativo, c.ID_camion,
                    ca.matricula, ca.modelo, ca.capacidad, ca.estado AS camion_estado
             FROM cuadrilla c
             JOIN usuario u  ON u.ID = c.ID_usuario
             LEFT JOIN camion ca ON ca.ID = c.ID_camion
             WHERE c.ID_usuario = ?"
        );
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /**
     * Camiones que la cuadrilla puede tomar: los libres (sin cuadrilla) más el que
     * ya tiene. Se excluyen los dados de baja — no se manda a operar un camión
     * retirado. `ID_camion` es UNIQUE, así que ofrecer uno ocupado sería ofrecer un
     * error de base.
     */
    public static function camionesDisponibles(int $idCuadrilla): array
    {
        $st = Database::conexion()->prepare(
            "SELECT ca.ID, ca.matricula, ca.modelo, ca.estado
             FROM camion ca
             WHERE ca.estado <> 'dado de baja'
               AND (ca.ID NOT IN (SELECT ID_camion FROM cuadrilla WHERE ID_camion IS NOT NULL)
                    OR ca.ID = (SELECT ID_camion FROM cuadrilla WHERE ID_usuario = ?))
             ORDER BY ca.matricula"
        );
        $st->execute([$idCuadrilla]);
        return $st->fetchAll();
    }

    /** Historial de rutas tomadas (más reciente primero; la primera es la vigente). */
    public static function historial(int $id): array
    {
        $st = Database::conexion()->prepare(
            'SELECT t.fecha, t.ID_ruta, r.nombre, r.zona, r.estado
             FROM toma t JOIN ruta r ON r.ID = t.ID_ruta
             WHERE t.ID_cuadrilla = ? ORDER BY t.fecha DESC'
        );
        $st->execute([$id]);
        return $st->fetchAll();
    }

    /** Intervenciones de la cuadrilla (incidencias que le asignaron). */
    public static function intervenciones(int $id): array
    {
        $st = Database::conexion()->prepare(
            'SELECT i.ID_intervencion, i.fecha_inicio, i.fecha_fin, i.tarea_finalizada,
                    i.ID_incidencia, h.tipo, h.estado
             FROM intervencion i
             JOIN incidencia_historial h ON h.ID_incidencia = i.ID_incidencia
             WHERE i.ID_cuadrilla = ? ORDER BY i.fecha_inicio DESC'
        );
        $st->execute([$id]);
        return $st->fetchAll();
    }

    /** Tira de resumen de la flota de cuadrillas. */
    public static function resumen(): array
    {
        $f = Database::conexion()->query(
            "SELECT COUNT(*) AS total,
                    SUM(c.estado = 'en servicio')  AS en_servicio,
                    SUM(c.ID_camion IS NULL)       AS sin_camion,
                    SUM(u.estado <> 'activo')      AS cuenta_inactiva
             FROM cuadrilla c JOIN usuario u ON u.ID = c.ID_usuario"
        )->fetch() ?: [];
        return [
            'total'           => (int) ($f['total'] ?? 0),
            'en_servicio'     => (int) ($f['en_servicio'] ?? 0),
            'sin_camion'      => (int) ($f['sin_camion'] ?? 0),
            'cuenta_inactiva' => (int) ($f['cuenta_inactiva'] ?? 0),
        ];
    }

    /**
     * Guarda lo propio del subtipo: estado operativo y camión. No toca `usuario`
     * (eso es la Pantalla 3) ni `toma` (eso es la Pantalla 7).
     *
     * @return array{ok:bool,error?:string}
     */
    public static function actualizar(int $id, array $d): array
    {
        if (self::porId($id) === null) return ['ok' => false, 'error' => 'Cuadrilla inexistente.'];

        $estado = (string) ($d['estado'] ?? '');
        if (!in_array($estado, self::ESTADOS, true)) return ['ok' => false, 'error' => 'Estado operativo inválido.'];

        $pdo    = Database::conexion();
        $idCam  = (int) ($d['id_camion'] ?? 0);
        $camion = null;

        if ($idCam > 0) {
            $st = $pdo->prepare("SELECT estado FROM camion WHERE ID = ?");
            $st->execute([$idCam]);
            $es = $st->fetchColumn();
            if ($es === false) return ['ok' => false, 'error' => 'El camión no existe.'];
            if ($es === 'dado de baja') return ['ok' => false, 'error' => 'Ese camión está dado de baja.'];

            // UNIQUE en ID_camion: hay que chequear que no lo tenga otra cuadrilla.
            $st = $pdo->prepare('SELECT ID_usuario FROM cuadrilla WHERE ID_camion = ? AND ID_usuario <> ?');
            $st->execute([$idCam, $id]);
            if ($st->fetchColumn()) return ['ok' => false, 'error' => 'Ese camión ya está asignado a otra cuadrilla.'];

            $camion = $idCam;
        }

        $pdo->prepare('UPDATE cuadrilla SET ID_camion = ?, estado = ? WHERE ID_usuario = ?')
            ->execute([$camion, $estado, $id]);
        return ['ok' => true];
    }
}
