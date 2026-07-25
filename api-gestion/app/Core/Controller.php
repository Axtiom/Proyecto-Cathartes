<?php
declare(strict_types=1);

/**
 * Controlador base de la API. A diferencia de la landing, esta app NO
 * renderiza HTML: toda respuesta es JSON.
 */
abstract class Controller
{
    protected function json(array $datos, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Cuerpo JSON del request como arreglo (vacío si no hay o es inválido). */
    protected function entrada(): array
    {
        $raw = file_get_contents('php://input');
        $datos = json_decode($raw ?: '', true);
        return is_array($datos) ? $datos : [];
    }
}
