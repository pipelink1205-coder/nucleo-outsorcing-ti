<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id']) || empresa_portal_id() === null) {
    header('Location: /inventario_ti/login.php');
    exit();
}

$rol = strtolower($_SESSION['user_rol'] ?? '');
if (in_array($rol, ['operador', 'administrador'], true)) {
    $_SESSION['id_rol'] = 1;
    $_SESSION['id_usuario'] = 2;
} elseif ($rol === 'auditor') {
    $_SESSION['id_rol'] = 3;
    $_SESSION['id_usuario'] = 5;
} else {
    $_SESSION['id_rol'] = 2;
    $_SESSION['id_usuario'] = 3;
}

$_SESSION['nombre_completo'] = $_SESSION['user_nombre'] ?? 'Usuario';
$_SESSION['portal_rol'] = $rol;
$_SESSION['portal_empresa_id'] = empresa_portal_id();
$_SESSION['portal_empresa_nombre'] = $_SESSION['empresa_nombre'] ?? 'Empresa';

require_once __DIR__ . '/../config/database.php';
asegurar_cliente_empresa($pdo, (int) $_SESSION['portal_empresa_id'], $_SESSION['portal_empresa_nombre']);

header('Location: index.php');
exit();

function empresa_portal_id(): ?int
{
    if (!empty($_SESSION['user_empresa_id'])) {
        return (int) $_SESSION['user_empresa_id'];
    }
    if (!empty($_SESSION['empresa_activa_id'])) {
        return (int) $_SESSION['empresa_activa_id'];
    }
    return null;
}

function asegurar_cliente_empresa(PDO $pdo, int $idEmpresa, string $nombre): void
{
    $stmt = $pdo->prepare('SELECT id_cliente FROM clientes WHERE id_empresa_portal = ? LIMIT 1');
    $stmt->execute([$idEmpresa]);
    if ($stmt->fetchColumn()) {
        return;
    }
    $correo = 'empresa' . $idEmpresa . '@portal.local';
    $insert = $pdo->prepare('INSERT INTO clientes (nombre, correo_electronico, empresa, id_empresa_portal) VALUES (?, ?, ?, ?)');
    $insert->execute([$nombre, $correo, $nombre, $idEmpresa]);
}
