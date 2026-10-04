<?php
require_once __DIR__ . '/../../includes/nucleo.php';

function soporte_scope(array $ctx, string $alias = 't'): string
{
    if (!preg_match('/^[a-z]+$/', $alias)) { throw new InvalidArgumentException('Alias no válido'); }
    $sql = "$alias.id_empresa_portal=" . (int) $ctx['empresa'];
    if ($ctx['sucursal']) { $sql .= " AND $alias.id_sucursal=" . (int) $ctx['sucursal']; }
    if ($ctx['rol'] === 'empleado') { $sql .= " AND $alias.id_solicitante_usuario=" . (int) $ctx['usuario']; }
    return $sql;
}

function soporte_ticket(PDO $db, array $ctx, int $id): array
{
    $stmt = $db->prepare('SELECT t.* FROM tickets t WHERE t.id_ticket=? AND ' . soporte_scope($ctx));
    $stmt->execute([$id]);
    $ticket = $stmt->fetch();
    if (!$ticket) { throw new RuntimeException('Ticket no encontrado', 404); }
    return $ticket;
}

function soporte_identidad(PDO $db, array $ctx): int
{
    // No vincular por email ni por coincidencia de IDs: conservar identidades históricas.
    $stmt = $db->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario_nucleo=?');
    $stmt->execute([$ctx['usuario']]);
    $id = $stmt->fetchColumn();
    if (!$id) {
        $stmt = $db->prepare('INSERT INTO usuarios (id_rol,nombre_completo,email,password_hash,id_usuario_nucleo) VALUES (?,?,?,?,?)');
        $stmt->execute([2, $ctx['nombre'], 'nucleo-' . $ctx['usuario'] . '@identidad.invalid', password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $ctx['usuario']]);
        $id = (int) $db->lastInsertId();
    }
    $stmt = $db->prepare('UPDATE usuarios SET nombre_completo=? WHERE id_usuario=?');
    $stmt->execute([$ctx['nombre'], $id]);
    $stmt = $db->prepare('INSERT IGNORE INTO agentes (id_usuario,puesto) VALUES (?,?)');
    $stmt->execute([$id, 'Identidad del núcleo']);
    return (int) $id;
}

function soporte_validar_vinculos(PDO $core, PDO $db, array $ctx, array $datos): array
{
    $sucursal = (int) ($datos['id_sucursal'] ?? 0);
    nucleo_referencia($core, $ctx, 'sucursales', $sucursal);
    $solicitante = (int) ($datos['id_solicitante'] ?? 0);
    nucleo_referencia($core, $ctx, 'empleados', $solicitante, $sucursal);
    if ($ctx['rol'] === 'empleado' && $ctx['empleado'] !== $solicitante) { throw new RuntimeException('Solicitante no autorizado', 403); }
    $equipo = empty($datos['id_equipo']) ? null : (int) $datos['id_equipo'];
    if ($equipo) { nucleo_referencia($core, $ctx, 'equipos', $equipo, $sucursal); }
    $stmt = $db->prepare('SELECT id_cliente FROM clientes WHERE id_cliente=? AND id_empresa_portal=?');
    $stmt->execute([(int) ($datos['id_cliente'] ?? 0), $ctx['empresa']]);
    if (!$stmt->fetchColumn()) { throw new RuntimeException('Cliente fuera de la empresa', 403); }
    return [$sucursal, $solicitante, $equipo];
}

function soporte_agentes(PDO $core, PDO $db, array $ctx): array
{
    $q = $core->prepare("SELECT DISTINCT u.id FROM usuarios u JOIN usuario_roles ur ON ur.id_usuario=u.id JOIN roles r ON r.id=ur.id_rol WHERE u.activo=1 AND (u.id_empresa=? OR (u.id_empresa IS NULL AND r.nombre_rol='Operador')) AND r.nombre_rol IN ('Operador','Administrador')");
    $q->execute([$ctx['empresa']]);
    $ids = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    return $db->query('SELECT a.id_agente,u.nombre_completo FROM agentes a JOIN usuarios u ON a.id_usuario=u.id_usuario WHERE u.id_usuario_nucleo IN (' . implode(',', $ids ?: [0]) . ') ORDER BY u.nombre_completo')->fetchAll();
}
