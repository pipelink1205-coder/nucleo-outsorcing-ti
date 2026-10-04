<?php
require_once '../includes/auth_check.php';

// Solo los administradores pueden ejecutar esta acción crítica
if ($_SESSION['id_rol'] != 1) {
    die("Acceso denegado. Solo los administradores pueden realizar esta acción.");
}

require_once '../config/database.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirmar_reseteo'])) {
        try {
            // Desactivar temporalmente la revisión de llaves foráneas para permitir el borrado
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
            
            // Vaciar las tablas transaccionales y de configuración
            $pdo->exec('TRUNCATE TABLE Archivos_Adjuntos;');
            $pdo->exec('TRUNCATE TABLE Comentarios;');
            $pdo->exec('TRUNCATE TABLE Tickets;');
            $pdo->exec('TRUNCATE TABLE Clientes;');
            $pdo->exec('TRUNCATE TABLE TiposDeCaso;'); // AÑADIDO: Vaciar la tabla de Tipos de Caso
            
            // Reactivar la revisión de llaves foráneas
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
            
            $mensaje = "¡Reseteo completado! Las tablas de Tickets, Clientes, Tipos de Caso y sus datos asociados han sido vaciadas. Se conservaron los Usuarios, Roles y Agentes.";

        } catch (Exception $e) {
            $error = "Ocurrió un error fatal durante el reseteo: " . $e->getMessage();
            // Siempre intentar reactivar las llaves foráneas en caso de error
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="card border-danger">
        <div class="card-header bg-danger text-white">
            <h2 class="mb-0"><i class="bi bi-exclamation-triangle-fill"></i> Resetear Datos del Sistema</h2>
        </div>
        <div class="card-body">
            <?php if ($mensaje): ?>
                <div class="alert alert-success">
                    <h4>Proceso Finalizado</h4>
                    <p><?php echo $mensaje; ?></p>
                    <a href="index.php" class="btn btn-primary">Volver al Dashboard</a>
                </div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger">
                    <h4>Error</h4>
                    <p><?php echo $error; ?></p>
                    <a href="index.php" class="btn btn-secondary">Volver al Dashboard</a>
                </div>
            <?php else: ?>
                <h4 class="card-title text-danger">¡Estás a punto de realizar una acción destructiva!</h4>
                <p>Este proceso eliminará permanentemente todos los datos de las siguientes tablas:</p>
                <ul>
                    <li>Tickets</li>
                    <li>Comentarios</li>
                    <li>Archivos Adjuntos</li>
                    <li>Clientes</li>
                    <li>Tipos de Caso</li>
                </ul>
                <p class="fw-bold">Las únicas tablas que se conservarán intactas son `Usuarios`, `Roles` y `Agentes`.</p>
                <hr>
                <p><strong>¿Estás seguro de que quieres borrar todos los datos de configuración y de tickets?</strong></p>
                
                <form action="reset_datos.php" method="POST">
                    <button type="submit" name="confirmar_reseteo" class="btn btn-danger btn-lg">
                        <i class="bi bi-trash-fill"></i> Sí, borrar los datos
                    </button>
                    <a href="index.php" class="btn btn-secondary btn-lg">No, cancelar</a>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>