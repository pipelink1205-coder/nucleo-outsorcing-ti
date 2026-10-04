<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once '../vendor/autoload.php';
require_once '../config/database.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Lee todos los posibles filtros de la URL
$filtro_termino = $_GET['termino'] ?? '';
$filtro_agente = $_GET['agente'] ?? '';
$filtro_prioridad = $_GET['prioridad'] ?? '';
$filtro_estado = $_GET['estado_tabla'] ?? '';
$filtro_cliente = $_GET['cliente'] ?? '';
$filtro_facturacion = $_GET['facturacion'] ?? '';

// Construye la consulta dinámica
$where_conditions = [soporte_scope($soporte_ctx)];
$params = [];

if (!empty($filtro_termino)) {
    $where_conditions[] = "(t.asunto LIKE :termino OR t.id_ticket = :id_ticket)";
    $params[':termino'] = '%' . $filtro_termino . '%';
    $params[':id_ticket'] = $filtro_termino;
}
if (!empty($filtro_agente)) {
    $where_conditions[] = "t.id_agente_asignado = :agente";
    $params[':agente'] = $filtro_agente;
}
if (!empty($filtro_prioridad)) {
    $where_conditions[] = "t.prioridad = :prioridad";
    $params[':prioridad'] = $filtro_prioridad;
}
if (!empty($filtro_estado)) {
    $where_conditions[] = "t.estado = :estado_tabla";
    $params[':estado_tabla'] = $filtro_estado;
}
if (!empty($filtro_cliente)) {
    $where_conditions[] = "t.id_cliente = :cliente";
    $params[':cliente'] = $filtro_cliente;
}
if (!empty($filtro_facturacion)) {
    $where_conditions[] = "t.estado_facturacion = :facturacion";
    $params[':facturacion'] = $filtro_facturacion;
}

$sql = "SELECT t.id_ticket, COALESCE(t.solicitante_nombre,c.nombre,'Sin solicitante histórico') AS cliente, t.asunto, tc.nombre_tipo, t.estado, t.prioridad, t.costo, t.moneda, t.estado_facturacion, u.nombre_completo AS agente, t.fecha_creacion FROM Tickets AS t LEFT JOIN Clientes AS c ON t.id_cliente = c.id_cliente LEFT JOIN TiposDeCaso AS tc ON t.id_tipo_caso = tc.id_tipo_caso LEFT JOIN Agentes AS ag ON t.id_agente_asignado = ag.id_agente LEFT JOIN Usuarios AS u ON ag.id_usuario = u.id_usuario";
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(' AND ', $where_conditions);
}
$sql .= " ORDER BY t.id_ticket DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Reporte de Tickets');

$headerStyle = ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '212529']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]];
$sheet->getStyle('A1:K1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(20);

$sheet->getColumnDimension('A')->setAutoSize(true); $sheet->getColumnDimension('B')->setWidth(30); $sheet->getColumnDimension('C')->setWidth(40); $sheet->getColumnDimension('D')->setAutoSize(true);
$sheet->getColumnDimension('E')->setAutoSize(true); $sheet->getColumnDimension('F')->setAutoSize(true); $sheet->getColumnDimension('G')->setAutoSize(true); $sheet->getColumnDimension('H')->setAutoSize(true);
$sheet->getColumnDimension('I')->setWidth(30); $sheet->getColumnDimension('J')->setAutoSize(true); $sheet->getColumnDimension('K')->setAutoSize(true);
$sheet->getStyle('A1:K' . (count($tickets) + 1))->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

$sheet->setCellValue('A1', 'ID Ticket')->setCellValue('B1', 'Cliente')->setCellValue('C1', 'Asunto')->setCellValue('D1', 'Tipo de Caso')->setCellValue('E1', 'Estado')->setCellValue('F1', 'Prioridad')->setCellValue('G1', 'Costo')->setCellValue('H1', 'Facturacion')->setCellValue('I1', 'Agente Asignado')->setCellValue('J1', 'Fecha de Creacion')->setCellValue('K1', 'Moneda');

$row = 2;
foreach ($tickets as $ticket) {
    $sheet->setCellValue('A' . $row, $ticket['id_ticket']); $sheet->setCellValue('B' . $row, $ticket['cliente']); $sheet->setCellValue('C' . $row, $ticket['asunto']);
    $sheet->setCellValue('D' . $row, $ticket['nombre_tipo'] ?? 'N/A'); $sheet->setCellValue('E' . $row, $ticket['estado']); $sheet->setCellValue('F' . $row, $ticket['prioridad']);
    $sheet->setCellValue('G' . $row, (float) $ticket['costo']); $sheet->setCellValue('H' . $row, $ticket['estado_facturacion']); $sheet->setCellValue('I' . $row, $ticket['agente'] ?? 'Sin asignar');
    $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->setCellValue('J' . $row, $ticket['fecha_creacion']); $sheet->setCellValue('K' . $row, $ticket['moneda']);
    $row++;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="reporte_tickets.xlsx"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
