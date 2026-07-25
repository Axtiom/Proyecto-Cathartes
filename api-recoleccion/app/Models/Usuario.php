<?php
declare(strict_types=1);

/**
 * Acceso a datos de `usuario` y subtipos, vía PDO (prepared statements).
 *
 * Gotchas del MER respetados:
 *  - La columna de contraseña es `contrasena` (sin ñ — ver BUG-003: la ñ se
 *    corrompía al importar el dump). Se alias-ea a `hash` al leer.
 *  - `usuario.estado` arranca en 'no activo' por default (pendiente de aprobación).
 *  - Borrado: no se borra; nada de DELETE acá.
 */
class Usuario
{
    public static function porEmail(string $email): ?array
    {
        $st = Database::conexion()->prepare(
            'SELECT ID, nombre, email, `contrasena` AS hash, estado FROM usuario WHERE email = ?'
        );
        $st->execute([$email]);
        $fila = $st->fetch();
        return $fila ?: null;
    }

    public static function porId(int $id): ?array
    {
        $st = Database::conexion()->prepare(
            'SELECT ID, nombre, email, estado FROM usuario WHERE ID = ?'
        );
        $st->execute([$id]);
        $fila = $st->fetch();
        return $fila ?: null;
    }

    public static function existeEmail(string $email): bool
    {
        $st = Database::conexion()->prepare('SELECT 1 FROM usuario WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Alta de Vecino en una transacción: usuario (queda 'no activo' por default)
     * + vecino (saldo 0) + usuario_telefono. Devuelve el ID nuevo.
     */
    public static function crearVecino(string $nombreCompleto, string $email, string $hash, string $telefono): int
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('INSERT INTO usuario (nombre, email, `contrasena`) VALUES (?, ?, ?)');
            $st->execute([$nombreCompleto, $email, $hash]);
            $id = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO vecino (ID_usuario, saldo) VALUES (?, 0)')->execute([$id]);
            $pdo->prepare('INSERT INTO usuario_telefono (telefono, ID_usuario) VALUES (?, ?)')->execute([$telefono, $id]);

            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function actualizarPassword(int $id, string $hash): void
    {
        $st = Database::conexion()->prepare('UPDATE usuario SET `contrasena` = ? WHERE ID = ?');
        $st->execute([$hash, $id]);
    }
}
