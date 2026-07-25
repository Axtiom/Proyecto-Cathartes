<?php
declare(strict_types=1);

/**
 * Configuración de la API de Usuarios.
 *
 * Todo sale del .env vía Env::get() (nada hardcodeado): el salto local ->
 * producción es solo cambiar el .env. Acceso a los valores por Config::get().
 */
return [
    'app' => [
        'nombre'       => 'API Usuarios · SiGeRU',
        // Base del Frontend de Usuarios, para el enlace de recuperación y el
        // destino post-2FA de vecino/operario/cuadrilla.
        'frontend_url'  => Env::get('FRONTEND_URL', 'http://localhost/sigeru/frontend-usuarios'),
        // Base del Backoffice, destino post-2FA del administrador.
        'backoffice_url' => Env::get('BACKOFFICE_URL', 'http://localhost/sigeru/backoffice/public/usuarios'),
    ],

    // Cookie de sesión (Fase 3.95). Secure según entorno: en dev sobre HTTP va en
    // false; en producción (HTTPS) el .env lo pone en true. Nada hardcodeado.
    'cookie' => [
        'secure' => filter_var(Env::get('COOKIE_SECURE', 'false'), FILTER_VALIDATE_BOOLEAN),
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
        'ttl'    => (int) Env::get('APP_TOKEN_TTL', 7776000), // 90 días (Recordarme)
    ],

    'mail' => [
        'driver' => Env::get('MAIL_DRIVER', 'stub'), // stub | smtp
        'smtp'   => [
            'host'   => Env::get('SMTP_HOST', ''),
            'port'   => Env::get('SMTP_PORT', ''),
            'user'   => Env::get('SMTP_USER', ''),
            'pass'   => Env::get('SMTP_PASS', ''),
            'from'   => Env::get('SMTP_FROM', ''),
            'secure' => Env::get('SMTP_SECURE', ''),
        ],
    ],
];
