<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// SEGURIDAD CRÍTICA: Solo el Administrador (Rol 1) puede entrar aquí
if ($_SESSION['id_rol'] != 1) {
    header('Location: index.php');
    exit();
}

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ahora verificamos el input oculto, no el botón
    if (isset($_POST['confirmar_reseteo']) && $_POST['confirmar_reseteo'] == 'true') {
        try {
            // 1. Desactivar llaves foráneas
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
            
            // 2. VACIAR TABLAS
            $pdo->exec('TRUNCATE TABLE Archivos_Adjuntos;');
            $pdo->exec('TRUNCATE TABLE Comentarios;');
            $pdo->exec('TRUNCATE TABLE Tickets;');
            $pdo->exec('TRUNCATE TABLE Clientes;');
            $pdo->exec('TRUNCATE TABLE TiposDeCaso;');
            
            // 3. Reactivar llaves foráneas
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
            
            $mensaje = "¡Sistema restablecido correctamente! Base de datos limpia.";

        } catch (Exception $e) {
            $error = "Error crítico al intentar restablecer: " . $e->getMessage();
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
        }
    }
}

require_once '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Zona de Peligro: Restablecer Sistema</h2>
    <a href="index.php" class="btn btn-secondary">Volver al Dashboard</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card border-danger shadow-sm">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">Confirmación de Borrado Total</h5>
            </div>
            <div class="card-body text-center p-5">
                
                <?php if ($mensaje): ?>
                    <div class="alert alert-success shadow">
                        <h4><i class="bi bi-check-circle"></i> Proceso Completado</h4>
                        <p><?php echo $mensaje; ?></p>
                        <hr>
                        <a href="index.php" class="btn btn-success">Ir al Inicio</a>
                    </div>
                    <script>
                        Swal.fire({
                            icon: 'success',
                            title: '¡Reseteo Exitoso!',
                            text: 'El sistema ha quedado como nuevo.',
                            confirmButtonColor: '#198754'
                        });
                    </script>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger shadow">
                        <h4><i class="bi bi-x-circle"></i> Error</h4>
                        <p><?php echo $error; ?></p>
                    </div>
                <?php else: ?>

                    <div class="mb-4">
                        <i class="bi bi-radioactive text-danger" style="font-size: 4rem;"></i>
                    </div>

                    <h4 class="card-title text-danger fw-bold mb-4">¿Estás seguro de que quieres restablecer el sistema?</h4>
                    
                    <p class="lead">Esta acción eliminará <strong>PERMANENTEMENTE</strong>:</p>
                    
                    <ul class="list-group list-group-flush text-start d-inline-block mb-4">
                        <li class="list-group-item"><i class="bi bi-trash text-danger"></i> Todos los <strong>Tickets</strong>, Comentarios y Archivos.</li>
                        <li class="list-group-item"><i class="bi bi-trash text-danger"></i> Todos los <strong>Clientes</strong> registrados.</li>
                        <li class="list-group-item"><i class="bi bi-trash text-danger"></i> Todos los <strong>Tipos de Caso</strong>.</li>
                    </ul>

                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle-fill"></i> <strong>Nota:</strong> Los Usuarios (Login), Roles y Agentes <strong>SE CONSERVARÁN</strong>.
                    </div>

                    <form id="form-reset" action="reset_sistema.php" method="POST">
                        <input type="hidden" name="confirmar_reseteo" value="true">
                        
                        <div class="d-grid gap-2 col-8 mx-auto mt-4">
                            <button type="submit" class="btn btn-danger btn-lg">
                                <i class="bi bi-eraser-fill"></i> SÍ, BORRAR DATOS Y REINICIAR
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>

                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('form-reset').addEventListener('submit', function(e) {
        e.preventDefault(); // Detiene el envío normal

        Swal.fire({
            title: '¿Estás absolutamente seguro?',
            text: "¡No podrás revertir esto! Se borrará toda la base de datos operativa.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, borrar todo',
            cancelButtonText: 'Cancelar',
            focusCancel: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Envía el formulario manualmente tras confirmar
                this.submit();
            }
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>