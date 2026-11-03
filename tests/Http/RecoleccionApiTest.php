<?php
declare(strict_types=1);

/**
 * API de Recolección. IDs del seed: cuadrilla 3 (camión 1, ruta vigente 2), operario 2
 * (instalación 1 al 85 %), intervenciones 3 y 4 abiertas y la 1 cerrada. El repuesto 5 no tiene stock.
 */
final class RecoleccionApiTest extends HttpTestCase
{
    private function c(string $m, string $r, ?array $b = null): array
    {
        return $this->recoleccion($m, $r, $b, 'cuadrilla');
    }

    private function o(string $m, string $r, ?array $b = null): array
    {
        return $this->recoleccion($m, $r, $b, 'operario');
    }

    // ---------------------------------------------------------------- permisos
    public static function endpointsCuadrilla(): array
    {
        $e = [];
        foreach ([['GET', '/cuadrilla/ruta'], ['GET', '/cuadrilla/resumen'], ['GET', '/cuadrilla/rutas'], ['GET', '/cuadrilla/mapa'],
                  ['GET', '/cuadrilla/intervenciones'], ['GET', '/cuadrilla/intervencion?id=3'], ['GET', '/cuadrilla/repuestos'],
                  ['POST', '/cuadrilla/contenedor/verificar'], ['POST', '/cuadrilla/contenedor/estado'],
                  ['POST', '/cuadrilla/intervencion/repuestos'], ['POST', '/cuadrilla/intervencion/finalizar']] as [$m, $r]) {
            $e["$m $r"] = [$m, $r];
        }
        return $e;
    }

    public static function endpointsOperario(): array
    {
        return ['GET /operario/resumen' => ['GET', '/operario/resumen'], 'GET /operario/instalacion' => ['GET', '/operario/instalacion'],
                'POST /operario/ocupacion' => ['POST', '/operario/ocupacion']];
    }

    /** @dataProvider endpointsCuadrilla */
    public function testCuadrillaSinSesionDa401YOtrosRolesDan403(string $m, string $r): void
    {
        $cuerpo = $m === 'POST' ? [] : null;
        $this->assertSame(401, $this->recoleccion($m, $r, $cuerpo)[0]);
        foreach (['vecino', 'operario', 'administrador'] as $rol) {
            $this->login($rol);
            $this->assertSame(403, $this->recoleccion($m, $r, $cuerpo, $rol)[0], "$rol en $m $r");
        }
    }

    /** @dataProvider endpointsOperario */
    public function testOperarioSinSesionDa401YOtrosRolesDan403(string $m, string $r): void
    {
        $cuerpo = $m === 'POST' ? [] : null;
        $this->assertSame(401, $this->recoleccion($m, $r, $cuerpo)[0]);
        foreach (['vecino', 'cuadrilla', 'administrador'] as $rol) {
            $this->login($rol);
            $this->assertSame(403, $this->recoleccion($m, $r, $cuerpo, $rol)[0], "$rol en $m $r");
        }
    }

    // ----------------------------------------------------------------- lectura
    public function testResumenDeLaCuadrilla(): void
    {
        $this->login('cuadrilla');
        [$c, $j] = $this->c('GET', '/cuadrilla/resumen');
        $this->assertSame(200, $c);
        $this->assertSame(2, $j['metricas']['intervenciones_pendientes']);
        $this->assertSame(2, $j['ruta_activa']['id'], 'la ruta vigente es la toma más reciente');
        $this->assertSame(2, $j['ruta_activa']['total_contenedores'], 'el contenedor dado de baja no cuenta');
        $this->assertSame(1, $j['ruta_activa']['verificados']);
        $this->assertSame(1, $j['metricas']['contenedores_a_verificar']);
        $this->assertCount(2, $j['urgentes']);
        $this->assertSame('desbordado', $j['urgentes'][0]['estado_contenedor'], 'lo más urgente primero');
    }

    public function testRutasConAvanceDeHoy(): void
    {
        $this->login('cuadrilla');
        [, $j] = $this->c('GET', '/cuadrilla/rutas');
        $this->assertSame(2, $j['total'], 'tomó las rutas 1 y 2');
        $this->assertSame(2, $j['items'][0]['id'], 'la más reciente primero');
        $this->assertSame(1, $j['items'][0]['verificados']);
        $ids = array_column($j['items'][0]['contenedores'], 'id');
        $this->assertNotContains(8, $ids, 'dado de baja');
    }

    public function testMapaConContenedoresEIncidenciasAbiertas(): void
    {
        $this->login('cuadrilla');
        [, $j] = $this->c('GET', '/cuadrilla/mapa');
        $this->assertSame(2, $j['ruta']['id']);
        $this->assertCount(2, $j['contenedores']);
        $this->assertTrue($j['contenedores'][0]['verificado_hoy'] || $j['contenedores'][1]['verificado_hoy']);
        $this->assertSame([], $j['incidencias'], 'la ruta vigente (2) no tiene intervenciones abiertas');
        [, $j] = $this->c('GET', '/cuadrilla/mapa?ruta=1');
        $this->assertSame(1, $j['ruta']['id']);
        $ids = array_column($j['incidencias'], 'id');
        sort($ids);
        $this->assertSame([7, 8], $ids, 'las incidencias abiertas de la cuadrilla en esa ruta');
        $this->assertSame(404, $this->c('GET', '/cuadrilla/mapa?ruta=3')[0], 'la ruta 3 no la tomó');
    }

    public function testIntervencionesYDetalle(): void
    {
        $this->login('cuadrilla');
        [, $j] = $this->c('GET', '/cuadrilla/intervenciones');
        $this->assertSame(3, $j['total']);
        $this->assertSame('no', $j['items'][0]['tarea_finalizada'], 'abiertas primero');

        [$c, $j] = $this->c('GET', '/cuadrilla/intervencion?id=3');
        $this->assertSame(200, $c);
        $this->assertSame(8, $j['intervencion']['id_incidencia']);
        $this->assertSame('Juan Pérez', $j['intervencion']['vecino']);
        [, $j] = $this->c('GET', '/cuadrilla/intervencion?id=1');
        $this->assertSame('Tapa de contenedor 1100 L', $j['repuestos'][0]['nombre']);
        $this->assertSame(404, $this->c('GET', '/cuadrilla/intervencion?id=999')[0]);
    }

    public function testElInventarioSoloMuestraLoQueTieneStock(): void
    {
        $this->login('cuadrilla');
        [, $j] = $this->c('GET', '/cuadrilla/repuestos');
        $this->assertSame(4, $j['total']);
        $this->assertNotContains(5, array_column($j['items'], 'id'));
    }

    // --------------------------------------------------- aislamiento de cuadrillas
    private function crearCuadrilla2(): void
    {
        $this->login('administrador');
        [$c] = $this->usuarios('POST', '/usuarios', ['rol' => 'cuadrilla', 'nombre' => 'Cuadrilla Beta', 'email' => 'cuadrilla2@cathartes.uy',
            'password' => 'Cathartes2026', 'telefono' => '099 000 111', 'id_camion' => 2], 'administrador');
        $this->assertSame(201, $c);
        $this->login('cuadrilla2');
    }

    public function testUnaCuadrillaNoVeNiTocaLoDeOtra(): void
    {
        $this->crearCuadrilla2();
        $this->assertSame(404, $this->recoleccion('GET', '/cuadrilla/intervencion?id=3', null, 'cuadrilla2')[0]);
        foreach (['/cuadrilla/intervencion/finalizar' => ['id_intervencion' => 3],
                  '/cuadrilla/contenedor/estado' => ['id_intervencion' => 3, 'estado' => 'funcional'],
                  '/cuadrilla/intervencion/repuestos' => ['id_intervencion' => 3, 'id_repuesto' => 1, 'cuantos' => 1]] as $ruta => $cuerpo) {
            $this->assertSame(404, $this->recoleccion('POST', $ruta, $cuerpo, 'cuadrilla2')[0], $ruta);
        }
        [, $j] = $this->recoleccion('GET', '/cuadrilla/intervenciones', null, 'cuadrilla2');
        $this->assertSame(0, $j['total']);
        $this->assertSame(403, $this->recoleccion('POST', '/cuadrilla/contenedor/verificar', ['id_contenedor' => 3], 'cuadrilla2')[0], 'contenedor de una ruta que no tomó');
        $this->assertSame('no', $this->fila('SELECT tarea_finalizada FROM intervencion WHERE ID_intervencion = 3')['tarea_finalizada']);
        $this->assertSame(12, $this->cuenta('SELECT cantidad FROM repuestos WHERE ID = 1'), 'el stock no se tocó');
    }

    // ----------------------------------------------------------------- escritura
    public function testVerificarContenedorEsIdempotente(): void
    {
        $this->login('cuadrilla');
        [$c, $j] = $this->c('POST', '/cuadrilla/contenedor/verificar', ['id_contenedor' => 6]);
        $this->assertSame(200, $c);
        $this->assertFalse($j['ya_verificado']);
        [, $j] = $this->c('POST', '/cuadrilla/contenedor/verificar', ['id_contenedor' => 6]);
        $this->assertTrue($j['ya_verificado']);
        $this->assertSame(1, $this->cuenta('SELECT COUNT(*) FROM verificacion WHERE ID_contenedor = 6 AND fecha >= CURDATE()'));
        [, $r] = $this->c('GET', '/cuadrilla/resumen');
        $this->assertSame(0, $r['metricas']['contenedores_a_verificar']);

        $this->assertSame(409, $this->c('POST', '/cuadrilla/contenedor/verificar', ['id_contenedor' => 8])[0], 'dado de baja');
        $this->assertSame(400, $this->c('POST', '/cuadrilla/contenedor/verificar', [])[0]);
        $this->assertSame(403, $this->c('POST', '/cuadrilla/contenedor/verificar', ['id_contenedor' => 999])[0]);
    }

    public function testCambiarElEstadoDelContenedor(): void
    {
        $this->login('cuadrilla');
        $this->assertSame(200, $this->c('POST', '/cuadrilla/contenedor/estado', ['id_intervencion' => 3, 'estado' => 'funcional'])[0]);
        $this->assertSame('funcional', $this->fila('SELECT estado FROM contenedor WHERE ID = 2')['estado']);
        $this->assertSame(400, $this->c('POST', '/cuadrilla/contenedor/estado', ['id_intervencion' => 3, 'estado' => 'dado de baja'])[0], 'la baja es del administrador');
        $this->assertSame(400, $this->c('POST', '/cuadrilla/contenedor/estado', ['id_intervencion' => 3, 'estado' => 'roto'])[0]);
        $this->assertSame(409, $this->c('POST', '/cuadrilla/contenedor/estado', ['id_intervencion' => 1, 'estado' => 'funcional'])[0], 'intervención ya cerrada');
    }

    public function testUsarRepuestosDescuentaStockYSuma(): void
    {
        $this->login('cuadrilla');
        $this->assertSame(200, $this->c('POST', '/cuadrilla/intervencion/repuestos', ['id_intervencion' => 3, 'id_repuesto' => 2, 'cuantos' => 3])[0]);
        $this->assertSame(5, $this->cuenta('SELECT cantidad FROM repuestos WHERE ID = 2'));
        $this->assertSame(200, $this->c('POST', '/cuadrilla/intervencion/repuestos', ['id_intervencion' => 3, 'id_repuesto' => 2, 'cuantos' => 2])[0]);
        $this->assertSame(3, $this->cuenta('SELECT cantidad FROM repuestos WHERE ID = 2'));
        $this->assertSame(5, $this->cuenta('SELECT cuantos FROM usa WHERE ID_intervencion = 3 AND ID_repuesto = 2'), 'el mismo repuesto se acumula');
        [, $j] = $this->c('GET', '/cuadrilla/intervencion?id=3');
        $this->assertSame(5, $j['repuestos'][0]['cuantos']);
    }

    public function testNoSePuedeUsarMasDeLoQueHay(): void
    {
        $this->login('cuadrilla');
        [$c, $j] = $this->c('POST', '/cuadrilla/intervencion/repuestos', ['id_intervencion' => 3, 'id_repuesto' => 4, 'cuantos' => 6]);
        $this->assertSame(409, $c);
        $this->assertStringContainsString('quedan 5', $j['error']);
        $this->assertSame(5, $this->cuenta('SELECT cantidad FROM repuestos WHERE ID = 4'), 'no descuenta nada');
        $this->assertSame(0, $this->cuenta('SELECT COUNT(*) FROM usa WHERE ID_intervencion = 3'));
        $this->assertSame(409, $this->c('POST', '/cuadrilla/intervencion/repuestos', ['id_intervencion' => 3, 'id_repuesto' => 5, 'cuantos' => 1])[0], 'sin stock');
        foreach ([0, -1, 'mucho', 2.5, null] as $malo) {
            $this->assertSame(400, $this->c('POST', '/cuadrilla/intervencion/repuestos', ['id_intervencion' => 3, 'id_repuesto' => 1, 'cuantos' => $malo])[0], var_export($malo, true));
        }
        $this->assertSame(404, $this->c('POST', '/cuadrilla/intervencion/repuestos', ['id_intervencion' => 3, 'id_repuesto' => 99, 'cuantos' => 1])[0]);
        $this->assertSame(12, $this->cuenta('SELECT cantidad FROM repuestos WHERE ID = 1'));
    }

    public function testFinalizarCierraElCicloYAvisaAlVecino(): void
    {
        $this->login('cuadrilla');
        $noLeidas = $this->cuenta("SELECT COUNT(*) FROM notificacion WHERE ID_usuario = 4 AND leida = 'no'");
        [$c] = $this->c('POST', '/cuadrilla/intervencion/finalizar', ['id_intervencion' => 3]);
        $this->assertSame(200, $c);
        $i = $this->fila('SELECT tarea_finalizada, fecha_fin FROM intervencion WHERE ID_intervencion = 3');
        $this->assertSame('si', $i['tarea_finalizada']);
        $this->assertNotNull($i['fecha_fin']);
        $this->assertSame('finalizada', $this->fila('SELECT estado FROM incidencia_historial WHERE ID_incidencia = 8')['estado']);
        $n = $this->fila("SELECT tipo, mensaje, ID_incidencia FROM notificacion WHERE ID_usuario = 4 ORDER BY ID DESC LIMIT 1");
        $this->assertSame('incidencia finalizada', $n['tipo']);
        $this->assertSame(8, (int) $n['ID_incidencia']);
        $this->assertSame($noLeidas + 1, $this->cuenta("SELECT COUNT(*) FROM notificacion WHERE ID_usuario = 4 AND leida = 'no'"));

        $this->assertSame(409, $this->c('POST', '/cuadrilla/intervencion/finalizar', ['id_intervencion' => 3])[0], 'no se cierra dos veces');
        $this->assertSame(409, $this->c('POST', '/cuadrilla/intervencion/repuestos', ['id_intervencion' => 3, 'id_repuesto' => 1, 'cuantos' => 1])[0], 'cerrada: no se editan repuestos');
        $this->assertSame(1, $this->cuenta("SELECT COUNT(*) FROM notificacion WHERE ID_incidencia = 8 AND tipo = 'incidencia finalizada'"), 'un solo aviso');
        [, $r] = $this->c('GET', '/cuadrilla/resumen');
        $this->assertSame(1, $r['metricas']['intervenciones_pendientes']);
    }

    // ----------------------------------------------------------------- operario
    public function testOperarioVeSuInstalacionYMaquinaria(): void
    {
        $this->login('operario');
        [$c, $j] = $this->o('GET', '/operario/resumen');
        $this->assertSame(200, $c);
        $this->assertSame(1, $j['instalacion']['id']);
        $this->assertSame(85, $j['instalacion']['porcentaje']);
        $this->assertTrue($j['instalacion']['alerta']);
        $this->assertSame(['funcional' => 0, 'total' => 1], $j['maquinaria']);
        [, $j] = $this->o('GET', '/operario/instalacion');
        $this->assertSame('Hyundai HX220', $j['maquinaria'][0]['modelo'], 'solo la maquinaria de SU instalación');
    }

    public function testRegistrarOcupacion(): void
    {
        $this->login('operario');
        [$c, $j] = $this->o('POST', '/operario/ocupacion', ['ocupacion_actual' => 400]);
        $this->assertSame(200, $c);
        $this->assertFalse($j['alerta']);
        $this->assertSame(400, $this->cuenta('SELECT ocupacion_actual FROM instalacion WHERE ID = 1'));
        foreach ([[1001, 'superar'], [-5, 'entero'], ['mucho', 'entero'], [1.5, 'entero'], [null, 'entero'], [99999999999, 'entero']] as [$malo, $frag]) {
            [$c, $j] = $this->o('POST', '/operario/ocupacion', ['ocupacion_actual' => $malo]);
            $this->assertSame(400, $c, var_export($malo, true));
            $this->assertStringContainsString($frag, $j['error']);
        }
        $this->assertSame(400, $this->cuenta('SELECT ocupacion_actual FROM instalacion WHERE ID = 1'), 'los valores inválidos no se guardan');
        $this->assertSame(6878, $this->cuenta('SELECT ocupacion_actual FROM instalacion WHERE ID = 2'), 'no toca otras instalaciones');
    }

    public function testCruzarElUmbralAvisaAlosAdministradoresUnaSolaVez(): void
    {
        $this->login('operario');
        $admins = $this->cuenta("SELECT COUNT(*) FROM administrador a JOIN usuario u ON u.ID = a.ID_usuario WHERE u.estado = 'activo'");
        $this->o('POST', '/operario/ocupacion', ['ocupacion_actual' => 400]);                 // baja a 40 %
        $antes = $this->cuenta("SELECT COUNT(*) FROM notificacion WHERE tipo = 'instalacion llena'");
        [, $j] = $this->o('POST', '/operario/ocupacion', ['ocupacion_actual' => 820]);          // cruza el 80 %
        $this->assertTrue($j['alerta']);
        $this->assertSame($antes + $admins, $this->cuenta("SELECT COUNT(*) FROM notificacion WHERE tipo = 'instalacion llena'"));
        [, $j] = $this->o('POST', '/operario/ocupacion', ['ocupacion_actual' => 900]);          // ya estaba arriba
        $this->assertFalse($j['alerta']);
        $this->assertSame($antes + $admins, $this->cuenta("SELECT COUNT(*) FROM notificacion WHERE tipo = 'instalacion llena'"), 'no repite el aviso');
    }
}
