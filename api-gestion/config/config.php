<?php
declare(strict_types=1);

/**
 * Configuración de la API de Gestión. Todo sale del .env (nada hardcodeado):
 * el salto local -> producción es solo cambiar el .env. Acceso por Config::get().
 *
 * `frontend-usuarios`, `landing-page`, `backoffice`, `api-usuarios` y
 * `api-recoleccion` son apps hermanas de esta en el repo.
 *
 * IMPORTANTE: token.secret debe ser IDÉNTICO al de las otras 3 apps — todas
 * validan la misma cookie de sesión (`cathartes_sesion`), emitida por
 * api-usuarios.
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

    'token' => [
        'secret' => Env::get('APP_TOKEN_SECRET', ''),
        'ttl'    => (int) Env::get('APP_TOKEN_TTL', 7776000), // 90 días (Recordarme)
    ],

    'cookie' => [
        'secure' => filter_var(Env::get('COOKIE_SECURE', 'false'), FILTER_VALIDATE_BOOLEAN),
    ],

    // Storage de las fotos de incidencias. Es una carpeta COMPARTIDA con el
    // backoffice (que las lee): esta app las escribe al reportar una
    // incidencia. Vive un nivel arriba de esta app, al mismo nivel que las
    // 4 apps y los 2 frontends. Ruta relativa, no un path absoluto de una
    // máquina en particular.
    'uploads' => [
        'incidencias' => Env::get('INCIDENCIAS_UPLOADS_DIR', dirname(BASE_PATH) . '/storage/uploads/incidencias'),
    ],
];
