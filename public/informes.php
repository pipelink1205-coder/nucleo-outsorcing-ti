<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
if (!es_operador()) {
    header('Location: modulos.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informes de servicio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="d-flex gap-2 mb-3">
        <?php if (empresa_id_activa() !== null): ?>
            <a href="modulos.php" class="btn btn-outline-secondary btn-sm">Volver a módulos</a>
        <?php endif; ?>
        <a href="plataforma.php" class="btn btn-outline-secondary btn-sm">Volver al núcleo</a>
    </div>
    <h1 class="h3">Informes de servicio</h1>
    <p class="lead">Esta sección es solo para el equipo de outsourcing. El administrador, el auditor y el empleado de cada empresa no la ven.</p>
    <p>Aquí van a quedar los informes que ustedes preparan y presentan: inventario levantado, tickets atendidos, costos y el estado del servicio. El cliente sigue viendo su operación diaria en su propio enlace.</p>
</div>
</body>
</html>
