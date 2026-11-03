<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Notificacion.php';

/**
 * Datos y reglas de la cuadrilla en territorio (PDO directo a `cathartes`).
 * Datos reales, nunca demo: sin datos -> arreglos vacíos o null.
 *
 * Regla común: toda operación se hace SOBRE LO QUE ES DE ESA CUADRILLA. Una intervención o
 * una ruta ajena se trata como inexistente (404) para no revelar que existe.
 *
 * "La ruta actual" de una cuadrilla es su toma MÁS RECIENTE (RN10: `toma` es un historial).
 * Los métodos devuelven ['ok'=>bool, 'error'?, 'codigo'?] donde corresponde; el Controller
 * los traduce a HTTP (400 validación, 403 permiso, 404, 409 estado).
 */
class Recoleccion
{
    public const ESTADOS_CONTENEDOR = ['funcional', 'no funcional', 'desbordado']; // 'dado de baja' es del administrador

    private static function falla(string $m, int $c = 400): array
    {
        return ['ok' => false, 'error' => $m, 'codigo' => $c];
    }

    // ------------------------------------------------------------------ rutas
    /**
     * Rutas que tomó una cuadrilla (tabla `toma`), con la instalación de destino.
     * Se conserva tal cual para GET /cuadrilla/ruta.
     * @return list<array{id:int,nombre:string,frecuencia:string,instalacion:string,fecha:string}>
     */
    public static function rutasDeCuadrilla(int $idCuadrilla): array
    {
        $sql = 'SELECT r.ID AS id, r.nombre, r.frecuencia, ins.nombre AS instalacion, t.fecha
                FROM toma t
                JOIN ruta        r   ON r.ID  = t.ID_ruta
                JOIN instalacion ins ON ins.ID = r.ID_instalacion
                WHERE t.ID_cuadrilla = ?
                ORDER BY t.fecha DESC';
        $st = Database::conexion()->prepare($sql);
        $st->execute([$idCuadrilla]);
        $out = [];
        foreach ($st->fetchAll() as $f) {
            $out[] = [
                'id'          => (int) $f['id'],
                'nombre'      => (string) $f['nombre'],
                'frecuencia'  => (string) $f['frecuencia'],
                'instalacion' => (string) $f['instalacion'],
                'fecha'       => (string) $f['fecha'],
            ];
        }
        return $out;
    }

    /** La ruta vigente: la toma más reciente. null si todavía no tomó ninguna. */
    public static function rutaActiva(int $idC): ?array
    {
        $st = Database::conexion()->prepare(
            'SELECT r.ID, r.nombre, r.zona, r.frecuencia, r.estado, t.fecha AS fecha_toma
             FROM toma t JOIN ruta r ON r.ID = t.ID_ruta
             WHERE t.ID_cuadrilla = ? ORDER BY t.fecha DESC LIMIT 1'
        );
        $st->execute([$idC]);
        $f = $st->fetch();
        return $f ? self::ruta($f) : null;
    }

    private static function ruta(array $f): array
    {
        return [
            'id' => (int) $f['ID'], 'nombre' => (string) $f['nombre'], 'zona' => (string) $f['zona'],
            'frecuencia' => (string) $f['frecuencia'], 'estado' => (string) $f['estado'],
            'fecha_toma' => (string) $f['fecha_toma'],
        ];
    }

    /**
     * Contenedores de una ruta (sin los dados de baja) y si esta cuadrilla ya los verificó HOY.
     * @return list<array>
     */
    public static function contenedoresDeRuta(int $idRuta, int $idC): array
    {
        $st = Database::conexion()->prepare(
            "SELECT c.ID, c.ubicacion, c.tipo, c.estado, c.latitud, c.longitud,
                    EXISTS (SELECT 1 FROM verificacion v
                            WHERE v.ID_contenedor = c.ID AND v.ID_usuario = ? AND v.fecha >= CURDATE()) AS verificado_hoy
             FROM contenedor c
             WHERE c.ID_ruta = ? AND c.estado <> 'dado de baja'
             ORDER BY c.ID"
        );
        $st->execute([$idC, $idRuta]);
        return array_map(fn ($f) => [
            'id' => (int) $f['ID'], 'ubicacion' => (string) $f['ubicacion'], 'tipo' => (string) $f['tipo'],
            'estado' => (string) $f['estado'],
            'latitud' => $f['latitud'] !== null ? (float) $f['latitud'] : null,
            'longitud' => $f['longitud'] !== null ? (float) $f['longitud'] : null,
            'verificado_hoy' => (bool) $f['verificado_hoy'],
        ], $st->fetchAll());
    }

    /**
     * Todas las rutas que tomó (una vez cada una, con su toma más reciente), la más nueva primero.
     * @return list<array>
     */
    public static function rutas(int $idC): array
    {
        $st = Database::conexion()->prepare(
            'SELECT r.ID, r.nombre, r.zona, r.frecuencia, r.estado, MAX(t.fecha) AS fecha_toma
             FROM toma t JOIN ruta r ON r.ID = t.ID_ruta
             WHERE t.ID_cuadrilla = ?
             GROUP BY r.ID, r.nombre, r.zona, r.frecuencia, r.estado
             ORDER BY fecha_toma DESC'
        );
        $st->execute([$idC]);
        $out = [];
        foreach ($st->fetchAll() as $f) {
            $r = self::ruta($f);
            $r['contenedores'] = self::contenedoresDeRuta($r['id'], $idC);
            $r['verificados']  = count(array_filter($r['contenedores'], fn ($c) => $c['verificado_hoy']));
            $out[] = $r;
        }
        return $out;
    }

    // ---------------------------------------------------------------- resumen
    /**
     * Inicio de la cuadrilla: métricas, ruta vigente y las intervenciones más urgentes.
     * `rutas_hoy` = rutas ACTIVAS que tomó (toma es un historial: no hay una fecha de vencimiento).
     */
    public static function resumen(int $idC): array
    {
        $pdo = Database::conexion();
        $n = function (string $sql, array $p) use ($pdo): int {
            $st = $pdo->prepare($sql);
            $st->execute($p);
            return (int) $st->fetchColumn();
        };

        $activa = self::rutaActiva($idC);
        $ruta = null;
        $aVerificar = 0;
        if ($activa !== null) {
            $cont = self::contenedoresDeRuta($activa['id'], $idC);
            $verif = count(array_filter($cont, fn ($c) => $c['verificado_hoy']));
            $aVerificar = count($cont) - $verif;
            $ruta = $activa + ['verificados' => $verif, 'total_contenedores' => count($cont)];
        }

        $st = $pdo->prepare(
            "SELECT i.ID_intervencion AS id_intervencion, i.ID_incidencia AS id_incidencia, h.tipo,
                    i.fecha_inicio, c.ubicacion, c.estado AS estado_contenedor
             FROM intervencion i
             JOIN incidencia_historial h ON h.ID_incidencia = i.ID_incidencia
             JOIN contenedor c ON c.ID = h.ID_contenedor
             WHERE i.ID_cuadrilla = ? AND i.tarea_finalizada = 'no'
             ORDER BY CASE c.estado WHEN 'desbordado' THEN 0 WHEN 'no funcional' THEN 1 ELSE 2 END, i.fecha_inicio ASC
             LIMIT 5"
        );
        $st->execute([$idC]);
        $urgentes = array_map(fn ($f) => [
            'id_intervencion' => (int) $f['id_intervencion'], 'id_incidencia' => (int) $f['id_incidencia'],
            'tipo' => (string) $f['tipo'], 'fecha_inicio' => (string) $f['fecha_inicio'],
            'ubicacion' => (string) $f['ubicacion'], 'estado_contenedor' => (string) $f['estado_contenedor'],
        ], $st->fetchAll());

        return [
            'metricas' => [
                'rutas_hoy' => $n("SELECT COUNT(DISTINCT t.ID_ruta) FROM toma t JOIN ruta r ON r.ID = t.ID_ruta
                                   WHERE t.ID_cuadrilla = ? AND r.estado = 'activa'", [$idC]),
                'intervenciones_pendientes' => $n("SELECT COUNT(*) FROM intervencion WHERE ID_cuadrilla = ? AND tarea_finalizada = 'no'", [$idC]),
                'contenedores_a_verificar'  => $aVerificar,
            ],
            'ruta_activa' => $ruta,
            'urgentes'    => $urgentes,
        ];
    }

    // ------------------------------------------------------------------- mapa
    /**
     * Mapa operativo: los contenedores de una ruta y las incidencias abiertas de la cuadrilla en ellos.
     * Sin $idRuta usa la ruta vigente. @return array|null null si pidió una ruta que no tomó
     */
    public static function mapa(int $idC, ?int $idRuta): ?array
    {
        if ($idRuta !== null && $idRuta > 0) {
            $st = Database::conexion()->prepare(
                'SELECT r.ID, r.nombre, r.zona, r.frecuencia, r.estado, MAX(t.fecha) AS fecha_toma
                 FROM toma t JOIN ruta r ON r.ID = t.ID_ruta
                 WHERE t.ID_cuadrilla = ? AND r.ID = ? GROUP BY r.ID, r.nombre, r.zona, r.frecuencia, r.estado'
            );
            $st->execute([$idC, $idRuta]);
            $f = $st->fetch();
            if (!$f) return null;
            $ruta = self::ruta($f);
        } else {
            $ruta = self::rutaActiva($idC);
            if ($ruta === null) return ['ruta' => null, 'contenedores' => [], 'incidencias' => []];
        }

        $st = Database::conexion()->prepare(
            "SELECT h.ID_incidencia, h.tipo, c.ID AS id_contenedor, c.ubicacion, c.latitud, c.longitud
             FROM intervencion i
             JOIN incidencia_historial h ON h.ID_incidencia = i.ID_incidencia
             JOIN contenedor c ON c.ID = h.ID_contenedor
             WHERE i.ID_cuadrilla = ? AND i.tarea_finalizada = 'no' AND c.ID_ruta = ?
               AND c.latitud IS NOT NULL AND c.longitud IS NOT NULL"
        );
        $st->execute([$idC, $ruta['id']]);
        $inc = array_map(fn ($f) => [
            'id' => (int) $f['ID_incidencia'], 'tipo' => 'incidencia', 'estado' => 'en curso',
            'latitud' => (float) $f['latitud'], 'longitud' => (float) $f['longitud'],
            'titulo' => $f['tipo'] . ' · ' . $f['ubicacion'], 'ubicacion' => 'contenedor #' . $f['id_contenedor'],
        ], $st->fetchAll());

        return [
            'ruta' => ['id' => $ruta['id'], 'nombre' => $ruta['nombre'], 'zona' => $ruta['zona']],
            'contenedores' => self::contenedoresDeRuta($ruta['id'], $idC),
            'incidencias'  => $inc,
        ];
    }

    /**
     * Marca un contenedor como recorrido HOY. El contenedor tiene que estar en una ruta que
     * esta cuadrilla tomó. Es idempotente: verificarlo dos veces el mismo día no duplica nada.
     */
    public static function verificar(int $idC, int $idContenedor): array
    {
        if ($idContenedor <= 0) return self::falla('Falta el contenedor.');
        $pdo = Database::conexion();
        $st = $pdo->prepare(
            'SELECT c.estado FROM contenedor c
             WHERE c.ID = ? AND EXISTS (SELECT 1 FROM toma t WHERE t.ID_cuadrilla = ? AND t.ID_ruta = c.ID_ruta)'
        );
        $st->execute([$idContenedor, $idC]);
        $estado = $st->fetchColumn();
        if ($estado === false) return self::falla('Ese contenedor no está en ninguna de tus rutas.', 403);
        if ($estado === 'dado de baja') return self::falla('El contenedor está dado de baja.', 409);

        $st = $pdo->prepare('SELECT 1 FROM verificacion WHERE ID_usuario = ? AND ID_contenedor = ? AND fecha >= CURDATE() LIMIT 1');
        $st->execute([$idC, $idContenedor]);
        if ($st->fetchColumn()) return ['ok' => true, 'ya_verificado' => true];

        // INSERT IGNORE: la PK incluye la fecha al segundo; dos toques en el mismo segundo no deben dar 500.
        $pdo->prepare('INSERT IGNORE INTO verificacion (ID_usuario, ID_contenedor, fecha) VALUES (?, ?, NOW())')
            ->execute([$idC, $idContenedor]);
        return ['ok' => true, 'ya_verificado' => false];
    }

    // -------------------------------------------------------- intervenciones
    /**
     * Abiertas primero y, dentro de cada grupo, las más nuevas. Ojo: un ENUM('si','no') se ordena
     * por POSICIÓN ('si' < 'no'), por eso se ordena por la comparación y no por la columna.
     * @return list<array>
     */
    public static function intervenciones(int $idC): array
    {
        $st = Database::conexion()->prepare(
            "SELECT i.ID_intervencion, i.ID_incidencia, h.tipo, i.fecha_inicio, i.fecha_fin, i.tarea_finalizada,
                    c.ubicacion, c.estado AS estado_contenedor
             FROM intervencion i
             JOIN incidencia_historial h ON h.ID_incidencia = i.ID_incidencia
             JOIN contenedor c ON c.ID = h.ID_contenedor
             WHERE i.ID_cuadrilla = ?
             ORDER BY (i.tarea_finalizada = 'si') ASC, i.fecha_inicio DESC"
        );
        $st->execute([$idC]);
        return array_map(fn ($f) => [
            'id_intervencion' => (int) $f['ID_intervencion'], 'id_incidencia' => (int) $f['ID_incidencia'],
            'tipo' => (string) $f['tipo'], 'fecha_inicio' => (string) $f['fecha_inicio'], 'fecha_fin' => $f['fecha_fin'],
            'tarea_finalizada' => (string) $f['tarea_finalizada'], 'ubicacion' => (string) $f['ubicacion'],
            'estado_contenedor' => (string) $f['estado_contenedor'],
        ], $st->fetchAll());
    }

    /** Detalle + repuestos usados. null si no existe O NO ES DE ESTA CUADRILLA. */
    public static function intervencion(int $idC, int $idInt): ?array
    {
        $pdo = Database::conexion();
        $st = $pdo->prepare(
            'SELECT i.ID_intervencion, i.ID_incidencia, i.fecha_inicio, i.fecha_fin, i.tarea_finalizada,
                    h.tipo, h.descripcion, h.estado AS estado_incidencia,
                    c.ID AS id_contenedor, c.ubicacion, c.estado AS estado_contenedor, u.nombre AS vecino
             FROM intervencion i
             JOIN incidencia_historial h ON h.ID_incidencia = i.ID_incidencia
             JOIN contenedor c ON c.ID = h.ID_contenedor
             JOIN usuario u ON u.ID = h.ID_usuario
             WHERE i.ID_intervencion = ? AND i.ID_cuadrilla = ?'
        );
        $st->execute([$idInt, $idC]);
        $f = $st->fetch();
        if (!$f) return null;

        $st = $pdo->prepare(
            'SELECT r.nombre, r.tipo, us.cuantos FROM usa us JOIN repuestos r ON r.ID = us.ID_repuesto
             WHERE us.ID_intervencion = ? ORDER BY r.nombre'
        );
        $st->execute([$idInt]);
        return [
            'intervencion' => [
                'id_intervencion' => (int) $f['ID_intervencion'], 'id_incidencia' => (int) $f['ID_incidencia'],
                'tipo' => (string) $f['tipo'], 'descripcion' => $f['descripcion'], 'vecino' => (string) $f['vecino'],
                'id_contenedor' => (int) $f['id_contenedor'], 'ubicacion' => (string) $f['ubicacion'],
                'estado_contenedor' => (string) $f['estado_contenedor'], 'estado_incidencia' => (string) $f['estado_incidencia'],
                'fecha_inicio' => (string) $f['fecha_inicio'], 'fecha_fin' => $f['fecha_fin'],
                'tarea_finalizada' => (string) $f['tarea_finalizada'],
            ],
            'repuestos' => array_map(fn ($r) => ['nombre' => (string) $r['nombre'], 'tipo' => (string) $r['tipo'], 'cuantos' => (int) $r['cuantos']], $st->fetchAll()),
        ];
    }

    /** Inventario de repuestos con stock. */
    public static function repuestos(): array
    {
        return array_map(fn ($f) => [
            'id' => (int) $f['ID'], 'nombre' => (string) $f['nombre'], 'tipo' => (string) $f['tipo'], 'cantidad' => (int) $f['cantidad'],
        ], Database::conexion()->query('SELECT ID, nombre, tipo, cantidad FROM repuestos WHERE cantidad > 0 ORDER BY nombre')->fetchAll());
    }

    /** Una intervención PROPIA y todavía abierta, o el error que corresponde. @return array{ok:bool,fila?:array,error?:string,codigo?:int} */
    private static function abierta(int $idC, int $idInt): array
    {
        if ($idInt <= 0) return self::falla('Falta la intervención.');
        $st = Database::conexion()->prepare(
            'SELECT i.ID_intervencion, i.ID_incidencia, i.tarea_finalizada, h.ID_contenedor, h.ID_usuario
             FROM intervencion i JOIN incidencia_historial h ON h.ID_incidencia = i.ID_incidencia
             WHERE i.ID_intervencion = ? AND i.ID_cuadrilla = ?'
        );
        $st->execute([$idInt, $idC]);
        $f = $st->fetch();
        if (!$f) return self::falla('Intervención inexistente.', 404);
        if ($f['tarea_finalizada'] === 'si') return self::falla('La intervención ya está finalizada.', 409);
        return ['ok' => true, 'fila' => $f];
    }

    /** Cambia el estado del contenedor de la incidencia (no puede darlo de baja: eso es del administrador). */
    public static function cambiarEstadoContenedor(int $idC, int $idInt, string $estado): array
    {
        $a = self::abierta($idC, $idInt);
        if (!$a['ok']) return $a;
        if (!in_array($estado, self::ESTADOS_CONTENEDOR, true)) return self::falla('Estado inválido (funcional, no funcional o desbordado).');
        $pdo = Database::conexion();
        $st = $pdo->prepare('SELECT estado FROM contenedor WHERE ID = ?');
        $st->execute([$a['fila']['ID_contenedor']]);
        if ($st->fetchColumn() === 'dado de baja') return self::falla('El contenedor está dado de baja.', 409);
        $pdo->prepare('UPDATE contenedor SET estado = ? WHERE ID = ?')->execute([$estado, $a['fila']['ID_contenedor']]);
        return ['ok' => true];
    }

    /**
     * Registra repuestos usados y los DESCUENTA del inventario, todo en una transacción.
     * Si no alcanza el stock se rechaza (409). Usar el mismo repuesto dos veces suma la cantidad.
     */
    public static function usarRepuesto(int $idC, int $idInt, int $idRepuesto, int $cuantos): array
    {
        $a = self::abierta($idC, $idInt);
        if (!$a['ok']) return $a;
        if ($cuantos < 1 || $cuantos > 100000) return self::falla('La cantidad tiene que ser un entero entre 1 y 100.000.');
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT nombre, cantidad FROM repuestos WHERE ID = ? FOR UPDATE');
            $st->execute([$idRepuesto]);
            $r = $st->fetch();
            if (!$r) { $pdo->rollBack(); return self::falla('El repuesto no existe.', 404); }
            if ((int) $r['cantidad'] < $cuantos) {
                $pdo->rollBack();
                return self::falla('No alcanza el stock de «' . $r['nombre'] . '»: quedan ' . (int) $r['cantidad'] . '.', 409);
            }
            $pdo->prepare('UPDATE repuestos SET cantidad = cantidad - ? WHERE ID = ?')->execute([$cuantos, $idRepuesto]);
            $pdo->prepare('INSERT INTO usa (ID_intervencion, ID_repuesto, cuantos) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE cuantos = cuantos + VALUES(cuantos)')->execute([$idInt, $idRepuesto, $cuantos]);
            $pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Cierra la tarea: la intervención queda finalizada con fecha de fin, la incidencia pasa a
     * 'finalizada' y se avisa al vecino que la reportó. Es el paso que cierra el ciclo.
     */
    public static function finalizar(int $idC, int $idInt): array
    {
        $a = self::abierta($idC, $idInt);
        if (!$a['ok']) return $a;
        $f = $a['fila'];
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE intervencion SET tarea_finalizada = 'si', fecha_fin = NOW() WHERE ID_intervencion = ? AND tarea_finalizada = 'no'")->execute([$idInt]);
            $pdo->prepare("UPDATE incidencia_historial SET estado = 'finalizada' WHERE ID_incidencia = ?")->execute([$f['ID_incidencia']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        Notificacion::crear((int) $f['ID_usuario'], 'incidencia finalizada',
            'Tu incidencia #' . $f['ID_incidencia'] . ' fue resuelta por la cuadrilla.', (int) $f['ID_incidencia']);
        return ['ok' => true];
    }
}
