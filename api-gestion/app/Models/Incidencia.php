<?php
declare(strict_types=1);

/**
 * Model de Incidencias de la API de Gestión (PDO directo a `cathartes`).
 *
 * Es el lado del vecino: reportar una incidencia y hacer seguimiento de las
 * propias, scopeado por ID de usuario. La validación, asignación de cuadrilla
 * y el resto del ciclo de vida administrativo vive en la Incidencia del
 * Backoffice — apps separadas, cada una con el modelo que le corresponde a
 * su tarea.
 */
class Incidencia
{

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
     * curso' por default del DDL.
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

    /**
     * Compensación: si después de crear la incidencia falla el guardado de la foto, se borra la
     * fila. La foto es obligatoria (RF-16): una incidencia sin foto no puede quedar registrada.
     * Solo se borra si todavía no tiene nada colgando (intervención ni notificaciones).
     */
    public static function descartarHuerfana(int $id): void
    {
        $pdo = Database::conexion();
        $pdo->prepare('DELETE FROM notificacion WHERE ID_incidencia = ?')->execute([$id]);
        $pdo->prepare(
            'DELETE FROM incidencia_historial WHERE ID_incidencia = ?
               AND NOT EXISTS (SELECT 1 FROM intervencion WHERE ID_incidencia = ?)'
        )->execute([$id, $id]);
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
