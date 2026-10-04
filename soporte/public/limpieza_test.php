<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Solo los administradores pueden ejecutar esto
if ($_SESSION['id_rol'] != 1) {
    die("Acceso denegado.");
}

echo "<h1>Simulación de Limpieza de Datos</h1>";
echo "<p><strong>IMPORTANTE:</strong> Este script solo muestra los datos que se borrarían. No se ha eliminado nada.</p>";

// --- Definir la fecha de corte (tickets más antiguos que esta fecha) ---
$fecha_corte = date('Y-m-d H:i:s', strtotime('-1 year'));
echo "<h2>Buscando tickets cerrados antes del: " . $fecha_corte . "</h2>";

try {
    // Consulta para SELECCIONAR los tickets que cumplen los criterios
    $sql = "SELECT id_ticket, asunto, estado, fecha_creacion 
            FROM Tickets 
            WHERE estado IN ('Resuelto', 'Cerrado', 'Anulado') 
            AND fecha_creacion < ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fecha_corte]);
    $tickets_a_borrar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($tickets_a_borrar)) {
        echo "<p>No se encontraron tickets para limpiar.</p>";
    } else {
        echo "<p>Se encontraron " . count($tickets_a_borrar) . " tickets que serían eliminados:</p>";
        echo "<ul>";
        foreach ($tickets_a_borrar as $ticket) {
            echo "<li>Ticket #" . $ticket['id_ticket'] . ": " . htmlspecialchars($ticket['asunto']) . " (Creado: " . $ticket['fecha_creacion'] . ")</li>";
        }
        echo "</ul>";

        echo "<hr>";
        echo "<h3>Para borrarlos permanentemente:</h3>";
        echo "<p>El comando SQL a ejecutar sería:</p>";
        echo "<pre style='background-color: #f0f0f0; padding: 10px; border-radius: 5px;'>";
        echo "<code>DELETE FROM Tickets WHERE estado IN ('Resuelto', 'Cerrado', 'Anulado') AND fecha_creacion < '{$fecha_corte}';</code>";
        echo "</pre>";
        echo "<p><strong>Recuerda:</strong> Al borrar un ticket, sus comentarios y adjuntos también se eliminarán automáticamente gracias a la configuración de la base de datos (ON DELETE CASCADE).</p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Error durante la simulación: " . $e->getMessage() . "</p>";
}