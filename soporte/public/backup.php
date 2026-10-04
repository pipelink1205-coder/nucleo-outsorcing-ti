<?php
require_once '../includes/auth_check.php';
// Solo los administradores pueden acceder
if ($_SESSION['id_rol'] != 1) {
    header('Location: index.php');
    exit();
}
require_once '../config/database.php'; // Necesitamos los datos de conexión

// Lógica para generar y descargar el backup
if (isset($_POST['generar_backup'])) {
    
    // Nombre del archivo de backup con la fecha y hora
    $backup_file = 'backup_soporte_' . date("Y-m-d_H-i-s") . '.sql';
    
    // Comando para ejecutar mysqldump
    // NOTA: Asegúrate de que la ruta a mysqldump.exe sea correcta para tu instalación de XAMPP.
    $command = "C:\\xampp\\mysql\\bin\\mysqldump.exe --user={$username} --password={$password} --host={$host} --databases {$dbname}";
    
    // Ejecutar el comando
    $output = shell_exec($command);
    
    // Forzar la descarga del archivo
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary");
    header("Content-disposition: attachment; filename=\"" . $backup_file . "\"");
    
    // Imprimir el contenido del backup
    echo $output;
    exit;
}

require_once '../includes/header.php';
?>

<h2 class="mb-4">Generar Copia de Seguridad</h2>

<div class="card">
    <div class="card-header fw-bold">
        Copia de Seguridad de la Base de Datos
    </div>
    <div class="card-body">
        <p>
            Al hacer clic en el botón, se generará una copia de seguridad completa de la base de datos en un archivo <code>.sql</code>.
        </p>
        <p>
            Este archivo contiene toda la estructura de las tablas y todos los datos registrados hasta el momento (tickets, clientes, usuarios, etc.). Guarda este archivo en un lugar seguro.
        </p>
        
        <form action="backup.php" method="POST">
            <button type="submit" name="generar_backup" class="btn btn-primary btn-lg">
                <i class="bi bi-download"></i> Generar y Descargar Backup Ahora
            </button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>