<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once '../vendor/autoload.php';
require_once '../config/database.php';

class PDF extends FPDF {
    protected $widths; function Header() { $this->SetFont('Arial', 'B', 14); $this->Cell(0, 10, 'Reporte de Tickets de Gestion Integral', 0, 1, 'C'); $this->Ln(5); }
    function Footer() { $this->SetY(-15); $this->SetFont('Arial', 'I', 8); $this->Cell(0, 10, 'Pagina ' . $this->PageNo(), 0, 0, 'C'); }
    function SetWidths($w) { $this->widths = $w; }
    function Row($data) {
        $nb = 0; for ($i = 0; $i < count($data); $i++) { $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i])); }
        $h = 5 * $nb; $this->CheckPageBreak($h);
        for ($i = 0; $i < count($data); $i++) {
            $w = $this->widths[$i]; $a = 'L'; $x = $this->GetX(); $y = $this->GetY();
            $this->Rect($x, $y, $w, $h); $this->MultiCell($w, 5, $data[$i], 0, $a); $this->SetXY($x + $w, $y);
        }
        $this->Ln($h);
    }
    function CheckPageBreak($h) { if ($this->GetY() + $h > $this->PageBreakTrigger) { $this->AddPage($this->CurOrientation); }}
    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw']; if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize; $s = str_replace("\r", '', $txt);
        $nb = strlen($s); if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i]; if ($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c == ' ') $sep = $i; $l += $cw[$c]; if ($l > $wmax) {
                if ($sep == -1) { if ($i == $j) $i++; } else { $i = $sep + 1; }
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else { $i++; }
        } return $nl;
    }
}

// Lógica de filtros
$filtro_termino = $_GET['termino'] ?? ''; $filtro_agente = $_GET['agente'] ?? ''; $filtro_prioridad = $_GET['prioridad'] ?? '';
$filtro_estado = $_GET['estado_tabla'] ?? ''; $filtro_cliente = $_GET['cliente'] ?? ''; $filtro_facturacion = $_GET['facturacion'] ?? '';

$where_conditions = [soporte_scope($soporte_ctx)]; $params = [];
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

$pdf = new PDF('L', 'mm', 'A4');
$pdf->AddPage();

// Encabezados de la tabla con todas las columnas
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(230, 230, 230);
$pdf->SetWidths([10, 35, 50, 30, 20, 20, 20, 20, 35, 30]);
$pdf->Row(['ID', 'Cliente', 'Asunto', 'Tipo de Caso', 'Estado', 'Prioridad', 'Costo', 'Facturacion', 'Agente', 'Fecha Creacion']);

// Datos de la tabla
$pdf->SetFont('Arial', '', 7);
foreach ($tickets as $ticket) {
    $pdf->Row([
        $ticket['id_ticket'],
        utf8_decode($ticket['cliente']),
        utf8_decode($ticket['asunto']),
        utf8_decode($ticket['nombre_tipo'] ?? 'N/A'),
        $ticket['estado'],
        $ticket['prioridad'],
        number_format($ticket['costo'], 2, ',', '.') . ' ' . $ticket['moneda'],
        $ticket['estado_facturacion'],
        utf8_decode($ticket['agente'] ?? 'Sin asignar'),
        date('d/m/Y H:i', strtotime($ticket['fecha_creacion']))
    ]);
}

$pdf->Output('D', 'reporte_tickets.pdf');
exit;