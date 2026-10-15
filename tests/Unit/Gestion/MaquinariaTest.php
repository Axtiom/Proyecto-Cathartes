<?php
declare(strict_types=1);

final class MaquinariaTest extends BaseDatosTestCase
{
    private function datos(array $pisar = []): array
    {
        return array_merge(['modelo' => 'JCB 3CX', 'tipo' => 'retroexcavadora', 'id_instalacion' => 2], $pisar);
    }

    public function testCrearConEstadoPorDefecto(): void
    {
        $r = Maquinaria::crear($this->datos());
        $this->assertTrue($r['ok']);
        $m = Maquinaria::porId($r['id']);
        $this->assertSame('funcional', $m['estado']);
        $this->assertSame('Vertedero Felipe Cardoso', $m['instalacion']);
    }

    /** @dataProvider invalidos */
    public function testCrearRechazaDatosInvalidos(array $pisar, string $fragmento): void
    {
        $r = Maquinaria::crear($this->datos($pisar));
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString($fragmento, $r['error']);
    }

    public static function invalidos(): array
    {
        return [
            'sin modelo'              => [['modelo' => ''], 'modelo'],
            'sin tipo'                => [['tipo' => ''], 'tipo'],
            'estado inválido'         => [['estado' => 'dado de baja'], 'Estado'],
            'sin instalación'         => [['id_instalacion' => ''], 'instalación'],
            'instalación inexistente' => [['id_instalacion' => 99], 'no existe'],
        ];
    }

    public function testListarFiltraPorInstalacionYEstado(): void
    {
        $this->assertCount(4, Maquinaria::listar());
        $this->assertCount(3, Maquinaria::listar(2));
        $this->assertCount(1, Maquinaria::listar(null, 'no funcional'));
    }

    public function testActualizarEsParcialYPermiteMoverDeInstalacion(): void
    {
        $this->assertTrue(Maquinaria::actualizar(1, ['estado' => 'en mantenimiento', 'id_instalacion' => 1])['ok']);
        $m = Maquinaria::porId(1);
        $this->assertSame('en mantenimiento', $m['estado']);
        $this->assertSame(1, $m['id_instalacion']);
        $this->assertSame('Caterpillar D6', $m['modelo']);
        $this->assertSame(404, Maquinaria::actualizar(999, ['estado' => 'funcional'])['codigo']);
    }

    public function testEliminar(): void
    {
        $this->assertTrue(Maquinaria::eliminar(1)['ok']);
        $this->assertNull(Maquinaria::porId(1));
        $this->assertSame(404, Maquinaria::eliminar(1)['codigo']);
    }
}
