<?php
declare(strict_types=1);

/**
 * Front controller — único punto de entrada del Frontend de Usuarios.
 * Todo el tráfico HTTP entra por acá (ver .htaccess).
 *
 * App pública: NO toca la base directo. Todo dato sale de la API de Usuarios
 * vía ApiClient. Acá solo se renderiza HTML.
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/Env.php';
require BASE_PATH . '/app/Core/Config.php';
require BASE_PATH . '/app/Core/Router.php';
require BASE_PATH . '/app/Core/Controller.php';
require BASE_PATH . '/app/Core/View.php';
require BASE_PATH . '/app/Core/Sesion.php';
require BASE_PATH . '/app/Core/ApiClient.php';

Env::cargar(BASE_PATH . '/.env');
Config::cargar(require BASE_PATH . '/config/config.php');

Sesion::iniciar();

$router = new Router();
require BASE_PATH . '/routes/web.php';

$router->dispatch(
    $_SERVER['REQUEST_URI'] ?? '/',
    $_SERVER['REQUEST_METHOD'] ?? 'GET'
);
