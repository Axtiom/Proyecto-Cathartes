<?php
declare(strict_types=1);

/** CRUD completo de api-gestion, de punta a punta por HTTP (sesión de administrador). */
final class GestionApiTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->login('administrador');
    }

    private function g(string $metodo, string $ruta, ?array $cuerpo = null): array
    {
        return $this->gestion($metodo, $ruta, $cuerpo, 'administrador');
    }

    // --------------------------------------------------------------- camiones
    public function testCicloCompletoDeUnCamion(): void
    {
        [$c, $j] = $this->g('POST', '/camiones', ['matricula' => 'SBC 1234', 'modelo' => 'Volvo FM', 'capacidad' => 12000]);
        $this->assertSame(201, $c);
        $id = $j['id'];
        $this->assertSame('SBC 1234', $j['camion']['matricula']);

        [$c, $j] = $this->g('GET', "/camiones/detalle?id=$id");
        $this->assertSame(200, $c);
        $this->assertSame('funcional', $j['camion']['estado']);

        [$c, $j] = $this->g('PUT', "/camiones?id=$id", ['estado' => 'en mantenimiento']);
        $this->assertSame(200, $c);
        $this->assertSame('en mantenimiento', $j['camion']['estado']);
        $this->assertSame('Volvo FM', $j['camion']['modelo']);

        [$c] = $this->g('DELETE', "/camiones?id=$id", []);
        $this->assertSame(400, $c, 'la baja exige motivo');

        [$c] = $this->g('DELETE', "/camiones?id=$id", ['motivo' => 'Fin de vida útil']);
        $this->assertSame(200, $c);
        [, $j] = $this->g('GET', "/camiones/detalle?id=$id");
        $this->assertSame('dado de baja', $j['camion']['estado']);
        $this->assertSame('Fin de vida útil', $j['camion']['motivo_baja']);

        [$c] = $this->g('PUT', "/camiones?id=$id", ['modelo' => 'X']);
        $this->assertSame(409, $c, 'dado de baja no se edita');
    }

    public function testCamionesCodigosDeError(): void
    {
        [$c, $j] = $this->g('POST', '/camiones', ['matricula' => 'EME 542', 'modelo' => 'X', 'capacidad' => 1]);
        $this->assertSame(400, $c);
        $this->assertStringContainsString('Ya existe', $j['error']);
        $this->assertSame(404, $this->g('GET', '/camiones/detalle?id=999')[0]);
        $this->assertSame(404, $this->g('PUT', '/camiones?id=999', ['modelo' => 'X'])[0]);
        $this->assertSame(404, $this->g('DELETE', '/camiones?id=999', ['motivo' => 'x'])[0]);
        $this->assertSame(404, $this->g('GET', '/camiones/detalle')[0], 'sin id');
        $this->assertSame(404, $this->g('PUT', '/camiones?id=abc', ['modelo' => 'X'])[0], 'id no numérico');
    }

    public function testListadoDeCamionesConFiltros(): void
    {
        [, $j] = $this->g('GET', '/camiones');
        $this->assertSame(4, $j['total']);
        [, $j] = $this->g('GET', '/camiones?estado=en%20mantenimiento');
        $this->assertSame(1, $j['total']);
    }

    // ------------------------------------------------------------ contenedores
    public function testCicloCompletoDeUnContenedor(): void
    {
        [$c, $j] = $this->g('POST', '/contenedores', ['ubicacion' => 'Av. Italia 3200', 'latitud' => -34.8998,
            'longitud' => -56.1402, 'tipo' => 'reciclable', 'id_ruta' => 1]);
        $this->assertSame(201, $c);
        $id = $j['id'];

        [, $j] = $this->g('GET', "/contenedores/detalle?id=$id");
        $this->assertSame('Buceo — Pocitos', $j['contenedor']['ruta']);

        [$c, $j] = $this->g('PUT', "/contenedores?id=$id", ['estado' => 'desbordado', 'id_ruta' => 3]);
        $this->assertSame(200, $c);
        $this->assertSame('desbordado', $j['contenedor']['estado']);
        $this->assertSame(1, $j['contenedor']['id_ruta'], 'la ruta no se cambia desde el contenedor');

        $this->assertSame(400, $this->g('DELETE', "/contenedores?id=$id", [])[0]);
        $this->assertSame(200, $this->g('DELETE', "/contenedores?id=$id", ['motivo' => 'Quemado'])[0]);
        $this->assertSame(409, $this->g('DELETE', "/contenedores?id=$id", ['motivo' => 'otra vez'])[0]);

        [, $pub] = $this->gestion('GET', '/contenedores/publicos');
        $ids = array_column($pub['items'] ?? $pub['contenedores'] ?? [], 'id');
        $this->assertNotContains($id, $ids, 'un contenedor dado de baja deja de verse en el mapa público');
    }

    public function testContenedorValidaEnElBackend(): void
    {
        $base = ['ubicacion' => 'x', 'tipo' => 'basura', 'id_ruta' => 1];
        $this->assertSame(400, $this->g('POST', '/contenedores', ['tipo' => 'basura', 'id_ruta' => 1])[0]);
        $this->assertSame(400, $this->g('POST', '/contenedores', array_merge($base, ['latitud' => 500]))[0]);
        $this->assertSame(400, $this->g('POST', '/contenedores', array_merge($base, ['id_ruta' => 99]))[0]);
        $this->assertSame(404, $this->g('GET', '/contenedores/detalle?id=999')[0]);
    }

    // ----------------------------------------------------------- instalaciones
    public function testCicloCompletoDeUnaInstalacion(): void
    {
        [$c, $j] = $this->g('POST', '/instalaciones', ['nombre' => 'Centro de acopio Cerro', 'ubicacion' => 'Cerro',
            'tipo' => 'centro de acopio', 'capacidad' => 500, 'ocupacion_actual' => 450]);
        $this->assertSame(201, $c);
        $id = $j['id'];
        $this->assertTrue($j['instalacion']['alerta'], '90 % supera el umbral del 80 %');

        [$c, $j] = $this->g('PUT', "/instalaciones?id=$id", ['ocupacion_actual' => 100]);
        $this->assertSame(200, $c);
        $this->assertFalse($j['instalacion']['alerta']);

        $this->assertSame(400, $this->g('PUT', "/instalaciones?id=$id", ['ocupacion_actual' => 501])[0]);

        [, $j] = $this->g('GET', "/instalaciones/detalle?id=$id");
        $this->assertSame(0, $j['instalacion']['dependencias']['rutas']);
        $this->assertSame(200, $this->g('DELETE', "/instalaciones?id=$id")[0]);
        $this->assertSame(404, $this->g('GET', "/instalaciones/detalle?id=$id")[0]);
    }

    public function testNoSeEliminaUnaInstalacionConDependencias(): void
    {
        [$c, $j] = $this->g('DELETE', '/instalaciones?id=1');
        $this->assertSame(409, $c);
        $this->assertSame(3, $j['dependencias']['rutas']);
        $this->assertSame(200, $this->g('GET', '/instalaciones/detalle?id=1')[0], 'sigue existiendo');
    }

    // --------------------------------------------------------------- maquinaria
    public function testCicloCompletoDeLaMaquinaria(): void
    {
        [$c, $j] = $this->g('POST', '/maquinaria', ['modelo' => 'JCB 3CX', 'tipo' => 'retroexcavadora', 'id_instalacion' => 2]);
        $this->assertSame(201, $c);
        $id = $j['id'];

        [, $j] = $this->g('GET', '/maquinaria?instalacion=2');
        $this->assertSame(4, $j['total']);

        [$c, $j] = $this->g('PUT', "/maquinaria?id=$id", ['estado' => 'en mantenimiento']);
        $this->assertSame(200, $c);
        $this->assertSame('en mantenimiento', $j['maquina']['estado']);

        $this->assertSame(400, $this->g('PUT', "/maquinaria?id=$id", ['estado' => 'roto'])[0]);
        $this->assertSame(400, $this->g('POST', '/maquinaria', ['modelo' => 'X', 'tipo' => 'y', 'id_instalacion' => 99])[0]);

        $this->assertSame(200, $this->g('DELETE', "/maquinaria?id=$id")[0]);
        $this->assertSame(404, $this->g('GET', "/maquinaria/detalle?id=$id")[0]);
    }
}
