<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// --- LÓGICA DE FILTROS ---
$filtro_termino = $_GET['termino'] ?? '';
$filtro_telefono = $_GET['telefono'] ?? '';
$filtro_pais = $_GET['pais'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($filtro_termino)) {
    $where_conditions[] = "(nombre LIKE :termino OR empresa LIKE :termino)";
    $params[':termino'] = '%' . $filtro_termino . '%';
}
if (!empty($filtro_telefono)) {
    $where_conditions[] = "telefono LIKE :telefono";
    $params[':telefono'] = '%' . $filtro_telefono . '%';
}
if (!empty($filtro_pais)) {
    $where_conditions[] = "pais LIKE :pais";
    $params[':pais'] = '%' . $filtro_pais . '%';
}
if ($filtro_estado !== '') {
    $where_conditions[] = "activo = :estado";
    $params[':estado'] = $filtro_estado;
}

// Se usan los nombres de columna correctos de tu tabla: correo_electronico y telefono
$sql = "SELECT id_cliente, nombre, empresa, correo_electronico, telefono, pais, ciudad, activo FROM Clientes";
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(' AND ', $where_conditions);
}
$sql .= " ORDER BY nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-people-fill"></i> Gestión de Clientes</h2>
    <a href="crear_cliente.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Añadir Nuevo Cliente</a>
</div>

<div class="card mb-4">
    <div class="card-header fw-bold"><a class="text-decoration-none text-dark" data-bs-toggle="collapse" href="#collapseFilters" role="button" aria-expanded="true"><i class="bi bi-funnel-fill"></i> Filtros y Reportes</a></div>
    <div class="collapse show" id="collapseFilters">
        <div class="card-body">
            <form id="formFiltrosClientes" action="gestionar_clientes.php" method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="termino" class="form-label">Buscar por Nombre o Empresa:</label>
                    <input type="text" id="termino" name="termino" class="form-control" value="<?php echo htmlspecialchars($filtro_termino); ?>">
                </div>
                <div class="col-md-3">
                    <label for="telefono" class="form-label">Teléfono:</label>
                    <input type="text" id="telefono" name="telefono" class="form-control" value="<?php echo htmlspecialchars($filtro_telefono); ?>">
                </div>
                <div class="col-md-3">
                    <label for="pais" class="form-label">País:</label>
                    <input type="text" id="pais" name="pais" class="form-control" value="<?php echo htmlspecialchars($filtro_pais); ?>">
                </div>
                <div class="col-md-2">
                    <label for="estado" class="form-label">Estado:</label>
                    <select id="estado" name="estado" class="form-select">
                        <option value="">Todos</option>
                        <option value="1" <?php if ($filtro_estado === '1') echo 'selected'; ?>>Activo</option>
                        <option value="0" <?php if ($filtro_estado === '0') echo 'selected'; ?>>Inactivo</option>
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary me-2">Filtrar</button>
                    <a href="gestionar_clientes.php" class="btn btn-secondary">Limpiar</a>
                </div>
            </form>
            
            <?php if ($_SESSION['id_rol'] == 1): ?>
            <hr>
            <p class="small text-muted mb-2">La exportación aplicará los filtros de búsqueda actuales.</p>
            <div>
                <button type="button" onclick="exportarClientes('excel')" class="btn btn-success"><i class="bi bi-file-earmark-excel-fill"></i> Excel</button>
                <button type="button" onclick="exportarClientes('pdf')" class="btn btn-danger"><i class="bi bi-file-earmark-pdf-fill"></i> PDF</button>
                <button type="button" onclick="exportarClientes('imprimir')" class="btn btn-info"><i class="bi bi-printer-fill"></i> Imprimir</button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header fw-bold">
        Lista de Clientes (<?php echo count($clientes); ?> encontrados)
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Empresa</th>
                        <th>Correo Electrónico</th>
                        <th>Teléfono</th>
                        <th>País</th>
                        <th>Ciudad</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clientes)): ?>
                        <tr><td colspan="9" class="text-center">No se encontraron clientes con los filtros aplicados.</td></tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cliente['id_cliente']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['empresa'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($cliente['correo_electronico'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($cliente['telefono'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($cliente['pais'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($cliente['ciudad'] ?? 'N/A'); ?></td>
                                <td><span class="badge bg-<?php echo $cliente['activo'] ? 'success' : 'secondary'; ?>"><?php echo $cliente['activo'] ? 'Activo' : 'Inactivo'; ?></span></td>
                                <td><a href="editar_cliente.php?id=<?php echo $cliente['id_cliente']; ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil-fill"></i> Editar</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function exportarClientes(formato) {
    const form = document.getElementById('formFiltrosClientes');
    const params = new URLSearchParams(new FormData(form)).toString();
    let url = '';
    
    if (formato === 'excel') { url = `exportar_clientes_excel.php?${params}`; } 
    else if (formato === 'pdf') { url = `exportar_clientes_pdf.php?${params}`; } 
    else if (formato === 'imprimir') { url = `imprimir_clientes.php?${params}`; }
    
    if (url) {
        formato === 'imprimir' ? window.open(url, '_blank') : window.location.href = url;
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>