<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class Usuario {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Da de baja o rechaza a un usuario cambiando su estado estrictamente a 'inactivo'
     */
    public function darDeBaja(int $idUsuario): bool {
        $sql = "UPDATE usuarios SET estado = 'inactivo', updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $idUsuario]);
    }

    /**
     * Reactiva a un usuario cambiando su estado a 'activo'
     */
    public function reactivar(int $idUsuario): bool {
        $sql = "UPDATE usuarios SET estado = 'activo', updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $idUsuario]);
    }

    /**
     * Obtiene todos los usuarios filtrados por estado ('activo' o 'inactivo')
     */
    public function obtenerPorEstado(string $estado = 'activo'): array {
        $sql = "SELECT id, nombre, apellido, email, rol, estado, creado_en FROM usuarios WHERE estado = :estado ORDER BY id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':estado' => $estado]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca un usuario por ID
     */
    public function obtenerPorId(int $idUsuario): ?array {
        $sql = "SELECT id, nombre, apellido, email, rol, estado FROM usuarios WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $idUsuario]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }
}