<?php
declare(strict_types=1);

final class ContenedorTest extends BaseDatosTestCase
{
    private function datos(array $pisar = []): array
    {
        return array_merge(['ubicacion' => 'Av. Italia 3200', 'latitud' => -34.8998, 'longitud' => -56.1402,
            'tipo' => 'reciclable', 'id_ruta' => 1], $pisar);
    }

    public function testCrearContenedorValido(): void
    {
        $r = Contenedor::crear($this->datos());
        $this->assertTrue($r['ok']);
        $c = Contenedor::completo($r['id']);
        $this->assertSame('funcional', $c['estado']);
        $this->assertSame(1, $c['id_ruta']);
        $this->assertEqualsWithDelta(-34.8998, $c['latitud'], 0.00001);
    }

    public function testLasCoordenadasSonOpcionales(): void
    {
        $r = Contenedor::crear($this->datos(['latitud' => '', 'longitud' => '']));
        $this->assertTrue($r['ok']);
        $this->assertNull(Contenedor::completo($r['id'])['latitud']);
    }

    /** @dataProvider invalidos */
    public function testCrearRechazaDatosInvalidos(array $pisar, string $fragmento): void
    {
        $r = Contenedor::crear($this->datos($pisar));
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString($fragmento, $r['error']);
    }

    public static function invalidos(): array
    {
        return [
            'sin ubicación'      => [['ubicacion' => ''], 'ubicación'],
            'ubicación enorme'   => [['ubicacion' => str_repeat('x', 201)], 'hasta 200'],
            'tipo inválido'      => [['tipo' => 'vidrio'], 'Tipo'],
            'estado de baja'     => [['estado' => 'dado de baja'], 'Estado'],
            'latitud fuera'      => [['latitud' => 91], 'latitud'],
            'longitud fuera'     => [['longitud' => -181], 'longitud'],
            'latitud texto'      => [['latitud' => 'norte'], 'latitud'],
            'latitud desbordada' => [['latitud' => '99999999999'], 'latitud'],
            'ruta inexistente'   => [['id_ruta' => 99], 'no existe'],
            'sin ruta'           => [['id_ruta' => ''], 'ruta'],
        ];
    }

    public function testActualizarCambiaCamposPeroNoLaRuta(): void
    {
        $r = Contenedor::actualizar(1, ['estado' => 'desbordado', 'id_ruta' => 2]);
        $this->assertTrue($r['ok']);
        $c = Contenedor::completo(1);
        $this->assertSame('desbordado', $c['estado']);
        $this->assertSame(3, $c['id_ruta'], 'la ruta se decide desde la ruta, no desde el contenedor');
        $this->assertSame('Av. Rivera 3245, Buceo', $c['ubicacion'], 'lo que no se envía se conserva');
    }

    public function testActualizarValidaIgualQueCrear(): void
    {
        $this->assertFalse(Contenedor::actualizar(1, ['tipo' => 'vidrio'])['ok']);
        $this->assertSame(404, Contenedor::actualizar(999, ['tipo' => 'basura'])['codigo']);
    }

    public function testBajaLogicaExigeMotivo(): void
    {
        $this->assertFalse(Contenedor::baja(1, '')['ok']);
        $this->assertTrue(Contenedor::baja(1, 'Roto por vandalismo')['ok']);
        $c = Contenedor::completo(1);
        $this->assertSame('dado de baja', $c['estado']);
        $this->assertSame('Roto por vandalismo', $c['motivo_baja']);
        $this->assertNotNull($c['fecha_baja']);
    }

    public function testNoSeDaDeBajaDosVecesNiSeEditaUnoDadoDeBaja(): void
    {
        $this->assertSame(409, Contenedor::baja(8, 'x')['codigo']);               // el 8 ya está de baja en el seed
        $this->assertSame(409, Contenedor::actualizar(8, ['tipo' => 'basura'])['codigo']);
    }

    public function testRutasDisponibles(): void
    {
        $this->assertCount(3, Contenedor::rutas());
    }
}
