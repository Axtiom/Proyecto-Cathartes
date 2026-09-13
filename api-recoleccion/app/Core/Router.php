<?php
declare(strict_types=1);

/**
 * Router de la API de Recolección. JSON.
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
        $this->noEncontrado();
    }

    /**
     * Normaliza el path de la request recortando dinámicamente el prefijo del
     * subdirectorio donde vive el front controller. El prefijo se deduce de
     * SCRIPT_NAME, NADA hardcodeado. Así las rutas matchean igual servidas en
     * subcarpeta (Apache local) o en la raíz de un vhost (Rocky).
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

    private function noEncontrado(): void
    {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'no encontrado'], JSON_UNESCAPED_UNICODE);
    }
}
