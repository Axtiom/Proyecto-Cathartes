<?php
declare(strict_types=1);

/**
 * Model de Recompensas del Backoffice (PDO directo a `cathartes`). Catálogo de
 * gamificación: alta, edición y quita del catálogo.
 *
 * «RECOMPENSA ACTIVA» = EXISTE EN EL CATÁLOGO. La tabla `recompensa` no tiene
 * columna de estado ni flag de activación (MER congelado), así que desactivar una
 * recompensa es sacarla del catálogo. Es el único lugar del sistema donde hay un
 * DELETE real, y está acotado: `canje` referencia `recompensa` con ON DELETE
 * RESTRICT, así que una recompensa que alguien ya canjeó **no se puede borrar** —
 * el historial de canjes queda intacto. Solo se van las que nunca se canjearon.
 *
 * El costo en puntos (`valor`) es configurable por el admin; los 150 puntos por
 * incidencia validada son fijos y los acredita un TRIGGER de la base
 * (`trg_incidencia_valida_puntos`), no esta pantalla.
 */
class Recompensa
{
    private const POR_PAGINA = 25;
    public const PUNTOS_POR_INCIDENCIA = 150; // regla de dominio fija (CLAUDE.md §4)

    /** @return array{items:array,total:int,pagina:int,paginas:int} */
    public static function listar(array $o): array
    {
        $where = []; $p = [];
        $q = trim((string) ($o['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(r.nombre LIKE ? OR r.descripcion LIKE ?)';
            $p[] = '%' . $q . '%';
            $p[] = '%' . $q . '%';
        }
        // Solo las que ya se canjearon alguna vez / solo las que nunca.
        if (($o['movimiento'] ?? '') === 'canjeadas') {
            $where[] = 'EXISTS (SELECT 1 FROM canje c WHERE c.ID_recompensa = r.ID)';
        }
        if (($o['movimiento'] ?? '') === 'sin canjes') {
            $where[] = 'NOT EXISTS (SELECT 1 FROM canje c WHERE c.ID_recompensa = r.ID)';
        }
        $sqlW = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $orden = ($o['orden'] ?? '') === 'valor' ? 'r.valor ASC' : 'r.nombre ASC';

        $pdo = Database::conexion();
        $stT = $pdo->prepare("SELECT COUNT(*) FROM recompensa r {$sqlW}");
        $stT->execute($p);
        $total = (int) $stT->fetchColumn();

        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina  = min(max(1, (int) ($o['pagina'] ?? 1)), $paginas);
        $off     = ($pagina - 1) * self::POR_PAGINA;

        $st = $pdo->prepare(
            "SELECT r.ID, r.nombre, r.descripcion, r.valor,
                    (SELECT COUNT(*) FROM canje c WHERE c.ID_recompensa = r.ID) AS canjes,
                    (SELECT COALESCE(SUM(c.puntos_gastados), 0) FROM canje c WHERE c.ID_recompensa = r.ID) AS puntos
             FROM recompensa r
             {$sqlW} ORDER BY {$orden} LIMIT {$off}, " . self::POR_PAGINA
        );
        $st->execute($p);
        return ['items' => $st->fetchAll(), 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    public static function porId(int $id): ?array
    {
        $st = Database::conexion()->prepare('SELECT * FROM recompensa WHERE ID = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Canjes de una recompensa (quién y cuándo). Es lo que impide borrarla. */
    public static function canjes(int $id): array
    {
        $st = Database::conexion()->prepare(
            'SELECT c.fecha, c.puntos_gastados, c.ID_vecino, u.nombre
             FROM canje c JOIN usuario u ON u.ID = c.ID_vecino
             WHERE c.ID_recompensa = ? ORDER BY c.fecha DESC'
        );
        $st->execute([$id]);
        return $st->fetchAll();
    }

    /** Últimos canjes del sistema, para la pantalla del catálogo. */
    public static function canjesRecientes(int $limite = 10): array
    {
        $st = Database::conexion()->prepare(
            'SELECT c.fecha, c.puntos_gastados, c.ID_vecino, u.nombre, r.nombre AS recompensa
             FROM canje c
             JOIN usuario u    ON u.ID = c.ID_vecino
             JOIN recompensa r ON r.ID = c.ID_recompensa
             ORDER BY c.fecha DESC LIMIT ' . max(1, min(50, $limite))
        );
        $st->execute();
        return $st->fetchAll();
    }

    /**
     * Métricas de la pantalla. Los puntos OTORGADOS se derivan de la regla de
     * dominio (150 fijos por incidencia validada), que es lo que acredita el
     * trigger: no hay tabla de movimientos de puntos, así que se recalcula.
     *
     * `otorgados - canjeados` tiene que dar el saldo en circulación
     * (`SUM(vecino.saldo)`). Se muestran los dos para que un desvío se vea.
     */
    public static function resumen(): array
    {
        $pdo = Database::conexion();

        $cat = (int) $pdo->query('SELECT COUNT(*) FROM recompensa')->fetchColumn();

        $mes = $pdo->query(
            "SELECT COUNT(*) AS canjes, COALESCE(SUM(puntos_gastados), 0) AS puntos
             FROM canje
             WHERE YEAR(fecha) = YEAR(CURDATE()) AND MONTH(fecha) = MONTH(CURDATE())"
        )->fetch() ?: [];

        $hist = $pdo->query(
            'SELECT COUNT(*) AS canjes, COALESCE(SUM(puntos_gastados), 0) AS puntos FROM canje'
        )->fetch() ?: [];

        $validadas = (int) $pdo->query(
            "SELECT COUNT(*) FROM incidencia_historial WHERE validada = 'si'"
        )->fetchColumn();

        $saldo = (int) $pdo->query('SELECT COALESCE(SUM(saldo), 0) FROM vecino')->fetchColumn();

        $otorgados = $validadas * self::PUNTOS_POR_INCIDENCIA;
        $canjeados = (int) ($hist['puntos'] ?? 0);

        return [
            'catalogo'      => $cat,
            'canjes_mes'    => (int) ($mes['canjes'] ?? 0),
            'puntos_mes'    => (int) ($mes['puntos'] ?? 0),
            'canjes_total'  => (int) ($hist['canjes'] ?? 0),
            'canjeados'     => $canjeados,
            'validadas'     => $validadas,
            'otorgados'     => $otorgados,
            'saldo'         => $saldo,
            'esperado'      => $otorgados - $canjeados,
            'cuadra'        => ($otorgados - $canjeados) === $saldo,
        ];
    }

    /** @return array{ok:bool,error?:string,id?:int} */
    public static function crear(array $d): array
    {
        $v = self::validar($d);
        if ($v !== null) return ['ok' => false, 'error' => $v];

        $pdo = Database::conexion();
        $pdo->prepare('INSERT INTO recompensa (nombre, descripcion, valor) VALUES (?, ?, ?)')
            ->execute([trim((string) $d['nombre']), self::texto($d['descripcion'] ?? ''), (int) $d['valor']]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    }

    /** @return array{ok:bool,error?:string} */
    public static function actualizar(int $id, array $d): array
    {
        if (self::porId($id) === null) return ['ok' => false, 'error' => 'Recompensa inexistente.'];
        $v = self::validar($d);
        if ($v !== null) return ['ok' => false, 'error' => $v];

        Database::conexion()
            ->prepare('UPDATE recompensa SET nombre = ?, descripcion = ?, valor = ? WHERE ID = ?')
            ->execute([trim((string) $d['nombre']), self::texto($d['descripcion'] ?? ''), (int) $d['valor'], $id]);
        return ['ok' => true];
    }

    /**
     * Saca la recompensa del catálogo. Es un DELETE real (no hay columna de
     * estado), y por eso se comprueba antes que no tenga canjes: si los tiene, el
     * historial manda y la recompensa se queda.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function eliminar(int $id): array
    {
        if (self::porId($id) === null) return ['ok' => false, 'error' => 'Recompensa inexistente.'];

        $pdo = Database::conexion();
        $st  = $pdo->prepare('SELECT COUNT(*) FROM canje WHERE ID_recompensa = ?');
        $st->execute([$id]);
        $n = (int) $st->fetchColumn();
        if ($n > 0) {
            return ['ok' => false, 'error' => 'Tiene ' . $n . ' canje' . ($n === 1 ? '' : 's') . ' registrado' . ($n === 1 ? '' : 's') . ': no se saca del catálogo para no romper el historial. Bajale el interés cambiándole el costo o el nombre.'];
        }

        $pdo->prepare('DELETE FROM recompensa WHERE ID = ?')->execute([$id]);
        return ['ok' => true];
    }

    private static function validar(array $d): ?string
    {
        if (trim((string) ($d['nombre'] ?? '')) === '') return 'El nombre es obligatorio.';
        if (mb_strlen(trim((string) $d['nombre'])) > 100) return 'El nombre no puede pasar de 100 caracteres.';
        if (mb_strlen(trim((string) ($d['descripcion'] ?? ''))) > 255) return 'La descripción no puede pasar de 255 caracteres.';

        // El rango se valida contra el límite de la columna (INT), no contra el
        // cast de PHP: un número de 12 dígitos pasa el cast y lo rechaza MySQL.
        if (!entero_en_rango($d['valor'] ?? '', 1, MYSQL_INT_MAX)) {
            return 'El costo en puntos tiene que ser un número entero entre 1 y ' . number_format(MYSQL_INT_MAX, 0, ',', '.') . '.';
        }
        return null;
    }

    /** Descripción opcional: '' -> null (la columna es NULL-able). */
    private static function texto($v): ?string
    {
        $v = trim((string) $v);
        return $v === '' ? null : $v;
    }

    // ------------------------------------------------- universo Vecino ----
    // Los siguientes métodos son los que consumía /api/recompensas, /api/canjes,
    // /api/movimientos y /api/mis-canjes (gamificación del lado del vecino).

    /** Catálogo completo, formato liviano para el vecino. @return list<array> */
    public static function catalogo(): array
    {
        $sql = 'SELECT ID AS id, nombre, descripcion, valor FROM recompensa ORDER BY valor';
        return array_map(static function (array $r): array {
            return [
                'id'          => (int) $r['id'],
                'nombre'      => (string) $r['nombre'],
                'descripcion' => $r['descripcion'] !== null ? (string) $r['descripcion'] : null,
                'valor'       => (int) $r['valor'],
            ];
        }, Database::conexion()->query($sql)->fetchAll());
    }

    /**
     * Canje transaccional: valida saldo suficiente, inserta el canje y descuenta
     * el saldo. Devuelve el nuevo saldo. Lanza con mensaje de dominio si no alcanza.
     */
    public static function canjear(int $idVecino, int $idRecompensa): int
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $r = $pdo->prepare('SELECT valor FROM recompensa WHERE ID = ?');
            $r->execute([$idRecompensa]);
            $valor = $r->fetchColumn();
            if ($valor === false) {
                throw new DomainException('La recompensa no existe.');
            }
            $valor = (int) $valor;

            $s = $pdo->prepare('SELECT saldo FROM vecino WHERE ID_usuario = ? FOR UPDATE');
            $s->execute([$idVecino]);
            $saldo = $s->fetchColumn();
            if ($saldo === false) {
                throw new DomainException('No sos un vecino.');
            }
            $saldo = (int) $saldo;
            if ($saldo < $valor) {
                throw new DomainException('Saldo insuficiente. Te faltan ' . ($valor - $saldo) . ' puntos.');
            }

            $pdo->prepare('INSERT INTO canje (ID_vecino, ID_recompensa, fecha, puntos_gastados) VALUES (?, ?, NOW(), ?)')
                ->execute([$idVecino, $idRecompensa, $valor]);
            $pdo->prepare('UPDATE vecino SET saldo = saldo - ? WHERE ID_usuario = ?')
                ->execute([$valor, $idVecino]);

            $pdo->commit();
            return $saldo - $valor;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Recompensas ya canjeadas por el vecino (para la personalización del perfil). */
    public static function canjesDe(int $idVecino): array
    {
        $sql = 'SELECT r.ID AS id, r.nombre, r.descripcion, r.valor, c.fecha
                FROM canje c JOIN recompensa r ON r.ID = c.ID_recompensa
                WHERE c.ID_vecino = ? ORDER BY c.fecha DESC';
        $st = Database::conexion()->prepare($sql);
        $st->execute([$idVecino]);
        return array_map(static function (array $r): array {
            return [
                'id'          => (int) $r['id'],
                'nombre'      => (string) $r['nombre'],
                'descripcion' => $r['descripcion'] !== null ? (string) $r['descripcion'] : null,
                'valor'       => (int) $r['valor'],
                'fecha'       => (string) $r['fecha'],
            ];
        }, $st->fetchAll());
    }

    /**
     * Libro de movimientos: +150 por cada incidencia validada, −valor por cada
     * canje. Ordenado por fecha (más nuevo primero). Devuelve {items, total}.
     */
    public static function movimientos(int $idVecino): array
    {
        $pdo = Database::conexion();

        $inc = $pdo->prepare(
            "SELECT ID_incidencia AS n, fecha_ing AS fecha FROM incidencia_historial
             WHERE ID_usuario = ? AND validada = 'si'"
        );
        $inc->execute([$idVecino]);

        $items = [];
        foreach ($inc->fetchAll() as $i) {
            $items[] = [
                'fecha'    => (string) $i['fecha'],
                'concepto' => 'Incidencia #' . (int) $i['n'] . ' validada',
                'puntos'   => 150,
            ];
        }

        $can = $pdo->prepare(
            'SELECT c.fecha, r.nombre, c.puntos_gastados FROM canje c
             JOIN recompensa r ON r.ID = c.ID_recompensa WHERE c.ID_vecino = ?'
        );
        $can->execute([$idVecino]);
        foreach ($can->fetchAll() as $c) {
            $items[] = [
                'fecha'    => (string) $c['fecha'],
                'concepto' => 'Canje: ' . (string) $c['nombre'],
                'puntos'   => -((int) $c['puntos_gastados']),
            ];
        }

        usort($items, static function ($a, $b) {
            return strcmp($b['fecha'], $a['fecha']); // desc
        });

        $total = array_sum(array_column($items, 'puntos'));
        return ['items' => $items, 'total' => $total];
    }
}
