<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

$mensaje_error = '';
// Consultas para llenar los menús desplegables
$portalEmpresa = (int) ($_SESSION['portal_empresa_id'] ?? 0);
if ($portalEmpresa > 0) {
    $stmt_clientes_portal = $pdo->prepare("SELECT id_cliente, nombre FROM Clientes WHERE id_empresa_portal = ? ORDER BY nombre ASC");
    $stmt_clientes_portal->execute([$portalEmpresa]);
    $clientes = $stmt_clientes_portal->fetchAll(PDO::FETCH_ASSOC);
} else {
    $clientes = $pdo->query("SELECT id_cliente, nombre FROM Clientes ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
}
$tipos_de_caso = $pdo->query("SELECT id_tipo_caso, nombre_tipo FROM TiposDeCaso WHERE activo = 1 ORDER BY nombre_tipo ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recopilación de datos del formulario
    $id_cliente = $_POST['id_cliente'];
    $id_tipo_caso = $_POST['id_tipo_caso'];
    $asunto = trim($_POST['asunto']);
    $prioridad = $_POST['prioridad'];
    $descripcion = trim($_POST['descripcion']);

    if (empty($id_cliente) || empty($id_tipo_caso) || empty($asunto) || empty($descripcion)) {
        $mensaje_error = "Por favor, complete todos los campos obligatorios (*).";
    } else {
        $pdo->beginTransaction();
        try {
            // 1. Insertar el ticket
            $stmt = $pdo->prepare(
                "INSERT INTO Tickets (id_cliente, id_tipo_caso, asunto, prioridad, descripcion, estado, id_empresa_portal) 
                 VALUES (?, ?, ?, ?, ?, 'Abierto', ?)"
            );
            $stmt->execute([$id_cliente, $id_tipo_caso, $asunto, $prioridad, $descripcion, $portalEmpresa > 0 ? $portalEmpresa : null]);
            $id_ticket_nuevo = $pdo->lastInsertId();

            // 2. Insertar la descripción como el primer comentario
            $stmt_comentario = $pdo->prepare(
                "INSERT INTO Comentarios (id_ticket, id_autor, tipo_autor, comentario, es_privado) 
                 VALUES (?, ?, 'Cliente', ?, 0)"
            );
            $stmt_comentario->execute([$id_ticket_nuevo, $id_cliente, "Ticket creado con la siguiente descripción:\n\n" . $descripcion]);
            $id_comentario_inicial = $pdo->lastInsertId();
            
            // 3. Procesar múltiples archivos adjuntos si existen
            if (isset($_FILES['adjuntos']) && !empty(array_filter($_FILES['adjuntos']['name']))) {
                $upload_dir = __DIR__ . '/../uploads/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }

                foreach ($_FILES['adjuntos']['name'] as $key => $name) {
                    if ($_FILES['adjuntos']['error'][$key] == UPLOAD_ERR_OK) {
                        $nombre_original = basename($name);
                        $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
                        $nombre_guardado = uniqid('ticket' . $id_ticket_nuevo . '_', true) . '.' . $extension;
                        $ruta_archivo_completa = $upload_dir . $nombre_guardado;
                        $ruta_archivo_db = 'uploads/' . $nombre_guardado;

                        if (move_uploaded_file($_FILES['adjuntos']['tmp_name'][$key], $ruta_archivo_completa)) {
                            $stmt_adjunto = $pdo->prepare(
                                "INSERT INTO Archivos_Adjuntos (id_ticket, id_comentario, nombre_original, nombre_guardado, ruta_archivo, tipo_mime) 
                                 VALUES (?, ?, ?, ?, ?, ?)"
                            );
                            $stmt_adjunto->execute([$id_ticket_nuevo, $id_comentario_inicial, $nombre_original, $nombre_guardado, $ruta_archivo_db, $_FILES['adjuntos']['type'][$key]]);
                        }
                    }
                }
            }

            $pdo->commit();
            header("Location: ver_ticket.php?id=" . $id_ticket_nuevo . "&status=created");
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje_error = "Error al registrar el ticket: " . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-plus-circle-fill"></i> Crear Nuevo Ticket de Soporte</h2>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<?php if ($mensaje_error): ?>
    <div class="alert alert-danger"><?php echo $mensaje_error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-4">
        <form action="crear_ticket.php" method="POST" enctype="multipart/form-data">
            <div class="row g-4">
                
                <div class="col-md-6">
                    <label for="id_cliente" class="form-label">Cliente *</label>
                    <select class="form-select" id="id_cliente" name="id_cliente" required>
                        <option value="" disabled selected>Selecciona un cliente...</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id_cliente']; ?>"><?php echo htmlspecialchars($cliente['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="id_tipo_caso" class="form-label">Tipo de Caso *</label>
                    <select class="form-select" id="id_tipo_caso" name="id_tipo_caso" required>
                        <option value="" disabled selected>Selecciona un tipo...</option>
                        <?php foreach ($tipos_de_caso as $tipo): ?>
                            <option value="<?php echo $tipo['id_tipo_caso']; ?>"><?php echo htmlspecialchars($tipo['nombre_tipo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="asunto" class="form-label">Asunto *</label>
                    <input type="text" class="form-control" id="asunto" name="asunto" required>
                </div>

                <div class="col-md-6">
                    <label for="prioridad" class="form-label">Prioridad *</label>
                    <select class="form-select" id="prioridad" name="prioridad" required>
                        <option value="Baja">Baja</option>
                        <option value="Media" selected>Media</option>
                        <option value="Alta">Alta</option>
                        <option value="Urgente">Urgente</option>
                    </select>
                </div>
                
                <div class="col-12">
                    <label for="descripcion" class="form-label">Descripción del Problema *</label>
                    <textarea class="form-control" id="descripcion" name="descripcion" rows="6" required></textarea>
                </div>
                
                <div class="col-12">
                    <label for="adjuntos" class="form-label">Adjuntar Archivos (Opcional)</label>
                    <input class="form-control" type="file" id="adjuntos" name="adjuntos[]" multiple>
                    <div class="form-text">Puedes seleccionar varios archivos a la vez.</div>
                </div>
                
                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary">Registrar Ticket</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>