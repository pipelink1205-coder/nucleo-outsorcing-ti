<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

$mensaje_error = '';
$sucursales = $core->prepare('SELECT id,nombre FROM sucursales WHERE id_empresa=?' . ($soporte_ctx['sucursal'] ? ' AND id=' . (int) $soporte_ctx['sucursal'] : ''));
$sucursales->execute([$soporte_ctx['empresa']]);
$sucursales = $sucursales->fetchAll();
$solicitantes = $core->prepare('SELECT id,CONCAT(nombres,\' \',apellidos) AS nombre FROM empleados WHERE id_empresa=?' . ($soporte_ctx['sucursal'] ? ' AND id_sucursal=' . (int) $soporte_ctx['sucursal'] : '') . ($soporte_ctx['rol'] === 'empleado' ? ' AND id=' . (int) $soporte_ctx['empleado'] : ''));
$solicitantes->execute([$soporte_ctx['empresa']]);
$solicitantes = $solicitantes->fetchAll();
$equipos = $core->prepare('SELECT id,codigo_inventario AS nombre FROM equipos WHERE id_empresa=?' . ($soporte_ctx['sucursal'] ? ' AND id_sucursal=' . (int) $soporte_ctx['sucursal'] : ''));
$equipos->execute([$soporte_ctx['empresa']]);
$equipos = $equipos->fetchAll();
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
    try { [$id_sucursal, $id_solicitante, $id_equipo] = soporte_validar_vinculos($core, $pdo, $soporte_ctx, $_POST); }
    catch (Throwable $e) { nucleo_error($e); }
    // Recopilación de datos del formulario
    $id_cliente = $_POST['id_cliente'];
    $id_tipo_caso = $_POST['id_tipo_caso'];
    $asunto = trim($_POST['asunto']);
    $prioridad = $_POST['prioridad'];
    $descripcion = trim($_POST['descripcion']);
    $validar_tipo = $pdo->prepare('SELECT id_tipo_caso FROM tiposdecaso WHERE id_tipo_caso=? AND activo=1');
    $validar_tipo->execute([(int) $id_tipo_caso]);
    if (!$validar_tipo->fetchColumn() || !in_array($prioridad, ['Baja','Media','Alta','Urgente'], true) || strlen($asunto) > 255) {
        nucleo_error(new RuntimeException('Tipo, prioridad o asunto no válido', 422));
    }

    if (empty($id_cliente) || empty($id_tipo_caso) || empty($asunto) || empty($descripcion)) {
        $mensaje_error = "Por favor, complete todos los campos obligatorios (*).";
    } else {
        $pdo->beginTransaction();
        try {
            // 1. Insertar el ticket
            $stmt = $pdo->prepare(
                "INSERT INTO Tickets (id_cliente, id_tipo_caso, asunto, prioridad, descripcion, estado, id_empresa_portal, id_sucursal, id_solicitante, id_equipo, id_solicitante_usuario)
                 VALUES (?, ?, ?, ?, ?, 'Abierto', ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$id_cliente, $id_tipo_caso, $asunto, $prioridad, $descripcion, $portalEmpresa, $id_sucursal, $id_solicitante, $id_equipo, $soporte_ctx['usuario']]);
            $id_ticket_nuevo = $pdo->lastInsertId();

            // 2. Insertar la descripción como el primer comentario
            $stmt_comentario = $pdo->prepare(
                "INSERT INTO Comentarios (id_ticket, id_autor, tipo_autor, comentario, es_privado)
                 VALUES (?, ?, 'Cliente', ?, 0)"
            );
            $stmt_comentario->execute([$id_ticket_nuevo, $id_cliente, "Ticket creado con la siguiente descripción:\n\n" . $descripcion]);
            $id_comentario_inicial = $pdo->lastInsertId();
            $pdo->prepare('UPDATE comentarios SET id_usuario_nucleo=? WHERE id_comentario=?')->execute([$soporte_ctx['usuario'], $id_comentario_inicial]);

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
            $mensaje_error = 'No se pudo registrar el ticket.';
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
                <?php foreach (['id_sucursal' => ['Sucursal', $sucursales], 'id_solicitante' => ['Solicitante', $solicitantes], 'id_equipo' => ['Equipo (opcional)', $equipos]] as $campo => [$etiqueta, $opciones]): ?>
                <div class="col-md-4">
                    <label class="form-label"><?php echo htmlspecialchars($etiqueta); ?></label>
                    <select class="form-select" name="<?php echo $campo; ?>" <?php echo $campo !== 'id_equipo' ? 'required' : ''; ?>>
                        <option value="">Seleccione...</option>
                        <?php foreach ($opciones as $opcion): ?><option value="<?php echo (int) $opcion['id']; ?>"><?php echo htmlspecialchars($opcion['nombre']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>

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
