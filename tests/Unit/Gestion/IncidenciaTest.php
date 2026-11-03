<?php
declare(strict_types=1);

final class IncidenciaTest extends BaseDatosTestCase
{
    public function testCrearRegistraLaIncidenciaDelVecino(): void
    {
        $id = Incidencia::crear(4, 1, 'desbordado', 'Lleno');
        $f = $this->fila('SELECT validada, estado, ID_usuario, ID_contenedor, foto FROM incidencia_historial WHERE ID_incidencia = ?', [$id]);
        $this->assertSame(['no', 'en curso', '4', '1', null], [$f['validada'], $f['estado'], (string) $f['ID_usuario'], (string) $f['ID_contenedor'], $f['foto']]);
    }

    public function testUnaIncidenciaSinFotoSeDescarta(): void
    {
        // La foto es obligatoria: si el guardado falla, la fila no puede quedar.
        $id = Incidencia::crear(4, 1, 'roto', null);
        Notificacion::crear(1, 'nueva incidencia', 'aviso', $id);
        Incidencia::descartarHuerfana($id);
        $this->assertSame(0, $this->cuenta('SELECT COUNT(*) FROM incidencia_historial WHERE ID_incidencia = ?', [$id]));
        $this->assertSame(0, $this->cuenta('SELECT COUNT(*) FROM notificacion WHERE ID_incidencia = ?', [$id]), 'y su aviso tampoco');
    }

    public function testNoSeDescartaUnaIncidenciaQueYaTieneIntervencion(): void
    {
        Incidencia::descartarHuerfana(8);   // la 8 tiene una intervención abierta en el seed
        $this->assertSame(1, $this->cuenta('SELECT COUNT(*) FROM incidencia_historial WHERE ID_incidencia = 8'));
    }
}
