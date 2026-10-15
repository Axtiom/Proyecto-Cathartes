<?php
declare(strict_types=1);

/**
 * Levanta api-usuarios, api-gestion y backoffice con el servidor embebido de PHP (php -S), apuntando a la
 * base de PRUEBA, y deja sus URLs en las constantes URL_USUARIOS / URL_GESTION / URL_BACKOFFICE.
 * Los servidores se apagan solos al terminar la corrida.
 */
require __DIR__ . '/bootstrap_comun.php';
require __DIR__ . '/Http/HttpTestCase.php';

$puertoLibre = function (): int {
    $s = stream_socket_server('tcp://127.0.0.1:0', $errno, $err);
    $nombre = stream_socket_get_name($s, false);
    fclose($s);
    return (int) substr(strrchr($nombre, ':'), 1);
};

$procesos = [];
foreach (['USUARIOS' => 'api-usuarios', 'GESTION' => 'api-gestion', 'BACKOFFICE' => 'backoffice'] as $clave => $app) {
    $puerto = $puertoLibre();
    $raiz   = dirname(__DIR__) . '/' . $app . '/public';
    $proc   = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:{$puerto}", '-t', $raiz],
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', sys_get_temp_dir() . "/cathartes_test_{$app}.log", 'a'], 2 => ['file', sys_get_temp_dir() . "/cathartes_test_{$app}.log", 'a']],
        $pipes, null, null
    );
    if (!is_resource($proc)) {
        fwrite(STDERR, "No se pudo levantar {$app}\n");
        exit(1);
    }
    $procesos[] = $proc;
    define("URL_{$clave}", "http://127.0.0.1:{$puerto}");
}
register_shutdown_function(function () use ($procesos) {
    foreach ($procesos as $p) {
        proc_terminate($p);
    }
});

// Esperar a que respondan (cualquier respuesta HTTP sirve).
foreach ([URL_USUARIOS, URL_GESTION, URL_BACKOFFICE] as $url) {
    $listo = false;
    for ($i = 0; $i < 50 && !$listo; $i++) {
        $ch = curl_init($url . '/health');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 1]);
        curl_exec($ch);
        $listo = curl_getinfo($ch, CURLINFO_HTTP_CODE) > 0;
        curl_close($ch);
        if (!$listo) usleep(100000);
    }
    if (!$listo) {
        fwrite(STDERR, "El servidor {$url} no arrancó\n");
        exit(1);
    }
}
