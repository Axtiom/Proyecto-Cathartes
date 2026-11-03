<?php
declare(strict_types=1);

/**
 * Base de los tests por HTTP. Cada test arranca con la base sembrada y se loguea con el
 * rol que necesita. Contraseña de todas las cuentas del seed: Cathartes2026.
 */
abstract class HttpTestCase extends BaseDatosTestCase
{
    protected const CUENTAS = [
        'administrador' => 'admin@cathartes.uy',
        'admin_comun'   => 'admin2@cathartes.uy',
        'operario'      => 'operario@cathartes.uy',
        'cuadrilla'     => 'cuadrilla@cathartes.uy',
        'cuadrilla2'    => 'cuadrilla2@cathartes.uy',   // la crea el test que la necesita
        'vecino'        => 'vecino@correo.com',
    ];

    private array $jars = [];

    /** @return array{0:int,1:array|null} [código HTTP, JSON decodificado] */
    protected function pedir(string $metodo, string $url, ?array $cuerpo = null, ?string $sesion = null): array
    {
        $ch = curl_init($url);
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']];
        if ($cuerpo !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($cuerpo);
        }
        if ($sesion !== null) {
            $jar = $this->jar($sesion);
            $opts[CURLOPT_COOKIEFILE] = $jar;
            $opts[CURLOPT_COOKIEJAR]  = $jar;
        }
        curl_setopt_array($ch, $opts);
        $raw = (string) curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$codigo, json_decode($raw, true)];
    }

    private function jar(string $sesion): string
    {
        return $this->jars[$sesion] ??= tempnam(sys_get_temp_dir(), 'cj_');
    }

    /** Inicia sesión en api-usuarios y deja la cookie guardada bajo el nombre $sesion. */
    protected function login(string $sesion, ?string $email = null, string $password = 'Cathartes2026'): array
    {
        return $this->pedir('POST', URL_USUARIOS . '/login',
            ['email' => $email ?? self::CUENTAS[$sesion], 'password' => $password], $sesion);
    }

    protected function usuarios(string $metodo, string $ruta, ?array $cuerpo = null, ?string $sesion = null): array
    {
        return $this->pedir($metodo, URL_USUARIOS . $ruta, $cuerpo, $sesion);
    }

    protected function gestion(string $metodo, string $ruta, ?array $cuerpo = null, ?string $sesion = null): array
    {
        return $this->pedir($metodo, URL_GESTION . $ruta, $cuerpo, $sesion);
    }

    /** Token de sesión (cookie cathartes_sesion) que dejó el login de la sesión $sesion. */
    private function tokenDe(string $sesion): ?string
    {
        foreach (file($this->jar($sesion)) ?: [] as $linea) {
            $linea = rtrim($linea, "\r\n");
            if (str_starts_with($linea, '#HttpOnly_')) $linea = substr($linea, 10);
            $c = explode("\t", $linea);
            if (count($c) >= 7 && $c[5] === 'cathartes_sesion') return $c[6];
        }
        return null;
    }

    /**
     * Pide una pantalla HTML del backoffice con la sesión $sesion (ya logueada en api-usuarios).
     * Usa un jar propio (para el PHPSESSID del CSRF) y reenvía el token de sesión.
     * No sigue redirecciones. @return array{0:int,1:string,2:string} [código, cuerpo, Location]
     */
    protected function html(string $metodo, string $ruta, array $campos = [], ?string $sesion = null): array
    {
        $ch = curl_init(URL_BACKOFFICE . $ruta);
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_TIMEOUT => 10, CURLOPT_HEADER => true];
        if ($sesion !== null) {
            $jarBo = $this->jar($sesion . '#bo');
            $opts[CURLOPT_COOKIEFILE] = $jarBo;
            $opts[CURLOPT_COOKIEJAR]  = $jarBo;
            if (($t = $this->tokenDe($sesion)) !== null) $opts[CURLOPT_COOKIE] = 'cathartes_sesion=' . $t;
        }
        if ($metodo === 'POST') $opts[CURLOPT_POSTFIELDS] = http_build_query($campos);
        curl_setopt_array($ch, $opts);
        $raw = (string) curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $tam = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $cab = substr($raw, 0, $tam);
        preg_match('/^Location:\s*(.+)$/mi', $cab, $m);
        return [$codigo, substr($raw, $tam), trim($m[1] ?? '')];
    }

    /** Token CSRF que el backoffice incrusta en sus formularios (sale de GET $ruta). */
    protected function csrf(string $sesion, string $ruta): string
    {
        [, $cuerpo] = $this->html('GET', $ruta, [], $sesion);
        preg_match('/name="csrf" value="([^"]+)"/', $cuerpo, $m);
        $this->assertNotEmpty($m[1] ?? '', "no se encontró el token CSRF en $ruta");
        return $m[1];
    }

    protected function recoleccion(string $metodo, string $ruta, ?array $cuerpo = null, ?string $sesion = null): array
    {
        return $this->pedir($metodo, URL_RECOLECCION . $ruta, $cuerpo, $sesion);
    }

    /** Un PNG válido de 1x1 píxel (la API verifica los primeros bytes del archivo, no la extensión). */
    protected const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /**
     * Reporta una incidencia como multipart (campos + archivo `foto`), igual que el formulario del vecino.
     * @return array{0:int,1:array|null}
     */
    protected function reportar(string $sesion, array $campos, string $bytesFoto, string $nombre = 'foto.png', string $mime = 'image/png'): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'foto_');
        file_put_contents($tmp, $bytesFoto);
        $ch = curl_init(URL_GESTION . '/vecino/incidencias');
        $jar = $this->jar($sesion);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 10,
            CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar,
            CURLOPT_POSTFIELDS => $campos + ['foto' => new CURLFile($tmp, $mime, $nombre)]]);
        $raw = (string) curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        @unlink($tmp);
        return [$codigo, json_decode($raw, true)];
    }

    /** Envía un formulario del backoffice: toma el token CSRF de $paginaConFormulario y hace POST a $accion. */
    protected function formulario(string $sesion, string $paginaConFormulario, string $accion, array $campos): array
    {
        $csrf = $this->csrf($sesion, $paginaConFormulario);
        return $this->html('POST', $accion, ['csrf' => $csrf] + $campos, $sesion);
    }

    protected function tearDown(): void
    {
        foreach ($this->jars as $jar) {
            @unlink($jar);
        }
    }
}
