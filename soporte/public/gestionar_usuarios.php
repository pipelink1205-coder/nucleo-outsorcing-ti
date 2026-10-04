<?php
require_once '../includes/auth_check.php';
if ($_SESSION['id_rol'] != 1) { header('Location: index.php'); exit(); }
require_once '../config/database.php';
require_once '../includes/header.php';

$stmt = $pdo->query("
    SELECT u.id_usuario, u.nombre_completo, u.email, u.activo, u.telefono, u.ruta_foto, r.nombre_rol
    FROM Usuarios u JOIN Roles r ON u.id_rol = r.id_rol ORDER BY u.nombre_completo
");
$usuarios = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Gestionar Usuarios</h2>
    <a href="crear_usuario_admin.php" class="btn btn-success"><i class="bi bi-person-plus-fill"></i> Crear Nuevo Usuario</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr><th>Foto</th><th>Nombre Completo</th><th>Email</th><th>Teléfono</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td>
                            <img src="<?php echo !empty($usuario['ruta_foto']) ? $usuario['ruta_foto'] : 'assets/img/default-avatar.png'; ?>" alt="Avatar" class="rounded-circle" width="40" height="40">
                        </td>
                        <td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['telefono']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['nombre_rol']); ?></td>
                        <td><?php if ($usuario['activo']): ?><span class="badge bg-success">Activo</span><?php else: ?><span class="badge bg-danger">Inactivo</span><?php endif; ?></td>
                        <td><a href="editar_usuario.php?id=<?php echo $usuario['id_usuario']; ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square"></i> Editar</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>