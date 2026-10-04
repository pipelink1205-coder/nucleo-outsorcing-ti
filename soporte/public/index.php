<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

$portalEmpresa = (int) ($_SESSION['portal_empresa_id'] ?? 0);
$portalSql = ' AND ' . str_replace('t.', '', soporte_scope($soporte_ctx));

// Inicializar variables
$total_abiertos = $total_pendientes = $total_resueltos = $total_tickets = 0;
$chart_labels_donut_json = $chart_values_donut_json = '[]';
$chart_labels_bar_json = $chart_values_bar_json = '[]';

// --- CÁLCULOS SOLO PARA ADMIN ---
if ($_SESSION['id_rol'] == 1) {
    $total_abiertos = $pdo->query("SELECT COUNT(*) FROM Tickets WHERE estado = 'Abierto'$portalSql")->fetchColumn();
    $total_pendientes = $pdo->query("SELECT COUNT(*) FROM Tickets WHERE estado IN ('En Progreso', 'En Espera')$portalSql")->fetchColumn();
    $total_resueltos = $pdo->query("SELECT COUNT(*) FROM Tickets WHERE estado IN ('Resuelto', 'Cerrado')$portalSql")->fetchColumn();
    $total_tickets = $pdo->query("SELECT COUNT(*) FROM Tickets WHERE estado != 'Anulado'$portalSql")->fetchColumn(); 

    // Datos Gráfico Donut
    $stmt_chart_donut = $pdo->query("SELECT estado, COUNT(*) as total FROM Tickets WHERE estado != 'Anulado'$portalSql GROUP BY estado ORDER BY estado");
    $chart_data_donut = $stmt_chart_donut->fetchAll(PDO::FETCH_ASSOC);
    $chart_labels_donut = []; $chart_values_donut = [];
    foreach ($chart_data_donut as $data) {
        $chart_labels_donut[] = $data['estado'];
        $chart_values_donut[] = $data['total'];
    }
    $chart_labels_donut_json = json_encode($chart_labels_donut);
    $chart_values_donut_json = json_encode($chart_values_donut);

    // Datos Gráfico Barras
    $meses_es = ["", "Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];
    $chart_labels_bar = [];
    $chart_data_bar_default = [];
    for ($i = 2; $i >= 0; $i--) {
        $date = new DateTime("first day of -$i month");
        $month_key = $date->format('Y-m');
        $month_name = $meses_es[(int)$date->format('n')] . "'" . $date->format('y');
        $chart_labels_bar[] = $month_name;
        $chart_data_bar_default[$month_key] = 0;
    }
    $start_date = (new DateTime("first day of -2 month"))->format('Y-m-d 00:00:00');
    $stmt_bar_chart = $pdo->prepare("SELECT YEAR(fecha_creacion) as anio, MONTH(fecha_creacion) as mes, COUNT(*) as total FROM Tickets WHERE fecha_creacion >= ?$portalSql GROUP BY anio, mes ORDER BY anio, mes");
    $stmt_bar_chart->execute([$start_date]);
    $monthly_data = $stmt_bar_chart->fetchAll(PDO::FETCH_ASSOC);
    foreach ($monthly_data as $data) {
        $month_key = $data['anio'] . '-' . str_pad($data['mes'], 2, '0', STR_PAD_LEFT);
        if (isset($chart_data_bar_default[$month_key])) {
            $chart_data_bar_default[$month_key] = $data['total'];
        }
    }
    $chart_values_bar_json = json_encode(array_values($chart_data_bar_default));
    $chart_labels_bar_json = json_encode($chart_labels_bar);
}

// --- FILTROS Y BÚSQUEDA ---
$filtro_termino = $_GET['termino'] ?? '';
$filtro_cliente = $_GET['cliente'] ?? '';
$filtro_agente = $_GET['agente'] ?? '';
$filtro_prioridad = $_GET['prioridad'] ?? '';
$filtro_estado_tabla = $_GET['estado_tabla'] ?? '';
$filtro_facturacion = $_GET['facturacion'] ?? '';
$filtro_fecha_inicio = $_GET['fecha_inicio'] ?? '';
$filtro_fecha_fin = $_GET['fecha_fin'] ?? '';

$where_conditions = [soporte_scope($soporte_ctx)];
$params = [];

if ($_SESSION['id_rol'] != 1 && $portalEmpresa === 0) {
    $stmt_agente_logueado = $pdo->prepare("SELECT id_agente FROM Agentes WHERE id_usuario = ?");
    $stmt_agente_logueado->execute([$_SESSION['id_usuario']]);
    $id_agente_actual = $stmt_agente_logueado->fetchColumn();
    $where_conditions[] = "t.id_agente_asignado = :id_agente_logueado";
    $params[':id_agente_logueado'] = $id_agente_actual ?: 0;
}

if (!empty($filtro_termino)) { $where_conditions[] = "(t.asunto LIKE :termino OR t.id_ticket = :id_ticket)"; $params[':termino'] = '%' . $filtro_termino . '%'; $params[':id_ticket'] = $filtro_termino; }
if (!empty($filtro_cliente)) { $where_conditions[] = "t.id_cliente = :cliente"; $params[':cliente'] = $filtro_cliente; }
if (!empty($filtro_agente) && $_SESSION['id_rol'] == 1) { $where_conditions[] = "t.id_agente_asignado = :agente"; $params[':agente'] = $filtro_agente; }
if (!empty($filtro_prioridad)) { $where_conditions[] = "t.prioridad = :prioridad"; $params[':prioridad'] = $filtro_prioridad; }
if (!empty($filtro_estado_tabla)) { $where_conditions[] = "t.estado = :estado_tabla"; $params[':estado_tabla'] = $filtro_estado_tabla; }
if (!empty($filtro_facturacion) && $_SESSION['id_rol'] == 1) { $where_conditions[] = "t.estado_facturacion = :facturacion"; $params[':facturacion'] = $filtro_facturacion; }
if (!empty($filtro_fecha_inicio)) { $where_conditions[] = "DATE(t.fecha_creacion) >= :fecha_inicio"; $params[':fecha_inicio'] = $filtro_fecha_inicio; }
if (!empty($filtro_fecha_fin)) { $where_conditions[] = "DATE(t.fecha_creacion) <= :fecha_fin"; $params[':fecha_fin'] = $filtro_fecha_fin; }
if ($portalEmpresa > 0) { $where_conditions[] = "t.id_empresa_portal = :portal_empresa"; $params[':portal_empresa'] = $portalEmpresa; }

$sql_lista = "SELECT t.id_ticket, t.asunto, t.estado, t.prioridad, t.fecha_creacion, c.nombre AS nombre_cliente, u.nombre_completo AS nombre_agente, tc.nombre_tipo, t.fecha_vencimiento, t.costo, t.moneda, t.estado_facturacion FROM Tickets AS t JOIN Clientes AS c ON t.id_cliente = c.id_cliente LEFT JOIN Agentes AS ag ON t.id_agente_asignado = ag.id_agente LEFT JOIN Usuarios AS u ON ag.id_usuario = u.id_usuario LEFT JOIN TiposDeCaso AS tc ON t.id_tipo_caso = tc.id_tipo_caso";
if (!empty($where_conditions)) { $sql_lista .= " WHERE " . implode(' AND ', $where_conditions); }
$sql_lista .= " ORDER BY t.fecha_creacion DESC";
$stmt_lista = $pdo->prepare($sql_lista);
$stmt_lista->execute($params);
$tickets = $stmt_lista->fetchAll();

$status_classes = ['Abierto' => 'primary', 'En Progreso' => 'info', 'En Espera' => 'warning', 'Resuelto' => 'success', 'Cerrado' => 'secondary', 'Anulado' => 'dark'];
$priority_classes = ['Baja' => 'success', 'Media' => 'warning', 'Alta' => 'danger', 'Urgente' => 'danger fw-bold'];
$facturacion_classes = ['Pendiente' => 'warning', 'Facturado' => 'info', 'Pagado' => 'success', 'Anulado' => 'secondary'];
$agentes_disponibles = soporte_agentes($core, $pdo, $soporte_ctx);
if ($portalEmpresa > 0) {
    $stmt_clientes_portal = $pdo->prepare("SELECT id_cliente, nombre FROM Clientes WHERE id_empresa_portal = ? ORDER BY nombre ASC");
    $stmt_clientes_portal->execute([$portalEmpresa]);
    $clientes_disponibles = $stmt_clientes_portal->fetchAll();
} else {
    $clientes_disponibles = $pdo->query("SELECT id_cliente, nombre FROM Clientes ORDER BY nombre ASC")->fetchAll();
}
$mensaje_exito = (isset($_GET['status']) && $_GET['status'] === 'created') ? '<div class="alert alert-success">¡Ticket creado con éxito!</div>' : '';

require_once '../includes/header.php';
?>

<h2 class="mb-4 text-primary fw-bold"><i class="bi bi-speedometer2"></i> Dashboard General</h2>

<?php if ($_SESSION['id_rol'] == 1): ?>
<style>
    /* Estilos específicos para las tarjetas del dashboard */
    .card-stat { border: none; border-radius: 12px; color: white; transition: transform 0.3s; position: relative; overflow: hidden; }
    .card-stat:hover { transform: translateY(-5px); }
    .card-stat .card-body { position: relative; z-index: 2; }
    .card-stat .icon-bg { position: absolute; right: 10px; bottom: 10px; font-size: 5rem; opacity: 0.2; z-index: 1; }
    
    /* Degradados modernos */
    .bg-gradient-blue { background: linear-gradient(135deg, #178f82 0%, #0b3d36 100%); }
    .bg-gradient-orange { background: linear-gradient(135deg, #ff9900 0%, #cc5200 100%); }
    .bg-gradient-green { background: linear-gradient(135deg, #28a745 0%, #145222 100%); }
    .bg-gradient-dark { background: linear-gradient(135deg, #343a40 0%, #1d2124 100%); }
</style>

<div class="row g-4 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card card-stat bg-gradient-blue shadow">
            <div class="card-body">
                <h5 class="card-title text-white-50">Abiertos</h5>
                <h2 class="display-4 fw-bold mb-0"><?php echo $total_abiertos; ?></h2>
                <i class="bi bi-envelope-open-fill icon-bg"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card card-stat bg-gradient-orange shadow">
            <div class="card-body">
                <h5 class="card-title text-white-50">Pendientes</h5>
                <h2 class="display-4 fw-bold mb-0"><?php echo $total_pendientes; ?></h2>
                <i class="bi bi-clock-history icon-bg"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card card-stat bg-gradient-green shadow">
            <div class="card-body">
                <h5 class="card-title text-white-50">Resueltos</h5>
                <h2 class="display-4 fw-bold mb-0"><?php echo $total_resueltos; ?></h2>
                <i class="bi bi-check-circle-fill icon-bg"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card card-stat bg-gradient-dark shadow">
            <div class="card-body">
                <h5 class="card-title text-white-50">Total Activos</h5>
                <h2 class="display-4 fw-bold mb-0"><?php echo $total_tickets; ?></h2>
                <i class="bi bi-bar-chart-fill icon-bg"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card h-100 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="fw-bold text-primary m-0"><i class="bi bi-pie-chart-fill"></i> Resumen por Estado</h6>
            </div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <canvas id="ticketsChartDonut" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="fw-bold text-primary m-0"><i class="bi bi-bar-chart-line-fill"></i> Tickets Creados (Últimos 3 Meses)</h6>
            </div>
            <div class="card-body">
                <canvas id="ticketsChartBar" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4 shadow-sm">
    <div class="card-header bg-white py-3 border-0">
        <a class="text-decoration-none text-dark fw-bold d-block" data-bs-toggle="collapse" href="#collapseFilters" role="button" aria-expanded="true">
            <i class="bi bi-funnel-fill text-primary"></i> Filtros y Reportes <i class="bi bi-chevron-down float-end"></i>
        </a>
    </div>
    <div class="collapse show" id="collapseFilters">
        <div class="card-body bg-light">
            <form id="formFiltros" action="index.php" method="GET">
                <div class="row g-3">
                    <div class="col-lg-4 col-md-6"><label class="form-label small fw-bold text-muted">Buscar:</label><input type="text" name="termino" class="form-control" placeholder="Asunto o ID..." value="<?php echo htmlspecialchars($filtro_termino); ?>"></div>
                    <div class="col-lg-4 col-md-6"><label class="form-label small fw-bold text-muted">Cliente:</label><select name="cliente" class="form-select"><option value="">Todos</option><?php foreach($clientes_disponibles as $c): ?><option value="<?php echo $c['id_cliente']; ?>" <?php if($filtro_cliente==$c['id_cliente']) echo 'selected'; ?>><?php echo htmlspecialchars($c['nombre']); ?></option><?php endforeach; ?></select></div>
                    <?php if ($_SESSION['id_rol'] == 1): ?><div class="col-lg-4 col-md-6"><label class="form-label small fw-bold text-muted">Agente:</label><select name="agente" class="form-select"><option value="">Todos</option><?php foreach($agentes_disponibles as $a): ?><option value="<?php echo $a['id_agente']; ?>" <?php if($filtro_agente==$a['id_agente']) echo 'selected'; ?>><?php echo htmlspecialchars($a['nombre_completo']); ?></option><?php endforeach; ?></select></div><?php endif; ?>
                    <div class="col-lg-3"><label class="form-label small fw-bold text-muted">Prioridad:</label><select name="prioridad" class="form-select"><option value="">Todas</option><option value="Baja" <?php if($filtro_prioridad=='Baja') echo 'selected'; ?>>Baja</option><option value="Media" <?php if($filtro_prioridad=='Media') echo 'selected'; ?>>Media</option><option value="Alta" <?php if($filtro_prioridad=='Alta') echo 'selected'; ?>>Alta</option><option value="Urgente" <?php if($filtro_prioridad=='Urgente') echo 'selected'; ?>>Urgente</option></select></div>
                    <div class="col-lg-3"><label class="form-label small fw-bold text-muted">Estado:</label><select name="estado_tabla" class="form-select"><option value="">Todos</option><option value="Abierto" <?php if($filtro_estado_tabla=='Abierto') echo 'selected'; ?>>Abierto</option><option value="En Progreso" <?php if($filtro_estado_tabla=='En Progreso') echo 'selected'; ?>>En Progreso</option><option value="Resuelto" <?php if($filtro_estado_tabla=='Resuelto') echo 'selected'; ?>>Resuelto</option></select></div>
                    <div class="col-lg-6 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary px-4 me-2"><i class="bi bi-search"></i> Filtrar</button>
                        <a href="index.php" class="btn btn-outline-secondary">Limpiar</a>
                    </div>
                </div>
            </form>
            <?php if ($_SESSION['id_rol'] == 1): ?>
            <hr>
            <div class="btn-group">
                <button type="button" onclick="exportar('excel')" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</button>
                <button type="button" onclick="exportar('pdf')" class="btn btn-danger btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
                <button type="button" onclick="exportar('imprimir')" class="btn btn-secondary btn-sm"><i class="bi bi-printer"></i> Imprimir</button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold text-secondary">Resultados de Búsqueda</h5>
    <?php if ($soporte_ctx['rol'] !== 'auditor'): ?>
    <a href="crear_ticket.php" class="btn btn-primary rounded-pill px-4"><i class="bi bi-plus-lg"></i> Nuevo Ticket</a>
    <?php endif; ?>
</div>

<?php echo $mensaje_exito; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-secondary">
                    <tr>
                        <th class="ps-3">SLA</th>
                        <th>ID</th>
                        <th>Asunto</th>
                        <th>Cliente</th>
                        <th>Agente</th>
                        <th>Estado</th>
                        <th>Prioridad</th>
                        <th>Fecha</th>
                        <?php if ($_SESSION['id_rol'] == 1): ?>
                            <th>Facturación</th>
                        <?php endif; ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted">No se encontraron tickets.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td class="text-center ps-3">
                                    <?php
                                    $sla_icon = ''; $sla_color = '';
                                    if ($ticket['fecha_vencimiento'] && !in_array($ticket['estado'], ['Resuelto', 'Cerrado', 'Anulado'])) {
                                        $ahora = new DateTime(); $vencimiento = new DateTime($ticket['fecha_vencimiento']);
                                        if ($ahora > $vencimiento) { $sla_icon = 'bi-exclamation-circle-fill'; $sla_color = 'text-danger'; } 
                                        elseif ($ahora->diff($vencimiento)->days < 2) { $sla_icon = 'bi-exclamation-triangle-fill'; $sla_color = 'text-warning'; }
                                    }
                                    if ($sla_icon): ?><i class="bi <?php echo $sla_icon . ' ' . $sla_color; ?>" style="font-size: 1.2rem;"></i><?php endif; ?>
                                </td>
                                <td class="fw-bold">#<?php echo htmlspecialchars($ticket['id_ticket']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['asunto']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['nombre_cliente']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['nombre_agente'] ?? '-'); ?></td>
                                <td><span class="badge bg-<?php echo $status_classes[$ticket['estado']] ?? 'secondary'; ?> rounded-pill"><?php echo htmlspecialchars($ticket['estado']); ?></span></td>
                                <td><span class="badge bg-<?php echo $priority_classes[$ticket['prioridad']] ?? 'secondary'; ?>"><?php echo htmlspecialchars($ticket['prioridad']); ?></span></td>
                                <td class="small text-muted"><?php echo date('d/m/Y', strtotime($ticket['fecha_creacion'])); ?></td>
                                <?php if ($_SESSION['id_rol'] == 1): ?>
                                    <td><span class="badge bg-<?php echo $facturacion_classes[$ticket['estado_facturacion']] ?? 'light text-dark'; ?>"><?php echo htmlspecialchars($ticket['estado_facturacion']); ?></span></td>
                                <?php endif; ?>
                                <td class="text-end pe-3"><a href="ver_ticket.php?id=<?php echo $ticket['id_ticket']; ?>" class="btn btn-sm btn-outline-primary rounded-circle"><i class="bi bi-eye"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function exportar(formato) {
    const form = document.getElementById('formFiltros');
    const params = new URLSearchParams(new FormData(form)).toString();
    let url = '';
    if (formato === 'excel') url = `exportar_excel.php?${params}`;
    else if (formato === 'pdf') url = `exportar_pdf.php?${params}`;
    else if (formato === 'imprimir') url = `imprimir_tickets.php?${params}`;
    if (url) formato === 'imprimir' ? window.open(url, '_blank') : window.location.href = url;
}

document.addEventListener("DOMContentLoaded", function() {
    if (document.getElementById('ticketsChartDonut')) {
        const ctxDonut = document.getElementById('ticketsChartDonut').getContext('2d');
        new Chart(ctxDonut, { type: 'doughnut', data: { labels: <?php echo $chart_labels_donut_json; ?>, datasets: [{ data: <?php echo $chart_values_donut_json; ?>, backgroundColor: ['#178f82', '#f59e0b', '#2ec4a0', '#6c757d', '#5ee69a', '#0b4f48'], hoverOffset: 4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' }}}});
    }
    if (document.getElementById('ticketsChartBar')) {
        const ctxBar = document.getElementById('ticketsChartBar').getContext('2d');
        new Chart(ctxBar, { type: 'bar', data: { labels: <?php echo $chart_labels_bar_json; ?>, datasets: [{ label: 'Tickets', data: <?php echo $chart_values_bar_json; ?>, backgroundColor: 'rgba(13, 110, 253, 0.7)', borderRadius: 5 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }}, scales: { y: { beginAtZero: true, grid: { borderDash: [2, 4] } }, x: { grid: { display: false } } } }});
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
