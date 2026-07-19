<?php
declare(strict_types=1);

/**
 * Datos de la landing pública.
 *
 * PROVISORIO: hoy los contadores están en 0 (primera entrega, sin BD viva).
 * Cuando existan datos reales, esta clase se reemplaza por un cliente
 * en app/Services/ que consulte las APIs — las vistas NO cambian.
 */
class Estadisticas
{
    /** Tarjetas de "Tipos de incidencia". */
    public static function tiposIncidencia(): array
    {
        return [
            [
                'clave'       => 'desbordado',
                'titulo'      => 'Desbordado',
                'contador'    => '0',
                'etiqueta'    => 'activos',
                'descripcion' => 'Contenedor lleno sin capacidad de recepción. Riesgo sanitario inmediato.',
            ],
            [
                'clave'       => 'roto',
                'titulo'      => 'Roto',
                'contador'    => '0',
                'etiqueta'    => 'en reparación',
                'descripcion' => 'Tapa rota, cuerpo fisurado o mecanismo de apertura fuera de servicio.',
            ],
            [
                'clave'       => 'falta_recoleccion',
                'titulo'      => 'Falta de recolección',
                'contador'    => '0',
                'etiqueta'    => 'reportados hoy',
                'descripcion' => 'Ruta no completada según el cronograma oficial del servicio.',
            ],
        ];
    }

    /** Tarjetas de "Estadísticas públicas". */
    public static function publicas(): array
    {
        return [
            ['valor' => '0',    'unidad' => 'incidencias',  'detalle' => 'Resueltas este año',     'anio' => '2026'],
            ['valor' => '0',    'unidad' => 'contenedores', 'detalle' => 'Activos en Montevideo',   'anio' => '2026'],
            ['valor' => '0 h',  'unidad' => 'promedio',     'detalle' => 'Tiempo de respuesta',     'anio' => '2026'],
        ];
    }

    /** Métricas chicas del hero. */
    public static function hero(): array
    {
        return [
            ['valor' => '0',    'etiqueta' => 'contenedores'],
            ['valor' => '0 h',  'etiqueta' => 'tiempo resp.'],
            ['valor' => '0%',   'etiqueta' => 'resueltos'],
        ];
    }

    /** Panel de "Rutas de recolección". */
    public static function rutas(): array
    {
        return [
            ['etiqueta' => 'Rutas activas',           'valor' => '0'],
            ['etiqueta' => 'Vehículos en operación',  'valor' => '0'],
            ['etiqueta' => 'Zonas cubiertas hoy',     'valor' => '0 / 0'],
        ];
    }
}
