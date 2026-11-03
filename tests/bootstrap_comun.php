<?php
declare(strict_types=1);

// Variables de entorno por defecto de la base de PRUEBA (se pueden pisar desde fuera).
foreach (['DB_HOST' => '127.0.0.1', 'DB_NAME' => 'cathartes_test', 'DB_USER' => 'cathartes_test',
          'DB_PASS' => 'cathartes_test_pass', 'APP_TOKEN_SECRET' => 'secreto-solo-para-tests-0123456789abcdef'] as $k => $v) {
    if (getenv($k) === false || getenv($k) === '') {
        putenv("$k=$v");
    }
}
// Las fotos de incidencias que suben los tests van a una carpeta temporal, no al storage del proyecto.
$uploads = sys_get_temp_dir() . '/cathartes_test_uploads';
if (!is_dir($uploads)) {
    mkdir($uploads, 0775, true);
}
putenv('INCIDENCIAS_UPLOADS_DIR=' . $uploads);
define('UPLOADS_PRUEBA', $uploads);

require __DIR__ . '/lib/BaseDatosPrueba.php';
require __DIR__ . '/lib/BaseDatosTestCase.php';
