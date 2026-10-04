<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// SEGURIDAD: Solo Administradores
if ($_SESSION['id_rol'] != 1) {
    header('Location: index.php');
    exit();
}

$swal_alert = null;

// --- LÓGICA 1: GENERAR BACKUP (EXPORTAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_backup'])) {
    
    // Limpiar cualquier salida previa
    if (ob_get_level()) ob_end_clean();
    
    $fecha = date('Y-m-d_H-i-s');
    $nombre_archivo = "backup_soporte_$fecha.sql";
    
    $sql = "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    try {
        $tablas = [];
        $result = $pdo->query('SHOW TABLES');
        while ($row = $result->fetch(PDO::FETCH_NUM)) { $tablas[] = $row[0]; }

        foreach ($tablas as $tabla) {
            $result = $pdo->query('SELECT * FROM ' . $tabla);
            $num_fields = $result->columnCount();
            
            $sql .= "DROP TABLE IF EXISTS `$tabla`;\n";
            $row2 = $pdo->query('SHOW CREATE TABLE ' . $tabla)->fetch(PDO::FETCH_NUM);
            $sql .= $row2[1] . ";\n\n";
            
            for ($i = 0; $i < $num_fields; $i++) {
                while ($row = $result->fetch(PDO::FETCH_NUM)) {
                    $sql .= "INSERT INTO `$tabla` VALUES(";
                    for ($j = 0; $j < $num_fields; $j++) {
                        // Manejo correcto de NULL
                        if (!is_null($row[$j])) {
                            $row[$j] = addslashes($row[$j]);
                            $row[$j] = str_replace("\n", "\\n", $row[$j]);
                            $sql .= '"' . $row[$j] . '"'; 
                        } else {
                            $sql .= 'NULL'; 
                        }
                        if ($j < ($num_fields - 1)) { $sql .= ','; }
                    }
                    $sql .= ");\n";
                }
            }
            $sql .= "\n\n";
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;";

        // Forzar descarga
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=\"$nombre_archivo\"");
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($sql));
        echo $sql;
        exit;

    } catch (Exception $e) {
        $swal_alert = [
            'icon' => 'error',
            'title' => 'Error de Exportación',
            'text' => "No se pudo generar el respaldo: " . $e->getMessage()
        ];
    }
}

// --- LÓGICA 2: RESTAURAR BACKUP (IMPORTAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restaurar_backup'])) {
    
    if (isset($_FILES['archivo_sql']) && $_FILES['archivo_sql']['error'] === UPLOAD_ERR_OK) {
        
        $nombre_archivo = $_FILES['archivo_sql']['name'];
        $ext = pathinfo($nombre_archivo, PATHINFO_EXTENSION);

        if ($ext !== 'sql') {
            $swal_alert = [
                'icon' => 'error',
                'title' => 'Formato Incorrecto',
                'text' => 'Por favor, sube únicamente archivos con extensión .sql'
            ];
        } else {
            // Aumentar tiempo de ejecución y memoria
            set_time_limit(0); 
            
            $archivo_temporal = $_FILES['archivo_sql']['tmp_name'];
            $handle = fopen($archivo_temporal, "r");
            $consulta_actual = '';
            
            try {
                // Desactivar FK para permitir DROP/CREATE sin problemas
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

                if ($handle) {
                    while (($linea = fgets($handle)) !== false) {
                        $linea = trim($linea);
                        
                        // Ignorar comentarios y líneas vacías
                        if ($linea == '' || substr($linea, 0, 2) == '--' || substr($linea, 0, 2) == '/*') {
                            continue;
                        }

                        // Validación: Detectar si el archivo contiene errores PHP (archivo corrupto)
                        if (strpos($linea, '<b>Deprecated</b>') !== false || strpos($linea, 'Fatal error') !== false) {
                            throw new Exception("El archivo SQL está corrupto (contiene errores de PHP). Por favor, genera una NUEVA copia de seguridad con el sistema actualizado.");
                        }

                        $consulta_actual .= $linea;

                        // Si la línea termina en punto y coma, ejecutar
                        if (substr($linea, -1, 1) == ';') {
                            $pdo->exec($consulta_actual);
                            $consulta_actual = '';
                        }
                    }
                    fclose($handle);
                }

                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                
                $swal_alert = [
                    'icon' => 'success',
                    'title' => '¡Restauración Exitosa!',
                    'text' => "El sistema ha sido restaurado correctamente."
                ];

            } catch (Exception $e) {
                // Intentar reactivar FK aunque falle
                try { $pdo->exec('SET FOREIGN_KEY_CHECKS = 1'); } catch (Exception $ex) {}
                
                $swal_alert = [
                    'icon' => 'error',
                    'title' => 'Error Crítico',
                    'text' => "Falló la restauración: " . $e->getMessage()
                ];
            }
        }
    } else {
        $swal_alert = [
            'icon' => 'warning',
            'title' => 'Error de Carga',
            'text' => 'No seleccionaste ningún archivo o hubo un error en la subida.'
        ];
    }
}

require_once '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-database-fill-gear"></i> Copia de Seguridad y Restauración</h2>
    <a href="index.php" class="btn btn-secondary">Volver al Dashboard</a>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card h-100 shadow-sm border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-download"></i> Exportar Base de Datos</h5>
            </div>
            <div class="card-body text-center p-4">
                <i class="bi bi-database-down text-primary" style="font-size: 4rem;"></i>
                <h4 class="card-title mt-3">Generar Respaldo</h4>
                <p class="card-text text-muted">
                    Descarga un archivo <strong>.sql</strong> con toda la información actual del sistema.
                </p>
                <form action="copia_seguridad.php" method="POST" class="mt-4">
                    <button type="submit" name="generar_backup" class="btn btn-primary w-100 btn-lg" onclick="mostrarToastCarga()">
                        <i class="bi bi-cloud-download"></i> Descargar Copia
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100 shadow-sm border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-upload"></i> Restaurar Sistema</h5>
            </div>
            <div class="card-body text-center p-4">
                <i class="bi bi-database-up text-warning" style="font-size: 4rem;"></i>
                <h4 class="card-title mt-3">Cargar Respaldo</h4>
                <p class="card-text text-muted">
                    Sube un archivo <strong>.sql</strong> para restaurar el sistema.
                </p>
                
                <div class="alert alert-danger py-2 small" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i> <strong>Atención:</strong> Se borrarán los datos actuales.
                </div>

                <form id="form-restaurar" action="copia_seguridad.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="restaurar_backup" value="true">
                    
                    <div class="mb-3 text-start">
                        <label for="archivo_sql" class="form-label fw-bold">Seleccionar archivo SQL:</label>
                        <input class="form-control" type="file" id="archivo_sql" name="archivo_sql" accept=".sql" required>
                    </div>
                    
                    <button type="submit" class="btn btn-warning w-100 btn-lg">
                        <i class="bi bi-cloud-upload"></i> Restaurar Base de Datos
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // 1. Alertas de PHP
    <?php if ($swal_alert): ?>
        Swal.fire({
            icon: '<?php echo $swal_alert['icon']; ?>',
            title: '<?php echo $swal_alert['title']; ?>',
            text: '<?php echo $swal_alert['text']; ?>',
            confirmButtonColor: '#3085d6'
        });
    <?php endif; ?>

    // 2. Confirmación antes de Restaurar
    document.getElementById('form-restaurar').addEventListener('submit', function(e) {
        e.preventDefault(); 

        Swal.fire({
            title: '¿Estás seguro?',
            text: "Se reemplazarán TODOS los datos actuales con el archivo seleccionado. ¡Verifique que el archivo sea correcto!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107', 
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, restaurar ahora',
            cancelButtonText: 'Cancelar',
            focusCancel: true
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Procesando...',
                    text: 'Restaurando la base de datos, por favor espere.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                this.submit();
            }
        });
    });

    function mostrarToastCarga() {
        const Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
        });
        Toast.fire({ icon: 'info', title: 'Generando copia limpia...' });
    }
</script>

<?php require_once '../includes/footer.php'; ?>