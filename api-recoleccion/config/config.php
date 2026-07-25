<?php
declare(strict_types=1);

/**
 * Configuración de la API de Recolección.
 *
 * Todo sale del .env vía Env::get() (nada hardcodeado): el salto local ->
 * producción es solo cambiar el .env. Acceso a los valores por Config::get().
 *
 * El token comparte secreto con api-usuarios: los tokens de sesión los emite
 * api-usuarios y esta API solo los VALIDA (mismo APP_TOKEN_SECRET). No hay mail
 * acá.
 */
return [
    'app' => [
        'nombre' => 'API Recolección · SiGeRU',
    ],

    'db' => [
        'host'    => Env::get('DB_HOST', '127.0.0.1'),
        'name'    => Env::get('DB_NAME', 'cathartes'),
        'user'    => Env::get('DB_USER', ''),
        'pass'    => Env::get('DB_PASS', ''),
        'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    ],

    'token' => [
        'secret' => Env::get('APP_TOKEN_SECRET', ''),
        'ttl'    => (int) Env::get('APP_TOKEN_TTL', 7776000),
    ],
];
