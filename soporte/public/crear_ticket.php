<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';
require_once __DIR__.'/../includes/adjuntos.php';

$mensaje_error = '';
$sucursales = $core->prepare('SELECT id,nombre FROM sucursales WHERE id_empresa=?' . ($soporte_ctx['sucursal'] ? ' AND id=' . (int) $soporte_ctx['sucursal'] : ''));
$sucursales->execute([$soporte_ctx['empresa']]);
$sucursales = $sucursales->fetchAll();
$solicitantes = $core->prepare('SELECT id,CONCAT(nombres,\' \',apellidos) AS nombre FROM empleados WHERE id_empresa=?' . ($soporte_ctx['sucursal'] ? ' AND id_sucursal=' . (int) $soporte_ctx['sucursal'] : '') . ($soporte_ctx['rol'] === 'empleado' ? ' AND id=' . (int) $soporte_ctx['empleado'] : ''));
$solicitantes->execute([$soporte_ctx['empresa']]);
$solicitantes = $solicitantes->fetchAll();
$equipos = $core->prepare('SELECT id,codigo_inventario AS nombre FROM equipos WHERE id_empresa=?' . ($soporte_ctx['sucursal'] ? ' AND id_sucursal=' . (int) $soporte_ctx['sucursal'] : ''));
$equipos->execute([$soporte_ctx['empresa']]);
$equipos = $soporte_ctx['rol']==='empleado' ? soporte_equipos_asignados($core,$soporte_ctx) : $equipos->fetchAll();
$portalEmpresa = $soporte_ctx['empresa'];
$tipos_de_caso = $pdo->query("SELECT id_tipo_caso, nombre_tipo FROM TiposDeCaso WHERE activo = 1 ORDER BY nombre_tipo ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { $archivos_validos=soporte_validar_archivos($_FILES['adjuntos']??[]); [$id_sucursal, $id_solicitante, $id_equipo] = soporte_validar_vinculos($core, $pdo, $soporte_ctx, $_POST); }
    catch (Throwable $e) { nucleo_error($e); }
    // Recopilación de datos del formulario
    $id_cliente = !empty($_POST['id_cliente']) ? (int)$_POST['id_cliente'] : null;
    $solicitante_nombre = $id_solicitante ? nucleo_referencia($core,$soporte_ctx,'empleados',$id_solicitante,$id_sucursal) : null;
    $solicitante_nombre = $solicitante_nombre ? trim($solicitante_nombre['nombres'].' '.$solicitante_nombre['apellidos']) : trim($_POST['solicitante_nombre']);
    $solicitante_contacto = $id_solicitante ? null : trim($_POST['solicitante_contacto']);
    $id_tipo_caso = $_POST['id_tipo_caso'] ?? 0;
    $asunto = trim($_POST['asunto'] ?? '');
    $prioridad = $_POST['prioridad'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');
    $validar_tipo = $pdo->prepare('SELECT id_tipo_caso FROM tiposdecaso WHERE id_tipo_caso=? AND activo=1');
    $validar_tipo->execute([(int) $id_tipo_caso]);
    if (!$validar_tipo->fetchColumn() || !in_array($prioridad, ['Baja','Media','Alta','Urgente'], true) || strlen($asunto) > 255) {
        nucleo_error(new RuntimeException('Tipo, prioridad o asunto no válido', 422));
    }

    if (empty($id_tipo_caso) || empty($asunto) || empty($descripcion)) {
        $mensaje_error = "Por favor, complete todos los campos obligatorios (*).";
    } else {
        $guardados=[];
        $pdo->beginTransaction();
        try {
            soporte_bloquear_asignacion($core,$pdo,$soporte_ctx,$id_equipo);
            // 1. Insertar el ticket
            $stmt = $pdo->prepare(
                "INSERT INTO Tickets (id_cliente, id_tipo_caso, asunto, prioridad, descripcion, estado, id_empresa_portal, id_sucursal, id_solicitante, id_equipo, id_solicitante_usuario, moneda, solicitante_nombre, solicitante_contacto)
                 VALUES (?, ?, ?, ?, ?, 'Abierto', ?, ?, ?, ?, ?, 'COP', ?, ?)"
            );
            $stmt->execute([$id_cliente, $id_tipo_caso, $asunto, $prioridad, $descripcion, $portalEmpresa, $id_sucursal, $id_solicitante, $id_equipo, $soporte_ctx['usuario'], $solicitante_nombre, $solicitante_contacto]);
            $id_ticket_nuevo = $pdo->lastInsertId();

            // 2. Insertar la descripción como el primer comentario
            $stmt_comentario = $pdo->prepare(
                "INSERT INTO Comentarios (id_ticket, id_autor, tipo_autor, comentario, es_privado)
                 VALUES (?, ?, 'Agente', ?, 0)"
            );
            $agente=$pdo->prepare('SELECT id_agente FROM agentes WHERE id_usuario=?'); $agente->execute([$_SESSION['id_usuario']]);
            $stmt_comentario->execute([$id_ticket_nuevo, $agente->fetchColumn(), "Ticket creado con la siguiente descripción:\n\n" . $descripcion]);
            $id_comentario_inicial = $pdo->lastInsertId();
            $pdo->prepare('UPDATE comentarios SET id_usuario_nucleo=? WHERE id_comentario=?')->execute([$soporte_ctx['usuario'], $id_comentario_inicial]);

            soporte_guardar_archivos($pdo,(int)$id_ticket_nuevo,(int)$id_comentario_inicial,$archivos_validos,$guardados);

            $pdo->commit();
            header("Location: ver_ticket.php?id=" . $id_ticket_nuevo . "&status=created");
            exit();

        } catch (Throwable $e) {
            $pdo->rollBack();
            soporte_borrar_archivos($guardados);
            if ($e instanceof RuntimeException && $e->getCode()===403) { nucleo_error($e); }
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
                <div class="col-12"><label class="form-label">Empresa</label><p class="form-control-plaintext"><?php echo htmlspecialchars($soporte_ctx['empresa_nombre']); ?></p></div>
                <div class="col-md-4">
                    <label class="form-label">Sucursal</label>
                    <?php if (count($sucursales) === 1): ?>
                        <input type="hidden" name="id_sucursal" value="<?php echo (int)$sucursales[0]['id']; ?>">
                        <p><?php echo htmlspecialchars($sucursales[0]['nombre']); ?> (asignada automáticamente)</p>
                    <?php elseif (!$sucursales): ?><p>Sin sucursal registrada</p>
                    <?php else: ?>
                        <select class="form-select" name="id_sucursal" required><option value="">Seleccione...</option>
                        <?php foreach($sucursales as $opcion): ?><option value="<?php echo (int)$opcion['id']; ?>"><?php echo htmlspecialchars($opcion['nombre']); ?></option><?php endforeach; ?></select>
                    <?php endif; ?>
                </div>
                <?php if ($soporte_ctx['rol']==='empleado'): ?>
                <div class="col-md-4"><label class="form-label">Solicitante</label><input type="hidden" name="id_solicitante" value="<?php echo (int)$soporte_ctx['empleado']; ?>"><p><?php echo htmlspecialchars($solicitantes[0]['nombre']); ?></p></div>
                <?php else: ?>
                <div class="col-md-4"><label class="form-label">Solicitante</label>
                    <select class="form-select" name="id_solicitante" id="id_solicitante"><option value="">Persona no registrada</option>
                    <?php foreach($solicitantes as $opcion): ?><option value="<?php echo (int)$opcion['id']; ?>"><?php echo htmlspecialchars($opcion['nombre']); ?></option><?php endforeach; ?></select>
                </div>
                <?php endif; ?>
                <div class="col-md-4"><label class="form-label">Equipo (opcional)</label>
                    <select class="form-select" name="id_equipo"><option value="">Sin equipo</option>
                    <?php foreach($equipos as $opcion): ?><option value="<?php echo (int)$opcion['id']; ?>"><?php echo htmlspecialchars($opcion['nombre']); ?></option><?php endforeach; ?></select>
                </div>
                <?php if ($soporte_ctx['rol']!=='empleado'): ?>
                <div class="col-md-6" id="persona_nombre"><label class="form-label">Nombre del solicitante *</label><input class="form-control" name="solicitante_nombre" maxlength="200" required></div>
                <div class="col-md-6" id="persona_contacto"><label class="form-label">Contacto (correo o teléfono) *</label><input class="form-control" name="solicitante_contacto" maxlength="255" required></div>
                <?php endif; ?>
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

<script>
const solicitante = document.getElementById('id_solicitante');
function actualizarPersona() {
    for (const id of ['persona_nombre','persona_contacto']) {
        const contenedor=document.getElementById(id);
        contenedor.hidden=!!solicitante.value;
        contenedor.querySelector('input').required=!solicitante.value;
    }
}
if (solicitante) { solicitante.addEventListener('change',actualizarPersona); actualizarPersona(); }
</script>
