<?php
declare(strict_types=1);

/**
 * Notificaciones y alertas (tabla `notificacion`). Una sola clase, idéntica en las cuatro
 * apps (cada app lleva su copia del núcleo): quien produce un evento la crea; api-usuarios
 * la expone para que cada persona lea las suyas; el backoffice y el frontend la muestran.
 *
 * Qué se notifica, a quién y desde dónde:
 *   'nueva incidencia'      -> administradores   (api-gestion, al reportar el vecino)
 *   'incidencia validada'   -> vecino            (backoffice, al validar)
 *   'incidencia asignada'   -> vecino            (backoffice, al asignar cuadrilla)
 *   'intervencion asignada' -> cuadrilla         (backoffice, al asignar cuadrilla)
 *   'incidencia finalizada' -> vecino            (api-recoleccion o backoffice, al cerrar)
 *   'instalacion llena'     -> administradores   (al cruzar el umbral del 80 % de ocupación)
 *
 * Una notificación es un AVISO: nunca bloquea ni deshace la acción que la originó. Si no se
 * pudiera escribir, el error se registra en el log y la acción principal sigue adelante.
 */
class Notificacion
{
    public const UMBRAL_INSTALACION = 80; // % de ocupación que dispara la alerta
    private const MAX_MENSAJE = 255;

    /** Crea una notificación para una persona. @return bool si se pudo escribir */
    public static function crear(int $idUsuario, string $tipo, string $mensaje, ?int $idIncidencia = null): bool
    {
        try {
            Database::conexion()->prepare(
                "INSERT INTO notificacion (ID_usuario, ID_incidencia, tipo, mensaje, fecha, leida)
                 VALUES (?, ?, ?, ?, NOW(), 'no')"
            )->execute([$idUsuario, $idIncidencia, mb_substr($tipo, 0, 50), self::cortar($mensaje)]);
            return true;
        } catch (Throwable $e) {
            error_log('[Notificacion] ' . $e->getMessage());
            return false;
        }
    }

    /** Avisa a TODOS los administradores activos. @return int cuántos avisos se crearon */
    public static function paraAdministradores(string $tipo, string $mensaje, ?int $idIncidencia = null): int
    {
        try {
            $st = Database::conexion()->prepare(
                "INSERT INTO notificacion (ID_usuario, ID_incidencia, tipo, mensaje, fecha, leida)
                 SELECT a.ID_usuario, ?, ?, ?, NOW(), 'no'
                 FROM administrador a JOIN usuario u ON u.ID = a.ID_usuario
                 WHERE u.estado = 'activo'"
            );
            $st->execute([$idIncidencia, mb_substr($tipo, 0, 50), self::cortar($mensaje)]);
            return $st->rowCount();
        } catch (Throwable $e) {
            error_log('[Notificacion] ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Alerta de instalación llena. Se llama DESPUÉS de cambiar la ocupación, con la que había
     * antes: solo avisa al CRUZAR el umbral hacia arriba (de <80 % a >=80 %), no en cada
     * actualización posterior, para no llenar de avisos repetidos a los administradores.
     * @return bool true si se generó la alerta
     */
    public static function revisarUmbral(int $idInstalacion, int $ocupacionAntes): bool
    {
        try {
            $st = Database::conexion()->prepare('SELECT nombre, capacidad, ocupacion_actual FROM instalacion WHERE ID = ?');
            $st->execute([$idInstalacion]);
            $i = $st->fetch();
            if (!$i || (int) $i['capacidad'] <= 0) return false;
            $antes = (int) floor($ocupacionAntes * 100 / (int) $i['capacidad']);
            $ahora = (int) floor((int) $i['ocupacion_actual'] * 100 / (int) $i['capacidad']);
            if ($antes >= self::UMBRAL_INSTALACION || $ahora < self::UMBRAL_INSTALACION) return false;
            return self::paraAdministradores(
                'instalacion llena',
                'La instalación «' . $i['nombre'] . '» llegó al ' . $ahora . ' % de su capacidad (alerta del ' . self::UMBRAL_INSTALACION . ' %).'
            ) > 0;
        } catch (Throwable $e) {
            error_log('[Notificacion] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Las notificaciones de UNA persona, las más nuevas primero. Nunca muestra las de otra.
     * @return list<array{id:int,tipo:string,mensaje:string,fecha:string,leida:bool,id_incidencia:?int}>
     */
    public static function listar(int $idUsuario, bool $soloNoLeidas = false, int $limite = 50): array
    {
        $sql = 'SELECT ID, tipo, mensaje, fecha, leida, ID_incidencia FROM notificacion WHERE ID_usuario = ?'
             . ($soloNoLeidas ? " AND leida = 'no'" : '')
             . ' ORDER BY fecha DESC, ID DESC LIMIT ' . max(1, min(200, $limite));
        $st = Database::conexion()->prepare($sql);
        $st->execute([$idUsuario]);
        return array_map(fn ($f) => [
            'id' => (int) $f['ID'], 'tipo' => (string) $f['tipo'], 'mensaje' => (string) $f['mensaje'],
            'fecha' => (string) $f['fecha'], 'leida' => $f['leida'] === 'si',
            'id_incidencia' => $f['ID_incidencia'] !== null ? (int) $f['ID_incidencia'] : null,
        ], $st->fetchAll());
    }

    public static function contarNoLeidas(int $idUsuario): int
    {
        $st = Database::conexion()->prepare("SELECT COUNT(*) FROM notificacion WHERE ID_usuario = ? AND leida = 'no'");
        $st->execute([$idUsuario]);
        return (int) $st->fetchColumn();
    }

    /** Marca UNA como leída, solo si es de esa persona. @return bool false si no existe o es de otro */
    public static function marcarLeida(int $id, int $idUsuario): bool
    {
        $st = Database::conexion()->prepare('SELECT 1 FROM notificacion WHERE ID = ? AND ID_usuario = ?');
        $st->execute([$id, $idUsuario]);
        if (!$st->fetchColumn()) return false;
        Database::conexion()->prepare("UPDATE notificacion SET leida = 'si' WHERE ID = ? AND ID_usuario = ?")->execute([$id, $idUsuario]);
        return true;
    }

    /** Marca todas las de esa persona. @return int cuántas cambiaron */
    public static function marcarTodas(int $idUsuario): int
    {
        $st = Database::conexion()->prepare("UPDATE notificacion SET leida = 'si' WHERE ID_usuario = ? AND leida = 'no'");
        $st->execute([$idUsuario]);
        return $st->rowCount();
    }

    private static function cortar(string $s): string
    {
        $s = trim($s);
        return mb_strlen($s) > self::MAX_MENSAJE ? mb_substr($s, 0, self::MAX_MENSAJE - 1) . '…' : $s;
    }
}
