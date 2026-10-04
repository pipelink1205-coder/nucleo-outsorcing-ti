<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

$id_ticket = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_ticket) {
    header('Location: index.php');
    exit();
}

try { $ticket_autorizado = soporte_ticket($pdo, $soporte_ctx, $id_ticket); }
catch (Throwable $e) { nucleo_error($e); }

// Autorizar antes de cualquier lectura relacionada o escritura.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['id_usuario'])) {
    $pdo->beginTransaction();
    try {
        $ultimo_comentario = (int) $pdo->query('SELECT COALESCE(MAX(id_comentario),0) FROM comentarios')->fetchColumn();
        $stmt_agente = $pdo->prepare("SELECT id_agente, u.nombre_completo FROM Agentes a JOIN Usuarios u ON a.id_usuario = u.id_usuario WHERE a.id_usuario = ?");
        $stmt_agente->execute([$_SESSION['id_usuario']]);
        $agente_actual = $stmt_agente->fetch();
        $id_agente_autor = $agente_actual ? $agente_actual['id_agente'] : null;
        $nombre_agente_autor = $agente_actual ? $agente_actual['nombre_completo'] : ($_SESSION['nombre_completo'] ?? 'Sistema');
        
        $accion_realizada = false;

        if (isset($_POST['agregar_comentario'])) {
            $comentario_texto = trim($_POST['comentario']);
            $archivos_subidos = isset($_FILES['adjuntos']) && !empty(array_filter($_FILES['adjuntos']['name']));
            if (!empty($comentario_texto) || $archivos_subidos) {
                $es_privado = isset($_POST['es_privado']) ? 1 : 0;
                $id_comentario_nuevo = null;
                if (empty($comentario_texto) && $archivos_subidos) { $comentario_texto = "Se adjuntaron archivos."; }
                $stmt_comentario = $pdo->prepare("INSERT INTO Comentarios (id_ticket, id_autor, tipo_autor, comentario, es_privado) VALUES (?, ?, 'Agente', ?, ?)");
                $stmt_comentario->execute([$id_ticket, $id_agente_autor, $comentario_texto, $es_privado]);
                $id_comentario_nuevo = $pdo->lastInsertId();
                if ($archivos_subidos) {
                    $upload_dir = __DIR__ . '/../uploads/';
                    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                    foreach ($_FILES['adjuntos']['name'] as $key => $name) {
                        if ($_FILES['adjuntos']['error'][$key] == UPLOAD_ERR_OK) {
                            $nombre_original = basename($name);
                            $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
                            $nombre_guardado = uniqid('ticket' . $id_ticket . '_', true) . '.' . $extension;
                            $ruta_archivo_completa = $upload_dir . $nombre_guardado;
                            $ruta_archivo_db = 'uploads/' . $nombre_guardado;
                            if (move_uploaded_file($_FILES['adjuntos']['tmp_name'][$key], $ruta_archivo_completa)) {
                                $stmt_adjunto = $pdo->prepare("INSERT INTO Archivos_Adjuntos (id_ticket, id_comentario, nombre_original, nombre_guardado, ruta_archivo, tipo_mime) VALUES (?, ?, ?, ?, ?, ?)");
                                $stmt_adjunto->execute([$id_ticket, $id_comentario_nuevo, $nombre_original, $nombre_guardado, $ruta_archivo_db, $_FILES['adjuntos']['type'][$key]]);
                            }
                        }
                    }
                }
                $accion_realizada = true;
            }
        }
        
        if (isset($_POST['cambiar_estado'])) {
            $nuevo_estado = htmlspecialchars($_POST['nuevo_estado']);
            $pdo->prepare("UPDATE Tickets SET estado = ? WHERE id_ticket = ?")->execute([$nuevo_estado, $id_ticket]);
            $comentario_log = "Estado cambiado a '{$nuevo_estado}' por {$nombre_agente_autor}.";
            if (!empty(trim($_POST['comentario_adicional']))) {
                $comentario_log .= "\n\n" . trim($_POST['comentario_adicional']);
            }
            $pdo->prepare("INSERT INTO Comentarios (id_ticket, id_autor, tipo_autor, comentario, es_privado) VALUES (?, ?, 'Agente', ?, 0)")->execute([$id_ticket, $id_agente_autor, $comentario_log]);
            $accion_realizada = true;
        }

        if (isset($_POST['asignar_ticket']) && $_SESSION['id_rol'] == 1) {
            $validar = $pdo->prepare('SELECT u.id_usuario_nucleo FROM agentes a JOIN usuarios u ON u.id_usuario=a.id_usuario WHERE a.id_agente=?');
            $validar->execute([(int) $_POST['id_nuevo_agente']]);
            $usuario_asignado = (int) $validar->fetchColumn();
            $q = $core->prepare("SELECT u.id FROM usuarios u JOIN usuario_roles ur ON ur.id_usuario=u.id JOIN roles r ON r.id=ur.id_rol WHERE u.id=? AND u.activo=1 AND (u.id_empresa=? OR (u.id_empresa IS NULL AND r.nombre_rol='Operador'))");
            $q->execute([$usuario_asignado, $soporte_ctx['empresa']]);
            if (!$q->fetchColumn()) { throw new RuntimeException('Agente no autorizado', 403); }
            $id_nuevo_agente = $_POST['id_nuevo_agente'];
            $stmt_agente_anterior = $pdo->prepare("SELECT u.nombre_completo FROM Tickets t LEFT JOIN Agentes a ON t.id_agente_asignado = a.id_agente LEFT JOIN Usuarios u ON a.id_usuario = u.id_usuario WHERE t.id_ticket = ?");
            $stmt_agente_anterior->execute([$id_ticket]);
            $nombre_agente_anterior = $stmt_agente_anterior->fetchColumn() ?: 'Nadie';
            $pdo->prepare("UPDATE Tickets SET id_agente_asignado = ? WHERE id_ticket = ?")->execute([$id_nuevo_agente, $id_ticket]);
            $stmt_agente_nuevo = $pdo->prepare("SELECT u.nombre_completo FROM Agentes a JOIN Usuarios u ON a.id_usuario = u.id_usuario WHERE a.id_agente = ?");
            $stmt_agente_nuevo->execute([$id_nuevo_agente]);
            $nombre_agente_nuevo = $stmt_agente_nuevo->fetchColumn();
            $comentario_log = "Ticket reasignado de '{$nombre_agente_anterior}' a '{$nombre_agente_nuevo}' por {$nombre_agente_autor}.";
            $pdo->prepare("INSERT INTO Comentarios (id_ticket, id_autor, tipo_autor, comentario, es_privado) VALUES (?, ?, 'Agente', ?, 1)")->execute([$id_ticket, $id_agente_autor, $comentario_log]);
            $accion_realizada = true;
        }

        if (isset($_POST['guardar_costo']) && $_SESSION['id_rol'] == 1) {
            if ($ticket_autorizado['estado_facturacion'] === 'Pagado') { throw new RuntimeException('Los costos pagados no pueden modificarse', 403); }
            $nuevo_costo = empty($_POST['costo']) ? null : (float)$_POST['costo'];
            $nueva_moneda = htmlspecialchars($_POST['moneda']);
            $nuevo_estado_facturacion = htmlspecialchars($_POST['estado_facturacion']);
            $nuevo_medio_pago = ($nuevo_estado_facturacion === 'Pagado') ? htmlspecialchars($_POST['medio_pago']) : null;
            $stmt_old = $pdo->prepare("SELECT costo, moneda, estado_facturacion, medio_pago FROM Tickets WHERE id_ticket = ?");
            $stmt_old->execute([$id_ticket]);
            $valores_antiguos = $stmt_old->fetch(PDO::FETCH_ASSOC);
            $log_cambios = [];
            if ($nuevo_costo != (float)$valores_antiguos['costo']) { $log_cambios[] = "Costo cambiado de '" . ($valores_antiguos['costo'] ?? '0.00') . "' a '" . number_format($nuevo_costo ?? 0, 2) . "'."; }
            if ($nueva_moneda != $valores_antiguos['moneda']) { $log_cambios[] = "Moneda cambiada de '" . ($valores_antiguos['moneda'] ?? 'N/A') . "' a '" . $nueva_moneda . "'."; }
            if ($nuevo_estado_facturacion != $valores_antiguos['estado_facturacion']) { $log_cambios[] = "Estado de facturación cambiado de '" . ($valores_antiguos['estado_facturacion'] ?? 'N/A') . "' a '" . $nuevo_estado_facturacion . "'."; }
            if ($nuevo_medio_pago != $valores_antiguos['medio_pago']) { $log_cambios[] = "Medio de pago establecido a '" . ($nuevo_medio_pago ?? 'N/A') . "'."; }
            if (!empty($log_cambios)) {
                $pdo->prepare("UPDATE Tickets SET costo = ?, moneda = ?, estado_facturacion = ?, medio_pago = ? WHERE id_ticket = ?")->execute([$nuevo_costo, $nueva_moneda, $nuevo_estado_facturacion, $nuevo_medio_pago, $id_ticket]);
                if ($nuevo_estado_facturacion == 'Pagado' && $valores_antiguos['estado_facturacion'] != 'Pagado') {
                    $comentario_log = "Se registró el pago del ticket por {$nombre_agente_autor} con los siguientes detalles:\n" . "- Monto: " . htmlspecialchars($nueva_moneda) . " " . number_format($nuevo_costo ?? 0, 2) . "\n" . "- Medio de Pago: " . htmlspecialchars($nuevo_medio_pago) . "\n" . "- Estado: Pagado";
                } else {
                    $comentario_log = "Se actualizaron los detalles de facturación por {$nombre_agente_autor}:\n- " . implode("\n- ", $log_cambios);
                }
                $pdo->prepare("INSERT INTO Comentarios (id_ticket, id_autor, tipo_autor, comentario, es_privado) VALUES (?, ?, 'Agente', ?, 1)")->execute([$id_ticket, $id_agente_autor, $comentario_log]);
                $accion_realizada = true;
            }
        }
        
        if (isset($_POST['anular_ticket']) && $_SESSION['id_rol'] == 1) {
            $motivo = trim($_POST['motivo_anulacion']);
            if(!empty($motivo)) {
                $pdo->prepare("UPDATE Tickets SET estado = 'Anulado' WHERE id_ticket = ?")->execute([$id_ticket]);
                $comentario_log = "Ticket anulado por {$nombre_agente_autor}.\nMotivo: " . $motivo;
                $pdo->prepare("INSERT INTO Comentarios (id_ticket, id_autor, tipo_autor, comentario, es_privado) VALUES (?, ?, 'Agente', ?, 1)")->execute([$id_ticket, $id_agente_autor, $comentario_log]);
                $accion_realizada = true;
            }
        }

        if ($accion_realizada) {
            $pdo->prepare('UPDATE comentarios SET id_usuario_nucleo=? WHERE id_ticket=? AND id_comentario>? AND id_autor=? AND tipo_autor=\'Agente\'')->execute([$soporte_ctx['usuario'], $id_ticket, $ultimo_comentario, $id_agente_autor]);
            $pdo->commit();
            header("Location: ver_ticket.php?id=$id_ticket&status=success");
            exit();
        } else {
            $pdo->rollBack();
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        if (in_array($e->getCode(), [403,404,422], true)) { nucleo_error($e); }
        header("Location: ver_ticket.php?id=$id_ticket&status=error&msg=" . urlencode($e->getMessage()));
        exit();
    }
}

// --- OBTENER DATOS PARA MOSTRAR EN LA PÁGINA ---
$stmt = $pdo->prepare("SELECT t.*, c.nombre AS nombre_cliente, u.nombre_completo AS nombre_agente, tc.nombre_tipo FROM Tickets AS t JOIN Clientes AS c ON t.id_cliente = c.id_cliente LEFT JOIN Agentes AS ag ON t.id_agente_asignado = ag.id_agente LEFT JOIN Usuarios AS u ON ag.id_usuario = u.id_usuario LEFT JOIN TiposDeCaso AS tc ON t.id_tipo_caso = tc.id_tipo_caso WHERE t.id_ticket = ?");
$stmt->execute([$id_ticket]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) { header('Location: index.php'); exit(); }

$agentes_disponibles = soporte_agentes($core, $pdo, $soporte_ctx);
$stmt_comentarios = $pdo->prepare("SELECT com.*, CASE WHEN com.tipo_autor = 'Cliente' THEN cli.nombre WHEN com.tipo_autor = 'Agente' THEN usu.nombre_completo ELSE 'Desconocido' END AS nombre_autor FROM Comentarios AS com LEFT JOIN Clientes AS cli ON com.tipo_autor = 'Cliente' AND com.id_autor = cli.id_cliente LEFT JOIN Agentes AS ag ON com.tipo_autor = 'Agente' AND com.id_autor = ag.id_agente LEFT JOIN Usuarios AS usu ON ag.id_usuario = usu.id_usuario WHERE com.id_ticket = ? ORDER BY com.fecha_creacion ASC");
$stmt_comentarios->execute([$id_ticket]);
$comentarios = $stmt_comentarios->fetchAll(PDO::FETCH_ASSOC);
$comentarios = array_filter($comentarios, function ($c) use ($soporte_ctx) { return !$c['es_privado'] || in_array($soporte_ctx['rol'], ['operador', 'administrador', 'auditor'], true); });
$stmt_adjuntos = $pdo->prepare("SELECT * FROM Archivos_Adjuntos WHERE id_ticket = ? AND id_comentario IS NOT NULL");
$stmt_adjuntos->execute([$id_ticket]);
$adjuntos_con_comentario = $stmt_adjuntos->fetchAll(PDO::FETCH_ASSOC);

$adjuntos_por_comentario = [];
foreach ($adjuntos_con_comentario as $adjunto) {
    $adjuntos_por_comentario[$adjunto['id_comentario']][] = $adjunto;
}

$costos_bloqueados = ($ticket['estado_facturacion'] == 'Pagado');
require_once '../includes/header.php';
$status_classes = ['Abierto' => 'primary', 'En Progreso' => 'info', 'En Espera' => 'warning', 'Resuelto' => 'success', 'Cerrado' => 'secondary', 'Anulado' => 'dark'];
$priority_classes = ['Baja' => 'success', 'Media' => 'warning', 'Alta' => 'danger', 'Urgente' => 'danger fw-bold'];
$estados_disponibles = ['Abierto', 'En Progreso', 'En Espera', 'Resuelto'];
$is_ticket_finalizado = in_array($ticket['estado'], ['Resuelto', 'Cerrado', 'Anulado']);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>Ticket #<?php echo htmlspecialchars($ticket['id_ticket']); ?>: <?php echo htmlspecialchars($ticket['asunto']); ?></h3>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header fw-bold">Detalles del Ticket</div>
            <div class="card-body">
                <p><strong>Cliente:</strong> <?php echo htmlspecialchars($ticket['nombre_cliente']); ?></p>
                <?php foreach (['id_empresa_portal' => ['empresas','Empresa'], 'id_sucursal' => ['sucursales','Sucursal'], 'id_solicitante' => ['empleados','Solicitante'], 'id_equipo' => ['equipos','Equipo']] as $columna => [$tabla,$titulo]): ?>
                <p><strong><?php echo $titulo; ?>:</strong> <?php
                    if (empty($ticket[$columna])) { echo $columna === 'id_equipo' ? 'Sin equipo' : 'Pendiente de clasificación'; }
                    else { $q = $core->prepare("SELECT * FROM `$tabla` WHERE id=?"); $q->execute([$ticket[$columna]]); $r = $q->fetch(); echo htmlspecialchars($r['nombre'] ?? $r['codigo_inventario'] ?? (($r['nombres'] ?? '') . ' ' . ($r['apellidos'] ?? ''))); }
                ?></p>
                <?php endforeach; ?>
                <p><strong>Agente Asignado:</strong> <?php echo htmlspecialchars($ticket['nombre_agente'] ?? 'Sin asignar'); ?></p>
                <p><strong>Tipo de Caso:</strong> <?php echo htmlspecialchars($ticket['nombre_tipo'] ?? 'No especificado'); ?></p>
                <p><strong>Estado:</strong> <span class="badge bg-<?php echo $status_classes[$ticket['estado']] ?? 'light'; ?> fs-6"><?php echo htmlspecialchars($ticket['estado']); ?></span></p>
                <p><strong>Prioridad:</strong> <span class="badge bg-<?php echo $priority_classes[$ticket['prioridad']] ?? 'light'; ?>"><?php echo htmlspecialchars($ticket['prioridad']); ?></span></p>
                <p><strong>Creado:</strong> <?php echo date('d/m/Y H:i', strtotime($ticket['fecha_creacion'])); ?></p>
            </div>
        </div>
        
        <?php if (!$is_ticket_finalizado && $_SESSION['id_rol'] == 1): ?>
        <div class="card mb-4">
            <div class="card-header fw-bold">Acciones</div>
            <div class="card-body">
                <form action="ver_ticket.php?id=<?php echo $id_ticket; ?>" method="POST" class="mb-3">
                    <label for="nuevo_estado" class="form-label fw-bold">Cambiar Estado:</label>
                    <select name="nuevo_estado" id="nuevo_estado" class="form-select mb-2">
                        <?php foreach ($estados_disponibles as $estado): ?>
                            <option value="<?php echo $estado; ?>" <?php echo ($ticket['estado'] == $estado) ? 'selected' : ''; ?>><?php echo $estado; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="mb-2">
                        <label for="comentario_adicional" class="form-label">Añadir comentario público (opcional)</label>
                        <textarea class="form-control" id="comentario_adicional" name="comentario_adicional" rows="2"></textarea>
                    </div>
                    <button type="submit" name="cambiar_estado" class="btn btn-info w-100">Guardar Estado</button>
                </form>
                
                <?php if ($_SESSION['id_rol'] == 1): ?>
                <hr>
                <form action="ver_ticket.php?id=<?php echo $id_ticket; ?>" method="POST" class="mb-3">
                    <label for="id_nuevo_agente" class="form-label fw-bold">Asignar a Agente:</label>
                    <div class="input-group">
                        <select name="id_nuevo_agente" id="id_nuevo_agente" class="form-select">
                            <?php foreach ($agentes_disponibles as $agente): ?>
                                <option value="<?php echo $agente['id_agente']; ?>" <?php echo ($ticket['id_agente_asignado'] == $agente['id_agente']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($agente['nombre_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="asignar_ticket" class="btn btn-primary">Asignar</button>
                    </div>
                </form>
                <?php endif; ?>

                <?php if ($_SESSION['id_rol'] == 1): ?>
                <div class="text-center mt-3">
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#anularTicketModal"><i class="bi bi-x-circle-fill"></i> Anular Ticket</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($_SESSION['id_rol'] == 1): ?>
        <div class="card">
            <div class="card-header fw-bold"><i class="bi bi-currency-dollar"></i> Gestión de Costos</div>
            <div class="card-body">
                <?php if ($costos_bloqueados): ?>
                    <div class="alert alert-success" role="alert"><i class="bi bi-check-circle-fill"></i> Este ticket ya ha sido pagado. No se permiten más cambios.</div>
                <?php endif; ?>
                <form action="ver_ticket.php?id=<?php echo $id_ticket; ?>" method="POST">
                    <div class="mb-3"><label for="costo" class="form-label">Costo</label><input type="text" class="form-control" id="costo" name="costo" value="<?php echo htmlspecialchars($ticket['costo'] ?? ''); ?>" <?php if ($costos_bloqueados) echo 'disabled'; ?>></div>
                    <div class="mb-3"><label for="moneda" class="form-label">Moneda</label><input type="text" class="form-control" id="moneda" name="moneda" value="<?php echo htmlspecialchars($ticket['moneda'] ?? 'PEN'); ?>" <?php if ($costos_bloqueados) echo 'disabled'; ?>></div>
                    <div class="mb-3">
                        <label for="estado_facturacion" class="form-label">Estado de Facturación</label>
                        <select class="form-select" id="estado_facturacion" name="estado_facturacion" <?php if ($costos_bloqueados) echo 'disabled'; ?>>
                            <option value="Pendiente" <?php echo ($ticket['estado_facturacion'] == 'Pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="Facturado" <?php echo ($ticket['estado_facturacion'] == 'Facturado') ? 'selected' : ''; ?>>Facturado</option>
                            <option value="Pagado" <?php echo ($ticket['estado_facturacion'] == 'Pagado') ? 'selected' : ''; ?>>Pagado</option>
                            <option value="Anulado" <?php echo ($ticket['estado_facturacion'] == 'Anulado') ? 'selected' : ''; ?>>Anulado</option>
                        </select>
                    </div>
                    <div class="mb-3" id="medio_pago_container"><label for="medio_pago" class="form-label">Medio de Pago</label><select class="form-select" id="medio_pago" name="medio_pago" <?php if ($costos_bloqueados) echo 'disabled'; ?>><option value="">Seleccione...</option><option value="Efectivo" <?php echo ($ticket['medio_pago'] == 'Efectivo') ? 'selected' : ''; ?>>Efectivo</option><option value="Tarjeta de Crédito/Débito" <?php echo ($ticket['medio_pago'] == 'Tarjeta de Crédito/Débito') ? 'selected' : ''; ?>>Tarjeta de Crédito/Débito</option><option value="Transferencia Bancaria" <?php echo ($ticket['medio_pago'] == 'Transferencia Bancaria') ? 'selected' : ''; ?>>Transferencia Bancaria</option><option value="Yape/Plin" <?php echo ($ticket['medio_pago'] == 'Yape/Plin') ? 'selected' : ''; ?>>Yape/Plin</option></select></div>
                    <div class="d-grid"><button type="submit" name="guardar_costo" class="btn btn-success" <?php if ($costos_bloqueados) echo 'disabled'; ?>><i class="bi bi-save"></i> Guardar Costo</button></div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header fw-bold">Descripción y Comentarios</div>
            <div class="card-body">
                <h5 class="card-title">Descripción del Problema</h5>
                <p class="text-bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($ticket['descripcion'])); ?></p>
                <hr>
                <h5 class="card-title mt-4">Historial de Comentarios</h5>
                <?php if (empty($comentarios)): ?><p>No hay comentarios en este ticket.</p><?php else: ?>
                    <?php foreach ($comentarios as $comentario): ?>
                        <?php
                        $es_comentario_costo = strpos($comentario['comentario'], 'facturación') !== false;
                        if ($es_comentario_costo && $_SESSION['id_rol'] != 1) {
                            continue;
                        }
                        ?>
                        <div class="mb-3 p-3 rounded <?php echo $comentario['es_privado'] ? 'border border-warning' : 'bg-light'; ?>">
                            <div class="d-flex justify-content-between">
                                <strong><i class="bi <?php echo $comentario['tipo_autor'] == 'Agente' ? 'bi-person-gear' : 'bi-person-circle'; ?>"></i> <?php echo htmlspecialchars($comentario['nombre_autor']); ?></strong>
                                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($comentario['fecha_creacion'])); ?></small>
                            </div>
                            <p class="mt-2 mb-0"><?php echo nl2br(htmlspecialchars($comentario['comentario'])); ?></p>
                            <?php if (isset($adjuntos_por_comentario[$comentario['id_comentario']])): ?>
                                <div class="mt-2 pt-2 border-top">
                                    <?php foreach ($adjuntos_por_comentario[$comentario['id_comentario']] as $adjunto_comentario): ?>
                                        <a href="descargar_adjunto.php?id=<?php echo (int) $adjunto_comentario['id_adjunto']; ?>" class="d-block small"><i class="bi bi-paperclip"></i> <?php echo htmlspecialchars($adjunto_comentario['nombre_original']); ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($comentario['es_privado']): ?><small class="d-block text-warning fw-bold mt-2"><i class="bi bi-eye-slash-fill"></i> Nota privada</small><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (!$is_ticket_finalizado && $soporte_ctx['rol'] !== 'auditor'): ?>
                <hr>
                <h5 class="card-title mt-4">Añadir Comentario</h5>
                <form action="ver_ticket.php?id=<?php echo $id_ticket; ?>" method="POST" enctype="multipart/form-data">
                    <div class="mb-3"><textarea class="form-control" name="comentario" rows="3" placeholder="Escribe tu comentario aquí..."></textarea></div>
                    <div class="mb-3"><label for="adjuntos" class="form-label">Adjuntar Archivos (Opcional)</label><input class="form-control" type="file" id="adjuntos" name="adjuntos[]" multiple></div>
                    <?php if ($_SESSION['id_rol'] == 1): ?><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="es_privado" id="es_privado"><label class="form-check-label" for="es_privado">Marcar como comentario privado</label></div><?php endif; ?>
                    <button type="submit" name="agregar_comentario" class="btn btn-primary"><i class="bi bi-send"></i> Enviar Comentario</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="anularTicketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="ver_ticket.php?id=<?php echo $id_ticket; ?>" method="POST">
                <div class="modal-header"><h5 class="modal-title">Anular Ticket #<?php echo $id_ticket; ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p>Estás a punto de anular este ticket. Esta acción no se puede deshacer.</p>
                    <div class="mb-3"><label for="motivo_anulacion" class="form-label"><strong>Motivo de la anulación (obligatorio):</strong></label><textarea class="form-control" id="motivo_anulacion" name="motivo_anulacion" rows="3" required></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" name="anular_ticket" class="btn btn-danger">Confirmar Anulación</button></div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const estadoFacturacionSelect = document.getElementById('estado_facturacion');
    if (estadoFacturacionSelect) {
        const medioPagoContainer = document.getElementById('medio_pago_container');
        function toggleMedioPago() {
            if (estadoFacturacionSelect.value === 'Pagado') {
                medioPagoContainer.style.display = 'block';
            } else {
                medioPagoContainer.style.display = 'none';
            }
        }
        toggleMedioPago();
        estadoFacturacionSelect.addEventListener('change', toggleMedioPago);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
