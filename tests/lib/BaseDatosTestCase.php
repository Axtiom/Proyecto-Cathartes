<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Cada test arranca con la base de prueba recién sembrada: los tests no dependen unos de otros. */
abstract class BaseDatosTestCase extends TestCase
{
    protected function setUp(): void
    {
        BaseDatosPrueba::reiniciar();
    }

    protected function fila(string $sql, array $p = []): ?array
    {
        $st = BaseDatosPrueba::conectar()->prepare($sql);
        $st->execute($p);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    protected function cuenta(string $sql, array $p = []): int
    {
        $st = BaseDatosPrueba::conectar()->prepare($sql);
        $st->execute($p);
        return (int) $st->fetchColumn();
    }
}
