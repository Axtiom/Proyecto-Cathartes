<?php
declare(strict_types=1);

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
     * SCRIPT_NAME (p. ej. `/sigeru/api-usuarios/public/index.php` -> base
     * `/sigeru/api-usuarios/public`), NADA hardcodeado. Así las rutas (`/health`,
     * `/login`, ...) matchean igual servidas en subcarpeta (Apache local) o en
     * la raíz de un vhost (Rocky Linux). En la raíz, la base es `/` y no recorta.
     */
    private function normalizarPath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($base !== '' && $base !== '/' && $base !== '.' && strpos($path, $base) === 0) {
            $path = substr($path, strlen($base));
        }

        $path = '/' . ltrim($path, '/');
        return rtrim($path, '/') ?: '/';
    }

    private function invocar(string $controller, string $action): void
    {
        $archivo = BASE_PATH . '/app/Controllers/' . $controller . '.php';
        if (!is_file($archivo)) {
            $this->noEncontrado();
            return;
        }
        require $archivo;
        (new $controller())->$action();
    }

    private function noEncontrado(): void
    {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'no encontrado'], JSON_UNESCAPED_UNICODE);
    }
}
