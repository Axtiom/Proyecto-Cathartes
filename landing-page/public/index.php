<?php
declare(strict_types=1);

/**
 * Front controller — único punto de entrada de la aplicación.
 * Todo el tráfico HTTP entra por acá (ver .htaccess).
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/Env.php';
require BASE_PATH . '/app/Core/Config.php';
require BASE_PATH . '/app/Core/Router.php';
require BASE_PATH . '/app/Core/Controller.php';
require BASE_PATH . '/app/Core/View.php';

Env::cargar(BASE_PATH . '/.env');
Config::cargar(require BASE_PATH . '/config/config.php');

$router = new Router();
require BASE_PATH . '/routes/web.php';

$router->dispatch(
    $_SERVER['REQUEST_URI'] ?? '/',
    $_SERVER['REQUEST_METHOD'] ?? 'GET'
);
