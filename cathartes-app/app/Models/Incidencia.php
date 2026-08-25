<?php
declare(strict_types=1);

/**
 * Model de Incidencias del Backoffice (PDO directo a `cathartes`).
 *
 * La zona no es columna de la incidencia: sale de contenedor -> ruta.zona.
 * "Asignada" no es columna: se infiere de que exista una `intervencion`.
 * Validar una incidencia (validada 'no' -> 'si') dispara el TRIGGER que acredita
 * 150 puntos al vecino (regla de dominio; no se acredita acá en la app).
 */
class Incidencia
{
    private const POR_PAGINA = 25;

    /**
     * @param array{estado?:string,validada?:string,tipo?:string,zona?:string,desde?:string,hasta?:string,pagina?:int} $o
     * @return array{items:array,total:int,pagina:int,paginas:int}
     */
    public static function listar(array $o): array
    {
        $where = [];
        $p = [];
        if (in_array($o['estado'] ?? '', ['en curso', 'finalizada'], true)) { $where[] = 'i.estado = ?'; $p[] = $o['estado']; }
        // Validada es el eje del trabajo del admin en esta pantalla: hasta que no
        // se valida no se acreditan los 150 puntos. El dashboard entra por acá.
        if (in_array($o['validada'] ?? '', ['si', 'no'], true)) { $where[] = 'i.validada = ?'; $p[] = $o['validada']; }
        if (in_array($o['tipo'] ?? '', ['desbordado', 'roto', 'falta de recoleccion'], true)) { $where[] = 'i.tipo = ?'; $p[] = $o['tipo']; }
        if (($o['zona'] ?? '') !== '') { $where[] = 'r.zona = ?'; $p[] = $o['zona']; }
        if (($o['desde'] ?? '') !== '') { $where[] = 'i.fecha_ing >= ?'; $p[] = $o['desde'] . ' 00:00:00'; }
        if (($o['hasta'] ?? '') !== '') { $where[] = 'i.fecha_ing <= ?'; $p[] = $o['hasta'] . ' 23:59:59'; }
        $sqlW = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $pdo = Database::conexion();
        $stT = $pdo->prepare("SELECT COUNT(*) FROM incidencia_historial i
                              JOIN contenedor c ON c.ID = i.ID_contenedor
                              JOIN ruta r ON r.ID = c.ID_ruta {$sqlW}");
        $stT->execute($p);
        $total = (int) $stT->fetchColumn();

        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina  = min(max(1, (int) ($o['pagina'] ?? 1)), $paginas);
        $off     = ($pagina - 1) * self::POR_PAGINA;

        $sql = "SELECT i.ID_incidencia AS id, i.fecha_ing, i.tipo, i.validada, i.estado,
                       (i.foto IS NOT NULL) AS tiene_foto, c.ubicacion, r.zona, u.nombre AS vecino,
                       EXISTS(SELECT 1 FROM intervencion v WHERE v.ID_incidencia = i.ID_incidencia) AS asignada,
                       (SELECT cu.nombre FROM intervencion v JOIN usuario cu ON cu.ID = v.ID_cuadrilla
                        WHERE v.ID_incidencia = i.ID_incidencia ORDER BY v.fecha_inicio DESC LIMIT 1) AS cuadrilla
                FROM incidencia_historial i
                JOIN contenedor c ON c.ID = i.ID_contenedor
                JOIN ruta r ON r.ID = c.ID_ruta
                JOIN usuario u ON u.ID = i.ID_usuario
                {$sqlW}
                ORDER BY i.ID_incidencia DESC
                LIMIT {$off}, " . self::POR_PAGINA;
        $st = $pdo->prepare($sql);
        $st->execute($p);

        return ['items' => $st->fetchAll(), 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    public static function detalle(int $id): ?array
    {
        $st = Database::conexion()->prepare(
            "SELECT i.*, c.ubicacion, c.ID AS id_contenedor, r.zona, r.nombre AS ruta,
                    u.nombre AS vecino, u.email AS vecino_email
             FROM incidencia_historial i
             JOIN contenedor c ON c.ID = i.ID_contenedor
             JOIN ruta r ON r.ID = c.ID_ruta
             JOIN usuario u ON u.ID = i.ID_usuario
             WHERE i.ID_incidencia = ?"
        );
        $st->execute([$id]);
        $inc = $st->fetch();
        if (!$inc) {
            return null;
        }
        $stv = Database::conexion()->prepare(
            "SELECT v.*, cu.nombre AS cuadrilla
             FROM intervencion v JOIN usuario cu ON cu.ID = v.ID_cuadrilla
             WHERE v.ID_incidencia = ? ORDER BY v.fecha_inicio DESC LIMIT 1"
        );
        $stv->execute([$id]);
        $inc['intervencion'] = $stv->fetch() ?: null;
        return $inc;
    }

    /** Nombre de archivo de la foto (o null). Solo el basename: anti path-traversal. */
    public static function fotoArchivo(int $id): ?string
    {
        $st = Database::conexion()->prepare('SELECT foto FROM incidencia_historial WHERE ID_incidencia = ?');
        $st->execute([$id]);
        $f = $st->fetchColumn();
        return ($f !== false && $f !== null && $f !== '') ? basename((string) $f) : null;
    }

    /** Zonas existentes (para el filtro), derivadas de las rutas con incidencias. */
    public static function zonas(): array
    {
        return Database::conexion()->query(
            'SELECT DISTINCT r.zona FROM ruta r ORDER BY r.zona'
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Cuadrillas para el combo de asignación (nombre de equipo = usuario.nombre). */
    public static function cuadrillas(): array
    {
        return Database::conexion()->query(
            "SELECT c.ID_usuario AS id, u.nombre, c.estado
             FROM cuadrilla c JOIN usuario u ON u.ID = c.ID_usuario
             WHERE u.estado = 'activo' ORDER BY u.nombre"
        )->fetchAll();
    }

    /** Validar el reporte (dispara el trigger de +150 puntos). Solo no -> si. */
    public static function validar(int $id): bool
    {
        $st = Database::conexion()->prepare("UPDATE incidencia_historial SET validada='si' WHERE ID_incidencia=? AND validada='no'");
        $st->execute([$id]);
        return $st->rowCount() > 0;
    }

    /** Asignar una cuadrilla creando una intervencion. Falla si ya está asignada. */
    public static function asignar(int $idInc, int $idCuadrilla): array
    {
        $pdo = Database::conexion();
        $chk = $pdo->prepare('SELECT 1 FROM intervencion WHERE ID_incidencia = ? LIMIT 1');
        $chk->execute([$idInc]);
        if ($chk->fetchColumn()) {
            return ['ok' => false, 'error' => 'La incidencia ya tiene una cuadrilla asignada.'];
        }
        $cu = $pdo->prepare("SELECT 1 FROM cuadrilla WHERE ID_usuario = ? LIMIT 1");
        $cu->execute([$idCuadrilla]);
        if (!$cu->fetchColumn()) {
            return ['ok' => false, 'error' => 'Cuadrilla inválida.'];
        }
        $pdo->prepare("INSERT INTO intervencion (fecha_inicio, tarea_finalizada, ID_incidencia, ID_cuadrilla)
                       VALUES (NOW(), 'no', ?, ?)")->execute([$idInc, $idCuadrilla]);
        return ['ok' => true];
    }

    // ------------------------------------------------- universo Vecino ----
    // Los siguientes métodos son los que consumía /api/vecino/* (reporte y
    // seguimiento de incidencias por el propio vecino, scopeado a su ID).

    private const TIPOS = ['desbordado', 'roto', 'falta de recoleccion'];

    public static function tipoValido(string $tipo): bool
    {
        return in_array($tipo, self::TIPOS, true);
    }

    /**
     * Incidencias de un vecino, más nuevas primero. Con ubicación del contenedor.
     * @param int|null $limite null = todas; N para un listado acotado.
     * @return list<array>
     */
    public static function delVecino(int $idUsuario, ?int $limite = null): array
    {
        $sql = "SELECT i.ID_incidencia AS id, i.fecha_ing, i.tipo, i.validada, i.estado,
                       i.descripcion, i.foto, i.ID_contenedor AS id_contenedor, c.ubicacion
                FROM incidencia_historial i
                JOIN contenedor c ON c.ID = i.ID_contenedor
                WHERE i.ID_usuario = ?
                ORDER BY i.fecha_ing DESC";
        if ($limite !== null) {
            $sql .= ' LIMIT ' . (int) $limite;
        }
        $st = Database::conexion()->prepare($sql);
        $st->execute([$idUsuario]);
        return array_map([self::class, 'unaDelVecino'], $st->fetchAll());
    }

    /** Detalle de una incidencia del vecino (o null si no es suya). */
    public static function delVecinoPorId(int $idUsuario, int $id): ?array
    {
        $st = Database::conexion()->prepare(
            "SELECT i.ID_incidencia AS id, i.fecha_ing, i.tipo, i.validada, i.estado,
                    i.descripcion, i.foto, i.ID_contenedor AS id_contenedor, c.ubicacion
             FROM incidencia_historial i
             JOIN contenedor c ON c.ID = i.ID_contenedor
             WHERE i.ID_usuario = ? AND i.ID_incidencia = ?"
        );
        $st->execute([$idUsuario, $id]);
        $fila = $st->fetch();
        return $fila ? self::unaDelVecino($fila) : null;
    }

    /** Intervención asociada (para la línea de tiempo del detalle), o null. */
    public static function intervencionDe(int $idIncidencia): ?array
    {
        $st = Database::conexion()->prepare(
            "SELECT iv.fecha_inicio, iv.fecha_fin, iv.tarea_finalizada, iv.ID_cuadrilla
             FROM intervencion iv WHERE iv.ID_incidencia = ? ORDER BY iv.fecha_inicio DESC LIMIT 1"
        );
        $st->execute([$idIncidencia]);
        $f = $st->fetch();
        if (!$f) {
            return null;
        }
        return [
            'fecha_inicio'     => (string) $f['fecha_inicio'],
            'fecha_fin'        => $f['fecha_fin'] !== null ? (string) $f['fecha_fin'] : null,
            'tarea_finalizada' => (string) $f['tarea_finalizada'],
            'id_cuadrilla'     => (int) $f['ID_cuadrilla'],
        ];
    }

    /** Métricas del vecino para /inicio. */
    public static function metricas(int $idUsuario): array
    {
        $pdo = Database::conexion();
        $enCurso = $pdo->prepare("SELECT COUNT(*) FROM incidencia_historial WHERE ID_usuario = ? AND estado = 'en curso'");
        $enCurso->execute([$idUsuario]);
        $fin = $pdo->prepare("SELECT COUNT(*) FROM incidencia_historial WHERE ID_usuario = ? AND estado = 'finalizada' AND YEAR(fecha_ing) = YEAR(CURDATE())");
        $fin->execute([$idUsuario]);
        return [
            'en_curso'          => (int) $enCurso->fetchColumn(),
            'finalizadas_anio'  => (int) $fin->fetchColumn(),
        ];
    }

    /**
     * Alta de incidencia (reporte del vecino). Queda validada='no', estado='en
     * curso' por default del DDL. Sin puntos acá: los 150 se acreditan al VALIDAR.
     * Devuelve el ID nuevo. La foto se adjunta después con guardarFoto().
     */
    public static function crear(int $idUsuario, int $idContenedor, string $tipo, ?string $descripcion): int
    {
        $st = Database::conexion()->prepare(
            "INSERT INTO incidencia_historial (fecha_ing, descripcion, tipo, ID_usuario, ID_contenedor)
             VALUES (NOW(), ?, ?, ?, ?)"
        );
        $st->execute([$descripcion, $tipo, $idUsuario, $idContenedor]);
        return (int) Database::conexion()->lastInsertId();
    }

    public static function guardarFoto(int $id, string $nombreArchivo): void
    {
        $st = Database::conexion()->prepare('UPDATE incidencia_historial SET foto = ? WHERE ID_incidencia = ?');
        $st->execute([$nombreArchivo, $id]);
    }

    private static function unaDelVecino(array $f): array
    {
        return [
            'id'            => (int) $f['id'],
            'fecha_ing'     => (string) $f['fecha_ing'],
            'tipo'          => (string) $f['tipo'],
            'validada'      => (string) $f['validada'],
            'estado'        => (string) $f['estado'],
            'descripcion'   => $f['descripcion'] !== null ? (string) $f['descripcion'] : null,
            'foto'          => $f['foto'] !== null ? (string) $f['foto'] : null,
            'id_contenedor' => (int) $f['id_contenedor'],
            'ubicacion'     => (string) $f['ubicacion'],
        ];
    }
}
