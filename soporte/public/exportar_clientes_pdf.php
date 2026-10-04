<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once '../vendor/autoload.php';
require_once '../config/database.php';

class PDF extends FPDF {
    function Header() { $this->SetFont('Arial', 'B', 12); $this->Cell(0, 10, 'Reporte de Clientes', 0, 1, 'C'); $this->Ln(5); }
    function Footer() { $this->SetY(-15); $this->SetFont('Arial', 'I', 8); $this->Cell(0, 10, 'Pagina ' . $this->PageNo(), 0, 0, 'C'); }
}

// Lógica de filtros
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
$sql .= " ORDER BY nombre ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Creación del PDF
$pdf = new PDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(35, 7, 'Nombre', 1);
$pdf->Cell(50, 7, 'Email', 1);
$pdf->Cell(25, 7, 'Telefono', 1);
$pdf->Cell(40, 7, 'Empresa', 1);
$pdf->Cell(30, 7, 'Pais', 1);
$pdf->Cell(30, 7, 'Ciudad', 1);
$pdf->Cell(25, 7, 'WhatsApp', 1);
$pdf->Cell(20, 7, 'Estado', 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 7);
foreach ($clientes as $cliente) {
    $pdf->Cell(35, 7, utf8_decode($cliente['nombre']), 1);
    $pdf->Cell(50, 7, utf8_decode($cliente['correo_electronico']), 1);
    $pdf->Cell(25, 7, $cliente['telefono'], 1);
    $pdf->Cell(40, 7, utf8_decode($cliente['empresa']), 1);
    $pdf->Cell(30, 7, utf8_decode($cliente['pais']), 1);
    $pdf->Cell(30, 7, utf8_decode($cliente['ciudad']), 1);
    $pdf->Cell(25, 7, $cliente['whatsapp'], 1);
    $pdf->Cell(20, 7, $cliente['activo'] ? 'Activo' : 'Inactivo', 1);
    $pdf->Ln();
}
$pdf->Output('D', 'reporte_clientes.pdf');
exit;