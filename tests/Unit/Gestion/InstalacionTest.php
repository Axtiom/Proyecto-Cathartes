<?php
declare(strict_types=1);

final class InstalacionTest extends BaseDatosTestCase
{
    private function datos(array $pisar = []): array
    {
        return array_merge(['nombre' => 'Centro de acopio Cerro', 'ubicacion' => 'Cerro, Montevideo',
            'tipo' => 'centro de acopio', 'capacidad' => 500, 'ocupacion_actual' => 100], $pisar);
    }

    public function testCrearYLeer(): void
    {
        $r = Instalacion::crear($this->datos());
        $this->assertTrue($r['ok']);
        $i = Instalacion::porId($r['id']);
        $this->assertSame(20, $i['porcentaje']);
        $this->assertFalse($i['alerta']);
    }

    public function testAlertaAlLlegarAlUmbralDel80PorCiento(): void
    {
        $r = Instalacion::crear($this->datos(['ocupacion_actual' => 400]));
        $this->assertTrue(Instalacion::porId($r['id'])['alerta']);
        $this->assertTrue(Instalacion::porId(1)['alerta'], 'el seed trae la instalación 1 al 85 %');
    }

    /** @dataProvider invalidos */
    public function testCrearRechazaDatosInvalidos(array $pisar, string $fragmento): void
    {
        $r = Instalacion::crear($this->datos($pisar));
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString($fragmento, $r['error']);
    }

    public static function invalidos(): array
    {
        return [
            'sin nombre'            => [['nombre' => ''], 'nombre'],
            'sin ubicación'         => [['ubicacion' => ''], 'ubicación'],
            'tipo inválido'         => [['tipo' => 'puerto'], 'Tipo'],
            'capacidad cero'        => [['capacidad' => 0], 'capacidad'],
            'ocupación negativa'    => [['ocupacion_actual' => -1], 'ocupación'],
            'ocupación > capacidad' => [['ocupacion_actual' => 501], 'no puede superar'],
            'latitud fuera'         => [['latitud' => 120], 'latitud'],
        ];
    }

    public function testActualizarEsParcial(): void
    {
        $this->assertTrue(Instalacion::actualizar(2, ['ocupacion_actual' => 9000])['ok']);
        $i = Instalacion::porId(2);
        $this->assertSame(9000, $i['ocupacion_actual']);
        $this->assertSame('Vertedero Felipe Cardoso', $i['nombre']);
    }

    public function testActualizarNoPermiteBajarLaCapacidadPorDebajoDeLaOcupacion(): void
    {
        $r = Instalacion::actualizar(2, ['capacidad' => 100]);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('no puede superar', $r['error']);
    }

    public function testNoSeEliminaUnaInstalacionConDependencias(): void
    {
        $r = Instalacion::eliminar(1); // 3 rutas, 1 operario y maquinaria
        $this->assertFalse($r['ok']);
        $this->assertSame(409, $r['codigo']);
        $this->assertSame(3, $r['dependencias']['rutas']);
        $this->assertNotNull(Instalacion::porId(1));
    }

    public function testSeEliminaUnaInstalacionSinDependencias(): void
    {
        $id = Instalacion::crear($this->datos())['id'];
        $this->assertTrue(Instalacion::eliminar($id)['ok']);
        $this->assertNull(Instalacion::porId($id));
        $this->assertSame(404, Instalacion::eliminar($id)['codigo']);
    }

    public function testListarFiltraPorTipo(): void
    {
        $this->assertCount(2, Instalacion::listar());
        $this->assertCount(1, Instalacion::listar('vertedero', 'Cardoso'));
    }
}
