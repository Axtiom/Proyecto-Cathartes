<?php
declare(strict_types=1);

/**
 * Gestión de cuentas. IDs del seed: 1 = administrador jefe, 5 = administrador común,
 * 2 = operario (instalación 1), 3 = cuadrilla (camión 1), 4 = vecino, 6 y 7 = vecinos pendientes.
 */
final class UsuarioTest extends BaseDatosTestCase
{
    private const JEFE = 1;
    private const ADMIN_COMUN = 5;

    private function vecino(array $pisar = []): array
    {
        return array_merge(['rol' => 'vecino', 'nombre' => 'Ana', 'apellido' => 'Gómez', 'email' => 'ana@correo.com',
            'password' => 'Clave12345', 'telefono' => '099 123 456'], $pisar);
    }

    // ---------------------------------------------------------------- alta
    public function testCrearVecinoActivoConPasswordHasheada(): void
    {
        $r = Usuario::crear($this->vecino(), self::JEFE);
        $this->assertTrue($r['ok']);
        $f = $this->fila('SELECT nombre, estado, contrasena FROM usuario WHERE ID = ?', [$r['id']]);
        $this->assertSame('Ana Gómez', $f['nombre']);
        $this->assertSame('activo', $f['estado']);
        $this->assertNotSame('Clave12345', $f['contrasena']);
        $this->assertTrue(password_verify('Clave12345', $f['contrasena']));
        $this->assertSame('vecino', Usuario::rolDe($r['id']));
        $this->assertSame(1, $this->cuenta("SELECT COUNT(*) FROM autorizacion WHERE ID_usuario = ? AND accion = 'aprobada'", [$r['id']]), 'queda auditado');
    }

    /** @dataProvider altasInvalidas */
    public function testAltaRechazaDatosInvalidos(array $pisar, string $fragmento, int $codigo): void
    {
        $r = Usuario::crear($this->vecino($pisar), self::JEFE);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString($fragmento, $r['error']);
        $this->assertSame($codigo, $r['codigo']);
    }

    public static function altasInvalidas(): array
    {
        return [
            'rol inválido'      => [['rol' => 'dios'], 'Rol inválido', 400],
            'sin nombre'        => [['nombre' => '', 'apellido' => ''], 'nombre', 400],
            'solo apellido'     => [['nombre' => '', 'apellido' => 'Gómez'], 'nombre', 400],
            'email inválido'    => [['email' => 'no-es-mail'], 'correo', 400],
            'email repetido'    => [['email' => 'vecino@correo.com'], 'Ya existe', 409],
            'password corta'    => [['password' => '1234567'], '8 caracteres', 400],
            'sin teléfono'      => [['telefono' => ''], 'teléfono', 400],
            'teléfono con letras' => [['telefono' => 'abcdef'], 'teléfono', 400],
            'campo de otro rol' => [['id_camion' => 2], 'no puede recibir', 400],
        ];
    }

    public function testSoloUnJefePuedeCrearAdministradores(): void
    {
        $d = $this->vecino(['rol' => 'administrador', 'email' => 'nuevo.admin@cathartes.uy']);
        $r = Usuario::crear($d, self::ADMIN_COMUN);
        $this->assertFalse($r['ok']);
        $this->assertSame(403, $r['codigo']);

        $r = Usuario::crear($d, self::JEFE);
        $this->assertTrue($r['ok']);
        $this->assertFalse(Usuario::esJefe($r['id']), 'por defecto no es jefe');
        $this->assertSame(1, $this->cuenta("SELECT COUNT(*) FROM gestiona WHERE ID_afectado = ? AND accion = 'alta'", [$r['id']]));
    }

    public function testOperarioNecesitaUnaInstalacionExistente(): void
    {
        $d = $this->vecino(['rol' => 'operario', 'email' => 'op@cathartes.uy']);
        $this->assertStringContainsString('instalación', Usuario::crear($d, self::JEFE)['error']);
        $this->assertStringContainsString('no existe', Usuario::crear($d + ['id_instalacion' => 99], self::JEFE)['error']);
        $r = Usuario::crear($d + ['id_instalacion' => 2], self::JEFE);
        $this->assertTrue($r['ok']);
        $this->assertSame(2, $this->cuenta('SELECT ID_instalacion FROM operario WHERE ID_usuario = ?', [$r['id']]));
    }

    public function testCuadrillaNoPuedeTomarUnCamionOcupadoNiDadoDeBaja(): void
    {
        $d = $this->vecino(['rol' => 'cuadrilla', 'email' => 'cuad@cathartes.uy']);
        $this->assertSame(409, Usuario::crear($d + ['id_camion' => 1], self::JEFE)['codigo'], 'el 1 ya es de la cuadrilla 3');
        $this->assertSame(409, Usuario::crear($d + ['id_camion' => 4], self::JEFE)['codigo'], 'el 4 está dado de baja');
        $this->assertStringContainsString('no existe', Usuario::crear($d + ['id_camion' => 99], self::JEFE)['error']);
        $r = Usuario::crear($d + ['id_camion' => 2], self::JEFE);
        $this->assertTrue($r['ok']);
        $this->assertSame(2, $this->cuenta('SELECT ID_camion FROM cuadrilla WHERE ID_usuario = ?', [$r['id']]));
    }

    public function testAltaEsAtomica(): void
    {
        // Falla en el subtipo (instalación inexistente se valida antes); forzamos un fallo real: email válido pero rol sin datos
        $antes = $this->cuenta('SELECT COUNT(*) FROM usuario');
        Usuario::crear($this->vecino(['rol' => 'operario', 'email' => 'x@y.com']), self::JEFE);
        $this->assertSame($antes, $this->cuenta('SELECT COUNT(*) FROM usuario'), 'un alta rechazada no deja filas sueltas');
    }

    // ---------------------------------------------------------- edición
    public function testActualizarEsParcialYReemplazaTelefonos(): void
    {
        $r = Usuario::actualizar(4, ['nombre' => 'Juan P. Pérez', 'telefonos' => ['091 000 111', '092 000 222']], self::JEFE);
        $this->assertTrue($r['ok']);
        $u = Usuario::detalle(4);
        $this->assertSame('Juan P. Pérez', $u['nombre']);
        $this->assertSame('vecino@correo.com', $u['email'], 'el email no se tocó');
        $this->assertSame(['091 000 111', '092 000 222'], $u['telefonos']);
    }

    public function testActualizarValidaEmailYPassword(): void
    {
        $this->assertStringContainsString('correo', Usuario::actualizar(4, ['email' => 'mal'], self::JEFE)['error']);
        $this->assertSame(409, Usuario::actualizar(4, ['email' => 'admin@cathartes.uy'], self::JEFE)['codigo']);
        $this->assertTrue(Usuario::actualizar(4, ['email' => 'vecino@correo.com'], self::JEFE)['ok'], 'puede conservar su propio email');
        $this->assertStringContainsString('8 caracteres', Usuario::actualizar(4, ['password' => 'corta'], self::JEFE)['error']);
        $this->assertTrue(Usuario::actualizar(4, ['password' => 'NuevaClave99'], self::JEFE)['ok']);
        $hash = $this->fila('SELECT contrasena FROM usuario WHERE ID = 4')['contrasena'];
        $this->assertTrue(password_verify('NuevaClave99', $hash));
    }

    public function testElRolNoSeCambia(): void
    {
        $r = Usuario::actualizar(4, ['rol' => 'administrador'], self::JEFE);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('no se puede cambiar', $r['error']);
        $this->assertSame('vecino', Usuario::rolDe(4));
    }

    public function testCambiarCamionDeLaCuadrilla(): void
    {
        $this->assertSame(409, Usuario::actualizar(3, ['id_camion' => 4], self::JEFE)['codigo'], 'dado de baja');
        $this->assertTrue(Usuario::actualizar(3, ['id_camion' => 2, 'estado_cuadrilla' => 'fuera de servicio'], self::JEFE)['ok']);
        $f = $this->fila('SELECT ID_camion, estado FROM cuadrilla WHERE ID_usuario = 3');
        $this->assertSame(2, (int) $f['ID_camion']);
        $this->assertSame('fuera de servicio', $f['estado']);
        $this->assertTrue(Usuario::actualizar(3, ['id_camion' => null], self::JEFE)['ok']);
        $this->assertNull($this->fila('SELECT ID_camion FROM cuadrilla WHERE ID_usuario = 3')['ID_camion'], 'quitar el camión');
    }

    public function testUnAdminComunSoloSePuedeEditarASiMismoOPorUnJefe(): void
    {
        $this->assertSame(403, Usuario::actualizar(1, ['nombre' => 'Hack'], self::ADMIN_COMUN)['codigo']);
        $this->assertTrue(Usuario::actualizar(5, ['nombre' => 'Bruno C.'], self::ADMIN_COMUN)['ok'], 'a sí mismo sí');
        $this->assertTrue(Usuario::actualizar(5, ['nombre' => 'Bruno Contralor'], self::JEFE)['ok']);
    }

    public function testSoloUnJefeCambiaLaJefaturaYNuncaSeDejaElSistemaSinJefe(): void
    {
        $this->assertSame(403, Usuario::actualizar(5, ['jefe' => 'si'], self::ADMIN_COMUN)['codigo'], 'no se puede auto-ascender');
        $this->assertSame(409, Usuario::actualizar(1, ['jefe' => 'no'], self::JEFE)['codigo'], 'RN4: es el último jefe');
        $this->assertTrue(Usuario::actualizar(5, ['jefe' => 'si'], self::JEFE)['ok']);
        $this->assertTrue(Usuario::esJefe(5));
        $this->assertTrue(Usuario::actualizar(1, ['jefe' => 'no'], self::JEFE)['ok'], 'con otro jefe ya se puede');
    }

    // ------------------------------------------- aprobar / rechazar / baja
    public function testAprobarSolicitudPendiente(): void
    {
        $this->assertSame('no activo', Usuario::porId(6)['estado']);
        $this->assertTrue(Usuario::aprobar(6, self::JEFE)['ok']);
        $this->assertSame('activo', Usuario::porId(6)['estado']);
        $this->assertSame(409, Usuario::aprobar(6, self::JEFE)['codigo'], 'no se aprueba dos veces');
        $this->assertSame(404, Usuario::aprobar(999, self::JEFE)['codigo']);
    }

    public function testRechazarSoloSirveParaPendientes(): void
    {
        $this->assertTrue(Usuario::rechazar(7, self::JEFE)['ok']);
        $this->assertSame(1, $this->cuenta("SELECT COUNT(*) FROM autorizacion WHERE ID_usuario = 7 AND accion = 'rechazada'"));
        $this->assertSame('no activo', Usuario::porId(7)['estado']);
        $this->assertSame(409, Usuario::rechazar(4, self::JEFE)['codigo'], 'una cuenta activa se da de baja, no se rechaza');
    }

    public function testBajaLogicaNoBorraLaCuenta(): void
    {
        $this->assertTrue(Usuario::baja(4, self::JEFE)['ok']);
        $this->assertSame('no activo', Usuario::porId(4)['estado']);
        $this->assertSame(1, $this->cuenta('SELECT COUNT(*) FROM usuario WHERE ID = 4'), 'la fila sigue existiendo');
        $this->assertSame(1, $this->cuenta("SELECT COUNT(*) FROM autorizacion WHERE ID_usuario = 4 AND accion = 'baja'"));
        $this->assertSame(409, Usuario::baja(4, self::JEFE)['codigo'], 'ya está dada de baja');
    }

    public function testNadieSeDaDeBajaASiMismo(): void
    {
        $this->assertSame(409, Usuario::baja(1, self::JEFE)['codigo']);
    }

    public function testSoloUnJefeDaDeBajaAdministradores(): void
    {
        $r = Usuario::baja(1, self::ADMIN_COMUN);
        $this->assertSame(403, $r['codigo']);
        $this->assertSame('activo', Usuario::porId(1)['estado']);
    }

    public function testElUltimoJefeActivoQuedaProtegido(): void
    {
        // Dos jefes (1 y 5). El 5 da de baja al 1 y queda UN solo jefe activo: el 5.
        Usuario::actualizar(5, ['jefe' => 'si'], self::JEFE);
        $this->assertFalse(Usuario::esUltimoJefe(5), 'con dos jefes activos ninguno es el último');
        $this->assertTrue(Usuario::baja(1, 5)['ok']);
        $this->assertTrue(Usuario::esUltimoJefe(5), 'ahora el 5 es el único jefe activo');
        $r = Usuario::actualizar(5, ['jefe' => 'no'], 5);   // RN4: no puede dejar al sistema sin jefe
        $this->assertSame(409, $r['codigo']);
        $this->assertTrue(Usuario::esJefe(5));
    }

    public function testDosAccionesSobreLaMismaCuentaEnElMismoSegundoNoRompenLaAuditoria(): void
    {
        // PK de `gestiona`/`autorizacion` incluye la fecha al segundo: antes esto daba un error 500.
        $nuevo = Usuario::crear($this->vecino(['rol' => 'administrador', 'email' => 'rapido@cathartes.uy']), self::JEFE);
        $this->assertTrue($nuevo['ok']);
        $this->assertTrue(Usuario::baja($nuevo['id'], self::JEFE)['ok']);
        $this->assertSame(2, $this->cuenta('SELECT COUNT(*) FROM gestiona WHERE ID_afectado = ?', [$nuevo['id']]), 'alta + baja quedan registradas');
    }

    // ----------------------------------------------------------- consulta
    public function testListarFiltraPorRolEstadoYTexto(): void
    {
        $this->assertCount(7, Usuario::listar());
        $this->assertCount(2, Usuario::listar('administrador'));
        $this->assertCount(2, Usuario::listar(null, 'no activo'));
        $this->assertCount(1, Usuario::listar(null, null, 'Garfan'));
        $this->assertCount(3, Usuario::listar('vecino'));
    }

    public function testListarNuncaExponeLaContrasena(): void
    {
        foreach (Usuario::listar() as $u) {
            $this->assertArrayNotHasKey('contrasena', $u);
            $this->assertArrayNotHasKey('password', $u);
        }
        $this->assertArrayNotHasKey('contrasena', Usuario::detalle(1));
    }
}
