<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once '../vendor/autoload.php';
require_once '../config/database.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// Lógica de filtros (sin cambios)
$filtro_termino = $_GET['termino'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$where_conditions = [];
$params = [];
if (!empty($filtro_termino)) {
    $where_conditions[] = "(nombre LIKE :termino OR correo_electronico LIKE :termino)";
    $params[':termino'] = '%' . $filtro_termino . '%';
}
if ($filtro_estado !== '') {
    $where_conditions[] = "activo = :activo";
    $params[':activo'] = $filtro_estado;
}
$sql = "SELECT * FROM Clientes";
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(' AND ', $where_conditions);
}
$sql .= " ORDER BY id_cliente DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Creación del archivo Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Reporte de Clientes');


// --- APLICACIÓN DE FORMATO ---

// 1. Estilo para los encabezados
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '212529']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];
$sheet->getStyle('A1:J1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(20);

// 2. Ajustar ancho de columnas
$sheet->getColumnDimension('A')->setAutoSize(true);
$sheet->getColumnDimension('B')->setWidth(30);
$sheet->getColumnDimension('C')->setWidth(30);
$sheet->getColumnDimension('D')->setAutoSize(true);
$sheet->getColumnDimension('E')->setWidth(20);
$sheet->getColumnDimension('F')->setAutoSize(true);
$sheet->getColumnDimension('G')->setAutoSize(true);
$sheet->getColumnDimension('H')->setAutoSize(true);
$sheet->getColumnDimension('I')->setAutoSize(true);
$sheet->getColumnDimension('J')->setAutoSize(true);

// 3. Forzar formato de texto para teléfonos para evitar notación científica
$sheet->getStyle('D')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
$sheet->getStyle('H')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

// 4. Centrar verticalmente todas las celdas
$sheet->getStyle('A1:J' . (count($clientes) + 1))->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);


// --- FIN DE APLICACIÓN DE FORMATO ---


// Añadir los encabezados
$sheet->setCellValue('A1', 'ID')->setCellValue('B1', 'Nombre Completo')->setCellValue('C1', 'Email')->setCellValue('D1', 'Teléfono')->setCellValue('E1', 'Empresa')->setCellValue('F1', 'País')->setCellValue('G1', 'Ciudad')->setCellValue('H1', 'WhatsApp')->setCellValue('I1', 'Telegram')->setCellValue('J1', 'Estado');

// Rellenar los datos
$row = 2;
foreach ($clientes as $cliente) {
    $sheet->setCellValue('A' . $row, $cliente['id_cliente']);
    $sheet->setCellValue('B' . $row, $cliente['nombre']);
    $sheet->setCellValue('C' . $row, $cliente['correo_electronico']);
    $sheet->setCellValue('D' . $row, $cliente['telefono']);
    $sheet->setCellValue('E' . $row, $cliente['empresa']);
    $sheet->setCellValue('F' . $row, $cliente['pais']);
    $sheet->setCellValue('G' . $row, $cliente['ciudad']);
    $sheet->setCellValue('H' . $row, $cliente['whatsapp']);
    $sheet->setCellValue('I' . $row, $cliente['telegram']);
    $sheet->setCellValue('J' . $row, $cliente['activo'] ? 'Activo' : 'Inactivo');
    $row++;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="reporte_clientes.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;