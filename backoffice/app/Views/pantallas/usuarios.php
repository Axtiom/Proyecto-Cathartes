<div class="card shadow-sm">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Lista de Usuarios Dados de Baja / Inactivos</h5>
        <a href="?estado=activo" class="btn btn-outline-light btn-sm">Ver Usuarios Activos</a>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover table-striped mb-0">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Nombre Completo</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($usuariosInactivos)): ?>
                    <?php foreach ($usuariosInactivos as $usuario): ?>
                        <tr>
                            <td><?= htmlspecialchars($usuario['id']) ?></td>
                            <td><?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']) ?></td>
                            <td><?= htmlspecialchars($usuario['email']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars(strtoupper($usuario['rol'])) ?></span></td>
                            <td>
                                <!-- Muestra consistentemente el estado "inactivo" -->
                                <span class="badge bg-danger">inactivo</span>
                            </td>
                            <td class="text-center">
                                <!-- 
                                   SE ELIMINÓ EL BOTÓN "Dar de baja" REDUNDANTE DE ESTA VISTA.
                                   Únicamente se ofrece la opción de reactivación si el usuario ya está dado de baja.
                                -->
                                <button class="btn btn-sm btn-success btn-reactivar" data-id="<?= $usuario['id'] ?>">
                                    <i class="fas fa-undo"></i> Reactivar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No existen usuarios inactivos en el sistema.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>