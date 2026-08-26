<?php
declare(strict_types=1);

/**
 * Router de la app unificada de Cathartes.
 *
 * Antes había 4 apps (api-usuarios, api-gestion, api-recoleccion, backoffice),
 * cada una con su propio Router/Controller/Model. Se fusionaron en una sola
 * app con un solo Model, una sola Vista, un solo Controller y un solo Core.
 *
 * Las rutas que empiezan con /api/ son las que antes eran las 3 APIs (JSON,
 * las consumen frontend-usuarios y landing-page por fetch). El resto son las
 * pantallas del backoffice (HTML server-rendered). El 404 responde en el
 * formato que corresponde según el prefijo.
 *
 * Recorta dinámicamente el prefijo del subdirectorio vía dirname(SCRIPT_NAME),
 * así matchea igual servido en subcarpeta (Apache local) o en la raíz de un
 * vhost — nada hardcodeado.
 */
class Router
{
    private array $rutas = [];

    public function get(string $path, string $controller, string $action): void
    {
        $this->registrar('GET', $path, $controller, $action);
    }

    public function post(string $path, string $controller, string $action): void
    {
        $this->registrar('POST', $path, $controller, $action);
    }

    private function registrar(string $method, string $path, string $controller, string $action): void
    {
        $this->rutas[] = compact('method', 'path', 'controller', 'action');
    }

    public function dispatch(string $uri, string $method): void
    {
        $path = $this->normalizarPath($uri);

        foreach ($this->rutas as $ruta) {
            if ($ruta['method'] === $method && $ruta['path'] === $path) {
                $this->invocar($ruta['controller'], $ruta['action']);
                return;
            }
        }
        $this->noEncontrado($path);
    }

    /**
     * Normaliza el path de la request recortando dinámicamente el prefijo del
     * subdirectorio donde vive el front controller. El prefijo se deduce de
     * SCRIPT_NAME (p. ej. `/cathartes/cathartes-app/public/index.php` -> base
     * `/cathartes/cathartes-app/public`), NADA hardcodeado. Así las rutas
     * (`/api/login`, `/usuarios`, ...) matchean igual servidas en subcarpeta
     * (Apache local) o en la raíz de un vhost (Rocky).
     */
    private function normalizarPath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = app_base();

        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . ltrim($path, '/');
        $path = rtrim($path, '/');
        return $path === '' ? '/' : $path;
    }

    private function invocar(string $controller, string $action): void
    {
        $ruta = BASE_PATH . '/app/Controllers/' . $controller . '.php';
        if (!is_file($ruta)) {
            $this->noEncontrado();
            return;
        }
        require_once $ruta;
        $instancia = new $controller();
        $instancia->$action();
    }

    private function noEncontrado(string $path = ''): void
    {
        http_response_code(404);
        if (str_starts_with($path, '/api')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'no encontrado'], JSON_UNESCAPED_UNICODE);
            return;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>404</h1><p>Pagina no encontrada.</p>';
    }
}
