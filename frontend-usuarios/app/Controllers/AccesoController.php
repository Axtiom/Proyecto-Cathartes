<?php
declare(strict_types=1);

/**
 * Pantallas de acceso del Frontend de Usuarios.
 *
 * Cada POST: verifica CSRF -> llama a la API con ApiClient -> interpreta el JSON.
 * En error, re-renderiza la vista con $errores y $valores (conserva lo tipeado;
 * nunca repone contraseñas). Esta app NO toca la base: todo pasa por la API.
 */
class AccesoController extends Controller
{
    // ---------- GET (render) ----------

    public function login(): void
    {
        $this->render('login', 'Iniciar sesión');
    }

    public function registro(): void
    {
        $this->render('registro', 'Crear cuenta');
    }

    public function verificar(): void
    {
        if (Sesion::preauth() === null) {
            $this->redirigir('/login');
        }
        $this->render('verificar', 'Verificación en dos pasos');
    }

    public function recuperar(): void
    {
        $this->render('recuperar', 'Recuperar contraseña');
    }

    public function restablecer(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        $this->render('restablecer', 'Restablecer contraseña', [], ['token' => $token]);
    }

    // ---------- POST (lógica) ----------

    public function loginPost(): void
    {
        if (!$this->csrfOk()) {
            $this->render('login', 'Iniciar sesión', ['general' => 'La sesión expiró, probá de nuevo.']);
            return;
        }

        $email    = trim((string) ($_POST['email'] ?? ''));
        $recordar = isset($_POST['recordar']);

        [$st, $body] = $this->api()->post('/login', [
            'email'    => $email,
            'password' => (string) ($_POST['password'] ?? ''),
            'recordar' => $recordar,
        ]);
        $d = $this->decodificar($body);

        if ($st === 200 && !empty($d['need2fa'])) {
            Sesion::guardarPreauth((string) $d['preauth']);
            if (isset($d['debug_codigo'])) {
                Sesion::flash('DEV · código 2FA: ' . $d['debug_codigo']);
            }
            $this->redirigir('/verificar');
        }

        $this->render('login', 'Iniciar sesión',
            ['general' => $this->mensaje($d, 'No pudimos iniciar sesión.')],
            ['email' => $email]
        );
    }

    public function registroPost(): void
    {
        if (!$this->csrfOk()) {
            $this->render('registro', 'Crear cuenta', ['general' => 'La sesión expiró, probá de nuevo.']);
            return;
        }

        $valores = [
            'nombre'   => trim((string) ($_POST['nombre'] ?? '')),
            'apellido' => trim((string) ($_POST['apellido'] ?? '')),
            'telefono' => trim((string) ($_POST['telefono'] ?? '')),
            'email'    => trim((string) ($_POST['email'] ?? '')),
        ];

        [$st, $body] = $this->api()->post('/registro', $valores + [
            'password'         => (string) ($_POST['password'] ?? ''),
            'password_confirm' => (string) ($_POST['password_confirm'] ?? ''),
        ]);
        $d = $this->decodificar($body);

        if ($st === 201 && !empty($d['ok'])) {
            $this->render('registro_ok', 'Cuenta creada');
            return;
        }

        $this->render('registro', 'Crear cuenta',
            $this->errores($d, 'No pudimos crear la cuenta.'),
            $valores
        );
    }

    public function verificarPost(): void
    {
        if (!$this->csrfOk()) {
            $this->render('verificar', 'Verificación en dos pasos', ['general' => 'La sesión expiró, probá de nuevo.']);
            return;
        }

        $preauth = Sesion::preauth();
        if ($preauth === null) {
            $this->redirigir('/login');
        }

        [$st, $body] = $this->api()->post('/verificar', [
            'preauth' => $preauth,
            'codigo'  => trim((string) ($_POST['codigo'] ?? '')),
        ]);
        $d = $this->decodificar($body);

        if ($st === 200 && !empty($d['token'])) {
            Sesion::guardarToken((string) $d['token'], (bool) ($d['recordar'] ?? false));
            Sesion::borrarPreauth();
            $this->redirigir('/inicio');
        }

        $this->render('verificar', 'Verificación en dos pasos',
            ['general' => $this->mensaje($d, 'No pudimos verificar el código.')]
        );
    }

    public function recuperarPost(): void
    {
        if (!$this->csrfOk()) {
            $this->render('recuperar', 'Recuperar contraseña', ['general' => 'La sesión expiró, probá de nuevo.']);
            return;
        }

        // respuesta genérica siempre (no revelar si el correo existe)
        $this->api()->post('/recuperar', ['email' => trim((string) ($_POST['email'] ?? ''))]);
        $this->render('recuperar_ok', 'Recuperar contraseña');
    }

    public function restablecerPost(): void
    {
        $token = (string) ($_POST['token'] ?? '');

        if (!$this->csrfOk()) {
            $this->render('restablecer', 'Restablecer contraseña',
                ['general' => 'La sesión expiró, probá de nuevo.'], ['token' => $token]);
            return;
        }

        [$st, $body] = $this->api()->post('/restablecer', [
            'token'            => $token,
            'password'         => (string) ($_POST['password'] ?? ''),
            'password_confirm' => (string) ($_POST['password_confirm'] ?? ''),
        ]);
        $d = $this->decodificar($body);

        if ($st === 200 && !empty($d['ok'])) {
            $this->render('restablecer_ok', 'Contraseña actualizada');
            return;
        }

        $this->render('restablecer', 'Restablecer contraseña',
            $this->errores($d, 'No pudimos actualizar la contraseña.'),
            ['token' => $token]
        );
    }

    // ---------- Protegida + logout ----------

    public function inicio(): void
    {
        if (!Sesion::autenticado()) {
            $this->redirigir('/login');
        }

        [$st, $body] = $this->api()->get('/yo');
        $d = $this->decodificar($body);

        if ($st === 200 && !empty($d['usuario'])) {
            $this->view('acceso.inicio', [
                'titulo'  => 'Inicio',
                'csrf'    => Sesion::csrf(),
                'usuario' => $d['usuario'],
            ], 'auth');
            return;
        }

        // 401 = cuenta desactivada (kill-switch) o token inválido
        Sesion::destruir();
        Sesion::flash('Tu sesión fue cerrada.');
        $this->redirigir('/login');
    }

    public function logout(): void
    {
        if ($this->csrfOk()) {
            $this->api()->post('/logout');
            Sesion::destruir();
        }
        $this->redirigir('/login');
    }

    // ---------- Helpers ----------

    private function api(): ApiClient
    {
        return new ApiClient();
    }

    private function csrfOk(): bool
    {
        return Sesion::csrfValido($_POST['_csrf'] ?? null);
    }

    private function decodificar(?string $body): array
    {
        $d = json_decode($body ?? '', true);
        return is_array($d) ? $d : [];
    }

    /** Mensaje de error de la API, con fallback si la API no respondió. */
    private function mensaje(array $d, string $fallback): string
    {
        return isset($d['error']) ? (string) $d['error'] : $fallback;
    }

    /** Arma $errores para la vista: general + por campo si la API lo indicó. */
    private function errores(array $d, string $fallback): array
    {
        $errores = ['general' => $this->mensaje($d, $fallback)];
        if (!empty($d['campo'])) {
            $errores[(string) $d['campo']] = (string) ($d['error'] ?? '');
        }
        return $errores;
    }

    private function render(string $vista, string $titulo, array $errores = [], array $valores = []): void
    {
        $this->view('acceso.' . $vista, [
            'titulo'  => $titulo,
            'csrf'    => Sesion::csrf(),
            'errores' => $errores,
            'valores' => $valores,
            'flash'   => Sesion::tomarFlash(),
        ], 'auth');
    }

    private function redirigir(string $ruta): void
    {
        header('Location: ' . $ruta);
        exit;
    }
}
