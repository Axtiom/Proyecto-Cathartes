<?php
declare(strict_types=1);

/**
 * Backoffice (HTML): panel con estadísticas y mapa, informes, listado de incidencias abiertas/cerradas
 * y la pantalla de notificaciones del administrador.
 */
final class PanelYNotificacionesBackofficeTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->login('administrador');
    }

    private function pagina(string $ruta, string $sesion = 'administrador'): string
    {
        [$codigo, $cuerpo] = $this->html('GET', $ruta, [], $sesion);
        $this->assertSame(200, $codigo, $ruta);
        return $cuerpo;
    }

    /** Los puntos del mapa que el servidor incrusta como JSON en el dashboard. */
    private function puntosDelMapa(): array
    {
        preg_match('#<script type="application/json" id="bo-mapa-datos">(.*?)</script>#s', $this->pagina('/dashboard'), $m);
        $this->assertNotEmpty($m[1] ?? '', 'el dashboard incrusta los puntos del mapa');
        return json_decode($m[1], true);
    }

    // ------------------------------------------------------------------- mapa
    public function testElMapaTraeContenedoresInstalacionesEIncidenciasAbiertas(): void
    {
        $p = $this->puntosDelMapa();
        $porTipo = array_count_values(array_column($p, 'tipo'));
        $this->assertSame(8, $porTipo['contenedor']);
        $this->assertSame($this->cuenta('SELECT COUNT(*) FROM instalacion WHERE latitud IS NOT NULL AND longitud IS NOT NULL'), $porTipo['instalacion'], 'solo se dibujan las que tienen coordenadas');
        $abiertas = $this->cuenta("SELECT COUNT(*) FROM incidencia_historial h JOIN contenedor c ON c.ID = h.ID_contenedor
                                   WHERE h.estado = 'en curso' AND c.latitud IS NOT NULL AND c.longitud IS NOT NULL");
        $this->assertSame($abiertas, $porTipo['incidencia'], 'una por cada incidencia abierta (la finalizada no aparece)');

        $inc = array_values(array_filter($p, fn ($x) => $x['tipo'] === 'incidencia'));
        $estados = array_column($inc, 'estado', 'id');
        $this->assertSame('sin validar', $estados[6], 'la 6 sigue pendiente de validar');
        $this->assertSame('en curso', $estados[8]);
        $this->assertArrayNotHasKey(1, $estados, 'la 1 ya está finalizada');
    }

    public function testUnaIncidenciaFinalizadaDejaDeVerseEnElMapa(): void
    {
        $this->formulario('administrador', '/maquinaria', '/incidencias/finalizar', ['id' => 8]);
        $ids = array_column(array_filter($this->puntosDelMapa(), fn ($x) => $x['tipo'] === 'incidencia'), 'id');
        $this->assertNotContains(8, $ids);
    }

    public function testElMapaTieneElFiltroDeIncidencias(): void
    {
        $this->assertStringContainsString('Incidencias abiertas', $this->pagina('/dashboard'));
    }

    // ----------------------------------------------------- top de desbordados
    public function testTopDeContenedoresDesbordadosDelMes(): void
    {
        $b = $this->pagina('/dashboard?periodo=30d');
        $this->assertStringContainsString('Contenedores que más se desbordan', $b);
        // Seed: el contenedor 2 tiene 2 reportes de desborde en 30 días y el 1 tiene 1 (la incidencia 6).
        $esperado = $this->cuenta("SELECT COUNT(*) FROM incidencia_historial WHERE tipo = 'desbordado' AND ID_contenedor = 2 AND fecha_ing >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $this->assertSame(2, $esperado);
        $this->assertMatchesRegularExpression('#Br\. España 2650, Pocitos.*?<b class="bo-mono">2</b>#s', $b);
        $this->assertLessThan(strpos($b, 'Av. Rivera 3245, Buceo'), strpos($b, 'Br. España 2650'), 'el más desbordado va primero');
    }

    public function testElPeriodoCambiaElRanking(): void
    {
        $mes = $this->pagina('/dashboard?periodo=mes');
        $treinta = $this->pagina('/dashboard?periodo=30d');
        $this->assertStringContainsString('bo-tab--activo', $mes);
        $this->assertNotSame($this->pagina('/dashboard?periodo=xyz'), '', 'un período inválido no rompe la pantalla');
        $this->assertSame(substr_count($this->pagina('/dashboard'), 'bo-tab--activo'), substr_count($mes, 'bo-tab--activo'), 'sin parámetro usa «este mes»');
        $this->assertStringContainsString('Últimos 30 días', $treinta);
    }

    public function testElTopSoloCuentaDesbordesNoOtrosTipos(): void
    {
        $b = $this->pagina('/dashboard?periodo=30d');
        // La incidencia 2 es de tipo «roto» en el contenedor 3 (Av. Brasil 3100): no debe figurar en el ranking.
        $this->assertStringNotContainsString('Av. Brasil 3100, Pocitos</td>', substr($b, (int) strpos($b, 'Contenedores que más se desbordan'), 3000));
    }

    // ------------------------------------------------------ incidencias e informes
    public function testListadoDeIncidenciasAbiertasYCerradas(): void
    {
        $abiertas = $this->pagina('/incidencias?estado=en%20curso');
        $cerradas = $this->pagina('/incidencias?estado=finalizada');
        $this->assertStringContainsString('#8', $abiertas);
        $this->assertStringNotContainsString('#1<', $abiertas);
        $this->assertMatchesRegularExpression('/#1\b/', $cerradas);
        $this->assertStringNotContainsString('#8<', $cerradas);
        $sinValidar = $this->pagina('/incidencias?validada=no');
        $this->assertMatchesRegularExpression('/#6\b/', $sinValidar);
    }

    public function testElInformeDeIncidenciasCuadraConLaBase(): void
    {
        $b = $this->pagina('/informes?informe=incidencias');
        $total = $this->cuenta('SELECT COUNT(*) FROM incidencia_historial');
        $enCurso = $this->cuenta("SELECT COUNT(*) FROM incidencia_historial WHERE estado = 'en curso'");
        $this->assertSame(7, $total);
        $this->assertMatchesRegularExpression('/>\s*' . $total . '\s*</', $b, 'total de incidencias');
        $this->assertMatchesRegularExpression('/>\s*' . $enCurso . '\s*</', $b, 'abiertas');
        foreach (['ocupacion', 'flota', 'rendimiento'] as $otro) {
            $this->assertSame(200, $this->html('GET', "/informes?informe=$otro", [], 'administrador')[0], $otro);
        }
    }

    public function testLosInformesSonSoloDelAdministrador(): void
    {
        foreach (['vecino', 'cuadrilla', 'operario'] as $rol) {
            $this->login($rol);
            foreach (['/dashboard', '/informes', '/notificaciones', '/incidencias'] as $ruta) {
                $this->assertSame(403, $this->html('GET', $ruta, [], $rol)[0], "$rol en $ruta");
            }
        }
        $this->assertContains($this->html('GET', '/dashboard')[0], [301, 302, 303], 'sin sesión: al login');
    }

    // --------------------------------------------------------- notificaciones
    public function testLaPantallaMuestraLasDelAdministradorYElGloboDeSinLeer(): void
    {
        $b = $this->pagina('/notificaciones');
        $this->assertStringContainsString('instalación «Centro de Acopio Buceo» llegó al 85', $b);
        $this->assertStringContainsString('Nueva incidencia #8', $b);
        $this->assertMatchesRegularExpression('#<span class="bo-nav__n"[^>]*>2</span>#', $b, 'el menú muestra 2 sin leer');
        $this->assertStringContainsString('Marcar todas como leídas (2)', $b);
        $this->assertStringContainsString('incidencias/ver?id=8', $b, 'enlaza a la incidencia');
    }

    public function testCadaAdministradorVeSoloLasSuyas(): void
    {
        $this->login('admin_comun');
        $b = $this->pagina('/notificaciones', 'admin_comun');
        $this->assertStringNotContainsString('Nueva incidencia #8', $b, 'son del administrador 1');
        $this->assertStringContainsString('Sin notificaciones', $b);
        $this->assertDoesNotMatchRegularExpression('#class="bo-nav__n"#', $b, 'y sin globito');
    }

    public function testMarcarLeidasDesdeLaPantalla(): void
    {
        $this->formulario('administrador', '/notificaciones', '/notificaciones/leer', ['id' => 4]);
        $this->assertSame('si', $this->fila('SELECT leida FROM notificacion WHERE ID = 4')['leida']);
        $this->assertSame('no', $this->fila('SELECT leida FROM notificacion WHERE ID = 5')['leida']);
        $this->assertStringContainsString('Marcar todas como leídas (1)', $this->pagina('/notificaciones'));

        $this->formulario('administrador', '/notificaciones', '/notificaciones/leer-todas', []);
        $this->assertSame(0, $this->cuenta("SELECT COUNT(*) FROM notificacion WHERE ID_usuario = 1 AND leida = 'no'"));
        $b = $this->pagina('/notificaciones?ver=nuevas');
        $this->assertStringContainsString('Todo al día', $b);
    }

    public function testNadieMarcaLasDeOtroAdministrador(): void
    {
        $this->login('admin_comun');
        $this->formulario('admin_comun', '/notificaciones', '/notificaciones/leer', ['id' => 4]);   // la 4 es del administrador 1
        $this->assertSame('no', $this->fila('SELECT leida FROM notificacion WHERE ID = 4')['leida']);
        $this->formulario('admin_comun', '/notificaciones', '/notificaciones/leer-todas', []);
        $this->assertSame(2, $this->cuenta("SELECT COUNT(*) FROM notificacion WHERE ID_usuario = 1 AND leida = 'no'"));
    }

    public function testSinTokenCsrfNoSeMarcaNada(): void
    {
        $this->html('GET', '/notificaciones', [], 'administrador');
        $this->assertSame(419, $this->html('POST', '/notificaciones/leer', ['id' => 4], 'administrador')[0]);
        $this->assertSame(419, $this->html('POST', '/notificaciones/leer-todas', [], 'administrador')[0]);
        $this->assertSame('no', $this->fila('SELECT leida FROM notificacion WHERE ID = 4')['leida']);
    }

    public function testElMensajeDeUnaNotificacionSeEscapaContraXss(): void
    {
        BaseDatosPrueba::conectar()->exec("INSERT INTO notificacion (ID_usuario, tipo, mensaje, fecha, leida) VALUES (1, 'nueva incidencia', '<script>alert(1)</script> <img src=x onerror=alert(2)>', NOW(), 'no')");
        $b = $this->pagina('/notificaciones');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $b);
        $this->assertStringNotContainsString('<img src=x', $b);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $b);
    }
}
