<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** El token de sesión y el de recuperación: firmado con HMAC, no reutilizable entre usos. */
final class TokenTest extends TestCase
{
    public function testTokenDeSesionValidoDevuelveSusDatos(): void
    {
        $t = Token::emitirSesion(7, 'vecino');
        $p = Token::validar($t, 'sesion');
        $this->assertNotNull($p);
        $this->assertSame(7, (int) $p['id']);
        $this->assertSame('vecino', $p['rol']);
    }

    public function testUnTokenDeRecuperacionNoSirveComoSesion(): void
    {
        $reset = Token::emitirReset(7);
        $this->assertNull(Token::validar($reset, 'sesion'));
        $this->assertNotNull(Token::validar($reset, 'reset'));
    }

    public function testUnTokenAlteradoSeRechaza(): void
    {
        [$payload, $firma] = explode('.', Token::emitirSesion(7, 'vecino'));
        $this->assertNull(Token::validar($payload . '.' . strrev($firma), 'sesion'), 'firma cambiada');

        $otro = explode('.', Token::emitirSesion(1, 'administrador'))[0];
        $this->assertNull(Token::validar($otro . '.' . $firma, 'sesion'), 'payload ajeno con firma de otro token');
    }

    /** @dataProvider basura */
    public function testBasuraSeRechazaSinExcepcion(string $token): void
    {
        $this->assertNull(Token::validar($token, 'sesion'));
    }

    public static function basura(): array
    {
        return [[''], ['abc'], ['a.b'], ['.'], ['....'], [str_repeat('x', 5000)]];
    }
}
