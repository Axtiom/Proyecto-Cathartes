<?php
declare(strict_types=1);

/**
 * Front controller — único punto de entrada de la app unificada de Cathartes.
 * Antes había 4 (uno por app: api-usuarios, api-gestion, api-recoleccion,
 * backoffice); ahora hay uno solo, con un solo Model/View/Controller/Core.
 *
 * Los archivos reales de public/ (css, js, assets) los sirve Apache directo;
 * todo lo demás entra por acá (ver .htaccess).
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/helpers.php';
require BASE_PATH . '/app/Core/Env.php';
require BASE_PATH . '/app/Core/Config.php';
require BASE_PATH . '/app/Core/Router.php';
require BASE_PATH . '/app/Core/Controller.php';
require BASE_PATH . '/app/Core/Database.php';
require BASE_PATH . '/app/Core/Token.php';
require BASE_PATH . '/app/Core/Mailer.php';
require BASE_PATH . '/app/Core/Auth.php';

Env::cargar(BASE_PATH . '/.env');
Config::cargar(require BASE_PATH . '/config/config.php');

/**
 * Red de contención de errores. Una excepción sin capturar (una PDOException,
 * por ejemplo) imprime por defecto el stack trace completo: rutas absolutas
 * del servidor, nombres de tabla y fragmentos de SQL. Eso no puede llegar al
 * navegador. Se loguea completo y se responde según el tipo de ruta: JSON
 * para /api/*, página mínima HTML para el resto. El detalle en pantalla solo
 * con APP_DEBUG=true (dev).
 */
$depurar = (bool) Config::get('app.debug', false);
ini_set('display_errors', $depurar ? '1' : '0');

set_exception_handler(function (Throwable $e) use ($depurar) {
    error_log('[cathartes] ' . get_class($e) . ': ' . $e->getMessage()
        . ' @ ' . $e->getFile() . ':' . $e->getLine());

    if (headers_sent()) {
        return;
    }
    http_response_code(500);

    $esApi = str_starts_with($_SERVER['REQUEST_URI'] ?? '', app_base() . '/api');
    if ($esApi) {
        header('Content-Type: application/json; charset=utf-8');
        $resp = ['ok' => false, 'error' => 'Error interno del servidor'];
        if ($depurar) {
            $resp['detalle'] = $e->getMessage();
        }
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        return;
    }

    header('Content-Type: text/html; charset=utf-8');
    require BASE_PATH . '/app/Views/pantallas/500.php';
});

Auth::iniciar();

$router = new Router();
require BASE_PATH . '/routes/web.php';

$router->dispatch(
    $_SERVER['REQUEST_URI'] ?? '/',
    $_SERVER['REQUEST_METHOD'] ?? 'GET'
);
