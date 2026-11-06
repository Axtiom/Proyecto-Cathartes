<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Gestión de Usuarios</h2>
        <div>
            <a href="?filtro=activo" class="btn btn-<?= ($filtro ?? 'activo') === 'activo' ? 'primary' : 'outline-primary' ?>">Usuarios Activos</a>
            <a href="?filtro=inactivo" class="btn btn-<?= ($filtro ?? '') === 'inactivo' ? 'secondary' : 'outline-secondary' ?>">Usuarios Inactivos / Dados de Baja</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nombre Completo</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($usuarios)): ?>
                        <?php foreach ($usuarios as $usr): ?>
                            <tr>
                                <td><?= htmlspecialchars($usr['id']) ?></td>
                                <td><?= htmlspecialchars($usr['nombre'] . ' ' . $usr['apellido']) ?></td>
                                <td><?= htmlspecialchars($usr['email']) ?></td>
                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars(strtoupper($usr['rol'])) ?></span></td>
                                <td>
                                    <span class="badge bg-<?= $usr['estado'] === 'activo' ? 'success' : 'danger' ?>">
                                        <?= htmlspecialchars($usr['estado']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?php if ($usr['estado'] === 'activo'): ?>
                                        <!-- En la vista de activos se muestra el botón de baja -->
                                        <button class="btn btn-sm btn-outline-danger btn-baja" data-id="<?= $usr['id'] ?>">Dar de baja</button>
                                    <?php else: ?>
                                        <!-- En usuarios inactivos / dados de baja se elimina el botón de baja para evitar bucles -->
                                        <button class="btn btn-sm btn-outline-success btn-reactivar" data-id="<?= $usr['id'] ?>">Reactivar Usuario</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No se encontraron usuarios en esta sección.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>