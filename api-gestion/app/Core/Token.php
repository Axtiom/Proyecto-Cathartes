<?php
declare(strict_types=1);

/**
 * Token firmado propio (HMAC-SHA256), sin librería ni tabla de tokens.
 *
 * Formato: base64url(json(payload)) . "." . base64url(hmac)
 * Firma con el secreto del .env; comparación con hash_equals.
 *
 * Dos tipos, mismo mecanismo. Cada payload lleva `typ` y validar() RECHAZA un
 * token cuyo `typ` no sea el esperado (evita reusar un token de un flujo en otro):
 *
 *   - sesion → {id, rol, exp, typ}   (token de sesión, se emite en el login)
 *   - reset  → {id, exp, typ}        (recuperación de contraseña, 1 hora)
 *
 * La revocación por cuenta (kill-switch) NO vive acá: el guard Auth re-chequea
 * usuario.estado='activo' contra la base en cada request protegido.
 */
class Token
{
    private const TTL_RESET = 3600; // 1 hora

    public static function emitirSesion(int $id, string $rol): string
    {
        return self::emitir([
            'id'  => $id,
            'rol' => $rol,
            'typ' => 'sesion',
        ], (int) Config::get('token.ttl', 7776000));
    }

    public static function emitirReset(int $id): string
    {
        return self::emitir([
            'id'  => $id,
            'typ' => 'reset',
        ], self::TTL_RESET);
    }

    /**
     * Valida firma, expiración y tipo. Devuelve el payload o null.
     */
    public static function validar(string $token, string $typEsperado): ?array
    {
        $partes = explode('.', $token);
        if (count($partes) !== 2) {
            return null;
        }

        [$cuerpo, $firma] = $partes;

        if (!hash_equals(self::firmar($cuerpo), $firma)) {
            return null;
        }

        $payload = json_decode(self::b64UrlDecode($cuerpo), true);
        if (!is_array($payload) || !isset($payload['exp'], $payload['typ'])) {
            return null;
        }

        if ((int) $payload['exp'] < time()) {
            return null;
        }

        if (!hash_equals((string) $payload['typ'], $typEsperado)) {
            return null;
        }

        return $payload;
    }

    private static function emitir(array $payload, int $ttl): string
    {
        $payload['exp'] = time() + $ttl;
        $cuerpo = self::b64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $cuerpo . '.' . self::firmar($cuerpo);
    }

    private static function firmar(string $cuerpo): string
    {
        $secreto = (string) Config::get('token.secret', '');
        return self::b64UrlEncode(hash_hmac('sha256', $cuerpo, $secreto, true));
    }

    private static function b64UrlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function b64UrlDecode(string $texto): string
    {
        return (string) base64_decode(strtr($texto, '-_', '+/'));
    }
}
