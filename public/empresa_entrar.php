<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !es_operador()) {
    header('Location: login.php');
    exit();
}

$id = (int) ($_GET['id'] ?? 0);
$stmt = $conexion->prepare("SELECT id, nombre, slug FROM empresas WHERE id = ? AND estado = 'Activa'");
$stmt->bind_param('i', $id);
$stmt->execute();
$empresa = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$empresa) {
    header('Location: plataforma.php');
    exit();
}

$_SESSION['empresa_activa_id'] = (int) $empresa['id'];
$_SESSION['empresa_nombre'] = $empresa['nombre'];
$_SESSION['empresa_slug'] = $empresa['slug'];
header('Location: modulos.php');
exit();
