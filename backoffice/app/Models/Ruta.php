<?php
declare(strict_types=1);

/**
 * Model de Rutas del Backoffice (PDO directo a `cathartes`). Alta y edición.
 *
 * No hay acción de baja aparte: `ruta.estado` es ENUM('activa','inactiva') y con
 * pasarla a 'inactiva' alcanza (la tabla no tiene `fecha_baja`/`motivo_baja` y el
 * MER está congelado). Igual una ruta nunca se borra: `contenedor` y `toma` la
 * referencian con ON DELETE RESTRICT.
 *
 * CUADRILLA ASIGNADA — `toma` es un HISTORIAL, no una asignación viva: su PK es
 * (ID_cuadrilla, ID_ruta, fecha) y no tiene columna de estado ni de "liberada".
 * Por lo tanto "la cuadrilla de la ruta" se lee como **la toma más reciente por
 * fecha**. Asignar = insertar una fila nueva con NOW(); la anterior queda como
 * historia, que es justamente para lo que sirve la tabla. No se hace UPDATE ni
 * DELETE sobre `toma`.
 */
class Ruta
{
    private const POR_PAGINA = 25;
    public const ESTADOS = ['activa', 'inactiva'];

    /**
     * SQL de la cuadrilla actual de la ruta. Va como subconsulta correlacionada y
     * no como JOIN: con `toma` histórica un JOIN duplicaría la ruta por cada
     * asignación pasada.
     */
    private const SQL_CUADRILLA_ACTUAL =
        '(SELECT u.nombre FROM toma t JOIN usuario u ON u.ID = t.ID_cuadrilla
           WHERE t.ID_ruta = r.ID ORDER BY t.fecha DESC LIMIT 1)';

    /** @return array{items:array,total:int,pagina:int,paginas:int} */
    public static function listar(array $o): array
    {
        $where = []; $p = [];
        if (in_array($o['estado'] ?? '', self::ESTADOS, true)) {
            $where[] = 'r.estado = ?';
            $p[] = $o['estado'];
        }
        if (trim((string) ($o['zona'] ?? '')) !== '') {
            $where[] = 'r.zona = ?';
            $p[] = trim((string) $o['zona']);
        }
        if ((int) ($o['instalacion'] ?? 0) > 0) {
            $where[] = 'r.ID_instalacion = ?';
            $p[] = (int) $o['instalacion'];
        }
        $q = trim((string) ($o['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(r.nombre LIKE ? OR r.frecuencia LIKE ?)';
            $p[] = '%' . $q . '%';
            $p[] = '%' . $q . '%';
        }
        $sqlW = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $pdo = Database::conexion();
        $stT = $pdo->prepare("SELECT COUNT(*) FROM ruta r {$sqlW}");
        $stT->execute($p);
        $total = (int) $stT->fetchColumn();

        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina  = min(max(1, (int) ($o['pagina'] ?? 1)), $paginas);
        $off     = ($pagina - 1) * self::POR_PAGINA;

        // `contenedores` cuenta los que están en operación; los dados de baja se
        // cuentan aparte para que no inflen el total de la ruta.
        $st = $pdo->prepare(
            "SELECT r.ID, r.nombre, r.zona, r.frecuencia, r.estado,
                    i.nombre AS instalacion,
                    (SELECT COUNT(*) FROM contenedor c WHERE c.ID_ruta = r.ID AND c.estado <> 'dado de baja') AS contenedores,
                    (SELECT COUNT(*) FROM contenedor c WHERE c.ID_ruta = r.ID AND c.estado = 'desbordado')    AS desbordados,
                    " . self::SQL_CUADRILLA_ACTUAL . " AS cuadrilla
             FROM ruta r JOIN instalacion i ON i.ID = r.ID_instalacion
             {$sqlW} ORDER BY r.zona, r.nombre LIMIT {$off}, " . self::POR_PAGINA
        );
        $st->execute($p);
        return ['items' => $st->fetchAll(), 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    public static function porId(int $id): ?array
    {
        // Trae también los datos de la instalación de destino: el mapa del detalle
        // la dibuja como punto de descarga junto a los contenedores.
        $st = Database::conexion()->prepare(
            'SELECT r.*, i.nombre AS instalacion, i.tipo AS instalacion_tipo,
                    i.latitud AS instalacion_lat, i.longitud AS instalacion_lng
             FROM ruta r JOIN instalacion i ON i.ID = r.ID_instalacion WHERE r.ID = ?'
        );
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Instalaciones para el select de destino. */
    public static function instalaciones(): array
    {
        return Database::conexion()
            ->query('SELECT ID, nombre, tipo FROM instalacion ORDER BY nombre')
            ->fetchAll();
    }

    /** Zonas ya usadas, para el filtro (la zona es texto libre, no un ENUM). */
    public static function zonas(): array
    {
        return Database::conexion()
            ->query('SELECT DISTINCT zona FROM ruta ORDER BY zona')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Cuadrillas que pueden tomar una ruta: cuenta activa y en servicio. */
    public static function cuadrillas(): array
    {
        return Database::conexion()->query(
            "SELECT c.ID_usuario AS ID, u.nombre
             FROM cuadrilla c JOIN usuario u ON u.ID = c.ID_usuario
             WHERE u.estado = 'activo' AND c.estado = 'en servicio'
             ORDER BY u.nombre"
        )->fetchAll();
    }

    /** Contenedores de la ruta, para el panel de detalle y el mapa. */
    public static function contenedores(int $id): array
    {
        $st = Database::conexion()->prepare(
            'SELECT ID, ubicacion, tipo, estado, latitud, longitud
             FROM contenedor WHERE ID_ruta = ? ORDER BY ID'
        );
        $st->execute([$id]);
        return $st->fetchAll();
    }

    /**
     * Todos los contenedores en operación, con la ruta a la que pertenecen hoy.
     * Es la lista del panel «por qué contenedores pasa»: se marcan los de esta
     * ruta y se pueden marcar los de otras para traérselos.
     *
     * Los dados de baja no aparecen: no forman parte de ningún recorrido activo.
     */
    public static function contenedoresAsignables(): array
    {
        return Database::conexion()->query(
            "SELECT c.ID, c.ubicacion, c.tipo, c.estado, c.ID_ruta,
                    r.nombre AS ruta, r.zona
             FROM contenedor c JOIN ruta r ON r.ID = c.ID_ruta
             WHERE c.estado <> 'dado de baja'
             ORDER BY c.ubicacion"
        )->fetchAll();
    }

    /** Las demás rutas, para elegir destino de los contenedores que se sacan. */
    public static function otras(int $id): array
    {
        $st = Database::conexion()->prepare(
            'SELECT ID, nombre, zona FROM ruta WHERE ID <> ? ORDER BY zona, nombre'
        );
        $st->execute([$id]);
        return $st->fetchAll();
    }

    /**
     * Define por qué contenedores pasa la ruta.
     *
     * OJO con el esquema: `contenedor.ID_ruta` es **NOT NULL**, así que un
     * contenedor siempre pertenece a alguna ruta — "sacarlo del recorrido" no
     * existe como tal, hay que mandarlo a otra ruta. Por eso el formulario pide un
     * destino cuando se desmarca algo que hoy es de esta ruta. El MER está
     * congelado: no se hace la columna nullable.
     *
     * @param int[] $ids       IDs marcados (los que pasan a ser de esta ruta)
     * @param int   $idDestino Ruta a la que van los desmarcados (0 si no hay)
     * @return array{ok:bool,error?:string,sumados?:int,movidos?:int}
     */
    public static function asignarContenedores(int $idRuta, array $ids, int $idDestino): array
    {
        if (self::porId($idRuta) === null) return ['ok' => false, 'error' => 'Ruta inexistente.'];

        $pdo = Database::conexion();

        // Solo se aceptan contenedores en operación y existentes. Un ID inventado
        // se rechaza en vez de ignorarse.
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        if ($ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $st = $pdo->prepare("SELECT COUNT(*) FROM contenedor WHERE ID IN ($ph) AND estado <> 'dado de baja'");
            $st->execute($ids);
            if ((int) $st->fetchColumn() !== count($ids)) {
                return ['ok' => false, 'error' => 'Alguno de los contenedores no existe o está dado de baja.'];
            }
        }

        // Los que hoy son de esta ruta y quedaron desmarcados necesitan destino.
        $st = $pdo->prepare("SELECT ID FROM contenedor WHERE ID_ruta = ? AND estado <> 'dado de baja'");
        $st->execute([$idRuta]);
        $actuales = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        $sacados  = array_values(array_diff($actuales, $ids));

        if ($sacados && $idDestino <= 0) {
            $cuantos = count($sacados) === 1
                ? 'el contenedor que desmarcaste'
                : 'los ' . count($sacados) . ' contenedores que desmarcaste';
            return ['ok' => false, 'error' => 'Un contenedor siempre pertenece a una ruta (ID_ruta es NOT NULL): elegí a qué ruta pasa ' . $cuantos . '.'];
        }
        if ($sacados) {
            if ($idDestino === $idRuta) {
                return ['ok' => false, 'error' => 'La ruta de destino tiene que ser distinta de esta.'];
            }
            $chk = $pdo->prepare('SELECT 1 FROM ruta WHERE ID = ?');
            $chk->execute([$idDestino]);
            if (!$chk->fetchColumn()) return ['ok' => false, 'error' => 'La ruta de destino no existe.'];
        }

        $sumados = array_values(array_diff($ids, $actuales));

        $pdo->beginTransaction();
        try {
            if ($sumados) {
                $ph = implode(',', array_fill(0, count($sumados), '?'));
                $pdo->prepare("UPDATE contenedor SET ID_ruta = ? WHERE ID IN ($ph)")
                    ->execute(array_merge([$idRuta], $sumados));
            }
            if ($sacados) {
                $ph = implode(',', array_fill(0, count($sacados), '?'));
                $pdo->prepare("UPDATE contenedor SET ID_ruta = ? WHERE ID IN ($ph)")
                    ->execute(array_merge([$idDestino], $sacados));
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'No se pudo guardar el recorrido.'];
        }

        return ['ok' => true, 'sumados' => count($sumados), 'movidos' => count($sacados)];
    }

    /** Historial de tomas de la ruta (más reciente primero). La primera es la vigente. */
    public static function historial(int $id): array
    {
        $st = Database::conexion()->prepare(
            'SELECT t.fecha, t.ID_cuadrilla, u.nombre, c.estado AS operativo
             FROM toma t
             JOIN usuario u   ON u.ID = t.ID_cuadrilla
             JOIN cuadrilla c ON c.ID_usuario = t.ID_cuadrilla
             WHERE t.ID_ruta = ? ORDER BY t.fecha DESC'
        );
        $st->execute([$id]);
        return $st->fetchAll();
    }

    /** @return array{ok:bool,error?:string,id?:int} */
    public static function crear(array $d): array
    {
        $v = self::validar($d);
        if ($v !== null) return ['ok' => false, 'error' => $v];

        $pdo = Database::conexion();
        $pdo->prepare(
            'INSERT INTO ruta (nombre, zona, frecuencia, estado, ID_instalacion) VALUES (?, ?, ?, ?, ?)'
        )->execute([
            trim((string) $d['nombre']), trim((string) $d['zona']), trim((string) $d['frecuencia']),
            $d['estado'], (int) $d['id_instalacion'],
        ]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    }

    /** @return array{ok:bool,error?:string} */
    public static function actualizar(int $id, array $d): array
    {
        if (self::porId($id) === null) return ['ok' => false, 'error' => 'Ruta inexistente.'];
        $v = self::validar($d);
        if ($v !== null) return ['ok' => false, 'error' => $v];

        Database::conexion()->prepare(
            'UPDATE ruta SET nombre = ?, zona = ?, frecuencia = ?, estado = ?, ID_instalacion = ? WHERE ID = ?'
        )->execute([
            trim((string) $d['nombre']), trim((string) $d['zona']), trim((string) $d['frecuencia']),
            $d['estado'], (int) $d['id_instalacion'], $id,
        ]);
        return ['ok' => true];
    }

    /**
     * Asigna una cuadrilla a la ruta: INSERT en `toma` con NOW(). No borra ni
     * actualiza la asignación anterior — queda como historial.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function asignarCuadrilla(int $idRuta, int $idCuadrilla): array
    {
        $r = self::porId($idRuta);
        if ($r === null) return ['ok' => false, 'error' => 'Ruta inexistente.'];
        if ($r['estado'] !== 'activa') {
            return ['ok' => false, 'error' => 'La ruta está inactiva: activala antes de asignarle una cuadrilla.'];
        }
        if ($idCuadrilla <= 0) return ['ok' => false, 'error' => 'Elegí una cuadrilla.'];

        $pdo = Database::conexion();
        $chk = $pdo->prepare(
            "SELECT 1 FROM cuadrilla c JOIN usuario u ON u.ID = c.ID_usuario
              WHERE c.ID_usuario = ? AND u.estado = 'activo' AND c.estado = 'en servicio'"
        );
        $chk->execute([$idCuadrilla]);
        if (!$chk->fetchColumn()) {
            return ['ok' => false, 'error' => 'Esa cuadrilla no existe, no está activa o está fuera de servicio.'];
        }

        // Si ya es la vigente, no se agrega una fila igual: ensuciaría el historial
        // sin cambiar nada.
        $hist = self::historial($idRuta);
        if ($hist && (int) $hist[0]['ID_cuadrilla'] === $idCuadrilla) {
            return ['ok' => false, 'error' => 'Esa cuadrilla ya tiene la ruta asignada.'];
        }

        $pdo->prepare('INSERT INTO toma (ID_cuadrilla, ID_ruta, fecha) VALUES (?, ?, NOW())')
            ->execute([$idCuadrilla, $idRuta]);
        return ['ok' => true];
    }

    private static function validar(array $d): ?string
    {
        if (trim((string) ($d['nombre'] ?? '')) === '')     return 'El nombre es obligatorio.';
        if (trim((string) ($d['zona'] ?? '')) === '')       return 'La zona es obligatoria.';
        if (trim((string) ($d['frecuencia'] ?? '')) === '') return 'La frecuencia es obligatoria.';
        if (!in_array($d['estado'] ?? '', self::ESTADOS, true)) return 'Estado inválido.';

        $idInst = (int) ($d['id_instalacion'] ?? 0);
        if ($idInst <= 0) return 'La instalación de destino es obligatoria.';
        $st = Database::conexion()->prepare('SELECT 1 FROM instalacion WHERE ID = ?');
        $st->execute([$idInst]);
        if (!$st->fetchColumn()) return 'La instalación de destino no existe.';
        return null;
    }
}
