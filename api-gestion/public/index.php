<?php
declare(strict_types=1);

/**
 * Front controller de la API de Gestión. Único punto de entrada de esta app.
 *
 * Todo lo que sirve esta app es JSON: estadísticas públicas, contenedores
 * (lectura pública/vecino), y el universo Vecino (reportar y seguir sus
 * incidencias).
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/helpers.php';
require BASE_PATH . '/app/Core/Env.php';
require BASE_PATH . '/app/Core/Config.php';
require BASE_PATH . '/app/Core/Router.php';
require BASE_PATH . '/app/Core/Controller.php';
require BASE_PATH . '/app/Core/Database.php';
require BASE_PATH . '/app/Core/Token.php';
require BASE_PATH . '/app/Core/Auth.php';

Env::cargar(BASE_PATH . '/.env');
Config::cargar(require BASE_PATH . '/config/config.php');

/**
 * Red de contención de errores. Una excepción sin capturar (una PDOException,
 * por ejemplo) imprime por defecto el stack trace completo: rutas absolutas
 * del servidor, nombres de tabla y fragmentos de SQL. Eso no puede llegar al
 * navegador: se loguea completo y se responde con un JSON genérico. El
 * detalle en el JSON solo con APP_DEBUG=true (dev).
 */
$depurar = (bool) Config::get('app.debug', false);
ini_set('display_errors', $depurar ? '1' : '0');

set_exception_handler(function (Throwable $e) use ($depurar) {
    error_log('[api-gestion] ' . get_class($e) . ': ' . $e->getMessage()
        . ' @ ' . $e->getFile() . ':' . $e->getLine());

    if (headers_sent()) {
        return;
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    $resp = ['ok' => false, 'error' => 'Error interno del servidor'];
    if ($depurar) {
        $resp['detalle'] = $e->getMessage();
    }
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
});

Auth::iniciar();

$router = new Router();
require BASE_PATH . '/routes/web.php';

$router->dispatch(
    $_SERVER['REQUEST_URI'] ?? '/',
    $_SERVER['REQUEST_METHOD'] ?? 'GET'
);
