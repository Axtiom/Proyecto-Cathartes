<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Models/Informe.php';

/**
 * Pantalla 10 — Informes. Solo lectura. Cuatro informes agregados, dos gráficos SVG
 * y salida a PDF por el diálogo de impresión del navegador (`@media print`), sin
 * librerías de PDF.
 *
 * `?informe=` elige cuál mostrar (vacío = los cuatro): así se puede imprimir un
 * informe suelto o el documento completo, sin duplicar código.
 */
class InformeController extends Controller
{
    /** Slug => título. El orden es el del documento. */
    public const INFORMES = [
        'incidencias'  => 'Incidencias',
        'ocupacion'    => 'Ocupación de instalaciones',
        'flota'        => 'Flota',
        'rendimiento'  => 'Rendimiento por cuadrilla',
    ];

    public function index(): void
    {
        $admin = Auth::exigir();

        $cual = (string) ($_GET['informe'] ?? '');
        if (!array_key_exists($cual, self::INFORMES)) $cual = '';

        $p = Informe::periodo($_GET);

        // Solo se consulta lo que se va a mostrar: un informe suelto no paga las
        // agregaciones de los otros tres.
        $ver = fn(string $s) => $cual === '' || $cual === $s;

        $this->vista('informes', [
            'activo'          => 'informes',
            'admin'           => $admin,
            'cual'            => $cual,
            'p'               => $p,
            'incidencias'     => $ver('incidencias')  ? Informe::incidencias($p) : null,
            'porMes'          => $ver('incidencias')  ? Informe::porMes() : null,
            'ocupacion'       => $ver('ocupacion')    ? Informe::ocupacion() : null,
            'flota'           => $ver('flota')        ? Informe::flota() : null,
            'rendimiento'     => $ver('rendimiento')  ? Informe::rendimiento($p) : null,
            'inconsistencias' => $ver('rendimiento')  ? Informe::inconsistencias() : [],
            'flash'           => $this->tomarFlash(),
            'csrf'            => Auth::csrf(),
        ]);
    }
}
