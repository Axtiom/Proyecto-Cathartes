<?php
declare(strict_types=1);

/**
 * Gestión de cuentas. CRUD completo, solo administrador (el rol se valida en el backend).
 *
 *   GET    /usuarios                  -> lista (filtros opcionales ?rol= ?estado= ?q=)
 *   GET    /usuarios/detalle?id=N     -> una cuenta con sus teléfonos
 *   POST   /usuarios                  -> alta con rol (administrador, operario, cuadrilla, vecino)
 *   PUT    /usuarios?id=N             -> edición parcial (el rol no se cambia)
 *   DELETE /usuarios?id=N             -> baja lógica (la cuenta queda 'no activo')
 *   POST   /usuarios/aprobar?id=N     -> aprueba una solicitud pendiente
 *   POST   /usuarios/rechazar?id=N    -> rechaza una solicitud pendiente
 *
 * Reglas: RN3 (solo un administrador jefe crea o da de baja administradores) y
 * RN4 (no se da de baja al último jefe activo). Nunca se devuelve la contraseña ni su hash.
 */
class UsuarioController extends Controller
{
    public function listar(): void
    {
        Auth::exigirRol('administrador');
        $items = Usuario::listar(
            isset($_GET['rol']) ? (string) $_GET['rol'] : null,
            isset($_GET['estado']) ? (string) $_GET['estado'] : null,
            isset($_GET['q']) ? (string) $_GET['q'] : null
        );
        $this->json(['ok' => true, 'total' => count($items), 'items' => $items]);
    }

    public function detalle(): void
    {
        Auth::exigirRol('administrador');
        $id = $this->idQuery();
        $u  = $id > 0 ? Usuario::detalle($id) : null;
        if ($u === null) {
            $this->json(['ok' => false, 'error' => 'Usuario inexistente'], 404);
            return;
        }
        $this->json(['ok' => true, 'usuario' => $u]);
    }

    public function crear(): void
    {
        $admin = Auth::exigirRol('administrador');
        $r = Usuario::crear($this->entrada(), (int) $admin['id']);
        $this->resultado($r, 201, $r['ok'] ? ['id' => $r['id'], 'usuario' => Usuario::detalle($r['id'])] : []);
    }

    public function actualizar(): void
    {
        $admin = Auth::exigirRol('administrador');
        $id = $this->idQuery();
        $r  = Usuario::actualizar($id, $this->entrada(), (int) $admin['id']);
        $this->resultado($r, 200, $r['ok'] ? ['usuario' => Usuario::detalle($id)] : []);
    }

    public function baja(): void
    {
        $admin = Auth::exigirRol('administrador');
        $this->resultado(Usuario::baja($this->idQuery(), (int) $admin['id']), 200, ['mensaje' => 'Usuario dado de baja.']);
    }

    public function aprobar(): void
    {
        $admin = Auth::exigirRol('administrador');
        $this->resultado(Usuario::aprobar($this->idQuery(), (int) $admin['id']), 200, ['mensaje' => 'Usuario aprobado.']);
    }

    public function rechazar(): void
    {
        $admin = Auth::exigirRol('administrador');
        $this->resultado(Usuario::rechazar($this->idQuery(), (int) $admin['id']), 200, ['mensaje' => 'Solicitud rechazada.']);
    }
}
