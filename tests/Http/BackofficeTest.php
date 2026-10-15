<?php
declare(strict_types=1);

/**
 * Pantallas del backoffice (HTML del lado del servidor): acceso, CSRF, y los CRUD de
 * usuarios, maquinaria e instalaciones tal como los usa una persona desde el navegador.
 */
final class BackofficeTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->login('administrador');
    }

    private function guardar(string $sesion, string $paginaConFormulario, string $accion, array $campos): array
    {
        $csrf = $this->csrf($sesion, $paginaConFormulario);
        return $this->html('POST', $accion, ['csrf' => $csrf] + $campos, $sesion);
    }

    // ------------------------------------------------------------------ acceso
    public function testSinSesionRedirigeAlLogin(): void
    {
        foreach (['/usuarios/editar?id=4', '/maquinaria', '/maquinaria/editar?id=1', '/instalaciones'] as $ruta) {
            [$codigo, , $loc] = $this->html('GET', $ruta);
            $this->assertContains($codigo, [301, 302, 303], $ruta);
            $this->assertNotSame('', $loc, $ruta);
        }
    }

    public function testUnVecinoNoEntraAlBackoffice(): void
    {
        $this->login('vecino');
        foreach (['/usuarios/editar?id=4', '/maquinaria', '/maquinaria/editar?id=1'] as $ruta) {
            $this->assertSame(403, $this->html('GET', $ruta, [], 'vecino')[0], $ruta);
        }
    }

    public function testLasPantallasNuevasCargan(): void
    {
        [$c, $b] = $this->html('GET', '/maquinaria', [], 'administrador');
        $this->assertSame(200, $c);
        $this->assertStringContainsString('Caterpillar D6', $b);
        $this->assertStringContainsString('Komatsu WA380', $b);

        $this->assertSame(200, $this->html('GET', '/maquinaria/editar?id=1', [], 'administrador')[0]);
        [$c, $b] = $this->html('GET', '/usuarios/editar?id=4', [], 'administrador');
        $this->assertSame(200, $c);
        $this->assertStringContainsString('vecino@correo.com', $b);

        [, $b] = $this->html('GET', '/usuarios?pestana=activos', [], 'administrador');
        $this->assertStringContainsString('usuarios/editar?id=4', $b, 'el listado enlaza a la edición');
    }

    public function testElMenuIncluyeMaquinaria(): void
    {
        [, $b] = $this->html('GET', '/instalaciones', [], 'administrador');
        $this->assertStringContainsString('>Maquinaria<', $b);
    }

    public function testRutaInexistenteVuelveAlListado(): void
    {
        [$c, , $loc] = $this->html('GET', '/maquinaria/editar?id=999', [], 'administrador');
        $this->assertContains($c, [301, 302, 303]);
        $this->assertStringContainsString('maquinaria', $loc);
    }

    // -------------------------------------------------------------------- CSRF
    public function testSinTokenCsrfLasEscriturasSeRechazan(): void
    {
        $this->html('GET', '/maquinaria', [], 'administrador');   // abre la sesión PHP
        $antes = $this->cuenta('SELECT COUNT(*) FROM maquinaria');
        [$c] = $this->html('POST', '/maquinaria/crear', ['modelo' => 'X', 'tipo' => 'y', 'estado' => 'funcional', 'id_instalacion' => 2], 'administrador');
        $this->assertSame(419, $c);
        [$c] = $this->html('POST', '/maquinaria/crear', ['csrf' => 'falso', 'modelo' => 'X', 'tipo' => 'y', 'estado' => 'funcional', 'id_instalacion' => 2], 'administrador');
        $this->assertSame(419, $c);
        [$c] = $this->html('POST', '/usuarios/actualizar', ['id' => 4, 'nombre' => 'Hack'], 'administrador');
        $this->assertSame(419, $c);
        $this->assertSame($antes, $this->cuenta('SELECT COUNT(*) FROM maquinaria'));
        $this->assertSame('Juan Pérez', $this->fila('SELECT nombre FROM usuario WHERE ID = 4')['nombre']);
    }

    // --------------------------------------------------------------- maquinaria
    public function testCicloDeMaquinariaDesdeLasPantallas(): void
    {
        [$c] = $this->guardar('administrador', '/maquinaria', '/maquinaria/crear',
            ['modelo' => 'JCB 3CX', 'tipo' => 'retroexcavadora', 'estado' => 'funcional', 'id_instalacion' => 2]);
        $this->assertContains($c, [302, 303]);
        $fila = $this->fila("SELECT ID, estado FROM maquinaria WHERE modelo = 'JCB 3CX'");
        $this->assertNotNull($fila, 'se creó la máquina');
        $id = (int) $fila['ID'];

        $this->guardar('administrador', "/maquinaria/editar?id=$id", '/maquinaria/actualizar',
            ['id' => $id, 'modelo' => 'JCB 3CX', 'tipo' => 'retroexcavadora', 'estado' => 'en mantenimiento', 'id_instalacion' => 1]);
        $f = $this->fila('SELECT estado, ID_instalacion FROM maquinaria WHERE ID = ?', [$id]);
        $this->assertSame('en mantenimiento', $f['estado']);
        $this->assertSame(1, (int) $f['ID_instalacion']);

        $this->guardar('administrador', "/maquinaria/editar?id=$id", '/maquinaria/eliminar', ['id' => $id]);
        $this->assertNull($this->fila('SELECT ID FROM maquinaria WHERE ID = ?', [$id]), 'se eliminó');
    }

    public function testMaquinariaInvalidaNoSeGuarda(): void
    {
        $antes = $this->cuenta('SELECT COUNT(*) FROM maquinaria');
        foreach ([
            ['modelo' => '', 'tipo' => 'x', 'estado' => 'funcional', 'id_instalacion' => 2],
            ['modelo' => 'M', 'tipo' => '', 'estado' => 'funcional', 'id_instalacion' => 2],
            ['modelo' => 'M', 'tipo' => 'x', 'estado' => 'volando', 'id_instalacion' => 2],
            ['modelo' => 'M', 'tipo' => 'x', 'estado' => 'funcional', 'id_instalacion' => 99],
        ] as $campos) {
            $this->guardar('administrador', '/maquinaria', '/maquinaria/crear', $campos);
        }
        $this->assertSame($antes, $this->cuenta('SELECT COUNT(*) FROM maquinaria'));
    }

    public function testElTextoLibreSeEscapaContraXss(): void
    {
        $this->guardar('administrador', '/maquinaria', '/maquinaria/crear',
            ['modelo' => '<script>alert(1)</script>', 'tipo' => '<img src=x onerror=alert(1)>', 'estado' => 'funcional', 'id_instalacion' => 2]);
        [, $b] = $this->html('GET', '/maquinaria', [], 'administrador');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $b);
        $this->assertStringNotContainsString('<img src=x', $b);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $b);
    }

    // ---------------------------------------------------------------- usuarios
    public function testEditarUnaCuentaDesdeLaPantalla(): void
    {
        $this->guardar('administrador', '/usuarios/editar?id=4', '/usuarios/actualizar', [
            'id' => 4, 'nombre' => 'Juan P. Pérez', 'email' => 'juan@correo.com', 'telefonos' => '091 000 111, 092 000 222', 'password' => '']);
        $this->assertSame('Juan P. Pérez', $this->fila('SELECT nombre FROM usuario WHERE ID = 4')['nombre']);
        $this->assertSame('juan@correo.com', $this->fila('SELECT email FROM usuario WHERE ID = 4')['email']);
        $this->assertSame(2, $this->cuenta('SELECT COUNT(*) FROM usuario_telefono WHERE ID_usuario = 4'));
        $this->assertSame(200, $this->login('juan', 'juan@correo.com')[0], 'la contraseña vacía no la cambió');
    }

    public function testCambiarLaContrasenaDesdeLaPantalla(): void
    {
        $this->guardar('administrador', '/usuarios/editar?id=4', '/usuarios/actualizar', [
            'id' => 4, 'nombre' => 'Juan Pérez', 'email' => 'vecino@correo.com', 'telefonos' => '094 567 890', 'password' => 'OtraClave2026']);
        $this->assertNotSame(200, $this->login('v1', 'vecino@correo.com', 'Cathartes2026')[0]);
        $this->assertSame(200, $this->login('v2', 'vecino@correo.com', 'OtraClave2026')[0]);
    }

    public function testEdicionInvalidaNoCambiaNada(): void
    {
        foreach ([
            ['nombre' => '', 'email' => 'vecino@correo.com', 'telefonos' => '094 567 890'],
            ['nombre' => 'Otro', 'email' => 'no-es-mail', 'telefonos' => '094 567 890'],
            ['nombre' => 'Otro', 'email' => 'admin@cathartes.uy', 'telefonos' => '094 567 890'],
            ['nombre' => 'Otro', 'email' => 'vecino@correo.com', 'telefonos' => 'abc'],
            ['nombre' => 'Otro', 'email' => 'vecino@correo.com', 'telefonos' => '094 567 890', 'password' => 'corta'],
        ] as $campos) {
            $this->guardar('administrador', '/usuarios/editar?id=4', '/usuarios/actualizar', ['id' => 4] + $campos);
        }
        $f = $this->fila('SELECT nombre, email FROM usuario WHERE ID = 4');
        $this->assertSame(['Juan Pérez', 'vecino@correo.com'], [$f['nombre'], $f['email']]);
    }

    public function testUnAdminComunNoEditaAOtroAdminPeroSiASiMismo(): void
    {
        $this->login('admin_comun');
        $this->guardar('admin_comun', '/usuarios/editar?id=5', '/usuarios/actualizar', [
            'id' => 1, 'nombre' => 'Hackeado', 'email' => 'admin@cathartes.uy', 'telefonos' => '091 234 567']);
        $this->assertSame('Galvan de Saero', $this->fila('SELECT nombre FROM usuario WHERE ID = 1')['nombre']);

        $this->guardar('admin_comun', '/usuarios/editar?id=5', '/usuarios/actualizar', [
            'id' => 5, 'nombre' => 'Bruno C.', 'email' => 'admin2@cathartes.uy', 'telefonos' => '095 111 222']);
        $this->assertSame('Bruno C.', $this->fila('SELECT nombre FROM usuario WHERE ID = 5')['nombre']);
    }

    public function testCuadrillaCambiaDeCamionDesdeLaPantalla(): void
    {
        $this->guardar('administrador', '/usuarios/editar?id=3', '/usuarios/actualizar', [
            'id' => 3, 'nombre' => 'Cuadrilla Alfa', 'email' => 'cuadrilla@cathartes.uy', 'telefonos' => '093 456 789',
            'id_camion' => 2, 'estado_cuadrilla' => 'fuera de servicio']);
        $f = $this->fila('SELECT ID_camion, estado FROM cuadrilla WHERE ID_usuario = 3');
        $this->assertSame([2, 'fuera de servicio'], [(int) $f['ID_camion'], $f['estado']]);
    }

    // ----------------------------------------------------------- instalaciones
    public function testNoSeEliminaUnaInstalacionConDependencias(): void
    {
        [, $b] = $this->html('GET', '/instalaciones/editar?id=1', [], 'administrador');
        $this->assertStringContainsString('No se puede eliminar', $b);
        $this->guardar('administrador', '/instalaciones/editar?id=1', '/instalaciones/eliminar', ['id' => 1]);
        $this->assertSame(1, $this->cuenta('SELECT COUNT(*) FROM instalacion WHERE ID = 1'));
    }

    public function testSeEliminaUnaInstalacionSinDependencias(): void
    {
        $this->guardar('administrador', '/instalaciones', '/instalaciones/crear', [
            'nombre' => 'Centro Cerro', 'ubicacion' => 'Cerro', 'tipo' => 'centro de acopio', 'capacidad' => 500, 'ocupacion_actual' => 10]);
        $id = (int) $this->fila("SELECT ID FROM instalacion WHERE nombre = 'Centro Cerro'")['ID'];
        [, $b] = $this->html('GET', "/instalaciones/editar?id=$id", [], 'administrador');
        $this->assertStringContainsString('se puede eliminar', $b);

        $this->guardar('administrador', "/instalaciones/editar?id=$id", '/instalaciones/eliminar', ['id' => $id]);
        $this->assertNull($this->fila('SELECT ID FROM instalacion WHERE ID = ?', [$id]));
    }
}
