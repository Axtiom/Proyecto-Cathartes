<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Recompensa.php';

/**
 * Gamificación del vecino (Fase 2 · Tanda B). Protegido + rol vecino, scopeado
 * por token.
 *   GET  /recompensas   -> catálogo
 *   POST /canjes        -> canjea una recompensa (descuenta saldo)
 *   GET  /movimientos   -> libro de puntos (validadas +150, canjes −valor)
 *   GET  /mis-canjes    -> recompensas ya canjeadas (personalización del perfil)
 */
class RecompensaVecinoController extends Controller
{
    private function vecino(): array
    {
        $ctx = Auth::proteger();
        if ($ctx['rol'] !== 'vecino') {
            $this->json(['ok' => false, 'error' => 'Requiere rol Vecino'], 403);
            exit;
        }
        return $ctx;
    }

    public function catalogo(): void
    {
        $this->vecino();
        try {
            $this->json(['ok' => true, 'items' => Recompensa::catalogo()]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function canjear(): void
    {
        $v = $this->vecino();
        $in = $this->entrada();
        $idRecompensa = (int) ($in['id_recompensa'] ?? 0);
        if ($idRecompensa <= 0) {
            $this->json(['ok' => false, 'error' => 'Falta la recompensa.'], 400);
            return;
        }
        try {
            $saldo = Recompensa::canjear((int) $v['id'], $idRecompensa);
            $this->json(['ok' => true, 'saldo' => $saldo, 'mensaje' => 'Canje realizado.']);
        } catch (DomainException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function movimientos(): void
    {
        $v = $this->vecino();
        try {
            $this->json(array_merge(['ok' => true], Recompensa::movimientos((int) $v['id'])));
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function misCanjes(): void
    {
        $v = $this->vecino();
        try {
            $this->json(['ok' => true, 'items' => Recompensa::canjesDe((int) $v['id'])]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
