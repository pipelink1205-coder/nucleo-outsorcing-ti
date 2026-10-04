<?php
require_once __DIR__ . '/../includes/auth_check.php';
$stmt = $pdo->prepare('SELECT id_cliente FROM clientes WHERE id_empresa_portal=? LIMIT 1');
$stmt->execute([$soporte_ctx['empresa']]);
if (!$stmt->fetchColumn()) {
    $stmt = $core->prepare('SELECT nombre FROM empresas WHERE id=?');
    $stmt->execute([$soporte_ctx['empresa']]);
    $nombre = $stmt->fetchColumn();
    $stmt = $pdo->prepare('INSERT INTO clientes (nombre,correo_electronico,empresa,id_empresa_portal) VALUES (?,?,?,?)');
    $stmt->execute([$nombre, 'empresa-' . $soporte_ctx['empresa'] . '@portal.invalid', $nombre, $soporte_ctx['empresa']]);
}
header('Location: index.php');
exit;
