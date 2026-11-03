<?php
declare(strict_types=1);

/**
 * Envío de correo, config-driven por `.env` (MAIL_DRIVER=stub|smtp).
 *
 *  - stub  → escribe el mail (destino, asunto, cuerpo) en storage/mail.log.
 *            Es lo que usamos para probar la recuperación de contraseña sin SMTP real.
 *  - smtp  → TODO. Como el proyecto no usa composer, PHPMailer habría que
 *            vendorearlo a mano; se cablea cuando el equipo tenga las credenciales.
 *            Por ahora cae al stub para no bloquear.
 */
class Mailer
{
    public static function enviar(string $destino, string $asunto, string $cuerpo): void
    {
        $driver = (string) Config::get('mail.driver', 'stub');

        if ($driver === 'smtp') {
            // TODO(smtp): vendorear PHPMailer y enviar de verdad con las
            // credenciales del .env. Hasta entonces se loguea como el stub.
            self::stub($destino, $asunto, $cuerpo);
            return;
        }

        self::stub($destino, $asunto, $cuerpo);
    }

    private static function stub(string $destino, string $asunto, string $cuerpo): void
    {
        $dir = BASE_PATH . '/storage';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $linea = sprintf(
            "[%s] PARA: %s | ASUNTO: %s\n%s\n%s\n",
            date('c'),
            $destino,
            $asunto,
            $cuerpo,
            str_repeat('-', 70)
        );

        file_put_contents($dir . '/mail.log', $linea, FILE_APPEND | LOCK_EX);
    }
}
