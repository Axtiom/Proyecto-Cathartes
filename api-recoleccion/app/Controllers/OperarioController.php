<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Operario.php';

/**
 * Endpoints del operario de instalación. SOLO rol operario (backend). El operario trabaja siempre
 * sobre SU instalación, que sale de su cuenta y no de un parámetro: no puede ver ni tocar otra.
 *
 *   GET  /operario/resumen      -> instalación y maquinaria funcional
 *   GET  /operario/instalacion  -> instalación y su maquinaria
 *   POST /operario/ocupacion    -> {ocupacion_actual}; si cruza el 80 % avisa a los administradores
 */
class OperarioController extends Controller
{
    private function yo(): int
    {
        return (int) Auth::exigirRol('operario')['id'];
    }

    public function resumen(): void
    {
        $this->json(['ok' => true] + Operario::resumen($this->yo()));
    }

    public function instalacion(): void
    {
        $i = Operario::instalacion($this->yo());
        $this->json(['ok' => true, 'instalacion' => $i, 'maquinaria' => $i ? Operario::maquinaria($i['id']) : []]);
    }

    public function ocupacion(): void
    {
        $in = $this->entrada();
        $r = Operario::registrarOcupacion($this->yo(), $in['ocupacion_actual'] ?? null);
        $this->resultado($r, 200, ['alerta' => $r['alerta'] ?? false, 'mensaje' => 'Ocupación registrada.']);
    }
}
