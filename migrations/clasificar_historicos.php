<?php
// Clasificación explícita, transaccional entre esquemas en el mismo servidor.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../includes/nucleo.php';
$archivo = $argv[1] ?? '';
if (!$archivo || !is_file($archivo)) { exit("Uso: php migrations/clasificar_historicos.php mapa.json [--aplicar]\nSin --aplicar valida y revierte.\n"); }
$mapa = json_decode(file_get_contents($archivo), true, 512, JSON_THROW_ON_ERROR);
$db = nucleo_db();
$schema = getenv('SUPPORT_DB_NAME') ?: 'soporte_db';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $schema)) { throw new RuntimeException('Esquema no válido'); }
$db->beginTransaction();
try {
    foreach ($mapa['entidades'] ?? [] as $r) {
        $t = $r['tabla'];
        if (!in_array($t, ['sucursales','empleados','equipos','areas','cargos','marcas','modelos','tipos_equipo','asignaciones','reparaciones','bajas','usuarios'], true)) { throw new RuntimeException('Tabla no autorizada'); }
        $q=$db->prepare('SELECT id FROM empresas WHERE id=?'); $q->execute([$r['id_empresa']]);
        if (!$q->fetchColumn()) { throw new RuntimeException('Empresa inexistente'); }
        $q=$db->prepare("SELECT id_empresa FROM `$t` WHERE id=? FOR UPDATE"); $q->execute([$r['id']]); $fila=$q->fetch();
        if (!$fila || ($fila['id_empresa'] && (int)$fila['id_empresa'] !== (int)$r['id_empresa'])) { throw new RuntimeException('Registro inexistente o ya clasificado en otra empresa'); }
        $db->prepare("UPDATE `$t` SET id_empresa=? WHERE id=?")->execute([$r['id_empresa'],$r['id']]);
    }
    // Validar relaciones de inventario: ningún equipo/empleado puede apuntar a otra empresa.
    foreach (['equipos','empleados','usuarios'] as $t) {
        $q=$db->query("SELECT COUNT(*) FROM `$t` e JOIN sucursales s ON s.id=e.id_sucursal WHERE e.id_empresa IS NOT NULL AND (s.id_empresa IS NULL OR e.id_empresa<>s.id_empresa)");
        if ($q->fetchColumn()) { throw new RuntimeException("Relación de sucursal inconsistente en $t"); }
    }
    foreach ($mapa['clientes'] ?? [] as $r) {
        $q=$db->prepare('SELECT id FROM empresas WHERE id=?'); $q->execute([$r['id_empresa']]); if (!$q->fetchColumn()) { throw new RuntimeException('Empresa inexistente'); }
        $q=$db->prepare("SELECT id_empresa_portal FROM `$schema`.clientes WHERE id_cliente=? FOR UPDATE"); $q->execute([$r['id_cliente']]); $fila=$q->fetch();
        if (!$fila || ($fila['id_empresa_portal'] && (int)$fila['id_empresa_portal'] !== (int)$r['id_empresa'])) { throw new RuntimeException('Cliente inexistente o empresa contradictoria'); }
        $db->prepare("UPDATE `$schema`.clientes SET id_empresa_portal=? WHERE id_cliente=?")->execute([$r['id_empresa'],$r['id_cliente']]);
    }
    foreach ($mapa['usuarios_soporte'] ?? [] as $r) {
        $q=$db->prepare('SELECT id FROM usuarios WHERE id=?'); $q->execute([$r['id_usuario_nucleo']]); if (!$q->fetchColumn()) { throw new RuntimeException('Usuario del núcleo inexistente'); }
        $q=$db->prepare("SELECT id_usuario_nucleo FROM `$schema`.usuarios WHERE id_usuario=? FOR UPDATE"); $q->execute([$r['id_usuario']]); $fila=$q->fetch();
        if (!$fila || ($fila['id_usuario_nucleo'] && (int)$fila['id_usuario_nucleo'] !== (int)$r['id_usuario_nucleo'])) { throw new RuntimeException('Identidad contradictoria'); }
        $db->prepare("UPDATE `$schema`.usuarios SET id_usuario_nucleo=? WHERE id_usuario=?")->execute([$r['id_usuario_nucleo'],$r['id_usuario']]);
    }
    foreach ($mapa['tickets'] ?? [] as $r) {
        $ctx=['empresa'=>(int)$r['id_empresa'],'sucursal'=>null];
        nucleo_referencia($db,$ctx,'sucursales',(int)$r['id_sucursal']);
        nucleo_referencia($db,$ctx,'empleados',(int)$r['id_solicitante'],(int)$r['id_sucursal']);
        if (!empty($r['id_equipo'])) { nucleo_referencia($db,$ctx,'equipos',(int)$r['id_equipo'],(int)$r['id_sucursal']); }
        $q=$db->prepare("SELECT t.id_empresa_portal,c.id_empresa_portal AS empresa_cliente FROM `$schema`.tickets t JOIN `$schema`.clientes c ON c.id_cliente=t.id_cliente WHERE t.id_ticket=? FOR UPDATE"); $q->execute([$r['id_ticket']]); $fila=$q->fetch();
        if (!$fila || (int)$fila['empresa_cliente'] !== $ctx['empresa'] || ($fila['id_empresa_portal'] && (int)$fila['id_empresa_portal'] !== $ctx['empresa'])) { throw new RuntimeException('Ticket y cliente no pertenecen a la empresa indicada'); }
        // No inventar el usuario creador histórico: se conserva NULL si se desconoce.
        $db->prepare("UPDATE `$schema`.tickets SET id_empresa_portal=?,id_sucursal=?,id_solicitante=?,id_equipo=? WHERE id_ticket=?")->execute([$ctx['empresa'],$r['id_sucursal'],$r['id_solicitante'],$r['id_equipo'] ?? null,$r['id_ticket']]);
    }
    if (in_array('--aplicar',$argv,true)) { $db->commit(); echo "Clasificación aplicada.\n"; }
    else { $db->rollBack(); echo "Clasificación validada; simulación revertida.\n"; }
} catch (Throwable $e) { $db->rollBack(); fwrite(STDERR, "Clasificación revertida: " . $e->getMessage() . "\n"); exit(1); }
