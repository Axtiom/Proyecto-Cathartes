<?php
declare(strict_types=1);

/**
 * Configuración de la API de Usuarios. Todo sale del .env (nada hardcodeado):
 * el salto local -> producción es solo cambiar el .env. Acceso por Config::get().
 *
 * `frontend-usuarios`, `backoffice`, `api-gestion` y `api-recoleccion` son
 * apps hermanas de esta en el repo (todas cuelgan del mismo nivel). Por eso
 * login_url y el destino post-login usan hermana() (helpers.php): arma la URL
 * relativa a esa carpeta hermana en base al propio SCRIPT_NAME, sin host ni
 * carpeta hardcodeados.
 *
 * IMPORTANTE: token.secret debe ser IDÉNTICO al de las otras 3 apps — esta
 * app EMITE la cookie de sesión que las otras tres validan.
 */
return [
    'app' => [
        'nombre' => 'Cathartes',
        'login_url' => Env::get('LOGIN_URL', hermana('frontend-usuarios', 'login.html')),
        // Modo depuración: SOLO dev. Con true, un error no capturado muestra el
        // detalle en el JSON; con false (producción) solo un mensaje genérico
        // y el detalle va al log de PHP. Nunca ponerlo en true en el servidor.
        'debug' => filter_var(Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    ],

    'db' => [
        'host'    => Env::get('DB_HOST', '127.0.0.1'),
        'name'    => Env::get('DB_NAME', 'cathartes'),
        'user'    => Env::get('DB_USER', ''),
        'pass'    => Env::get('DB_PASS', ''),
        'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    ],

    // Debe coincidir con el de las otras 3 apps: esta es la única que EMITE
    // el token (login/registro); las otras tres solo lo validan.
    'token' => [
        'secret' => Env::get('APP_TOKEN_SECRET', ''),
        'ttl'    => (int) Env::get('APP_TOKEN_TTL', 7776000), // 90 días (Recordarme)
    ],

    'cookie' => [
        'secure' => filter_var(Env::get('COOKIE_SECURE', 'false'), FILTER_VALIDATE_BOOLEAN),
    ],

    'mail' => [
        'driver' => Env::get('MAIL_DRIVER', 'stub'), // stub | smtp
        'host'   => Env::get('SMTP_HOST', ''),
        'port'   => Env::get('SMTP_PORT', ''),
        'user'   => Env::get('SMTP_USER', ''),
        'pass'   => Env::get('SMTP_PASS', ''),
        'from'   => Env::get('SMTP_FROM', ''),
        'secure' => Env::get('SMTP_SECURE', ''),
    ],
];
