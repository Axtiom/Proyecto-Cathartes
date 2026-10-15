<?php
declare(strict_types=1);

/**
 * Controlador base de toda la app (antes duplicado en las 4 apps separadas).
 * Cubre los dos estilos de respuesta que conviven ahora en un solo Controller:
 *  - vista(): HTML server-rendered (pantallas del backoffice).
 *  - json(): respuesta JSON (endpoints bajo /api/, para el frontend y la landing).
 * El Controller NUNCA emite marcado a mano ni concatena HTML.
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

    /** Responde JSON (endpoints /api/*). */
    protected function json(array $datos, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Traduce el resultado de un Model ({ok, error?, codigo?}) a la respuesta JSON.
     * Errores: usa el `codigo` que puso el Model (403, 404, 409...) o 400 por defecto.
     * Si va bien, responde $okCodigo junto con $extra.
     */
    protected function resultado(array $r, int $okCodigo = 200, array $extra = []): void
    {
        if (!($r['ok'] ?? false)) {
            $cuerpo = ['ok' => false, 'error' => (string) ($r['error'] ?? 'Error')];
            if (isset($r['dependencias'])) {
                $cuerpo['dependencias'] = $r['dependencias'];
            }
            $this->json($cuerpo, (int) ($r['codigo'] ?? 400));
            return;
        }
        $this->json(['ok' => true] + $extra, $okCodigo);
    }

    /** Id numérico de la query string (?id=N). 0 si falta o no es válido. */
    protected function idQuery(): int
    {
        $v = $_GET['id'] ?? '';
        return (is_string($v) && ctype_digit($v)) ? (int) $v : 0;
    }

    /** Redirige a una ruta de la app (con el prefijo del subdirectorio). */
    protected function redirigir(string $ruta): void
    {
        header('Location: ' . ruta($ruta));
        exit;
    }

    /**
     * Cuerpo del request. Los endpoints /api/* mandan JSON (fetch del
     * frontend): si el Content-Type es JSON, se decodifica. Las pantallas del
     * backoffice mandan formularios normales: si no, se devuelve $_POST.
     */
    protected function entrada(): array
    {
        $tipo = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (stripos($tipo, 'application/json') !== false) {
            $raw   = file_get_contents('php://input');
            $datos = json_decode($raw ?: '', true);
            return is_array($datos) ? $datos : [];
        }
        return $_POST;
    }

    /**
     * Deja un mensaje para la pantalla siguiente (patrón POST-Redirect-GET).
     * $tipo: 'ok' | 'error' — decide el color del aviso. Un "no se pudo" tiene
     * que salir en rojo, no en verde.
     */
    protected function flash(string $texto, string $tipo = 'ok'): void
    {
        $_SESSION['flash'] = ['texto' => $texto, 'tipo' => $tipo === 'error' ? 'error' : 'ok'];
    }

    /** Devuelve y consume el flash: ['texto' => ..., 'tipo' => ...] o null. */
    protected function tomarFlash(): ?array
    {
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return is_array($f) ? $f : null;
    }
}
