<?php
require_once '../templates/header.php';

// --- Lógica para CAMBIAR ESTADO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'], $_POST['type'])) {
    $id = (int)$_POST['id'];
    $type = $_POST['type'];
    $action = $_POST['action'];
    if (!in_array($action, ['activate','deactivate'], true)) { nucleo_error(new RuntimeException('Acción no válida', 422)); }
    $estado = ($action == 'deactivate') ? 'Inactivo' : 'Activo';
    
    // Mapa de tipos a tablas (asegúrate que 'tipo_equipo' esté correcto)
    $table_map = [
        'sucursal' => 'sucursales', 
        'tipo_equipo' => 'tipos_equipo', // Nombre de tipo esperado por catalogo_editar.php
        'marca' => 'marcas', 
        'modelo' => 'modelos', 
        'area' => 'areas', 
        'cargo' => 'cargos',
        'tipo' => 'tipos_equipo' // Dejamos esto por si la acción de activar/desactivar usa 'tipo'
    ];

    if (array_key_exists($type, $table_map)) {
        $table_name = $table_map[$type];
        $id_empresa_update = (int) empresa_id_activa();
        $stmt = $conexion->prepare("UPDATE {$table_name} SET estado = ? WHERE id = ? AND id_empresa = ?");
        $stmt->bind_param("sii", $estado, $id, $id_empresa_update);
        $stmt->execute();
        header("Location: gestion_catalogos.php");
        exit();
    }
}

// --- Lógica para AÑADIR NUEVOS ELEMENTOS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = null;
    if (isset($_POST['catalogo'])) {
        $catalogo = $_POST['catalogo'];
        $nombre = $_POST['nombre'];
        $id_empresa_catalogo = (int) empresa_id_activa();
        switch ($catalogo) {
            case 'tipo': $stmt = $conexion->prepare("INSERT INTO tipos_equipo (nombre, id_empresa) VALUES (?, ?)"); $stmt->bind_param("si", $nombre, $id_empresa_catalogo); break;
            case 'marca': $stmt = $conexion->prepare("INSERT INTO marcas (nombre, id_empresa) VALUES (?, ?)"); $stmt->bind_param("si", $nombre, $id_empresa_catalogo); break;
            case 'area': $stmt = $conexion->prepare("INSERT INTO areas (nombre, id_empresa) VALUES (?, ?)"); $stmt->bind_param("si", $nombre, $id_empresa_catalogo); break;
            case 'modelo': $id_marca = $_POST['id_marca']; $stmt = $conexion->prepare("INSERT INTO modelos (id_marca, nombre, id_empresa) VALUES (?, ?, ?)"); $stmt->bind_param("isi", $id_marca, $nombre, $id_empresa_catalogo); break;
            case 'cargo': $id_area = $_POST['id_area']; $stmt = $conexion->prepare("INSERT INTO cargos (id_area, nombre, id_empresa) VALUES (?, ?, ?)"); $stmt->bind_param("isi", $id_area, $nombre, $id_empresa_catalogo); break;
        }
    } elseif (isset($_POST['catalogo_sucursal'])) {
        $nombre = $_POST['nombre_sucursal'];
        $direccion = $_POST['direccion_sucursal'];
        $id_empresa_catalogo = (int) empresa_id_activa();
        $stmt = $conexion->prepare("INSERT INTO sucursales (nombre, direccion, id_empresa) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $nombre, $direccion, $id_empresa_catalogo);
    }
    if ($stmt && $stmt->execute()) { echo "<div class='alert alert-success mt-3'>Elemento agregado correctamente.</div>"; } 
    elseif($stmt) { echo "<div class='alert alert-danger mt-3'>Error al agregar: " . $stmt->error . "</div>"; }
    if ($stmt) $stmt->close();
}

// --- Cargar datos existentes para las tablas ---
$id_empresa_listas = (int) empresa_id_activa();
$sucursales = $conexion->query("SELECT * FROM sucursales WHERE id_empresa = $id_empresa_listas ORDER BY nombre");
$tipos = $conexion->query("SELECT * FROM tipos_equipo WHERE id_empresa = $id_empresa_listas ORDER BY nombre");
$marcas = $conexion->query("SELECT * FROM marcas WHERE id_empresa = $id_empresa_listas ORDER BY nombre");
$modelos = $conexion->query("SELECT m.id, m.nombre, m.estado, ma.nombre as marca_nombre FROM modelos m JOIN marcas ma ON m.id_marca = ma.id WHERE m.id_empresa = $id_empresa_listas ORDER BY ma.nombre, m.nombre");
$areas = $conexion->query("SELECT * FROM areas WHERE id_empresa = $id_empresa_listas ORDER BY nombre");
$cargos = $conexion->query("SELECT c.id, c.nombre, c.estado, a.nombre AS area_nombre FROM cargos c JOIN areas a ON c.id_area = a.id WHERE c.id_empresa = $id_empresa_listas ORDER BY a.nombre, c.nombre");
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2">Gestión de Catálogos</h1>
</div>

<div class="row">
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100"><div class="card-header">Sucursales</div><div class="card-body d-flex flex-column"><form method="POST" class="mb-3"><input type="hidden" name="catalogo_sucursal" value="1"><div class="mb-2"><label class="form-label">Nombre <span class="text-danger">*</span></label><input type="text" name="nombre_sucursal" class="form-control" required></div><div class="mb-2"><label class="form-label">Dirección</label><textarea name="direccion_sucursal" class="form-control" rows="1"></textarea></div><button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-plus"></i> Agregar</button></form><hr><div class="table-responsive flex-grow-1"><table class="table table-sm table-hover"><tbody><?php while ($item = $sucursales->fetch_assoc()): ?><tr><td><strong><?php echo htmlspecialchars($item['nombre']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($item['direccion']); ?></small><span class="badge float-end <?php echo $item['estado'] == 'Activo' ? 'bg-success' : 'bg-danger'; ?>"><?php echo $item['estado']; ?></span></td><td class="text-end align-middle"><div class="btn-group"><a href="catalogo_editar.php?id=<?php echo $item['id']; ?>&type=sucursal" class="btn btn-warning btn-sm" title="Editar"><i class="bi bi-pencil"></i></a><?php if ($item['estado'] == 'Activo'): ?><form method="POST" class="d-inline"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?php echo $item['id']; ?>"><input type="hidden" name="type" value="sucursal"><button type="submit" class="btn btn-danger btn-sm" title="Desactivar"><i class="bi bi-trash"></i></button></form><?php else: ?><form method="POST" class="d-inline"><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?php echo $item['id']; ?>"><input type="hidden" name="type" value="sucursal"><button type="submit" class="btn btn-success btn-sm" title="Activar"><i class="bi bi-check-circle"></i></button></form><?php endif; ?></div></td></tr><?php endwhile; ?></tbody></table></div></div></div>
    </div>
    
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100"><div class="card-header">Áreas</div><div class="card-body d-flex flex-column"><form method="POST" class="mb-3"><input type="hidden" name="catalogo" value="area"><div class="input-group"><input type="text" name="nombre" class="form-control" placeholder="Nueva área... *" required><button class="btn btn-primary" type="submit"><i class="bi bi-plus"></i></button></div></form><hr><div class="table-responsive flex-grow-1"><table class="table table-sm table-hover"><tbody><?php $areas->data_seek(0); while ($item = $areas->fetch_assoc()): ?><tr><td><?php echo htmlspecialchars($item['nombre']); ?><span class="badge float-end <?php echo $item['estado'] == 'Activo' ? 'bg-success' : 'bg-danger'; ?>"><?php echo $item['estado']; ?></span></td><td class="text-end align-middle"><div class="btn-group"><a href="catalogo_editar.php?id=<?php echo $item['id']; ?>&type=area" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i></a><?php if ($item['estado'] == 'Activo'): ?><form method="POST" class="d-inline"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?php echo $item['id']; ?>"><input type="hidden" name="type" value="area"><button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button></form><?php else: ?><form method="POST" class="d-inline"><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?php echo $item['id']; ?>"><input type="hidden" name="type" value="area"><button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-circle"></i></button></form><?php endif; ?></div></td></tr><?php endwhile; ?></tbody></table></div></div></div>
    </div>

    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100"><div class="card-header">Cargos (por Área)</div><div class="card-body d-flex flex-column"><form method="POST" class="mb-3"><input type="hidden" name="catalogo" value="cargo"><div class="mb-2"><select name="id_area" class="form-select" required><option value="">Selecciona un área *</option><?php $areas->data_seek(0); while($area = $areas->fetch_assoc()): ?><option value="<?php echo $area['id']; ?>"><?php echo htmlspecialchars($area['nombre']); ?></option><?php endwhile; ?></select></div><div class="input-group"><input type="text" name="nombre" class="form-control" placeholder="Nuevo cargo... *" required><button class="btn btn-primary" type="submit"><i class="bi bi-plus"></i></button></div></form><hr><div class="table-responsive flex-grow-1"><table class="table table-sm table-hover"><tbody><?php while ($item = $cargos->fetch_assoc()): ?><tr><td><strong><?php echo htmlspecialchars($item['area_nombre']); ?></strong> - <?php echo htmlspecialchars($item['nombre']); ?><span class="badge float-end <?php echo $item['estado'] == 'Activo' ? 'bg-success' : 'bg-danger'; ?>"><?php echo $item['estado']; ?></span></td><td class="text-end align-middle"><div class="btn-group"><a href="catalogo_editar.php?id=<?php echo $item['id']; ?>&type=cargo" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i></a><?php if ($item['estado'] == 'Activo'): ?><form method="POST" class="d-inline"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?php echo $item['id']; ?>"><input type="hidden" name="type" value="cargo"><button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button></form><?php else: ?><form method="POST" class="d-inline"><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?php echo $item['id']; ?>"><input type="hidden" name="type" value="cargo"><button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-circle"></i></button></form><?php endif; ?></div></td></tr><?php endwhile; ?></tbody></table></div></div></div>
    </div>

    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100"><div class="card-header">Tipos de Equipo</div><div class="card-body d-flex flex-column"><form method="POST" class="mb-3"><input type="hidden" name="catalogo" value="tipo"><div class="input-group"><input type="text" name="nombre" class="form-control" placeholder="Nuevo tipo... *" required><button class="btn btn-primary" type="submit"><i class="bi bi-plus"></i></button></div></form><hr><div class="table-responsive flex-grow-1"><table class="table table-sm table-hover"><tbody><?php while($item = $tipos->fetch_assoc()): ?><tr><td><?php echo htmlspecialchars($item['nombre']); ?><span class="badge float-end <?php echo $item['estado'] == 'Activo' ? 'bg-success' : 'bg-danger'; ?>"><?php echo $item['estado']; ?></span></td><td class="text-end align-middle"><div class="btn-group">
            
            <a href="catalogo_editar.php?id=<?php echo $item['id']; ?>&type=tipo_equipo" class="btn btn-warning btn-sm" title="Editar"><i class="bi bi-pencil"></i></a>
            
            <?php if ($item['estado'] == 'Activo'): ?><form method="POST" class="d-inline"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?php echo $item['id']; ?>"><input type="hidden" name="type" value="tipo"><button type="submit" class="btn btn-danger btn-sm" title="Desactivar"><i class="bi bi-trash"></i></button></form><?php else: ?><form method="POST" class="d-inline"><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?php echo $item['id']; ?>"><input type="hidden" name="type" value="tipo"><button type="submit" class="btn btn-success btn-sm" title="Activar"><i class="bi bi-check-circle"></i></button></form><?php endif; ?></div></td></tr><?php endwhile; ?></tbody></table></div></div></div>
    </div>

    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100"><div class="card-header">Marcas</div><div class="card-body d-flex flex-column"><form method="POST" class="mb-3"><input type="hidden" name="catalogo" value="marca"><div class="input-group"><input type="text" name="nombre" class="form-control" placeholder="Nueva marca... *" required><button class="btn btn-primary" type="submit"><i class="bi bi-plus"></i></button></div></form><hr><div class="table-responsive flex-grow-1"><table class="table table-sm table-hover"><tbody><?php $marcas->data_seek(0); while($item = $marcas->fetch_assoc()): ?><tr><td><?php echo htmlspecialchars($item['nombre']); ?><span class="badge float-end <?php echo $item['estado'] == 'Activo' ? 'bg-success' : 'bg-danger'; ?>"><?php echo $item['estado']; ?></span></td><td class="text-end align-middle"><div class="btn-group"><a href="catalogo_editar.php?id=<?php echo $item['id']; ?>&type=marca" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i></a><?php if ($item['estado'] == 'Activo'): ?><form method="POST" class="d-inline"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?php echo $item['id']; ?>"><input type="hidden" name="type" value="marca"><button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button></form><?php else: ?><form method="POST" class="d-inline"><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?php echo $item['id']; ?>"><input type="hidden" name="type" value="marca"><button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-circle"></i></button></form><?php endif; ?></div></td></tr><?php endwhile; ?></tbody></table></div></div></div>
    </div>

    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100"><div class="card-header">Modelos</div><div class="card-body d-flex flex-column"><form method="POST" class="mb-3"><input type="hidden" name="catalogo" value="modelo"><div class="mb-2"><select name="id_marca" class="form-select" required><option value="">Selecciona una marca *</option><?php $marcas->data_seek(0); while($marca = $marcas->fetch_assoc()): ?><option value="<?php echo $marca['id']; ?>"><?php echo htmlspecialchars($marca['nombre']); ?></option><?php endwhile; ?></select></div><div class="input-group"><input type="text" name="nombre" class="form-control" placeholder="Nuevo modelo... *" required><button class="btn btn-primary" type="submit"><i class="bi bi-plus"></i></button></div></form><hr><div class="table-responsive flex-grow-1"><table class="table table-sm table-hover"><tbody><?php while($item = $modelos->fetch_assoc()): ?><tr><td><strong><?php echo htmlspecialchars($item['marca_nombre']); ?></strong> - <?php echo htmlspecialchars($item['nombre']); ?><span class="badge float-end <?php echo $item['estado'] == 'Activo' ? 'bg-success' : 'bg-danger'; ?>"><?php echo $item['estado']; ?></span></td><td class="text-end align-middle"><div class="btn-group"><a href="catalogo_editar.php?id=<?php echo $item['id']; ?>&type=modelo" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i></a><?php if ($item['estado'] == 'Activo'): ?><form method="POST" class="d-inline"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?php echo $item['id']; ?>"><input type="hidden" name="type" value="modelo"><button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button></form><?php else: ?><form method="POST" class="d-inline"><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?php echo $item['id']; ?>"><input type="hidden" name="type" value="modelo"><button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-circle"></i></button></form><?php endif; ?></div></td></tr><?php endwhile; ?></tbody></table></div></div></div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>
