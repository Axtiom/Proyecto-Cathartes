<?php
declare(strict_types=1);

/** Seed: administradores 1 y 5; vecino 4 (3 avisos, 2 sin leer); cuadrilla 3. Instalación 1: 850 de 1000 m³ (85 %). */
final class NotificacionTest extends BaseDatosTestCase
{
    public function testCrearYListarSoloLasPropias(): void
    {
        $this->assertTrue(Notificacion::crear(2, 'incidencia validada', 'Hola operario', null));
        $propias = Notificacion::listar(2);
        $this->assertCount(1, $propias);
        $this->assertSame('Hola operario', $propias[0]['mensaje']);
        $this->assertFalse($propias[0]['leida']);
        $this->assertNull($propias[0]['id_incidencia']);
        $this->assertCount(3, Notificacion::listar(4), 'las 3 del vecino 4 (seed) no se mezclan con las del operario');
    }

    public function testListarOrdenaLasMasNuevasPrimeroYRespetaElLimite(): void
    {
        Notificacion::crear(2, 'x', 'primera');
        Notificacion::crear(2, 'x', 'segunda');
        Notificacion::crear(2, 'x', 'tercera');
        $this->assertSame(['tercera', 'segunda', 'primera'], array_column(Notificacion::listar(2), 'mensaje'));
        $this->assertCount(2, Notificacion::listar(2, false, 2));
        $this->assertCount(1, Notificacion::listar(2, false, 0), 'el límite mínimo es 1');
        $this->assertCount(3, Notificacion::listar(2, false, 99999), 'y el máximo se acota');
    }

    public function testMarcarLeidaSoloSiEsDeLaPersona(): void
    {
        $this->assertSame(2, Notificacion::contarNoLeidas(4));             // seed: la 2 y la 8
        $this->assertFalse(Notificacion::marcarLeida(4, 4), 'la notificación 4 es del administrador 1: el vecino 4 no puede marcarla');
        $this->assertSame('no', $this->fila('SELECT leida FROM notificacion WHERE ID = 4')['leida']);
        $this->assertTrue(Notificacion::marcarLeida(2, 4));
        $this->assertSame(1, Notificacion::contarNoLeidas(4));
        $this->assertFalse(Notificacion::marcarLeida(999, 4));
    }

    public function testMarcarTodasSoloLasDeLaPersona(): void
    {
        $this->assertSame(2, Notificacion::marcarTodas(4));
        $this->assertSame(0, Notificacion::contarNoLeidas(4));
        $this->assertSame(0, Notificacion::marcarTodas(4), 'idempotente');
        $this->assertSame(2, Notificacion::contarNoLeidas(1), 'no tocó las del administrador');
    }

    public function testParaAdministradoresAvisaSoloALosActivos(): void
    {
        $this->assertSame(2, Notificacion::paraAdministradores('nueva incidencia', 'Hay una incidencia nueva', 6));
        BaseDatosPrueba::conectar()->exec("UPDATE usuario SET estado = 'no activo' WHERE ID = 5");
        $this->assertSame(1, Notificacion::paraAdministradores('nueva incidencia', 'Otra', 6), 'el administrador dado de baja no recibe');
        $this->assertSame(0, $this->cuenta("SELECT COUNT(*) FROM notificacion WHERE ID_usuario = 5 AND mensaje = 'Otra'"));
    }

    public function testMensajesLargosSeCortanSinRomperNada(): void
    {
        $this->assertTrue(Notificacion::crear(2, str_repeat('t', 80), str_repeat('á', 400)));
        $n = Notificacion::listar(2)[0];
        $this->assertSame(255, mb_strlen($n['mensaje']));
        $this->assertStringEndsWith('…', $n['mensaje']);
        $this->assertSame(50, mb_strlen($this->fila('SELECT tipo FROM notificacion WHERE ID = ?', [$n['id']])['tipo']));
    }

    public function testUnaNotificacionNuncaTiraAbajoLaAccionQueLaOriginoSiFalla(): void
    {
        $log = ini_set('error_log', '/dev/null');   // el error se registra en el log a propósito; acá no ensucia la salida
        try {
            $this->assertFalse(Notificacion::crear(9999, 'x', 'usuario inexistente'), 'FK inválida: devuelve false, no lanza excepción');
            $this->assertSame(0, Notificacion::paraAdministradores('x', 'ok', 99999), 'incidencia inexistente: no avisa y no lanza');
        } finally {
            ini_set('error_log', (string) $log);
        }
    }

    /** @dataProvider casosDeUmbral */
    public function testAlertaDeInstalacionSoloAlCruzarElUmbralHaciaArriba(int $antes, int $ahora, bool $debeAlertar): void
    {
        BaseDatosPrueba::conectar()->exec("UPDATE instalacion SET ocupacion_actual = {$ahora} WHERE ID = 1");
        $this->assertSame($debeAlertar, Notificacion::revisarUmbral(1, $antes));
        $this->assertSame($debeAlertar ? 2 : 0, $this->cuenta("SELECT COUNT(*) FROM notificacion WHERE tipo = 'instalacion llena' AND ID > 8"), 'a los 2 administradores');
    }

    public static function casosDeUmbral(): array
    {
        return [
            'cruza hacia arriba (79 -> 80)' => [790, 800, true],
            'de bajo a muy alto'            => [100, 1000, true],
            'justo debajo, no alerta'       => [500, 799, false],
            'ya estaba arriba'              => [850, 900, false],
            'baja de arriba a abajo'        => [900, 500, false],
            'sigue en 80 exacto'            => [800, 800, false],
        ];
    }

    public function testElMensajeDeLaAlertaNombraLaInstalacionYElPorcentaje(): void
    {
        BaseDatosPrueba::conectar()->exec('UPDATE instalacion SET ocupacion_actual = 900 WHERE ID = 1');
        Notificacion::revisarUmbral(1, 100);
        $m = Notificacion::listar(1)[0]['mensaje'];
        $this->assertStringContainsString('Centro de Acopio Buceo', $m);
        $this->assertStringContainsString('90 %', $m);
        $this->assertFalse(Notificacion::revisarUmbral(999, 0), 'instalación inexistente');
    }
}
