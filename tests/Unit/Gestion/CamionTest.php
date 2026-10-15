<?php
declare(strict_types=1);

final class CamionTest extends BaseDatosTestCase
{
    private function datos(array $pisar = []): array
    {
        return array_merge(['matricula' => 'SBC 1234', 'modelo' => 'Volvo FM', 'capacidad' => 12000], $pisar);
    }

    public function testCrearCamionValido(): void
    {
        $r = Camion::crear($this->datos());
        $this->assertTrue($r['ok']);
        $c = Camion::porId($r['id']);
        $this->assertSame('SBC 1234', $c['matricula']);
        $this->assertSame('funcional', $c['estado'], 'por defecto entra funcional');
    }

    /** @dataProvider invalidos */
    public function testCrearRechazaDatosInvalidos(array $pisar, string $fragmentoError): void
    {
        $r = Camion::crear($this->datos($pisar));
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString($fragmentoError, $r['error']);
    }

    public static function invalidos(): array
    {
        return [
            'sin matrícula'        => [['matricula' => ' '], 'matrícula es obligatoria'],
            'matrícula muy larga'  => [['matricula' => str_repeat('A', 21)], 'hasta 20'],
            'sin modelo'           => [['modelo' => ''], 'modelo es obligatorio'],
            'capacidad cero'       => [['capacidad' => 0], 'capacidad'],
            'capacidad negativa'   => [['capacidad' => -5], 'capacidad'],
            'capacidad texto'      => [['capacidad' => 'mucha'], 'capacidad'],
            'capacidad desbordada' => [['capacidad' => '99999999999'], 'capacidad'],
            'estado inválido'      => [['estado' => 'volando'], 'Estado inválido'],
            'no se crea dado de baja' => [['estado' => 'dado de baja'], 'Estado inválido'],
        ];
    }

    public function testMatriculaDuplicadaSeRechaza(): void
    {
        $r = Camion::crear($this->datos(['matricula' => 'EME 542'])); // ya existe en el seed
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Ya existe', $r['error']);
    }

    public function testActualizarEsParcialYConservaLoQueNoSeEnvia(): void
    {
        $r = Camion::actualizar(1, ['estado' => 'en mantenimiento']);
        $this->assertTrue($r['ok']);
        $c = Camion::porId(1);
        $this->assertSame('en mantenimiento', $c['estado']);
        $this->assertSame('EME 542', $c['matricula']);
        $this->assertSame(12000, $c['capacidad']);
    }

    public function testActualizarPuedeConservarSuPropiaMatriculaPeroNoRobarLaDeOtro(): void
    {
        $this->assertTrue(Camion::actualizar(1, ['matricula' => 'EME 542', 'modelo' => 'Atego 1624'])['ok']);
        $r = Camion::actualizar(1, ['matricula' => 'ARE STP']); // es del camión 2
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Ya existe', $r['error']);
    }

    public function testActualizarInexistenteDa404(): void
    {
        $this->assertSame(404, Camion::actualizar(999, ['modelo' => 'X'])['codigo']);
    }

    public function testBajaExigeMotivoYRegistraFecha(): void
    {
        $sin = Camion::baja(2, '  ');
        $this->assertFalse($sin['ok']);
        $this->assertSame('funcional', Camion::porId(2)['estado'], 'sin motivo no se da de baja');

        $this->assertTrue(Camion::baja(2, 'Siniestro total')['ok']);
        $c = Camion::porId(2);
        $this->assertSame('dado de baja', $c['estado']);
        $this->assertSame('Siniestro total', $c['motivo_baja']);
        $this->assertNotNull($c['fecha_baja']);
        $this->assertSame(1, $this->cuenta('SELECT COUNT(*) FROM camion WHERE ID = 2'), 'la baja no borra la fila');
    }

    public function testNoSeDaDeBajaDosVecesNiSeEditaUnCamionDadoDeBaja(): void
    {
        $this->assertSame(409, Camion::baja(4, 'otra vez')['codigo']);          // el 4 ya está dado de baja en el seed
        $this->assertSame(409, Camion::actualizar(4, ['modelo' => 'X'])['codigo']);
    }

    public function testListarFiltraPorEstadoYBusqueda(): void
    {
        $this->assertCount(4, Camion::listar());
        $this->assertCount(1, Camion::listar('en mantenimiento'));
        $this->assertCount(1, Camion::listar(null, 'Volvo'));
        $this->assertSame('Cuadrilla Alfa', Camion::porId(1)['cuadrilla'], 'el camión 1 está asignado a la cuadrilla 3');
        $this->assertNull(Camion::porId(2)['cuadrilla']);
    }
}
