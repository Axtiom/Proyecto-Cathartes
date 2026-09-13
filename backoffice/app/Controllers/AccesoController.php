<?php
declare(strict_types=1);

/**
 * Acceso al Backoffice (Fase 3.95). Ya NO hay login propio: la autenticación es
 * la cookie unificada que emite la API de Usuarios (ver Auth). Acá solo queda la
 * entrada ('/') y el logout (que borra la cookie del host -> cierra en todas las
 * apps y vuelve al login unificado del frontend).
 */
class AccesoController extends Controller
{
    public function index(): void
    {
        // Si hay admin válido, al listado; si no, Auth manda al login unificado.
        if (Auth::adminValido()) {
            $this->redirigir('usuarios');
        }
        header('Location: ' . (string) Config::get('app.login_url', ''));
        exit;
    }

    public function logout(): void
    {
        Auth::verificarCsrf();
        Auth::borrarCookie();
        header('Location: ' . (string) Config::get('app.login_url', ''));
        exit;
    }
}
