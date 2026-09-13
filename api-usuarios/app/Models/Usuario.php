<?php
declare(strict_types=1);

/**
 * Model de Usuarios de la API de Usuarios (PDO directo a `cathartes`).
 *
 * Recordá (BUG-003): la columna de contraseña es `contrasena`, sin ñ. En PDO va
 * con backticks.
 *
 * Esta es la versión liviana: solo lo que necesitan el registro, el login y la
 * recuperación de contraseña. El ABM completo de cuentas (aprobar, rechazar,
 * dar de baja, listar, alta multirol) vive en el Usuario del Backoffice — son
 * apps separadas, cada una con el modelo que le corresponde a su tarea.
 */
class Usuario
{
    /** La usa Auth::proteger() (guard de sesión) en cada request protegido. */
    public static function porId(int $id): ?array
    {
        $st = Database::conexion()->prepare('SELECT ID, nombre, email, estado FROM usuario WHERE ID = ?');
        $st->execute([$id]);
        $u = $st->fetch();
        return $u ?: null;
    }

    /** SELECT * más el alias `hash` sobre `contrasena` (lo usa el login). */
    public static function porEmail(string $email): ?array
    {
        $st = Database::conexion()->prepare(
            'SELECT *, `contrasena` AS hash FROM usuario WHERE email = ?'
        );
        $st->execute([$email]);
        $u = $st->fetch();
        return $u ?: null;
    }

    public static function existeEmail(string $email): bool
    {
        $st = Database::conexion()->prepare('SELECT 1 FROM usuario WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Alta de Vecino en una transacción: usuario (queda 'no activo' por default)
     * + vecino + usuario_telefono. Devuelve el ID nuevo. La usa el
     * registro público (AccesoController::registro).
     */
    public static function crearVecino(string $nombreCompleto, string $email, string $hash, string $telefono): int
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('INSERT INTO usuario (nombre, email, `contrasena`) VALUES (?, ?, ?)');
            $st->execute([$nombreCompleto, $email, $hash]);
            $id = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO vecino (ID_usuario) VALUES (?)')->execute([$id]);
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
