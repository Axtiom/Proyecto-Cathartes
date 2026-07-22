<?php
declare(strict_types=1);

/**
 * Configuración global de la landing pública de SiGeRU.
 *
 * Nota: la landing NO consume las APIs. Los links de "Iniciar sesión",
 * "Registrarse" y "Reportar" solo NAVEGAN al punto de entrada de las
 * otras apps del monorepo. La URL base del Frontend de Usuarios sale del
 * .env (FRONTEND_USUARIOS_URL) — nada hardcodeado; el salto local ->
 * producción es solo cambiar el .env. Los números que se muestran salen del
 * modelo app/Models/Estadisticas.php (hoy en 0 hasta conectar las APIs).
 */
$frontendUsuarios = rtrim((string) Env::get('FRONTEND_USUARIOS_URL', 'http://127.0.0.1:8080'), '/');

return [
    'app' => [
        'nombre'   => 'Cathartes · SiGeRU',
    ],

    // Puntos de entrada de otras apps (solo navegación, no API).
    // login / registro / reportar viven en el Frontend de Usuarios.
    // rutas / mapa pertenecen a apps aún no construidas (quedan inertes).
    'enlaces' => [
        'login'    => $frontendUsuarios . '/login',
        'registro' => $frontendUsuarios . '/registro',
        'reportar' => $frontendUsuarios . '/login', // reportar exige login; hasta tener Vecino, va al login
        'rutas'    => '#',
        'mapa'     => '#',
    ],

    // Contacto mostrado en el footer (demo)
    'contacto' => [
        'telefono' => '0800 1234 (demo)',
        'email'    => 'contacto@axtiom.uy',
        'web'      => 'www.axtiom.uy',
        'lugar'    => 'Montevideo, Uruguay',
    ],
];
