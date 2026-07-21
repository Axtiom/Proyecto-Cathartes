<?php
declare(strict_types=1);

class View
{
    public static function render(string $vista, array $datos = [], string $layout = 'main'): void
    {
        $archivoVista = BASE_PATH . '/app/Views/' . str_replace('.', '/', $vista) . '.php';
        if (!is_file($archivoVista)) {
            http_response_code(500);
            echo "Vista no encontrada: {$vista}";
            return;
        }

        extract($datos, EXTR_SKIP);

        ob_start();
        require $archivoVista;
        $contenido = ob_get_clean();

        $archivoLayout = BASE_PATH . '/app/Views/layouts/' . $layout . '.php';
        if (is_file($archivoLayout)) {
            require $archivoLayout;
        } else {
            echo $contenido;
        }
    }

    public static function partial(string $nombre, array $datos = []): void
    {
        $archivo = BASE_PATH . '/app/Views/partials/' . $nombre . '.php';
        if (is_file($archivo)) {
            extract($datos, EXTR_SKIP);
            require $archivo;
        }
    }

    public static function e(?string $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}
