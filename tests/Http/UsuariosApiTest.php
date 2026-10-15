<?php
declare(strict_types=1);

/** CRUD de cuentas de api-usuarios por HTTP, incluyendo las reglas RN3 y RN4. */
final class UsuariosApiTest extends HttpTestCase
{
    private function u(string $sesion, string $metodo, string $ruta, ?array $cuerpo = null): array
    {
        return $this->usuarios($metodo, $ruta, $cuerpo, $sesion);
    }

    public function testCicloCompletoDeUnaCuenta(): void
    {
        $this->login('administrador');
        [$c, $j] = $this->u('administrador', 'POST', '/usuarios', [
            'rol' => 'operario', 'nombre' => 'Rosa', 'apellido' => 'Pérez', 'email' => 'rosa@cathartes.uy',
            'password' => 'Clave12345', 'telefono' => '099 555 444', 'id_instalacion' => 2]);
        $this->assertSame(201, $c);
        $id = $j['id'];
        $this->assertSame('operario', $j['usuario']['rol']);
        $this->assertArrayNotHasKey('contrasena', $j['usuario']);

        // La cuenta nueva puede iniciar sesión (el alta del administrador entra activa).
        [$login, $r] = $this->login('rosa', 'rosa@cathartes.uy', 'Clave12345');
        $this->assertSame(200, $login);
        $this->assertSame('operario', $r['rol']);

        [$c, $j] = $this->u('administrador', 'PUT', "/usuarios?id=$id", ['nombre' => 'Rosa M. Pérez', 'telefonos' => ['099 555 444', '098 111 222']]);
        $this->assertSame(200, $c);
        $this->assertSame('Rosa M. Pérez', $j['usuario']['nombre']);
        $this->assertCount(2, $j['usuario']['telefonos']);

        [$c] = $this->u('administrador', 'DELETE', "/usuarios?id=$id");
        $this->assertSame(200, $c);
        [, $j] = $this->u('administrador', 'GET', "/usuarios/detalle?id=$id");
        $this->assertSame('no activo', $j['usuario']['estado']);
    }

    public function testAprobarYRechazarSolicitudes(): void
    {
        $this->login('administrador');
        [, $j] = $this->u('administrador', 'GET', '/usuarios?estado=no%20activo');
        $this->assertSame(2, $j['total']);

        $this->assertSame(200, $this->u('administrador', 'POST', '/usuarios/aprobar?id=6')[0]);
        $this->assertSame(409, $this->u('administrador', 'POST', '/usuarios/aprobar?id=6')[0]);
        $this->assertSame(200, $this->u('administrador', 'POST', '/usuarios/rechazar?id=7')[0]);
        $this->assertSame(404, $this->u('administrador', 'POST', '/usuarios/aprobar?id=999')[0]);

        // La cuenta aprobada ya puede entrar; la rechazada no.
        $this->assertSame(200, $this->login('maria', 'maria.lopez@correo.com')[0]);
        $this->assertNotSame(200, $this->login('diego', 'diego.rodriguez@correo.com')[0]);
    }

    public function testRN3UnAdminComunNoCreaNiDaDeBajaAdministradores(): void
    {
        $this->login('admin_comun');
        [$c, $j] = $this->u('admin_comun', 'POST', '/usuarios', ['rol' => 'administrador', 'nombre' => 'Nuevo',
            'email' => 'nuevo@cathartes.uy', 'password' => 'Clave12345', 'telefono' => '099 111 222']);
        $this->assertSame(403, $c);
        $this->assertStringContainsString('jefe', $j['error']);

        $this->assertSame(403, $this->u('admin_comun', 'DELETE', '/usuarios?id=1')[0]);

        // Sí puede gestionar cuentas que no son de administrador.
        $this->assertSame(200, $this->u('admin_comun', 'DELETE', '/usuarios?id=4')[0]);
    }

    public function testRN4ElSistemaNuncaQuedaSinJefe(): void
    {
        $this->login('administrador');
        [$c, $j] = $this->u('administrador', 'PUT', '/usuarios?id=1', ['jefe' => 'no']);
        $this->assertSame(409, $c);
        $this->assertStringContainsString('último', $j['error']);
        $this->assertSame(409, $this->u('administrador', 'DELETE', '/usuarios?id=1')[0], 'ni darse de baja a sí mismo');
    }

    public function testValidacionesYCodigosDeError(): void
    {
        $this->login('administrador');
        $base = ['rol' => 'vecino', 'nombre' => 'Ana', 'email' => 'ana@correo.com', 'password' => 'Clave12345', 'telefono' => '099 123 456'];
        $this->assertSame(400, $this->u('administrador', 'POST', '/usuarios', array_merge($base, ['password' => 'corta']))[0]);
        $this->assertSame(400, $this->u('administrador', 'POST', '/usuarios', array_merge($base, ['email' => 'mal']))[0]);
        $this->assertSame(409, $this->u('administrador', 'POST', '/usuarios', array_merge($base, ['email' => 'vecino@correo.com']))[0]);
        $this->assertSame(400, $this->u('administrador', 'POST', '/usuarios', array_merge($base, ['rol' => 'dios']))[0]);
        $this->assertSame(404, $this->u('administrador', 'PUT', '/usuarios?id=999', ['nombre' => 'X'])[0]);
        $this->assertSame(404, $this->u('administrador', 'DELETE', '/usuarios?id=999')[0]);
        $this->assertSame(404, $this->u('administrador', 'GET', '/usuarios/detalle?id=999')[0]);
        $this->assertSame(400, $this->u('administrador', 'PUT', '/usuarios?id=4', ['rol' => 'administrador'])[0], 'el rol no se cambia');
    }

    public function testListadoConFiltros(): void
    {
        $this->login('administrador');
        [, $j] = $this->u('administrador', 'GET', '/usuarios');
        $this->assertSame(7, $j['total']);
        [, $j] = $this->u('administrador', 'GET', '/usuarios?rol=administrador');
        $this->assertSame(2, $j['total']);
        [, $j] = $this->u('administrador', 'GET', '/usuarios?q=Garfan');
        $this->assertSame('operario', $j['items'][0]['rol']);
    }
}
