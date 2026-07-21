<?php
declare(strict_types=1);

/**
 * Cliente HTTP (cURL) hacia la API de Usuarios.
 *
 * Base URL del .env (API_USUARIOS_URL). Manda Authorization: Bearer <token>
 * cuando hay sesión abierta. get()/post() devuelven [status, body] — el body
 * es el JSON crudo (string) tal cual lo devuelve la API, o null si cURL falló.
 */
class ApiClient
{
    private string $base;

    public function __construct(?string $base = null)
    {
        $this->base = rtrim($base ?? (string) Config::get('api.usuarios', ''), '/');
    }

    /** @return array{0:int,1:?string} [status, body] */
    public function get(string $ruta): array
    {
        return $this->request('GET', $ruta, null);
    }

    /** @return array{0:int,1:?string} [status, body] */
    public function post(string $ruta, array $datos = []): array
    {
        return $this->request('POST', $ruta, $datos);
    }

    /** @return array{0:int,1:?string} */
    private function request(string $metodo, string $ruta, ?array $datos): array
    {
        $ch = curl_init($this->base . '/' . ltrim($ruta, '/'));

        $headers = ['Accept: application/json'];

        $token = Sesion::token();
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $opciones = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ];

        if ($datos !== null) {
            $headers[]                     = 'Content-Type: application/json';
            $opciones[CURLOPT_POSTFIELDS]  = json_encode($datos, JSON_UNESCAPED_UNICODE);
        }

        $opciones[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opciones);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$status, $body === false ? null : (string) $body];
    }
}
