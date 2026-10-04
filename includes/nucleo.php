<?php
// Fuente única de identidad y organización para ambos módulos.
require_once __DIR__ . '/rutas.php';
function nucleo_db(): PDO
{
    static $db;
    if (!$db) {
        $db = new PDO('mysql:host=' . (getenv('DB_HOST') ?: 'localhost') . ';dbname=' . (getenv('DB_NAME') ?: 'inventario_ti') . ';charset=utf8mb4', getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    }
    return $db;
}

function nucleo_usuario(PDO $db, array $sesion): array
{
    $stmt = $db->prepare('SELECT u.*, r.nombre_rol FROM usuarios u JOIN usuario_roles ur ON ur.id_usuario=u.id JOIN roles r ON r.id=ur.id_rol WHERE u.id=? AND u.activo=1');
    $stmt->execute([(int) ($sesion['user_id'] ?? 0)]);
    $filas = $stmt->fetchAll();
    if (count($filas) !== 1) {
        throw new RuntimeException('Identidad no válida', 403);
    }
    return $filas[0];
}

function nucleo_contexto(PDO $db, array $sesion): array
{
    $u = nucleo_usuario($db, $sesion);
    if (!empty($u['id_empleado'])) {
        $v = $db->prepare('SELECT id_sucursal FROM empleados WHERE id=? AND id_empresa=?');
        $v->execute([$u['id_empleado'], $u['id_empresa']]);
        $sucursalEmpleado = $v->fetchColumn();
        if (!$sucursalEmpleado || ($u['id_sucursal'] && (int) $u['id_sucursal'] !== (int) $sucursalEmpleado)) { throw new RuntimeException('Vínculo de empleado no válido', 403); }
    }
    $rol = strtolower($u['nombre_rol']);
    $empresa = $rol === 'operador' && !$u['id_empresa'] ? (int) ($sesion['empresa_activa_id'] ?? 0) : (int) $u['id_empresa'];
    $stmt = $db->prepare("SELECT id,nombre,slug FROM empresas WHERE id=? AND estado='Activa'");
    $stmt->execute([$empresa]);
    $empresaFila = $stmt->fetch();
    if (!$empresaFila || !in_array($rol, ['operador', 'administrador', 'auditor', 'empleado'], true)) {
        throw new RuntimeException('Seleccione una empresa autorizada', 403);
    }
    if ($u['id_sucursal']) {
        $stmt = $db->prepare('SELECT id FROM sucursales WHERE id=? AND id_empresa=?');
        $stmt->execute([$u['id_sucursal'], $empresa]);
        if (!$stmt->fetchColumn()) { throw new RuntimeException('Sucursal de usuario fuera de su empresa', 403); }
    }
    return ['usuario' => (int) $u['id'], 'empresa' => $empresa, 'empresa_nombre' => $empresaFila['nombre'], 'empresa_slug' => $empresaFila['slug'], 'sucursal' => $u['id_sucursal'] ? (int) $u['id_sucursal'] : null, 'empleado' => !empty($u['id_empleado']) ? (int) $u['id_empleado'] : null, 'rol' => $rol, 'nombre' => $u['nombre'], 'email' => $u['email']];
}

function nucleo_referencia(PDO $db, array $ctx, string $tabla, int $id, ?int $sucursal = null): array
{
    if (!in_array($tabla, ['sucursales', 'empleados', 'equipos', 'usuarios', 'areas', 'cargos', 'marcas', 'modelos', 'tipos_equipo', 'asignaciones', 'reparaciones', 'bajas'], true)) {
        throw new InvalidArgumentException('Referencia desconocida');
    }
    $stmt = $db->prepare(in_array($tabla, ['asignaciones','reparaciones','bajas'], true)
        ? "SELECT c.*,e.id_sucursal FROM `$tabla` c JOIN equipos e ON e.id=c.id_equipo WHERE c.id=? AND e.id_empresa=?"
        : "SELECT * FROM `$tabla` WHERE id=? AND id_empresa=?");
    $stmt->execute([$id, $ctx['empresa']]);
    $fila = $stmt->fetch();
    if (!$fila || ($sucursal && array_key_exists('id_sucursal', $fila) && (int) $fila['id_sucursal'] !== $sucursal) || ($ctx['sucursal'] && ($tabla === 'sucursales' ? $id !== $ctx['sucursal'] : array_key_exists('id_sucursal', $fila) && (int) $fila['id_sucursal'] !== $ctx['sucursal']))) {
        throw new RuntimeException('Referencia fuera del ámbito autorizado', 404);
    }
    return $fila;
}

function nucleo_error(Throwable $e): void
{
    http_response_code(in_array($e->getCode(), [403, 404, 422], true) ? $e->getCode() : 500);
    exit(http_response_code() === 500 ? 'No se pudo procesar la solicitud.' : htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
