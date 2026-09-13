<?php
declare(strict_types=1);

/**
 * Configuración del Backoffice. Todo sale del .env (nada hardcodeado): el
 * salto local -> producción es solo cambiar el .env. Acceso por Config::get().
 *
 * `frontend-usuarios`, `landing-page`, `api-usuarios`, `api-gestion` y
 * `api-recoleccion` son apps hermanas de esta en el repo (todas cuelgan del
 * mismo nivel). Por eso login_url usa hermana() (helpers.php): arma la URL
 * relativa a esa carpeta hermana en base al propio SCRIPT_NAME, sin host ni
 * carpeta hardcodeados. Se puede pisar por .env si en producción viven en
 * otro lado.
 *
 * IMPORTANTE: token.secret debe ser IDÉNTICO al de las otras 3 apps — todas
 * emiten/validan la misma cookie de sesión (`cathartes_sesion`).
 */
return [
    'app' => [
        'nombre' => 'Cathartes',
        // Login unificado (frontend-usuarios): a donde se manda si no hay cookie válida.
        'login_url' => Env::get('LOGIN_URL', hermana('frontend-usuarios', 'login.html')),
        // Modo depuración: SOLO dev. Con true, un error no capturado muestra el
        // detalle en pantalla; con false (producción) muestra una página genérica
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

    // Debe coincidir con el de las otras 3 apps: todas comparten la misma
    // cookie de sesión y necesitan poder validarse el token unas a otras.
    'token' => [
        'secret' => Env::get('APP_TOKEN_SECRET', ''),
        'ttl'    => (int) Env::get('APP_TOKEN_TTL', 7776000), // 90 días (Recordarme)
    ],

    'cookie' => [
        'secure' => filter_var(Env::get('COOKIE_SECURE', 'false'), FILTER_VALIDATE_BOOLEAN),
    ],

    // Storage de las fotos de incidencias. Es una carpeta COMPARTIDA con
    // api-gestion (que las escribe): vive un nivel arriba de esta app, al
    // mismo nivel que las 4 apps y los 2 frontends. Ruta relativa, no un path
    // absoluto de una máquina en particular.
    'uploads' => [
        'incidencias' => Env::get('INCIDENCIAS_UPLOADS_DIR', dirname(BASE_PATH) . '/storage/uploads/incidencias'),
    ],
];
