<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Recoleccion.php';

/**
 * Endpoints de la cuadrilla en territorio. SOLO rol cuadrilla (se valida en el backend con
 * Auth::exigirRol, que además re-chequea usuario.estado='activo'). Todo se hace sobre lo que es de
 * la cuadrilla autenticada: el id sale de la sesión, nunca de un parámetro.
 *
 *   GET  /cuadrilla/ruta                      -> rutas que tomó (con instalación de destino)
 *   GET  /cuadrilla/resumen                   -> métricas, ruta vigente y urgentes
 *   GET  /cuadrilla/rutas                     -> rutas con sus contenedores y avance de hoy
 *   GET  /cuadrilla/mapa[?ruta=ID]            -> contenedores e incidencias abiertas de una ruta
 *   GET  /cuadrilla/intervenciones            -> intervenciones asignadas
 *   GET  /cuadrilla/intervencion?id=N         -> detalle + repuestos usados
 *   GET  /cuadrilla/repuestos                 -> inventario con stock
 *   POST /cuadrilla/contenedor/verificar      -> {id_contenedor}: lo marca recorrido hoy
 *   POST /cuadrilla/contenedor/estado         -> {id_intervencion, estado}
 *   POST /cuadrilla/intervencion/repuestos    -> {id_intervencion, id_repuesto, cuantos}: descuenta stock
 *   POST /cuadrilla/intervencion/finalizar    -> {id_intervencion}: cierra tarea e incidencia y avisa al vecino
 */
class CuadrillaController extends Controller
{
    private function yo(): int
    {
        return (int) Auth::exigirRol('cuadrilla')['id'];
    }

    /** Entero estricto de un valor de JSON/formulario; 0 si no es un entero. */
    private function entero(mixed $v): int
    {
        $r = filter_var($v, FILTER_VALIDATE_INT);
        return $r === false ? 0 : (int) $r;
    }

    public function ruta(): void
    {
        $this->json(['ok' => true, 'rutas' => Recoleccion::rutasDeCuadrilla($this->yo())]);
    }

    public function resumen(): void
    {
        $this->json(['ok' => true] + Recoleccion::resumen($this->yo()));
    }

    public function rutas(): void
    {
        $items = Recoleccion::rutas($this->yo());
        $this->json(['ok' => true, 'total' => count($items), 'items' => $items]);
    }

    public function mapa(): void
    {
        $yo = $this->yo();
        $ruta = isset($_GET['ruta']) && ctype_digit((string) $_GET['ruta']) ? (int) $_GET['ruta'] : null;
        $m = Recoleccion::mapa($yo, $ruta);
        if ($m === null) {
            $this->json(['ok' => false, 'error' => 'Esa ruta no es una de las tuyas.'], 404);
            return;
        }
        $this->json(['ok' => true] + $m);
    }

    public function intervenciones(): void
    {
        $items = Recoleccion::intervenciones($this->yo());
        $this->json(['ok' => true, 'total' => count($items), 'items' => $items]);
    }

    public function intervencion(): void
    {
        $d = Recoleccion::intervencion($this->yo(), $this->idQuery());
        if ($d === null) {
            $this->json(['ok' => false, 'error' => 'Intervención inexistente'], 404);
            return;
        }
        $this->json(['ok' => true] + $d);
    }

    public function repuestos(): void
    {
        $this->yo();
        $items = Recoleccion::repuestos();
        $this->json(['ok' => true, 'total' => count($items), 'items' => $items]);
    }

    public function verificar(): void
    {
        $in = $this->entrada();
        $r = Recoleccion::verificar($this->yo(), $this->entero($in['id_contenedor'] ?? null));
        $this->resultado($r, 200, ['ya_verificado' => $r['ya_verificado'] ?? false]);
    }

    public function estadoContenedor(): void
    {
        $in = $this->entrada();
        $this->resultado(
            Recoleccion::cambiarEstadoContenedor($this->yo(), $this->entero($in['id_intervencion'] ?? null), (string) ($in['estado'] ?? '')),
            200, ['mensaje' => 'Estado del contenedor actualizado.']
        );
    }

    public function usarRepuesto(): void
    {
        $in = $this->entrada();
        $this->resultado(
            Recoleccion::usarRepuesto($this->yo(), $this->entero($in['id_intervencion'] ?? null),
                $this->entero($in['id_repuesto'] ?? null), $this->entero($in['cuantos'] ?? null)),
            200, ['mensaje' => 'Repuesto registrado.']
        );
    }

    public function finalizar(): void
    {
        $in = $this->entrada();
        $this->resultado(Recoleccion::finalizar($this->yo(), $this->entero($in['id_intervencion'] ?? null)),
            200, ['mensaje' => 'Tarea finalizada.']);
    }
}
