<?php
declare(strict_types=1);

/**
 * Contrato entre la API de Recolección / Usuarios y las pantallas de frontend-usuarios.
 * Cada lista de campos sale de lo que el JS de esa pantalla LEE (cuadrilla-*.js, operario-*.js,
 * notificaciones.js, layout.js). Si un endpoint cambia un nombre, la pantalla se rompe sin
 * mostrar un error: este test lo detecta antes.
 */
final class ContratoFrontendTest extends HttpTestCase
{
    private function claves(array $obj, array $esperadas, string $donde): void
    {
        foreach ($esperadas as $k) {
            $this->assertArrayHasKey($k, $obj, "{$donde}: falta «{$k}»");
        }
    }

    public function testCuadrillaInicio(): void   // cuadrilla-inicio.js
    {
        $this->login('cuadrilla');
        [, $j] = $this->recoleccion('GET', '/cuadrilla/resumen', null, 'cuadrilla');
        $this->claves($j['metricas'], ['rutas_hoy', 'intervenciones_pendientes', 'contenedores_a_verificar'], 'metricas');
        $this->claves($j['ruta_activa'], ['nombre', 'zona', 'frecuencia', 'fecha_toma', 'estado', 'verificados', 'total_contenedores'], 'ruta_activa');
        $this->claves($j['urgentes'][0], ['id_intervencion', 'id_incidencia', 'tipo', 'fecha_inicio', 'ubicacion', 'estado_contenedor'], 'urgentes[0]');
    }

    public function testCuadrillaRutas(): void    // cuadrilla-rutas.js
    {
        $this->login('cuadrilla');
        [, $j] = $this->recoleccion('GET', '/cuadrilla/rutas', null, 'cuadrilla');
        $this->claves($j['items'][0], ['id', 'nombre', 'zona', 'frecuencia', 'fecha_toma', 'estado', 'verificados', 'contenedores'], 'ruta');
        $this->claves($j['items'][0]['contenedores'][0], ['id', 'ubicacion', 'estado', 'tipo'], 'contenedor');
    }

    public function testCuadrillaMapa(): void     // cuadrilla-mapa.js + js/mapa.js
    {
        $this->login('cuadrilla');
        [, $j] = $this->recoleccion('GET', '/cuadrilla/mapa?ruta=1', null, 'cuadrilla');   // la ruta 1 tiene las 2 intervenciones abiertas del seed
        $this->claves($j['ruta'], ['id', 'nombre', 'zona'], 'ruta');
        $this->claves($j['contenedores'][0], ['id', 'ubicacion', 'tipo', 'estado', 'latitud', 'longitud', 'verificado_hoy'], 'contenedor');
        $this->claves($j['incidencias'][0], ['id', 'tipo', 'estado', 'latitud', 'longitud', 'titulo', 'ubicacion'], 'incidencia (marcador del mapa)');
        $this->assertSame('incidencia', $j['incidencias'][0]['tipo'], 'js/mapa.js la reconoce por tipo');
    }

    public function testCuadrillaIntervenciones(): void   // cuadrilla-intervenciones.js
    {
        $this->login('cuadrilla');
        [, $j] = $this->recoleccion('GET', '/cuadrilla/intervenciones', null, 'cuadrilla');
        $this->claves($j['items'][0], ['id_intervencion', 'id_incidencia', 'tipo', 'fecha_inicio', 'ubicacion', 'tarea_finalizada'], 'item');
        [, $d] = $this->recoleccion('GET', '/cuadrilla/intervencion?id=1', null, 'cuadrilla');
        $this->claves($d['intervencion'], ['id_incidencia', 'tipo', 'id_contenedor', 'ubicacion', 'vecino', 'fecha_inicio', 'descripcion',
            'fecha_fin', 'estado_incidencia', 'estado_contenedor', 'tarea_finalizada'], 'intervencion');
        $this->claves($d['repuestos'][0], ['nombre', 'tipo', 'cuantos'], 'repuesto usado');
        [, $r] = $this->recoleccion('GET', '/cuadrilla/repuestos', null, 'cuadrilla');
        $this->claves($r['items'][0], ['id', 'nombre', 'tipo', 'cantidad'], 'repuesto');
    }

    public function testOperario(): void          // operario-inicio.js, operario-instalacion.js, operario-informes.js
    {
        $this->login('operario');
        [, $j] = $this->recoleccion('GET', '/operario/resumen', null, 'operario');
        $this->claves($j['instalacion'], ['nombre', 'tipo', 'ubicacion', 'capacidad', 'ocupacion_actual'], 'instalacion');
        $this->claves($j['maquinaria'], ['funcional', 'total'], 'maquinaria (resumen)');
        [, $j] = $this->recoleccion('GET', '/operario/instalacion', null, 'operario');
        $this->claves($j['instalacion'], ['nombre', 'tipo', 'ubicacion', 'capacidad', 'ocupacion_actual'], 'instalacion');
        $this->claves($j['maquinaria'][0], ['modelo', 'tipo', 'estado'], 'maquina');
    }

    public function testNotificaciones(): void    // notificaciones.js y la campana de layout.js
    {
        $this->login('vecino');
        [, $j] = $this->usuarios('GET', '/notificaciones', null, 'vecino');
        $this->claves($j, ['items', 'total', 'no_leidas'], 'respuesta');
        $this->claves($j['items'][0], ['id', 'tipo', 'mensaje', 'fecha', 'leida', 'id_incidencia'], 'notificación');
        $this->assertIsBool($j['items'][0]['leida']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $j['items'][0]['fecha'], 'Fmt.fechaHora espera «AAAA-MM-DD hh:mm»');
        [, $c] = $this->usuarios('GET', '/notificaciones/contador', null, 'vecino');
        $this->assertIsInt($c['no_leidas']);
    }

    public function testElFrontendNoLlamaAEndpointsQueLaApiNoExpone(): void
    {
        // Verificación estática: todo endpoint al que llaman las pantallas existe en las rutas de la API.
        $js = '';
        foreach (glob(dirname(__DIR__, 2) . '/frontend-usuarios/js/pantallas/{cuadrilla,operario}*.js', GLOB_BRACE) as $f) {
            $js .= file_get_contents($f);
        }
        preg_match_all("#'(/(?:cuadrilla|operario)/[a-z/]+)#", $js, $m);
        $rutas = file_get_contents(dirname(__DIR__, 2) . '/api-recoleccion/routes/web.php');
        $this->assertNotEmpty($m[1]);
        foreach (array_unique($m[1]) as $endpoint) {
            $this->assertStringContainsString("'" . $endpoint . "'", $rutas, "las pantallas llaman a $endpoint y la API no lo expone");
        }
    }
}
