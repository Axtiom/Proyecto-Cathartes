<?php
declare(strict_types=1);

/**
 * Configuración del Frontend de Usuarios.
 *
 * Esta app NO toca la base: consume la API de Usuarios. La URL base de la API
 * sale del .env (API_USUARIOS_URL) — nada hardcodeado.
 */
return [
    'app' => [
        'nombre' => 'Acceso · Cathartes',
    ],

    'api' => [
        'usuarios' => Env::get('API_USUARIOS_URL', 'http://127.0.0.1:8081'),
    ],
];
