<?php
declare(strict_types=1);

/**
 * Separación de endpoints por rol. Todo lo de gestión es SOLO del administrador:
 *   - sin sesión            -> 401
 *   - vecino/operario/cuadrilla -> 403
 *   - administrador         -> pasa el control (no 401/403)
 */
final class SeguridadRolesTest extends HttpTestCase
{
    /** [API, método, ruta, cuerpo] */
    public static function endpointsDeAdministrador(): array
    {
        $e = [];
        $rutas = [
            ['u', 'GET', '/usuarios'], ['u', 'GET', '/usuarios/detalle?id=1'], ['u', 'POST', '/usuarios'],
            ['u', 'PUT', '/usuarios?id=4'], ['u', 'DELETE', '/usuarios?id=4'],
            ['u', 'POST', '/usuarios/aprobar?id=6'], ['u', 'POST', '/usuarios/rechazar?id=7'],
            ['g', 'GET', '/camiones'], ['g', 'GET', '/camiones/detalle?id=1'], ['g', 'POST', '/camiones'],
            ['g', 'PUT', '/camiones?id=1'], ['g', 'DELETE', '/camiones?id=2'],
            ['g', 'GET', '/contenedores/detalle?id=1'], ['g', 'POST', '/contenedores'],
            ['g', 'PUT', '/contenedores?id=1'], ['g', 'DELETE', '/contenedores?id=1'], ['g', 'GET', '/rutas'],
            ['g', 'GET', '/instalaciones'], ['g', 'GET', '/instalaciones/detalle?id=1'], ['g', 'POST', '/instalaciones'],
            ['g', 'PUT', '/instalaciones?id=2'], ['g', 'DELETE', '/instalaciones?id=2'],
            ['g', 'GET', '/maquinaria'], ['g', 'GET', '/maquinaria/detalle?id=1'], ['g', 'POST', '/maquinaria'],
            ['g', 'PUT', '/maquinaria?id=1'], ['g', 'DELETE', '/maquinaria?id=1'],
        ];
        foreach ($rutas as [$api, $metodo, $ruta]) {
            $e["$metodo $ruta"] = [$api, $metodo, $ruta];
        }
        return $e;
    }

    private function llamar(string $api, string $metodo, string $ruta, ?string $sesion): array
    {
        $cuerpo = in_array($metodo, ['POST', 'PUT', 'DELETE'], true) ? [] : null;
        return $api === 'u' ? $this->usuarios($metodo, $ruta, $cuerpo, $sesion) : $this->gestion($metodo, $ruta, $cuerpo, $sesion);
    }

    /** @dataProvider endpointsDeAdministrador */
    public function testSinSesionDa401(string $api, string $metodo, string $ruta): void
    {
        [$codigo] = $this->llamar($api, $metodo, $ruta, null);
        $this->assertSame(401, $codigo);
    }

    /** @dataProvider endpointsDeAdministrador */
    public function testLosOtrosRolesRecibenElMismo403(string $api, string $metodo, string $ruta): void
    {
        foreach (['vecino', 'operario', 'cuadrilla'] as $rol) {
            $this->login($rol);
            [$codigo, $json] = $this->llamar($api, $metodo, $ruta, $rol);
            $this->assertSame(403, $codigo, "$rol en $metodo $ruta");
            $this->assertFalse($json['ok']);
        }
    }

    /** @dataProvider endpointsDeAdministrador */
    public function testElAdministradorPasaElControlDeRol(string $api, string $metodo, string $ruta): void
    {
        $this->login('administrador');
        [$codigo] = $this->llamar($api, $metodo, $ruta, 'administrador');
        $this->assertNotContains($codigo, [401, 403], "$metodo $ruta");
        $this->assertLessThan(500, $codigo, "$metodo $ruta no debe dar error de servidor");
    }

    public function testLosEndpointsPublicosNoPidenSesion(): void
    {
        [$c1] = $this->gestion('GET', '/contenedores/publicos');
        [$c2] = $this->gestion('GET', '/estadisticas/publicas');
        [$c3] = $this->gestion('GET', '/health');
        $this->assertSame([200, 200, 200], [$c1, $c2, $c3]);
    }

    public function testElVecinoSiPuedeVerLosContenedoresActivos(): void
    {
        $this->login('vecino');
        [$codigo, $json] = $this->gestion('GET', '/contenedores', null, 'vecino');
        $this->assertSame(200, $codigo);
        $this->assertTrue($json['ok']);
    }

    public function testNingunaRespuestaDeUsuariosExponeContrasenas(): void
    {
        $this->login('administrador');
        [, $lista] = $this->usuarios('GET', '/usuarios', null, 'administrador');
        [, $uno]   = $this->usuarios('GET', '/usuarios/detalle?id=1', null, 'administrador');
        $this->assertStringNotContainsStringIgnoringCase('contrasena', json_encode($lista) . json_encode($uno));
        $this->assertStringNotContainsString('$2y$', json_encode($lista) . json_encode($uno));
    }

    public function testUnaCuentaDadaDeBajaPierdeElAccesoAlInstante(): void
    {
        $this->login('vecino');
        [$antes] = $this->usuarios('GET', '/yo', null, 'vecino');
        $this->assertSame(200, $antes);

        $this->login('administrador');
        [$codigo] = $this->usuarios('DELETE', '/usuarios?id=4', [], 'administrador');
        $this->assertSame(200, $codigo);

        [$despues] = $this->usuarios('GET', '/yo', null, 'vecino');   // misma cookie, token todavía vigente
        $this->assertSame(401, $despues, 'el kill-switch revoca el acceso sin esperar a que el token venza');
        [$login] = $this->login('vecino2', 'vecino@correo.com');
        $this->assertNotSame(200, $login, 'tampoco puede volver a iniciar sesión');
    }

    public function testLoginConCredencialesMalasDaElMismoMensaje(): void
    {
        [$c1, $j1] = $this->login('x', 'vecino@correo.com', 'mala-clave');
        [$c2, $j2] = $this->login('y', 'no-existe@correo.com', 'mala-clave');
        $this->assertSame($c1, $c2);
        $this->assertSame($j1['error'], $j2['error'], 'no revela si el usuario existe');
    }

    public function testElTokenAlteradoSeRechaza(): void
    {
        $ch = curl_init(URL_USUARIOS . '/yo');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIE => 'cathartes_sesion=abc.def']);
        curl_exec($ch);
        $this->assertSame(401, curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);
    }
}
