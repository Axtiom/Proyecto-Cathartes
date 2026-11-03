<?php
declare(strict_types=1);

/**
 * Model del Dashboard (Pantalla 1). Solo LECTURA: agrega sobre las tablas que las
 * otras nueve pantallas administran. Se llama `Panel` y no `Dashboard` para no
 * chocar con el nombre de la sección en el sidebar ni con la vista.
 *
 * Criterio de las métricas: cada tarjeta responde «¿hay algo que hacer?». Las que
 * marcan trabajo pendiente llevan link a la pantalla donde se resuelve; las
 * informativas, no. No se inventan indicadores que el esquema no soporte.
 */
class Panel
{
    public const UMBRAL_INSTALACION = 80; // % de llenado que dispara alerta

    /** Todas las métricas de la pantalla, en una sola pasada por tabla. */
    public static function metricas(): array
    {
        $pdo = Database::conexion();
        $uno = fn(string $sql) => (int) $pdo->query($sql)->fetchColumn();

        return [
            // --- Requieren acción del administrador ---
            'usuarios_pendientes' => $uno("SELECT COUNT(*) FROM usuario WHERE estado = 'no activo'"),
            'sin_validar'         => $uno("SELECT COUNT(*) FROM incidencia_historial WHERE validada = 'no'"),
            'desbordados'         => $uno("SELECT COUNT(*) FROM contenedor WHERE estado = 'desbordado'"),
            'instalaciones_llenas' => $uno(
                'SELECT COUNT(*) FROM instalacion
                  WHERE capacidad > 0 AND (ocupacion_actual * 100 / capacidad) >= ' . self::UMBRAL_INSTALACION
            ),

            // --- Estado de la operación ---
            'sin_asignar' => $uno(
                "SELECT COUNT(*) FROM incidencia_historial h
                  WHERE h.estado = 'en curso'
                    AND NOT EXISTS (SELECT 1 FROM intervencion i WHERE i.ID_incidencia = h.ID_incidencia)"
            ),
            'en_curso'      => $uno("SELECT COUNT(*) FROM incidencia_historial WHERE estado = 'en curso'"),
            'cuadrillas'    => $uno("SELECT COUNT(*) FROM cuadrilla c JOIN usuario u ON u.ID = c.ID_usuario
                                      WHERE c.estado = 'en servicio' AND u.estado = 'activo'"),
            'cuadrillas_tot' => $uno('SELECT COUNT(*) FROM cuadrilla'),
            'rutas_activas' => $uno("SELECT COUNT(*) FROM ruta WHERE estado = 'activa'"),
            'rutas_tot'     => $uno('SELECT COUNT(*) FROM ruta'),
            'camiones_ok'   => $uno("SELECT COUNT(*) FROM camion WHERE estado = 'funcional'"),
            'camiones_tot'  => $uno("SELECT COUNT(*) FROM camion WHERE estado <> 'dado de baja'"),
            'contenedores'  => $uno("SELECT COUNT(*) FROM contenedor WHERE estado <> 'dado de baja'"),
        ];
    }

    /**
     * Incidencias que esperan al administrador: primero las que no están validadas,
     * y dentro de eso las más viejas primero — una incidencia vieja sin validar es
     * peor que una de hoy.
     */
    public static function pendientes(int $limite = 8): array
    {
        $st = Database::conexion()->prepare(
            "SELECT h.ID_incidencia, h.fecha_ing, h.tipo, h.validada, h.estado,
                    c.ubicacion, u.nombre AS vecino,
                    EXISTS (SELECT 1 FROM intervencion i WHERE i.ID_incidencia = h.ID_incidencia) AS asignada
             FROM incidencia_historial h
             JOIN contenedor c ON c.ID = h.ID_contenedor
             JOIN usuario u    ON u.ID = h.ID_usuario
             WHERE h.validada = 'no' OR h.estado = 'en curso'
             ORDER BY h.validada ASC, h.fecha_ing ASC
             LIMIT " . max(1, min(50, $limite))
        );
        $st->execute();
        return $st->fetchAll();
    }

    /**
     * Puntos del mapa: contenedores + instalaciones con coordenadas cargadas.
     * Los dados de baja se incluyen (el módulo los pinta en gris): en un mapa
     * operativo conviene ver que ahí ya no hay contenedor.
     */
    public static function puntos(): array
    {
        $pdo = Database::conexion();
        $puntos = [];

        $st = $pdo->query(
            'SELECT c.ID, c.ubicacion, c.tipo, c.estado, c.latitud, c.longitud,
                    r.nombre AS ruta, r.zona
             FROM contenedor c JOIN ruta r ON r.ID = c.ID_ruta
             WHERE c.latitud IS NOT NULL AND c.longitud IS NOT NULL'
        );
        foreach ($st as $c) {
            $puntos[] = [
                'id' => (int) $c['ID'], 'tipo' => 'contenedor', 'estado' => $c['estado'],
                'latitud' => (float) $c['latitud'], 'longitud' => (float) $c['longitud'],
                'titulo' => $c['ubicacion'],
                'ubicacion' => $c['tipo'] . ' · ' . $c['zona'] . ' · ' . $c['ruta'],
            ];
        }

        $st = $pdo->query(
            'SELECT ID, nombre, ubicacion, tipo, latitud, longitud, capacidad, ocupacion_actual
             FROM instalacion WHERE latitud IS NOT NULL AND longitud IS NOT NULL'
        );
        foreach ($st as $i) {
            $pc = (int) $i['capacidad'] > 0
                ? (int) round((int) $i['ocupacion_actual'] * 100 / (int) $i['capacidad'])
                : 0;
            $puntos[] = [
                'id' => (int) $i['ID'], 'tipo' => 'instalacion', 'estado' => $i['tipo'],
                'latitud' => (float) $i['latitud'], 'longitud' => (float) $i['longitud'],
                'titulo' => $i['nombre'],
                'ubicacion' => $i['ubicacion'] . ' · ' . $pc . '% de ocupación',
            ];
        }

        // Incidencias ABIERTAS, en el punto de su contenedor. Ámbar = todavía sin validar; rojo = validada y en curso.
        $st = $pdo->query(
            "SELECT h.ID_incidencia, h.tipo, h.validada, c.ubicacion, c.latitud, c.longitud
             FROM incidencia_historial h JOIN contenedor c ON c.ID = h.ID_contenedor
             WHERE h.estado = 'en curso' AND c.latitud IS NOT NULL AND c.longitud IS NOT NULL"
        );
        foreach ($st as $i) {
            $puntos[] = [
                'id' => (int) $i['ID_incidencia'], 'tipo' => 'incidencia',
                'estado' => $i['validada'] === 'si' ? 'en curso' : 'sin validar',
                'latitud' => (float) $i['latitud'], 'longitud' => (float) $i['longitud'],
                'titulo' => $i['tipo'] . ' · ' . $i['ubicacion'],
                'ubicacion' => $i['validada'] === 'si' ? 'validada, en curso' : 'pendiente de validar',
            ];
        }

        return $puntos;
    }

    public const PERIODOS = ['mes' => 'Este mes', '30d' => 'Últimos 30 días'];

    /**
     * Ranking de contenedores con más reportes de DESBORDE en el período: el dato que dice
     * dónde conviene reforzar la frecuencia o poner otro contenedor.
     * 'mes' = desde el día 1 del mes en curso; '30d' = los últimos 30 días corridos.
     *
     * @return list<array{id:int,ubicacion:string,zona:string,estado:string,veces:int,ultima:string}>
     */
    public static function topDesbordados(string $periodo = 'mes', int $limite = 5): array
    {
        $desde = $periodo === '30d' ? 'DATE_SUB(NOW(), INTERVAL 30 DAY)' : "DATE_FORMAT(NOW(), '%Y-%m-01')";
        $st = Database::conexion()->query(
            "SELECT c.ID, c.ubicacion, r.zona, c.estado, COUNT(*) AS veces, MAX(h.fecha_ing) AS ultima
             FROM incidencia_historial h
             JOIN contenedor c ON c.ID = h.ID_contenedor
             JOIN ruta r ON r.ID = c.ID_ruta
             WHERE h.tipo = 'desbordado' AND h.fecha_ing >= {$desde}
             GROUP BY c.ID, c.ubicacion, r.zona, c.estado
             ORDER BY veces DESC, ultima DESC, c.ID
             LIMIT " . max(1, min(20, $limite))
        );
        return array_map(fn ($f) => [
            'id' => (int) $f['ID'], 'ubicacion' => (string) $f['ubicacion'], 'zona' => (string) $f['zona'],
            'estado' => (string) $f['estado'], 'veces' => (int) $f['veces'], 'ultima' => (string) $f['ultima'],
        ], $st->fetchAll());
    }

    /** Cuántos puntos NO se pueden dibujar por no tener coordenadas cargadas. */
    public static function sinCoordenadas(): int
    {
        $pdo = Database::conexion();
        return (int) $pdo->query(
            'SELECT (SELECT COUNT(*) FROM contenedor WHERE latitud IS NULL OR longitud IS NULL)
                  + (SELECT COUNT(*) FROM instalacion WHERE latitud IS NULL OR longitud IS NULL)'
        )->fetchColumn();
    }
}
