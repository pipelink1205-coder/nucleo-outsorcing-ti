<?php
// Configuración de la base de datos
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'inventario_ti');

// Crear la conexión con MySQLi
$conexion = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar la conexión
if ($conexion->connect_error) {
    die("Error de Conexión: " . $conexion->connect_error);
}

// Establecer el charset a UTF-8
$conexion->set_charset("utf8mb4");

require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/inventario_guard.php';
inventario_autorizar();
if (session_status() === PHP_SESSION_ACTIVE) {
    tenant_bloquear_escritura();
}
