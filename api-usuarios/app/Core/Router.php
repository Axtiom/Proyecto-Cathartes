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
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->rutas as $ruta) {
            if ($ruta['method'] === $method && $ruta['path'] === $path) {
                $this->invocar($ruta['controller'], $ruta['action']);
                return;
            }
        }
        $this->noEncontrado();
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
