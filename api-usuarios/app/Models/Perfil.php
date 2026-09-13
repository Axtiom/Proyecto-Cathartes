<?php
declare(strict_types=1);

/**
 * Datos de perfil del usuario: `usuario` (nombre, email) + `usuario_telefono`
 * (multivaluado). Vía PDO. El email es UNIQUE en el MER: al actualizar se valida
 * que no lo tenga otro usuario.
 */
class Perfil
{
    public static function obtener(int $id): ?array
    {
        $pdo = Database::conexion();
        $u = $pdo->prepare('SELECT nombre, email FROM usuario WHERE ID = ?');
        $u->execute([$id]);
        $fila = $u->fetch();
        if (!$fila) {
            return null;
        }
        $t = $pdo->prepare('SELECT id_tel AS id, telefono FROM usuario_telefono WHERE ID_usuario = ? ORDER BY id_tel');
        $t->execute([$id]);
        $tels = array_map(static function (array $r): array {
            return ['id' => (int) $r['id'], 'telefono' => (string) $r['telefono']];
        }, $t->fetchAll());

        return [
            'nombre'    => (string) $fila['nombre'],
            'email'     => (string) $fila['email'],
            'telefonos' => $tels,
        ];
    }

    /** ¿Otro usuario (distinto de $id) ya usa este email? */
    public static function emailTomadoPorOtro(string $email, int $id): bool
    {
        $st = Database::conexion()->prepare('SELECT 1 FROM usuario WHERE email = ? AND ID <> ? LIMIT 1');
        $st->execute([$email, $id]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Actualiza nombre, email y la lista de teléfonos (reemplaza todos) en una
     * transacción. Devuelve el perfil actualizado.
     * @param list<string> $telefonos
     */
    public static function actualizar(int $id, string $nombre, string $email, array $telefonos): array
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE usuario SET nombre = ?, email = ? WHERE ID = ?')
                ->execute([$nombre, $email, $id]);

            // Reemplazo del multivaluado: borrar y reinsertar (CASCADE no aplica acá,
            // es borrado explícito de los teléfonos del propio usuario).
            $pdo->prepare('DELETE FROM usuario_telefono WHERE ID_usuario = ?')->execute([$id]);
            $ins = $pdo->prepare('INSERT INTO usuario_telefono (telefono, ID_usuario) VALUES (?, ?)');
            foreach ($telefonos as $tel) {
                $tel = trim((string) $tel);
                if ($tel !== '') {
                    $ins->execute([$tel, $id]);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return self::obtener($id) ?? [];
    }
}
