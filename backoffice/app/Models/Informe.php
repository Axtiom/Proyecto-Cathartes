<?php
declare(strict_types=1);

/**
 * Model de Informes (Pantalla 10). Solo LECTURA y solo consultas AGREGADAS: un
 * informe contesta una pregunta operativa, no repite la tabla cruda que ya está en
 * las pantallas 2 a 9.
 *
 * Período: aplica a los informes 1 (Incidencias) y 4 (Rendimiento), que son
 * temporales. Los informes 2 (Ocupación) y 3 (Flota) son foto del estado actual y
 * NO llevan período — cada uno lo dice en su encabezado para que no parezca un
 * olvido.
 *
 * Los promedios se calculan SOLO sobre lo finalizado y siempre viajan con su `n`.
 * Una intervención abierta no tiene `fecha_fin`: contarla como cero falsearía el
 * resultado, y un promedio sobre un caso no es un promedio.
 */
class Informe
{
    public const UMBRAL_OCUPACION = 80;
    private const MESES_GRAFICO   = 6;

    /**
     * Normaliza el período de la query string. Devuelve el par de fechas ya
     * validado (YYYY-MM-DD) y un texto para el encabezado del informe.
     *
     * @return array{desde:string,hasta:string,texto:string}
     */
    public static function periodo(array $g): array
    {
        // La forma correcta no alcanza: `2026-99-99` pasa el regex, MySQL lo toma
        // como fecha inválida y el informe salía en cero diciendo «hasta el
        // 99/99/2026» — un informe silenciosamente equivocado, peor que un error.
        // checkdate() descarta meses y días imposibles (y el 29/02 no bisiesto).
        $ok = function ($v): string {
            $s = (string) $v;
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) return '';
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $s : '';
        };
        $desde = $ok($g['desde'] ?? '');
        $hasta = $ok($g['hasta'] ?? '');

        // Rango invertido: se ordena en vez de devolver vacío, que es lo que el
        // usuario quiso decir.
        if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $f = fn($iso) => implode('/', array_reverse(explode('-', $iso)));
        if ($desde !== '' && $hasta !== '') $texto = 'del ' . $f($desde) . ' al ' . $f($hasta);
        elseif ($desde !== '')              $texto = 'desde el ' . $f($desde);
        elseif ($hasta !== '')              $texto = 'hasta el ' . $f($hasta);
        else                                $texto = 'todo el histórico';

        return ['desde' => $desde, 'hasta' => $hasta, 'texto' => $texto];
    }

    /** Fragmento WHERE del período sobre una columna de fecha. */
    private static function rango(array $p, string $col, array &$par): string
    {
        $w = [];
        if ($p['desde'] !== '') { $w[] = "{$col} >= ?"; $par[] = $p['desde'] . ' 00:00:00'; }
        if ($p['hasta'] !== '') { $w[] = "{$col} <= ?"; $par[] = $p['hasta'] . ' 23:59:59'; }
        return $w ? (' AND ' . implode(' AND ', $w)) : '';
    }

    // ------------------------------------------------------------------ 1 ----
    /** Informe 1 — Incidencias del período: totales, desgloses y resolución. */
    public static function incidencias(array $p): array
    {
        $pdo = Database::conexion();

        $par = [];
        $w   = self::rango($p, 'h.fecha_ing', $par);

        $tot = $pdo->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(h.validada = 'si'), 0) AS validadas,
                    COALESCE(SUM(h.validada = 'no'), 0) AS sin_validar,
                    COALESCE(SUM(h.estado = 'en curso'), 0)   AS en_curso,
                    COALESCE(SUM(h.estado = 'finalizada'), 0) AS finalizadas
             FROM incidencia_historial h WHERE 1=1 {$w}"
        );
        $tot->execute($par);
        $totales = $tot->fetch() ?: [];

        // Desglose por tipo. Se listan los tres tipos del ENUM aunque no tengan
        // filas: un tipo en cero es información, no una ausencia.
        $porTipo = [];
        foreach (['desbordado', 'roto', 'falta de recoleccion'] as $t) $porTipo[$t] = 0;
        $par2 = [];
        $w2   = self::rango($p, 'h.fecha_ing', $par2);
        $st = $pdo->prepare("SELECT h.tipo, COUNT(*) AS n FROM incidencia_historial h WHERE 1=1 {$w2} GROUP BY h.tipo");
        $st->execute($par2);
        foreach ($st as $r) $porTipo[$r['tipo']] = (int) $r['n'];

        // Tiempo hasta la resolución: del ingreso del reporte al cierre de la
        // intervención, SOLO sobre las que efectivamente se cerraron.
        $par3 = [];
        $w3   = self::rango($p, 'h.fecha_ing', $par3);
        $st = $pdo->prepare(
            "SELECT COUNT(*) AS n, AVG(TIMESTAMPDIFF(MINUTE, h.fecha_ing, i.fecha_fin)) AS prom
             FROM incidencia_historial h
             JOIN intervencion i ON i.ID_incidencia = h.ID_incidencia
             WHERE i.tarea_finalizada = 'si' AND i.fecha_fin IS NOT NULL {$w3}"
        );
        $st->execute($par3);
        $res = $st->fetch() ?: [];

        return [
            'total'       => (int) ($totales['total'] ?? 0),
            'validadas'   => (int) ($totales['validadas'] ?? 0),
            'sin_validar' => (int) ($totales['sin_validar'] ?? 0),
            'en_curso'    => (int) ($totales['en_curso'] ?? 0),
            'finalizadas' => (int) ($totales['finalizadas'] ?? 0),
            'por_tipo'    => $porTipo,
            'resueltas_n' => (int) ($res['n'] ?? 0),
            'resolucion'  => $res['prom'] !== null ? (int) round((float) $res['prom']) : null,
        ];
    }

    /**
     * Serie para el gráfico de barras: los últimos MESES_GRAFICO meses, con los
     * meses sin datos en cero. La serie se arma en PHP y no con un GROUP BY solo,
     * porque un GROUP BY omite los meses vacíos y el gráfico mentiría por omisión.
     */
    public static function porMes(): array
    {
        $serie = [];
        for ($i = self::MESES_GRAFICO - 1; $i >= 0; $i--) {
            $t = strtotime('first day of -' . $i . ' month');
            $serie[date('Y-m', $t)] = 0;
        }

        $st = Database::conexion()->query(
            "SELECT DATE_FORMAT(fecha_ing, '%Y-%m') AS mes, COUNT(*) AS n
             FROM incidencia_historial GROUP BY mes"
        );
        foreach ($st as $r) {
            if (array_key_exists($r['mes'], $serie)) $serie[$r['mes']] = (int) $r['n'];
        }

        // Se busca con la clave técnica (YYYY-MM) y se devuelve con la etiqueta
        // legible (MM/YYYY): en seis meses no hay dos etiquetas iguales.
        $salida = [];
        foreach ($serie as $mes => $n) {
            [$a, $m] = explode('-', $mes);
            $salida[$m . '/' . $a] = $n;
        }
        return $salida;
    }

    // ------------------------------------------------------------------ 2 ----
    /** Informe 2 — Ocupación de instalaciones (foto actual, sin período). */
    public static function ocupacion(): array
    {
        $filas = Database::conexion()->query(
            'SELECT ID, nombre, ubicacion, tipo, capacidad, ocupacion_actual
             FROM instalacion ORDER BY (ocupacion_actual / NULLIF(capacidad, 0)) DESC, nombre'
        )->fetchAll();

        foreach ($filas as &$f) {
            $cap = (int) $f['capacidad'];
            $f['pct']   = $cap > 0 ? (int) round((int) $f['ocupacion_actual'] * 100 / $cap) : 0;
            $f['libre'] = max(0, $cap - (int) $f['ocupacion_actual']);
            $f['alta']  = $f['pct'] >= self::UMBRAL_OCUPACION;
        }
        unset($f);

        return $filas;
    }

    // ------------------------------------------------------------------ 3 ----
    /**
     * Informe 3 — Flota por estado (foto actual). Los dados de baja se devuelven
     * aparte, con fecha y motivo: no son parte de la flota operativa.
     *
     * @return array{por_estado:array,operativos:int,baja:array}
     */
    public static function flota(): array
    {
        $pdo = Database::conexion();

        $porEstado = [];
        foreach (['funcional', 'en mantenimiento', 'no funcional'] as $e) $porEstado[$e] = ['n' => 0, 'capacidad' => 0];

        $st = $pdo->query(
            "SELECT estado, COUNT(*) AS n, COALESCE(SUM(capacidad), 0) AS capacidad
             FROM camion WHERE estado <> 'dado de baja' GROUP BY estado"
        );
        foreach ($st as $r) $porEstado[$r['estado']] = ['n' => (int) $r['n'], 'capacidad' => (int) $r['capacidad']];

        $baja = $pdo->query(
            "SELECT c.ID, c.matricula, c.modelo, c.capacidad, c.fecha_baja, c.motivo_baja
             FROM camion c WHERE c.estado = 'dado de baja' ORDER BY c.fecha_baja DESC"
        )->fetchAll();

        return [
            'por_estado' => $porEstado,
            'operativos' => array_sum(array_column($porEstado, 'n')),
            'baja'       => $baja,
        ];
    }

    // ------------------------------------------------------------------ 4 ----
    /**
     * Informe 4 — Rendimiento por cuadrilla, todo desde `intervencion`.
     *
     * LEFT JOIN desde `cuadrilla` a propósito: una cuadrilla SIN intervenciones
     * tiene que aparecer con ceros, no desaparecer — es justamente el caso que un
     * jefe quiere ver.
     *
     * El promedio va solo sobre finalizadas (`tarea_finalizada='si'` y con
     * `fecha_fin`), y se devuelve el `n` para mostrarlo al lado.
     *
     * `cuadra` verifica la identidad Asignadas = Finalizadas + Abiertas. No debería
     * fallar nunca (el ENUM es NOT NULL y solo tiene dos valores), pero si falla hay
     * datos inconsistentes y la vista lo marca en vez de esconderlo.
     */
    public static function rendimiento(array $p): array
    {
        $par = [];
        $w   = self::rango($p, 'i.fecha_inicio', $par);

        // El período va en el ON del LEFT JOIN, no en el WHERE: puesto en el WHERE
        // filtraría también las cuadrillas sin intervenciones y se perderían las
        // filas en cero, que es lo contrario de lo que se busca.
        $st = Database::conexion()->prepare(
            "SELECT c.ID_usuario AS id, u.nombre, u.estado AS cuenta, c.estado AS operativo,
                    COUNT(i.ID_intervencion) AS asignadas,
                    COALESCE(SUM(i.tarea_finalizada = 'si'), 0) AS finalizadas,
                    COALESCE(SUM(i.tarea_finalizada = 'no'), 0) AS abiertas,
                    COUNT(CASE WHEN i.tarea_finalizada = 'si' AND i.fecha_fin IS NOT NULL THEN 1 END) AS prom_n,
                    AVG(CASE WHEN i.tarea_finalizada = 'si' AND i.fecha_fin IS NOT NULL
                             THEN TIMESTAMPDIFF(MINUTE, i.fecha_inicio, i.fecha_fin) END) AS prom
             FROM cuadrilla c
             JOIN usuario u ON u.ID = c.ID_usuario
             LEFT JOIN intervencion i
                    ON i.ID_cuadrilla = c.ID_usuario {$w}
             GROUP BY c.ID_usuario, u.nombre, u.estado, c.estado
             ORDER BY u.nombre"
        );
        $st->execute($par);
        $filas = $st->fetchAll();

        foreach ($filas as &$f) {
            $a = (int) $f['asignadas'];
            $f['tasa']   = $a > 0 ? (int) round((int) $f['finalizadas'] * 100 / $a) : null;
            $f['prom']   = $f['prom'] !== null ? (int) round((float) $f['prom']) : null;
            $f['prom_n'] = (int) $f['prom_n'];
            $f['cuadra'] = $a === ((int) $f['finalizadas'] + (int) $f['abiertas']);
        }
        unset($f);

        return $filas;
    }

    /**
     * Intervenciones con datos contradictorios: abiertas con `fecha_fin`, cerradas
     * sin `fecha_fin`, o cierre anterior al inicio. Si aparece alguna, el informe lo
     * avisa arriba en vez de promediar basura.
     */
    public static function inconsistencias(): array
    {
        return Database::conexion()->query(
            "SELECT ID_intervencion, ID_cuadrilla, fecha_inicio, fecha_fin, tarea_finalizada,
                    CASE
                      WHEN tarea_finalizada = 'no' AND fecha_fin IS NOT NULL THEN 'abierta pero con fecha de fin'
                      WHEN tarea_finalizada = 'si' AND fecha_fin IS NULL     THEN 'finalizada sin fecha de fin'
                      ELSE 'la fecha de fin es anterior al inicio'
                    END AS motivo
             FROM intervencion
             WHERE (tarea_finalizada = 'no' AND fecha_fin IS NOT NULL)
                OR (tarea_finalizada = 'si' AND fecha_fin IS NULL)
                OR (fecha_fin IS NOT NULL AND fecha_fin < fecha_inicio)
             ORDER BY ID_intervencion"
        )->fetchAll();
    }
}
