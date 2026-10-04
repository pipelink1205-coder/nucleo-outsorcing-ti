<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

$mensaje = '';
$mensaje_tipo = ''; // 'success' o 'danger'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_actual = $_POST['password_actual'];
    $nueva_password = $_POST['nueva_password'];
    $confirmar_password = $_POST['confirmar_password'];
    $id_usuario = $_SESSION['id_usuario'];

    // 1. Validaciones básicas
    if (empty($password_actual) || empty($nueva_password) || empty($confirmar_password)) {
        $mensaje = 'Todos los campos son obligatorios.';
        $mensaje_tipo = 'danger';
    } elseif ($nueva_password !== $confirmar_password) {
        $mensaje = 'La nueva contraseña y su confirmación no coinciden.';
        $mensaje_tipo = 'danger';
    } else {
        // 2. Verificar la contraseña actual
        $stmt = $pdo->prepare("SELECT password_hash FROM Usuarios WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($password_actual, $usuario['password_hash'])) {
            // 3. Si la contraseña actual es correcta, hashear y actualizar la nueva
            $nuevo_password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
            $stmt_update = $pdo->prepare("UPDATE Usuarios SET password_hash = ? WHERE id_usuario = ?");
            
            if ($stmt_update->execute([$nuevo_password_hash, $id_usuario])) {
                $mensaje = '¡Contraseña actualizada con éxito!';
                $mensaje_tipo = 'success';
            } else {
                $mensaje = 'Hubo un error al actualizar la contraseña.';
                $mensaje_tipo = 'danger';
            }
        } else {
            $mensaje = 'La contraseña actual que ingresaste es incorrecta.';
            $mensaje_tipo = 'danger';
        }
    }
}

require_once '../includes/header.php';
?>

<h2 class="mb-4">Cambiar Contraseña</h2>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $mensaje_tipo; ?>">
                        <?php echo htmlspecialchars($mensaje); ?>
                    </div>
                <?php endif; ?>

                <form action="cambiar_password.php" method="POST">
                    <div class="mb-3">
                        <label for="password_actual" class="form-label">Contraseña Actual <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password_actual" name="password_actual" required>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label for="nueva_password" class="form-label">Nueva Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="nueva_password" name="nueva_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirmar_password" class="form-label">Confirmar Nueva Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirmar_password" name="confirmar_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Actualizar Contraseña</button>
                </form>
            </div>
        </div>
    </div>
</div>


<?php require_once '../includes/footer.php'; ?>