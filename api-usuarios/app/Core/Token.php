<?php
namespace App\Core;

class Token {
    // Clave secreta para entorno de desarrollo local
    private static $secret_key = "SiGeRU_Secret_Key_UTU_2026_Development";

    /**
     * Genera un JWT válido para desarrollo local (Expiración predeterminada: 24 horas)
     */
    public static function generarToken(array $data, int $expiracionHoras = 24): string {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode(array_merge($data, [
            'iat' => time(),
            'exp' => time() + ($expiracionHoras * 3600)
        ]));

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret_key, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Valida la firma del token y comprueba que no esté expirado
     */
    public static function validarToken(string $token): ?object {
        $partes = explode('.', $token);
        if (count($partes) !== 3) return null;

        list($header, $payload, $signature) = $partes;

        $validSignature = self::base64UrlEncode(
            hash_hmac('sha256', $header . "." . $payload, self::$secret_key, true)
        );

        if ($signature !== $validSignature) return null;

        $datosPayload = json_decode(self::base64UrlDecode($payload));

        if (isset($datosPayload->exp) && $datosPayload->exp < time()) {
            return null; // Token expirado
        }

        return $datosPayload;
    }

    private static function base64UrlEncode(string $text): string {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
    }

    private static function base64UrlDecode(string $text): string {
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $text));
    }
}