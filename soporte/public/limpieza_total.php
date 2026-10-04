<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Solo los administradores pueden ejecutar esta acción crítica
if ($_SESSION['id_rol'] != 1) {
    die("Acceso denegado. Solo los administradores pueden realizar esta acción.");
}

$mensaje = '';
$error = '';
$confirmado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirmar_limpieza'])) {
        try {
            // Desactivar temporalmente la revisión de llaves foráneas para permitir el borrado
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
            
            // Vaciar todas las tablas de datos transaccionales
            $pdo->exec('TRUNCATE TABLE Archivos_Adjuntos;');
            $pdo->exec('TRUNCATE TABLE Comentarios;');
            $pdo->exec('TRUNCATE TABLE Tickets;');
            $pdo->exec('TRUNCATE TABLE Clientes;'); // AÑADIDO: Vaciar la tabla de Clientes
            
            // Reactivar la revisión de llaves foráneas
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
            
            $mensaje = "¡Limpieza total completada con éxito! Las tablas de Tickets, Comentarios, Archivos Adjuntos y Clientes han sido vaciadas. El sistema está listo para producción.";
            $confirmado = true;

        } catch (Exception $e) {
            $error = "Ocurrió un error fatal durante la limpieza: " . $e->getMessage();
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
            <h2 class="mb-0"><i class="bi bi-exclamation-triangle-fill"></i> Limpieza TOTAL de la Base de Datos</h2>
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
                <h4 class="card-title text-danger">¡Estás a punto de borrar TODA la información del sistema!</h4>
                <p>Este proceso eliminará permanentemente:</p>
                <ul>
                    <li><strong>TODOS</strong> los tickets.</li>
                    <li><strong>TODOS</strong> los comentarios.</li>
                    <li><strong>TODOS</strong> los archivos adjuntos.</li>
                    <li class="fw-bold text-danger"><strong>TODOS LOS CLIENTES.</strong></li>
                </ul>
                <p>La base de datos quedará como nueva, lista para registrar datos reales. Tendrás que crear a tus clientes de nuevo.</p>
                <hr>
                <p><strong>¿Estás absolutamente seguro de que quieres continuar?</strong></p>
                
                <form action="limpieza_total.php" method="POST">
                    <button type="submit" name="confirmar_limpieza" class="btn btn-danger btn-lg">
                        <i class="bi bi-trash-fill"></i> Sí, entiendo y quiero borrar TODO
                    </button>
                    <a href="index.php" class="btn btn-secondary btn-lg">No, cancelar y volver</a>
                </form>
            <?php endif;