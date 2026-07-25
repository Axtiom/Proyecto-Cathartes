<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Usuario.php';

/**
 * Pantalla 3 — Usuarios. Aprobar/rechazar solicitudes, buscar, filtrar por rol,
 * paginar de a 25 y alta manual. El Controller solo consulta el Model y pasa un
 * array a la vista; no arma HTML.
 */
class UsuarioController extends Controller
{
    private const PESTANAS = ['pendientes', 'activos', 'baja', 'todos'];

    public function index(): void
    {
        $admin = Auth::exigir();

        $pestana = (string) ($_GET['pestana'] ?? 'pendientes');
        if (!in_array($pestana, self::PESTANAS, true)) {
            $pestana = 'pendientes';
        }
        $rol = (string) ($_GET['rol'] ?? '');
        $q   = (string) ($_GET['q'] ?? '');

        $datos = Usuario::listar([
            'pestana' => $pestana,
            'rol'     => $rol,
            'q'       => $q,
            'pagina'  => (int) ($_GET['pagina'] ?? 1),
        ]);

        $this->vista('usuarios', [
            'admin'         => $admin,
            'activo'        => 'usuarios',
            'pestana'       => $pestana,
            'rol'           => $rol,
            'q'             => $q,
            'datos'         => $datos,
            'conteos'       => Usuario::conteos(),
            'flash'         => $this->tomarFlash(),
            'csrf'          => Auth::csrf(),
            // Alta multirol (Fase 4.1)
            'esJefe'        => Usuario::esJefe((int) $admin['id']),
            'instalaciones' => Usuario::instalaciones(),
            'camiones'      => Usuario::camionesLibres(),
        ]);
    }

    public function aprobar(): void
    {
        $admin = Auth::exigir();
        Auth::verificarCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            Usuario::aprobar($id, (int) $admin['id']);
            $this->flash('Usuario #' . $id . ' aprobado.');
        }
        $this->volver();
    }

    public function rechazar(): void
    {
        $admin = Auth::exigir();
        Auth::verificarCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->volver();
            return;
        }

        // Dar de baja a un ADMINISTRADOR: RN4 (solo jefe) + protección del último jefe.
        if (Usuario::rolDe($id) === 'administrador') {
            if (!Usuario::esJefe((int) $admin['id'])) {
                http_response_code(403);
                exit('Permiso insuficiente: solo un administrador jefe puede dar de baja administradores.');
            }
            if (Usuario::esUltimoJefe($id)) {
                $this->flash('No se puede dar de baja al último administrador jefe activo: dejaría el sistema sin quien gestione administradores.');
                $this->volver();
                return;
            }
            Usuario::bajaAdministrador($id, (int) $admin['id']);
            $this->flash('Administrador #' . $id . ' dado de baja.');
            $this->volver();
            return;
        }

        Usuario::rechazar($id, (int) $admin['id']);
        $this->flash('Usuario #' . $id . ' rechazado / dado de baja.');
        $this->volver();
    }

    public function crear(): void
    {
        $admin = Auth::exigir();
        Auth::verificarCsrf();
        $rol = (string) ($_POST['rol'] ?? 'vecino');

        // RN4: crear un administrador exige que el admin de sesión sea jefe. Ocultar
        // la opción es UX; la protección real es este 403 (aunque fuercen el POST).
        if ($rol === 'administrador' && !Usuario::esJefe((int) $admin['id'])) {
            http_response_code(403);
            exit('Permiso insuficiente: solo un administrador jefe puede crear administradores.');
        }

        $res = Usuario::crear([
            'rol'            => $rol,
            'nombre'         => $_POST['nombre'] ?? '',
            'apellido'       => $_POST['apellido'] ?? '',
            'telefono'       => $_POST['telefono'] ?? '',
            'email'          => trim((string) ($_POST['email'] ?? '')),
            'password'       => $_POST['password'] ?? '',
            'id_instalacion' => $_POST['id_instalacion'] ?? 0,
            'id_camion'      => $_POST['id_camion'] ?? 0,
            'jefe'           => $_POST['jefe'] ?? 'no',
        ], (int) $admin['id']);

        $this->flash($res['ok']
            ? ('Usuario creado (#' . $res['id'] . ' · ' . $res['rol'] . ').')
            : ('No se pudo crear: ' . $res['error']));
        $this->volver();
    }

    // --- helpers de navegación / flash -------------------------------------
    private function volver(): void
    {
        $volver = (string) ($_POST['volver'] ?? '');
        $qs = (isset($volver[0]) && $volver[0] === '?') ? $volver : '';
        $this->redirigir('usuarios' . $qs);
    }

    private function flash(string $msg): void
    {
        $_SESSION['flash'] = $msg;
    }

    private function tomarFlash(): ?string
    {
        $m = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $m;
    }
}
