<?php
declare(strict_types=1);

/**
 * Configuración del Backoffice. Todo sale del .env (nada hardcodeado): el salto
 * local -> producción es solo cambiar el .env. Acceso por Config::get().
 */
return [
    'app' => [
        'nombre' => 'Backoffice · Cathartes',
        // Login unificado (frontend): a donde se manda si no hay cookie válida.
        'login_url'    => Env::get('LOGIN_URL', 'http://localhost/sigeru/frontend-usuarios/login.html'),
        // Base del Frontend de Usuarios, para el link "ir a tu inicio" de la 403.
        'frontend_url' => Env::get('FRONTEND_URL', 'http://localhost/sigeru/frontend-usuarios'),
        // Modo depuración: SOLO dev. Con true, un error no capturado muestra el
        // detalle en pantalla; con false (producción) muestra una página genérica
        // y el detalle va al log de PHP. Nunca ponerlo en true en el servidor.
        'debug'        => filter_var(Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    ],

    'db' => [
        'host'    => Env::get('DB_HOST', '127.0.0.1'),
        'name'    => Env::get('DB_NAME', 'cathartes'),
        'user'    => Env::get('DB_USER', ''),
        'pass'    => Env::get('DB_PASS', ''),
        'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    ],

    // Mismo secreto que api-usuarios: el backoffice VALIDA la cookie de sesión que
    // aquella emite (typ='sesion'). No emite tokens; solo los verifica.
    'token' => [
        'secret' => Env::get('APP_TOKEN_SECRET', ''),
        'ttl'    => (int) Env::get('APP_TOKEN_TTL', 7776000),
    ],

    'cookie' => [
        'secure' => filter_var(Env::get('COOKIE_SECURE', 'false'), FILTER_VALIDATE_BOOLEAN),
    ],

    // Storage de las fotos de incidencias (las escribe api-gestion). El backoffice
    // las lee de ahí para mostrarlas. Config-driven, nada hardcodeado.
    'uploads' => [
        'incidencias' => Env::get('INCIDENCIAS_UPLOADS_DIR', 'D:/sigeru/api-gestion/storage/uploads/incidencias'),
    ],
];
