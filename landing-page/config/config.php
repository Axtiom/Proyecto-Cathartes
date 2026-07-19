<?php
declare(strict_types=1);

/**
 * Configuración global de la landing pública de SiGeRU.
 *
 * Nota: la landing NO consume las APIs. Los links de "Iniciar sesión",
 * "Registrarse" y "Reportar" solo NAVEGAN al punto de entrada de las
 * otras apps del monorepo. Los números que se muestran salen del modelo
 * app/Models/Estadisticas.php (hoy en 0 hasta conectar las APIs).
 */
return [
    'app' => [
        'nombre'   => 'Cathartes · SiGeRU',
    ],

    // Puntos de entrada de otras apps (solo navegación, no API)
    'enlaces' => [
        'login'    => '/usuarios/login',
        'registro' => '/usuarios/registro',
        'reportar' => '/usuarios/reportar',
        'rutas'    => '/rutas',
        'mapa'     => '/rutas/mapa',
    ],

    // Contacto mostrado en el footer (demo)
    'contacto' => [
        'telefono' => '0800 1234 (demo)',
        'email'    => 'contacto@axtiom.uy',
        'web'      => 'www.axtiom.uy',
        'lugar'    => 'Montevideo, Uruguay',
    ],
];
