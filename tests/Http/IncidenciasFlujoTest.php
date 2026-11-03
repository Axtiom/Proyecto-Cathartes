<?php
declare(strict_types=1);

/**
 * El ciclo completo de una incidencia, de punta a punta y con las tres apps trabajando juntas:
 * el vecino reporta con foto -> el administrador valida y asigna (backoffice) -> la cuadrilla
 * trabaja y finaliza (api-recoleccion) -> el vecino lo ve. En cada paso, quién recibe qué aviso.
 */
final class IncidenciasFlujoTest extends HttpTestCase
{
    private const PAG = '/maquinaria';   // cualquier pantalla del backoffice con un formulario: de ahí sale el token CSRF

    private function noLeidas(int $usuario, string $tipo): int
    {
        return $this->cuenta("SELECT COUNT(*) FROM notificacion WHERE ID_usuario = ? AND tipo = ? AND leida = 'no'", [$usuario, $tipo]);
    }

    private function adminsActivos(): int
    {
        return $this->cuenta("SELECT COUNT(*) FROM administrador a JOIN usuario u ON u.ID = a.ID_usuario WHERE u.estado = 'activo'");
    }

    public function testCicloCompletoDeUnaIncidencia(): void
    {
        $this->login('vecino');
        $this->login('administrador');
        $this->login('cuadrilla');

        // 1) El vecino reporta con foto
        $avisosAdmin = $this->cuenta("SELECT COUNT(*) FROM notificacion WHERE tipo = 'nueva incidencia'");
        [$c, $j] = $this->reportar('vecino', ['id_contenedor' => 1, 'tipo' => 'desbordado', 'descripcion' => 'Bolsas por toda la vereda'], base64_decode(self::PNG_1X1));
        $this->assertSame(201, $c);
        $id = $j['id'];
        $inc = $this->fila('SELECT validada, estado, foto, ID_usuario FROM incidencia_historial WHERE ID_incidencia = ?', [$id]);
        $this->assertSame(['no', 'en curso', "$id.png", '4'], [$inc['validada'], $inc['estado'], $inc['foto'], (string) $inc['ID_usuario']]);
        $this->assertFileExists(UPLOADS_PRUEBA . "/$id.png", 'la foto quedó guardada en disco');
        $this->assertSame($avisosAdmin + $this->adminsActivos(), $this->cuenta("SELECT COUNT(*) FROM notificacion WHERE tipo = 'nueva incidencia'"), 'todos los administradores reciben el aviso');
        $this->assertSame(1, $this->cuenta("SELECT COUNT(*) FROM notificacion WHERE ID_usuario = 5 AND ID_incidencia = ?", [$id]), 'también el administrador común');

        // 2) No se puede asignar una incidencia sin validar
        $cuadrillaAntes = $this->noLeidas(3, 'intervencion asignada');
        $this->formulario('administrador', self::PAG, '/incidencias/asignar', ['id' => $id, 'id_cuadrilla' => 3]);
        $this->assertSame(0, $this->cuenta('SELECT COUNT(*) FROM intervencion WHERE ID_incidencia = ?', [$id]), 'sin validar no se asigna');
        $this->assertSame($cuadrillaAntes, $this->noLeidas(3, 'intervencion asignada'), 'y la cuadrilla no recibe un aviso de algo que no pasó');

        // 3) El administrador valida: avisa al vecino, una sola vez
        $vecinoAntes = $this->noLeidas(4, 'incidencia validada');
        $this->formulario('administrador', self::PAG, '/incidencias/validar', ['id' => $id]);
        $this->assertSame('si', $this->fila('SELECT validada FROM incidencia_historial WHERE ID_incidencia = ?', [$id])['validada']);
        $this->assertSame($vecinoAntes + 1, $this->noLeidas(4, 'incidencia validada'));
        $this->formulario('administrador', self::PAG, '/incidencias/validar', ['id' => $id]);
        $this->assertSame($vecinoAntes + 1, $this->noLeidas(4, 'incidencia validada'), 'validar otra vez no duplica el aviso');

        // 4) Asigna la cuadrilla: avisa al vecino y a la cuadrilla
        $this->formulario('administrador', self::PAG, '/incidencias/asignar', ['id' => $id, 'id_cuadrilla' => 3]);
        $this->assertSame(1, $this->cuenta('SELECT COUNT(*) FROM intervencion WHERE ID_incidencia = ? AND ID_cuadrilla = 3 AND tarea_finalizada = "no"', [$id]));
        $this->assertSame(1, $this->cuenta("SELECT COUNT(*) FROM notificacion WHERE ID_usuario = 4 AND tipo = 'incidencia asignada' AND ID_incidencia = ?", [$id]));
        $this->assertSame(1, $this->cuenta("SELECT COUNT(*) FROM notificacion WHERE ID_usuario = 3 AND tipo = 'intervencion asignada' AND ID_incidencia = ?", [$id]));
        $this->formulario('administrador', self::PAG, '/incidencias/asignar', ['id' => $id, 'id_cuadrilla' => 3]);
        $this->assertSame(1, $this->cuenta('SELECT COUNT(*) FROM intervencion WHERE ID_incidencia = ?', [$id]), 'no se asigna dos veces');

        // 5) La cuadrilla la ve, trabaja y la finaliza
        [, $lista] = $this->recoleccion('GET', '/cuadrilla/intervenciones', null, 'cuadrilla');
        $propia = array_values(array_filter($lista['items'], fn ($i) => $i['id_incidencia'] === $id));
        $this->assertCount(1, $propia);
        $idInt = $propia[0]['id_intervencion'];
        $this->assertSame(200, $this->recoleccion('POST', '/cuadrilla/contenedor/estado', ['id_intervencion' => $idInt, 'estado' => 'funcional'], 'cuadrilla')[0]);
        $this->assertSame(200, $this->recoleccion('POST', '/cuadrilla/intervencion/finalizar', ['id_intervencion' => $idInt], 'cuadrilla')[0]);

        // 6) El vecino ve el cierre y tiene su historial de avisos
        [, $v] = $this->gestion('GET', "/vecino/incidencia?id=$id", null, 'vecino');
        $this->assertSame('finalizada', $v['incidencia']['estado']);
        [, $n] = $this->usuarios('GET', '/notificaciones', null, 'vecino');
        $tipos = array_column(array_filter($n['items'], fn ($x) => $x['id_incidencia'] === $id), 'tipo');
        sort($tipos);
        $this->assertSame(['incidencia asignada', 'incidencia finalizada', 'incidencia validada'], $tipos);
    }

    public function testElReporteSinFotoValidaONoEsImagenNoSeRegistra(): void
    {
        $this->login('vecino');
        $incidencias = $this->cuenta('SELECT COUNT(*) FROM incidencia_historial');
        $avisos = $this->cuenta('SELECT COUNT(*) FROM notificacion');
        $base = ['id_contenedor' => 1, 'tipo' => 'roto'];

        $this->assertSame(400, $this->reportar('vecino', $base, 'esto es texto, no una imagen', 'foto.png')[0], 'texto con extensión .png');
        $this->assertSame(400, $this->reportar('vecino', $base, "GIF89a" . str_repeat("\0", 20), 'foto.gif', 'image/gif')[0], 'gif');
        $this->assertSame(400, $this->reportar('vecino', ['id_contenedor' => 1, 'tipo' => 'inventado'], base64_decode(self::PNG_1X1))[0], 'tipo inválido');
        $this->assertSame(400, $this->reportar('vecino', ['id_contenedor' => 8, 'tipo' => 'roto'], base64_decode(self::PNG_1X1))[0], 'contenedor dado de baja');
        $this->assertSame(400, $this->reportar('vecino', ['id_contenedor' => 999, 'tipo' => 'roto'], base64_decode(self::PNG_1X1))[0], 'contenedor inexistente');
        $this->assertSame($incidencias, $this->cuenta('SELECT COUNT(*) FROM incidencia_historial'), 'no quedó nada registrado');
        $this->assertSame($avisos, $this->cuenta('SELECT COUNT(*) FROM notificacion'), 'y no se avisó a nadie');
    }

    public function testSoloElVecinoReporta(): void
    {
        foreach (['cuadrilla', 'operario', 'administrador'] as $rol) {
            $this->login($rol);
            $this->assertSame(403, $this->reportar($rol, ['id_contenedor' => 1, 'tipo' => 'roto'], base64_decode(self::PNG_1X1))[0], $rol);
        }
    }

    public function testElAdministradorPuedeFinalizarDesdeElBackoffice(): void
    {
        $this->login('administrador');
        // Incidencia 8: validada, con intervención abierta de la cuadrilla 3 (seed)
        $vecino = $this->noLeidas(4, 'incidencia finalizada');
        $this->formulario('administrador', self::PAG, '/incidencias/finalizar', ['id' => 8]);
        $this->assertSame('finalizada', $this->fila('SELECT estado FROM incidencia_historial WHERE ID_incidencia = 8')['estado']);
        $i = $this->fila('SELECT tarea_finalizada, fecha_fin FROM intervencion WHERE ID_incidencia = 8');
        $this->assertSame('si', $i['tarea_finalizada'], 'la intervención abierta se cierra con la incidencia');
        $this->assertNotNull($i['fecha_fin']);
        $this->assertSame($vecino + 1, $this->noLeidas(4, 'incidencia finalizada'));
        $this->formulario('administrador', self::PAG, '/incidencias/finalizar', ['id' => 8]);
        $this->assertSame($vecino + 1, $this->noLeidas(4, 'incidencia finalizada'), 'no se finaliza (ni se avisa) dos veces');
    }

    public function testNoSeFinalizaUnaIncidenciaSinValidar(): void
    {
        $this->login('administrador');
        $sinValidar = (int) $this->fila("SELECT ID_incidencia FROM incidencia_historial WHERE validada = 'no' AND estado = 'en curso' LIMIT 1")['ID_incidencia'];
        $this->formulario('administrador', self::PAG, '/incidencias/finalizar', ['id' => $sinValidar]);
        $this->assertSame('en curso', $this->fila('SELECT estado FROM incidencia_historial WHERE ID_incidencia = ?', [$sinValidar])['estado']);
    }

    public function testCambiarLaOcupacionDesdeElBackofficeYLaApiTambienAlerta(): void
    {
        $this->login('administrador');
        $admins = $this->adminsActivos();
        $alertas = fn () => $this->cuenta("SELECT COUNT(*) FROM notificacion WHERE tipo = 'instalacion llena'");
        $base = $alertas();

        // API de gestión: el vertedero (69 %) pasa al 85 %
        [$c] = $this->gestion('PUT', '/instalaciones?id=2', ['ocupacion_actual' => 8500], 'administrador');
        $this->assertSame(200, $c);
        $this->assertSame($base + $admins, $alertas());
        $this->gestion('PUT', '/instalaciones?id=2', ['ocupacion_actual' => 9000], 'administrador');
        $this->assertSame($base + $admins, $alertas(), 'ya estaba arriba: no repite');

        // vuelve a bajar y a subir: nueva alerta
        $this->gestion('PUT', '/instalaciones?id=2', ['ocupacion_actual' => 1000], 'administrador');
        $this->gestion('PUT', '/instalaciones?id=2', ['ocupacion_actual' => 9500], 'administrador');
        $this->assertSame($base + 2 * $admins, $alertas());

        // Una instalación nueva que nace al 90 % alerta; al 10 %, no
        $this->gestion('POST', '/instalaciones', ['nombre' => 'Acopio Norte', 'ubicacion' => 'Norte', 'tipo' => 'centro de acopio', 'capacidad' => 100, 'ocupacion_actual' => 10], 'administrador');
        $this->assertSame($base + 2 * $admins, $alertas());
        $this->gestion('POST', '/instalaciones', ['nombre' => 'Acopio Sur', 'ubicacion' => 'Sur', 'tipo' => 'centro de acopio', 'capacidad' => 100, 'ocupacion_actual' => 90], 'administrador');
        $this->assertSame($base + 3 * $admins, $alertas());

        // Backoffice: la instalación 1 está al 85 %; baja a 50 y sube a 95
        $this->formulario('administrador', '/instalaciones/editar?id=1', '/instalaciones/actualizar', ['id' => 1, 'nombre' => 'Centro de Acopio Buceo',
            'ubicacion' => 'Av. Rivera 3900, Buceo, Montevideo', 'tipo' => 'centro de acopio', 'capacidad' => 1000, 'ocupacion_actual' => 500]);
        $this->assertSame($base + 3 * $admins, $alertas(), 'bajar no alerta');
        $this->formulario('administrador', '/instalaciones/editar?id=1', '/instalaciones/actualizar', ['id' => 1, 'nombre' => 'Centro de Acopio Buceo',
            'ubicacion' => 'Av. Rivera 3900, Buceo, Montevideo', 'tipo' => 'centro de acopio', 'capacidad' => 1000, 'ocupacion_actual' => 950]);
        $this->assertSame($base + 4 * $admins, $alertas());
    }
}
