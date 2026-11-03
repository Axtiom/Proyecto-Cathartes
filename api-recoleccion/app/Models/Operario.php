<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Notificacion.php';

/**
 * Lo que ve y hace el operario: SU instalación (la de `operario.ID_instalacion`), su maquinaria
 * y su ocupación. Un operario nunca llega a datos de otra instalación: el id sale de su cuenta,
 * nunca de un parámetro del request. `capacidad` y `ocupacion_actual` están en m³.
 */
class Operario
{
    /** La instalación de este operario, o null. */
    public static function instalacion(int $idOperario): ?array
    {
        $st = Database::conexion()->prepare(
            'SELECT i.ID, i.nombre, i.tipo, i.ubicacion, i.latitud, i.longitud, i.capacidad, i.ocupacion_actual
             FROM operario o JOIN instalacion i ON i.ID = o.ID_instalacion WHERE o.ID_usuario = ?'
        );
        $st->execute([$idOperario]);
        $f = $st->fetch();
        if (!$f) return null;
        $cap = (int) $f['capacidad'];
        $oc  = (int) $f['ocupacion_actual'];
        $pct = $cap > 0 ? (int) round($oc * 100 / $cap) : 0;
        return [
            'id' => (int) $f['ID'], 'nombre' => (string) $f['nombre'], 'tipo' => (string) $f['tipo'],
            'ubicacion' => (string) $f['ubicacion'], 'capacidad' => $cap, 'ocupacion_actual' => $oc,
            'porcentaje' => $pct, 'alerta' => $pct >= Notificacion::UMBRAL_INSTALACION,
        ];
    }

    /** @return list<array{id:int,modelo:string,tipo:string,estado:string}> */
    public static function maquinaria(int $idInstalacion): array
    {
        $st = Database::conexion()->prepare('SELECT ID, modelo, tipo, estado FROM maquinaria WHERE ID_instalacion = ? ORDER BY ID');
        $st->execute([$idInstalacion]);
        return array_map(fn ($f) => ['id' => (int) $f['ID'], 'modelo' => (string) $f['modelo'], 'tipo' => (string) $f['tipo'], 'estado' => (string) $f['estado']], $st->fetchAll());
    }

    /** Inicio del operario: la instalación y cuánta maquinaria está funcional. */
    public static function resumen(int $idOperario): array
    {
        $i = self::instalacion($idOperario);
        if ($i === null) return ['instalacion' => null, 'maquinaria' => ['funcional' => 0, 'total' => 0]];
        $maq = self::maquinaria($i['id']);
        return [
            'instalacion' => $i,
            'maquinaria'  => ['funcional' => count(array_filter($maq, fn ($m) => $m['estado'] === 'funcional')), 'total' => count($maq)],
        ];
    }

    /**
     * Registra la ocupación actual (m³). Tiene que ser un entero entre 0 y la capacidad.
     * Si con este valor la instalación cruza el 80 %, se avisa a los administradores.
     * @return array{ok:bool,error?:string,codigo?:int,alerta?:bool}
     */
    public static function registrarOcupacion(int $idOperario, mixed $valor): array
    {
        $i = self::instalacion($idOperario);
        if ($i === null) return ['ok' => false, 'error' => 'No tenés una instalación asignada.', 'codigo' => 409];
        if (!entero_en_rango($valor, 0, MYSQL_INT_MAX)) {
            return ['ok' => false, 'error' => 'La ocupación tiene que ser un número entero entre 0 y ' . number_format(MYSQL_INT_MAX, 0, ',', '.') . ' m³.', 'codigo' => 400];
        }
        $v = (int) $valor;
        if ($v > $i['capacidad']) {
            return ['ok' => false, 'error' => 'La ocupación (' . $v . ' m³) no puede superar la capacidad (' . $i['capacidad'] . ' m³).', 'codigo' => 400];
        }
        Database::conexion()->prepare('UPDATE instalacion SET ocupacion_actual = ? WHERE ID = ?')->execute([$v, $i['id']]);
        $alerta = Notificacion::revisarUmbral($i['id'], $i['ocupacion_actual']);
        return ['ok' => true, 'alerta' => $alerta];
    }
}
