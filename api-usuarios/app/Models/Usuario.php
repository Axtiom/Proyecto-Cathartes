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
     * Actualiza el estado de un usuario a 'inactivo' o 'activo'
     */
    public function cambiarEstado(int $idUsuario, string $nuevoEstado = 'inactivo'): bool {
        // Garantizar que el estado solo tome valores válidos de la lógica de negocio
        $estadoSanitizado = strtolower($nuevoEstado) === 'activo' ? 'activo' : 'inactivo';

        $sql = "UPDATE usuarios SET estado = :estado, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':estado' => $estadoSanitizado,
            ':id' => $idUsuario
        ]);
    }
}