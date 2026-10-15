<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Perfil.php';

/**
 * Perfil del usuario en sesión (Fase 2 · Tanda B). Protegido; cualquier rol.
 *   GET  /perfil  -> nombre, email, teléfonos
 *   POST /perfil  -> actualiza nombre, email, teléfonos (email UNIQUE validado)
 */
class PerfilController extends Controller
{
    public function mostrar(): void
    {
        $ctx = Auth::proteger();
        try {
            $p = Perfil::obtener((int) $ctx['id']);
            if ($p === null) {
                $this->json(['ok' => false, 'error' => 'No encontrado'], 404);
                return;
            }
            $this->json(['ok' => true, 'perfil' => $p]);
        } catch (Throwable $e) {
            error_log('[' . static::class . '] ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Error interno del servidor'], 500);
        }
    }

    public function actualizar(): void
    {
        $ctx = Auth::proteger();
        $in  = $this->entrada();
        $nombre    = trim((string) ($in['nombre'] ?? ''));
        $email     = trim((string) ($in['email'] ?? ''));
        $telefonos = is_array($in['telefonos'] ?? null) ? $in['telefonos'] : [];

        if ($nombre === '' || $email === '') {
            $this->json(['ok' => false, 'error' => 'Nombre y correo son obligatorios.'], 400);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['ok' => false, 'error' => 'El correo no es válido.', 'campo' => 'email'], 400);
            return;
        }
        try {
            if (Perfil::emailTomadoPorOtro($email, (int) $ctx['id'])) {
                $this->json(['ok' => false, 'error' => 'Ese correo ya está en uso.', 'campo' => 'email'], 409);
                return;
            }
            $p = Perfil::actualizar((int) $ctx['id'], $nombre, $email, $telefonos);
            $this->json(['ok' => true, 'perfil' => $p, 'mensaje' => 'Perfil actualizado.']);
        } catch (Throwable $e) {
            error_log('[' . static::class . '] ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Error interno del servidor'], 500);
        }
    }
}
