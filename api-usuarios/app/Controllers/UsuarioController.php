<?php
namespace App\Controllers;

use App\Models\Usuario;

class UsuarioController {
    private $usuarioModel;

    public function __construct() {
        $this->usuarioModel = new Usuario();
    }

    /**
     * Procesa la baja o rechazo de un usuario cambiando su estado estrictamente a "inactivo"
     */
    public function darDeBaja($id) {
        header('Content-Type: application/json');

        if (!$id || !is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Identificador de usuario no válido.']);
            return;
        }

        // Ejecutar actualización de estado a "inactivo"
        $resultado = $this->usuarioModel->cambiarEstado((int)$id, 'inactivo');

        if ($resultado) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'estado' => 'inactivo',
                'mensaje' => 'El usuario ha sido desactivado/rechazado correctamente.'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo actualizar el estado del usuario en la base de datos.']);
        }
    }
}