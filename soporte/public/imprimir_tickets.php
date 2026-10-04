<?php
require_once '../config/database.php';

// Lee todos los posibles filtros de la URL
$filtro_termino = $_GET['termino'] ?? ''; $filtro_agente = $_GET['agente'] ?? ''; $filtro_prioridad = $_GET['prioridad'] ?? '';
$filtro_estado = $_GET['estado_tabla'] ?? ''; $filtro_cliente = $_GET['cliente'] ?? ''; $filtro_facturacion = $_GET['facturacion'] ?? '';

// Construye la consulta dinámica
$where_conditions = []; $params = [];
if (!empty($filtro_termino)) { $where_conditions[] = "(t.asunto LIKE :termino OR t.id_ticket = :id_ticket)"; $params[':termino'] = '%' . $filtro_termino . '%'; $params[':id_ticket'] = $filtro_termino; }
if (!empty($filtro_agente)) { $where_conditions[] = "t.id_agente_asignado = :agente"; $params[':agente'] = $filtro_agente; }
if (!empty($filtro_prioridad)) { $where_conditions[] = "t.prioridad = :prioridad"; $params[':prioridad'] = $filtro_prioridad; }
if (!empty($filtro_estado)) { $where_conditions[] = "t.estado = :estado_tabla"; $params[':estado_tabla'] = $filtro_estado; }
if (!empty($filtro_cliente)) { $where_conditions[] = "t.id_cliente = :cliente"; $params[':cliente'] = $filtro_cliente; }
if (!empty($filtro_facturacion)) { $where_conditions[] = "t.estado_facturacion = :facturacion"; $params[':facturacion'] = $filtro_facturacion; }

// Consulta SQL con todas las columnas
$sql = "SELECT t.id_ticket, c.nombre AS cliente, t.asunto, tc.nombre_tipo, t.estado, t.prioridad, t.costo, t.moneda, t.estado_facturacion, u.nombre_completo AS agente, t.fecha_creacion FROM Tickets AS t JOIN Clientes AS c ON t.id_cliente = c.id_cliente LEFT JOIN TiposDeCaso AS tc ON t.id_tipo_caso = tc.id_tipo_caso LEFT JOIN Agentes AS ag ON t.id_agente_asignado = ag.id_agente LEFT JOIN Usuarios AS u ON ag.id_usuario = u.id_usuario";
if (!empty($where_conditions)) { $sql .= " WHERE " . implode(' AND ', $where_conditions); }
$sql .= " ORDER BY t.id_ticket DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
$titulo_reporte = "Reporte de Tickets";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($titulo_reporte); ?></title>
    <style>
        body { font-family: Arial, sans-serif; } table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; font-size: 9px; }
        th { background-color: #f2f2f2; } h1 { text-align: center; }
        @media print { h1 { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <h1><?php echo htmlspecialchars($titulo_reporte); ?></h1>
    <table>
        <thead>
            <tr>
                <th>ID</th><th>Cliente</th><th>Asunto</th><th>Tipo de Caso</th><th>Estado</th><th>Prioridad</th><th>Costo</th><th>Facturación</th><th>Agente</th><th>Fecha Creación</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td><?php echo $ticket['id_ticket']; ?></td>
                    <td><?php echo htmlspecialchars($ticket['cliente']); ?></td>
                    <td><?php echo htmlspecialchars($ticket['asunto']); ?></td>
                    <td><?php echo htmlspecialchars($ticket['nombre_tipo'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($ticket['estado']); ?></td>
                    <td><?php echo htmlspecialchars($ticket['prioridad']); ?></td>
                    <td><?php echo number_format($ticket['costo'], 2) . ' ' . htmlspecialchars($ticket['moneda']); ?></td>
                    <td><?php echo htmlspecialchars($ticket['estado_facturacion']); ?></td>
                    <td><?php echo htmlspecialchars($ticket['agente'] ?? 'Sin asignar'); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($ticket['fecha_creacion'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>