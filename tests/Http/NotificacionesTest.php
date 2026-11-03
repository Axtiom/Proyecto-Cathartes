<?php
declare(strict_types=1);

/** Notificaciones: cada persona ve y marca SOLO las suyas. Seed: vecino 4, administrador 1, cuadrilla 3, operario 2. */
final class NotificacionesTest extends HttpTestCase
{
    public static function endpoints(): array
    {
        return ['GET /notificaciones' => ['GET', '/notificaciones'], 'GET /notificaciones/contador' => ['GET', '/notificaciones/contador'],
                'POST /notificaciones/leer' => ['POST', '/notificaciones/leer?id=1'], 'POST /notificaciones/leer-todas' => ['POST', '/notificaciones/leer-todas']];
    }

    /** @dataProvider endpoints */
    public function testSinSesionDa401(string $m, string $r): void
    {
        $this->assertSame(401, $this->usuarios($m, $r, $m === 'POST' ? [] : null)[0]);
    }

    public function testCadaRolVeSoloLasSuyas(): void
    {
        $ids = ['vecino' => 4, 'administrador' => 1, 'cuadrilla' => 3, 'operario' => 2];   // id de usuario de cada rol en el seed
        foreach ($ids as $rol => $id) {
            $this->login($rol);
            [$c, $j] = $this->usuarios('GET', '/notificaciones', null, $rol);
            $this->assertSame(200, $c);
            $this->assertSame($this->cuenta('SELECT COUNT(*) FROM notificacion WHERE ID_usuario = ?', [$id]), $j['total'], $rol);
            $vistas = array_column($j['items'], 'id');
            $ajenas = $this->cuenta('SELECT COUNT(*) FROM notificacion WHERE ID_usuario <> ? AND ID IN (' . (implode(',', $vistas) ?: '0') . ')', [$id]);
            $this->assertSame(0, $ajenas, "$rol no ve avisos ajenos");
        }
        $this->login('operario');
        [, $j] = $this->usuarios('GET', '/notificaciones', null, 'operario');
        $this->assertSame(0, $j['total'], 'sin avisos: lista vacía, no error');
    }

    public function testContadorYFiltroDeNoLeidas(): void
    {
        $this->login('administrador');
        [, $c] = $this->usuarios('GET', '/notificaciones/contador', null, 'administrador');
        $this->assertSame(2, $c['no_leidas']);                       // seed: #4 y #5 sin leer
        [, $l] = $this->usuarios('GET', '/notificaciones?no_leidas=1', null, 'administrador');
        $this->assertSame(2, $l['total']);
        $this->assertSame([false, false], array_column($l['items'], 'leida'));
        [, $l] = $this->usuarios('GET', '/notificaciones?limite=1', null, 'administrador');
        $this->assertCount(1, $l['items']);
        $fechas = array_column($this->usuarios('GET', '/notificaciones', null, 'administrador')[1]['items'], 'fecha');
        $ordenadas = $fechas;
        rsort($ordenadas);
        $this->assertSame($ordenadas, $fechas, 'orden: más nuevas primero');
    }

    public function testMarcarUnaComoLeida(): void
    {
        $this->login('administrador');
        [$c, $j] = $this->usuarios('POST', '/notificaciones/leer?id=4', [], 'administrador');
        $this->assertSame(200, $c);
        $this->assertSame(1, $j['no_leidas']);
        $this->assertSame('si', $this->fila('SELECT leida FROM notificacion WHERE ID = 4')['leida']);
    }

    public function testNadieMarcaLasDeOtro(): void
    {
        $this->login('vecino');
        [$c] = $this->usuarios('POST', '/notificaciones/leer?id=4', [], 'vecino');      // la 4 es del administrador
        $this->assertSame(404, $c, 'se trata como inexistente: no revela que existe');
        $this->assertSame('no', $this->fila('SELECT leida FROM notificacion WHERE ID = 4')['leida']);
        $this->assertSame(404, $this->usuarios('POST', '/notificaciones/leer?id=999', [], 'vecino')[0]);
        $this->assertSame(404, $this->usuarios('POST', '/notificaciones/leer', [], 'vecino')[0], 'sin id');

        $this->usuarios('POST', '/notificaciones/leer-todas', [], 'vecino');
        $this->assertSame(2, $this->cuenta("SELECT COUNT(*) FROM notificacion WHERE ID_usuario = 1 AND leida = 'no'"), 'marcar todas no toca las de otros');
    }

    public function testMarcarTodas(): void
    {
        $this->login('cuadrilla');
        [$c, $j] = $this->usuarios('POST', '/notificaciones/leer-todas', [], 'cuadrilla');
        $this->assertSame(200, $c);
        $this->assertSame(2, $j['marcadas']);
        $this->assertSame(0, $this->usuarios('GET', '/notificaciones/contador', null, 'cuadrilla')[1]['no_leidas']);
        $this->assertSame(0, $this->usuarios('POST', '/notificaciones/leer-todas', [], 'cuadrilla')[1]['marcadas'], 'idempotente');
    }

    public function testUnaCuentaDadaDeBajaNoLeeNotificaciones(): void
    {
        $this->login('vecino');
        $this->login('administrador');
        $this->usuarios('DELETE', '/usuarios?id=4', [], 'administrador');
        $this->assertSame(401, $this->usuarios('GET', '/notificaciones', null, 'vecino')[0]);
    }
}
