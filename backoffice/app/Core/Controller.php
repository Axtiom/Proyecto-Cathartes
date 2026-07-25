<?php
declare(strict_types=1);

/**
 * Controlador base del Backoffice. A diferencia de la API de Usuarios (JSON),
 * este app renderiza HTML en el servidor: el Controller consulta el Model y le
 * pasa un array a la vista. El Controller NUNCA emite marcado ni concatena HTML.
 */
abstract class Controller
{
    /**
     * Renderiza una vista de app/Views/pantallas/ con los datos dados.
     * Los datos quedan como variables locales dentro de la vista.
     */
    protected function vista(string $__vista, array $__ctx = []): void
    {
        // Nombres con prefijo __ para no colisionar con las claves de $__ctx al
        // hacer extract (si el parámetro se llamara $datos y hubiera una clave
        // 'datos', EXTR_SKIP la saltearía y la vista recibiría el bolsón entero).
        extract($__ctx, EXTR_SKIP);
        require BASE_PATH . '/app/Views/pantallas/' . $__vista . '.php';
    }

    /** Redirige a una ruta de la app (con el prefijo del subdirectorio). */
    protected function redirigir(string $ruta): void
    {
        header('Location: ' . ruta($ruta));
        exit;
    }

    /** Datos de un POST de formulario (server-rendered => $_POST, no JSON). */
    protected function entrada(): array
    {
        return $_POST;
    }
}
