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

        return $puntos;
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
