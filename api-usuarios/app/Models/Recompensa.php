<?php
declare(strict_types=1);

/**
 * Gamificación: catálogo de `recompensa`, `canje` y el libro de movimientos de
 * puntos del vecino. Vía PDO, datos reales de `cathartes`.
 *
 * El saldo lo mantiene la API de Usuarios (esta): el canje descuenta en una
 * transacción. La acreditación de +150 por incidencia validada la hace el TRIGGER
 * de la base (ver CLAUDE.md), no acá.
 *
 * El libro de movimientos lee además `incidencia_historial` (dominio de Gestión)
 * en modo SOLO LECTURA para listar los +150 de cada incidencia validada: es la
 * vista de saldo del vecino y necesita ambos orígenes. Ninguna escritura cruzada.
 */
class Recompensa
{
    /** Catálogo completo. @return list<array{id:int,nombre:string,descripcion:?string,valor:int}> */
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
     * El total DEBE coincidir con vecino.saldo (verificación de la pantalla).
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
