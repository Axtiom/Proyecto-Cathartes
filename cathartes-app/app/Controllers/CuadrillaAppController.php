<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Recoleccion.php';

/**
 * Endpoints de la cuadrilla. Protegidos: Auth::proteger() valida la cookie de
 * sesión y re-chequea usuario.estado='activo' (kill-switch). El rol se deduce del subtipo.
 *
 * GET /cuadrilla/ruta -> rutas del día de la cuadrilla autenticada.
 */
class CuadrillaAppController extends Controller
{
    public function ruta(): void
    {
        $usuario = Auth::proteger();

        if ($usuario['rol'] !== 'cuadrilla') {
            $this->json(['ok' => false, 'error' => 'Requiere rol Cuadrilla'], 403);
            return;
        }

        try {
            $this->json([
                'ok'     => true,
                'rutas'  => Recoleccion::rutasDeCuadrilla($usuario['id']),
            ]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
