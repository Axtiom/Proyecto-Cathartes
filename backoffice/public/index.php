<?php
declare(strict_types=1);

/**
 * Front controller — único punto de entrada del Backoffice.
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
require BASE_PATH . '/app/Core/Auth.php';

Env::cargar(BASE_PATH . '/.env');
Config::cargar(require BASE_PATH . '/config/config.php');
Auth::iniciar();

$router = new Router();
require BASE_PATH . '/routes/web.php';

$router->dispatch(
    $_SERVER['REQUEST_URI'] ?? '/',
    $_SERVER['REQUEST_METHOD'] ?? 'GET'
);
