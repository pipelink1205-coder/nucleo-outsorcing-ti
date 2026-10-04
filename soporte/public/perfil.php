<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

$mensaje = '';
$tipo_mensaje = ''; // success o error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass_actual = $_POST['pass_actual'];
    $pass_nueva = $_POST['pass_nueva'];
    $pass_confirmar = $_POST['pass_confirmar'];
    $id_usuario = $_SESSION['id_usuario'];

    if (empty($pass_actual) || empty($pass_nueva) || empty($pass_confirmar)) {
        $mensaje = "Todos los campos son obligatorios.";
        $tipo_mensaje = "error";
    } elseif ($pass_nueva !== $pass_confirmar) {
        $mensaje = "Las contraseñas nuevas no coinciden.";
        $tipo_mensaje = "error";
    } else {
        // Verificar la contraseña actual
        $stmt = $pdo->prepare("SELECT password_hash FROM Usuarios WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($pass_actual, $usuario['password_hash'])) {
            // La contraseña actual es correcta, actualizamos
            $nuevo_hash = password_hash($pass_nueva, PASSWORD_DEFAULT);
            
            try {
                $update = $pdo->prepare("UPDATE Usuarios SET password_hash = ? WHERE id_usuario = ?");
                $update->execute([$nuevo_hash, $id_usuario]);
                
                $mensaje = "¡Contraseña actualizada correctamente!";
                $tipo_mensaje = "success";
            } catch (Exception $e) {
                $mensaje = "Error al actualizar: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        } else {
            $mensaje = "La contraseña actual es incorrecta.";
            $tipo_mensaje = "error";
        }
    }
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-person-gear"></i> Mi Perfil</h2>
    <a href="index.php" class="btn btn-secondary">Volver al Dashboard</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Cambiar Contraseña</h5>
            </div>
            <div class="card-body p-4">
                
                <form action="perfil.php" method="POST">
                    <div class="mb-3">
                        <label for="pass_actual" class="form-label">Contraseña Actual:</label>
                        <input type="password" class="form-control" id="pass_actual" name="pass_actual" required>
                    </div>
                    
                    <hr>

                    <div class="mb-3">
                        <label for="pass_nueva" class="form-label">Nueva Contraseña:</label>
                        <input type="password" class="form-control" id="pass_nueva" name="pass_nueva" required>
                    </div>

                    <div class="mb-3">
                        <label for="pass_confirmar" class="form-label">Confirmar Nueva Contraseña:</label>
                        <input type="password" class="form-control" id="pass_confirmar" name="pass_confirmar" required>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Actualizar Contraseña
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php if ($mensaje): ?>
<script>
    Swal.fire({
        icon: '<?php echo $tipo_mensaje; ?>',
        title: '<?php echo ($tipo_mensaje == 'success') ? '¡Éxito!' : 'Error'; ?>',
        text: '<?php echo $mensaje; ?>',
        confirmButtonColor: '#0d6efd'
    });
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>