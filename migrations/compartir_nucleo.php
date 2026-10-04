<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../includes/nucleo.php';
require_once __DIR__ . '/../soporte/config/database.php';
function agregar_columna(PDO $db, string $tabla, string $columna, string $definicion): void
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
    $stmt->execute([$tabla, $columna]);
    if (!$stmt->fetchColumn()) { $db->exec("ALTER TABLE `$tabla` ADD COLUMN `$columna` $definicion"); }
}
function migrar_compartido(PDO $core, PDO $support): void
{
    agregar_columna($core, 'usuarios', 'id_empleado', 'INT NULL');
    $core->exec('UPDATE empleados e JOIN sucursales s ON s.id=e.id_sucursal SET e.id_empresa=s.id_empresa WHERE e.id_empresa IS NULL AND s.id_empresa IS NOT NULL');
    $core->exec('UPDATE equipos e JOIN sucursales s ON s.id=e.id_sucursal SET e.id_empresa=s.id_empresa WHERE e.id_empresa IS NULL AND s.id_empresa IS NOT NULL');
    foreach (['asignaciones','reparaciones','bajas'] as $t) { $core->exec("UPDATE `$t` c JOIN equipos e ON e.id=c.id_equipo SET c.id_empresa=e.id_empresa WHERE c.id_empresa IS NULL AND e.id_empresa IS NOT NULL"); }
    if (!$core->query("SHOW INDEX FROM empleados WHERE Key_name='uk_empleado_dni_empresa'")->fetch()) { $core->exec('ALTER TABLE empleados ADD UNIQUE INDEX uk_empleado_dni_empresa (id_empresa,dni)'); }
    if ($core->query("SHOW INDEX FROM empleados WHERE Key_name='dni'")->fetch()) { $core->exec('ALTER TABLE empleados DROP INDEX dni'); }
    agregar_columna($support, 'usuarios', 'id_usuario_nucleo', 'INT NULL UNIQUE');
    agregar_columna($support, 'clientes', 'id_empresa_portal', 'INT NULL');
    foreach (['id_empresa_portal', 'id_sucursal', 'id_solicitante', 'id_solicitante_usuario', 'id_equipo'] as $c) { agregar_columna($support, 'tickets', $c, 'INT NULL'); }
    agregar_columna($support, 'comentarios', 'id_usuario_nucleo', 'INT NULL');
    $support->exec('UPDATE tickets t JOIN clientes c ON c.id_cliente=t.id_cliente SET t.id_empresa_portal=c.id_empresa_portal WHERE t.id_empresa_portal IS NULL AND c.id_empresa_portal IS NOT NULL');
    $stmt = $support->query("SHOW INDEX FROM tickets WHERE Key_name='idx_ticket_ambito'");
    if (!$stmt->fetch()) { $support->exec('ALTER TABLE tickets ADD INDEX idx_ticket_ambito (id_empresa_portal,id_sucursal,id_solicitante_usuario)'); }
    $schema = $core->query('SELECT DATABASE()')->fetchColumn();
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $schema)) { throw new RuntimeException('Nombre de base no válido'); }
    foreach (['sucursales' => 'id,id_empresa', 'empleados' => 'id,id_sucursal,id_empresa', 'equipos' => 'id,id_sucursal,id_empresa'] as $tabla => $columnas) {
        if (!$core->query("SHOW INDEX FROM `$tabla` WHERE Key_name='uk_nucleo_referencia'")->fetch()) {
            $core->exec("ALTER TABLE `$tabla` ADD UNIQUE INDEX uk_nucleo_referencia ($columnas)");
        }
    }
    if (!$support->query("SHOW INDEX FROM clientes WHERE Key_name='uk_cliente_empresa'")->fetch()) { $support->exec('ALTER TABLE clientes ADD UNIQUE INDEX uk_cliente_empresa (id_cliente,id_empresa_portal)'); }
    $fks = [
        'fk_ticket_empresa_nucleo' => "FOREIGN KEY (id_empresa_portal) REFERENCES `$schema`.empresas(id)",
        'fk_ticket_sucursal_nucleo' => "FOREIGN KEY (id_sucursal,id_empresa_portal) REFERENCES `$schema`.sucursales(id,id_empresa)",
        'fk_ticket_solicitante_nucleo' => "FOREIGN KEY (id_solicitante,id_sucursal,id_empresa_portal) REFERENCES `$schema`.empleados(id,id_sucursal,id_empresa)",
        'fk_ticket_equipo_nucleo' => "FOREIGN KEY (id_equipo,id_sucursal,id_empresa_portal) REFERENCES `$schema`.equipos(id,id_sucursal,id_empresa)",
        'fk_ticket_creador_nucleo' => "FOREIGN KEY (id_solicitante_usuario) REFERENCES `$schema`.usuarios(id)",
        'fk_ticket_cliente_empresa' => 'FOREIGN KEY (id_cliente,id_empresa_portal) REFERENCES clientes(id_cliente,id_empresa_portal)',
    ];
    foreach ($fks as $nombre => $definicion) {
        $q = $support->prepare('SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name=\'tickets\' AND constraint_name=?');
        $q->execute([$nombre]);
        if (!$q->fetchColumn()) { $support->exec("ALTER TABLE tickets ADD CONSTRAINT `$nombre` $definicion"); }
    }
    echo 'Tickets pendientes de clasificación: ' . $support->query('SELECT COUNT(*) FROM tickets WHERE id_empresa_portal IS NULL OR id_sucursal IS NULL OR id_solicitante IS NULL')->fetchColumn() . PHP_EOL;
}
if (realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) { migrar_compartido(nucleo_db(), $pdo); }
