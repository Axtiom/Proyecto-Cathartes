<?php
declare(strict_types=1);

/** Arranque de los tests unitarios de api-usuarios (carga el núcleo y los models de esa app). */
require __DIR__ . '/bootstrap_comun.php';

define('BASE_PATH', dirname(__DIR__) . '/api-usuarios');
foreach (['helpers', 'Env', 'Config', 'Database', 'Token'] as $core) {
    require BASE_PATH . "/app/Core/{$core}.php";
}
Env::cargar(BASE_PATH . '/.env'); // no existe en CI/tests: no pasa nada, manda el entorno
Config::cargar(require BASE_PATH . '/config/config.php');
foreach (glob(BASE_PATH . '/app/Models/*.php') as $modelo) {
    require_once $modelo;
}
