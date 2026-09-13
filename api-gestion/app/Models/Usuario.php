<?php
declare(strict_types=1);

/**
 * Model de Usuarios de la API de Gestión (PDO directo a `cathartes`).
 *
 * Esta API no administra cuentas: el único método que necesita es porId(),
 * que usa Auth::proteger() en cada request protegido (kill-switch de sesión).
 * El resto de la gestión de usuarios vive en api-usuarios (auth/registro) y en
 * el Backoffice (ABM de cuentas) — cada app tiene solo lo que su tarea requiere.
 */
class Usuario
{
    public static function porId(int $id): ?array
    {
        $st = Database::conexion()->prepare('SELECT ID, nombre, email, estado FROM usuario WHERE ID = ?');
        $st->execute([$id]);
        $u = $st->fetch();
        return $u ?: null;
    }
}
