<?php
require_once __DIR__ . '/nucleo.php';
function inventario_autorizar(): void
{
    if (PHP_SAPI === 'cli') { return; }
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    $ruta = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($ruta, ['login.php', 'auth.php', 'procesar_login.php', 'logout.php'], true)) { return; }
    try {
        $_SESSION['inventario_csrf'] = $_SESSION['inventario_csrf'] ?? bin2hex(random_bytes(32));
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['inventario_csrf'], $_POST['csrf']))) {
            throw new RuntimeException('Formulario no válido', 403);
        }
        if ($ruta === 'gestion_catalogos.php' && isset($_GET['action'])) { http_response_code(405); exit('Los cambios requieren POST.'); }
        if (!preg_match('/^(obtener_|generar_|descargar_|ver_evidencia|ver_acta|api\.)/', $ruta)) {
            ob_start(function ($html) {
                return preg_replace('/(<form\b[^>]*method=["\x27]POST["\x27][^>]*>)/i', '$1<input type="hidden" name="csrf" value="' . $_SESSION['inventario_csrf'] . '">', $html);
            });
        }
        $core = nucleo_db();
        // La plataforma también revalida el rol; nunca confiar en el rol enviado o antiguo.
        $u = nucleo_usuario($core, $_SESSION);
        $_SESSION['user_rol'] = $u['nombre_rol'];
        $_SESSION['user_empresa_id'] = $u['id_empresa'];
        $_SESSION['user_sucursal_id'] = $u['id_sucursal'];
        $_SESSION['user_nombre'] = $u['nombre'];
        $_SESSION['user_email'] = $u['email'];
        $rol = strtolower($u['nombre_rol']);
        if (in_array($ruta, ['plataforma.php', 'informes.php', 'empresa_entrar.php', 'empresa_salir.php'], true)) {
            if ($rol !== 'operador' || $u['id_empresa']) { throw new RuntimeException('Acceso exclusivo del operador global', 403); }
            return;
        }
        // Las herramientas globales de restauración/reset no pueden ejecutarse desde un portal.
        if (in_array($ruta, ['crear_admin.php','backup.php','generar_backup.php','restaurar_backup.php','reset_system.php','procesar_reset.php'], true)) { throw new RuntimeException('Herramienta global disponible solo por consola', 403); }
        $ctx = nucleo_contexto($core, $_SESSION);
        $GLOBALS['inventario_ctx'] = $ctx;
        $_SESSION['empresa_nombre'] = $ctx['empresa_nombre'];
        $_SESSION['empresa_slug'] = $ctx['empresa_slug'];
        $escritura = ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || isset($_GET['action']);
        if ($escritura && !in_array($rol, ['operador','administrador'], true) && $ruta !== 'cambiar_password.php') { throw new RuntimeException('Rol de solo lectura en inventario', 403); }
        if (in_array($ruta, ['gestion_usuarios.php','usuario_agregar.php','usuario_editar.php'], true) && !in_array($rol, ['operador','administrador'], true)) { throw new RuntimeException('Administración no autorizada', 403); }
        if ($ruta === 'configuracion.php' && $rol !== 'operador') { throw new RuntimeException('Configuración global exclusiva del operador', 403); }
        $referencias = ['id_sucursal'=>'sucursales','id_empleado'=>'empleados','id_equipo'=>'equipos','id_area'=>'areas','id_cargo'=>'cargos','id_marca'=>'marcas','id_modelo'=>'modelos','id_tipo_equipo'=>'tipos_equipo','id_asignacion'=>'asignaciones','id_asig'=>'asignaciones','id_reparacion'=>'reparaciones'];
        foreach ([$_GET, $_POST] as $entrada) {
            foreach ($referencias as $campo=>$tabla) {
                if (!empty($entrada[$campo])) { nucleo_referencia($core, $ctx, $tabla, (int) $entrada[$campo]); }
            }
        }
        if (!empty($_POST['id_sucursal'])) {
            foreach (['id_empleado'=>'empleados','id_equipo'=>'equipos'] as $campo=>$t) {
                if (!empty($_POST[$campo])) { nucleo_referencia($core, $ctx, $t, (int) $_POST[$campo], (int) $_POST['id_sucursal']); }
            }
        }
        foreach (['id_asignacion'=>'asignaciones','id_reparacion'=>'reparaciones'] as $campo=>$t) {
            if (!empty($_POST[$campo]) && !empty($_POST['id_equipo'])) {
                $relacion = nucleo_referencia($core, $ctx, $t, (int) $_POST[$campo]);
                if ((int) $relacion['id_equipo'] !== (int) $_POST['id_equipo']) { throw new RuntimeException('Equipo diferente del registro original', 403); }
            }
        }
        $tabla = null;
        if (strpos($ruta, 'equipo_') === 0) { $tabla='equipos'; }
        elseif (strpos($ruta, 'empleado_') === 0) { $tabla='empleados'; }
        elseif (strpos($ruta, 'usuario_') === 0) { $tabla='usuarios'; }
        elseif (strpos($ruta, 'asignacion_') === 0 || in_array($ruta, ['descargar_acta.php','generar_acta.php','generar_acta_devolucion.php'], true)) { $tabla='asignaciones'; }
        elseif ($ruta === 'reparacion_finalizar.php') { $tabla='reparaciones'; }
        elseif (in_array($ruta, ['generar_acta_baja.php','ver_acta_baja.php','ver_evidencia.php'], true)) { $tabla='bajas'; }
        elseif (in_array($ruta, ['catalogo_editar.php','gestion_catalogos.php'], true)) { $tabla=['sucursal'=>'sucursales','area'=>'areas','cargo'=>'cargos','tipo'=>'tipos_equipo','tipo_equipo'=>'tipos_equipo','marca'=>'marcas','modelo'=>'modelos'][$_GET['type'] ?? $_POST['type'] ?? ''] ?? null; }
        $idRegistro = $_GET['id'] ?? $_POST['id'] ?? null;
        if ($tabla && !empty($idRegistro)) { nucleo_referencia($core, $ctx, $tabla, (int) $idRegistro); }
        if (!empty($_POST['id_rol']) && $rol !== 'operador') {
            $q = $core->prepare('SELECT nombre_rol FROM roles WHERE id=?');
            $q->execute([(int) $_POST['id_rol']]);
            if (!in_array(strtolower((string) $q->fetchColumn()), ['administrador','auditor','empleado'], true)) { throw new RuntimeException('No puede conceder ese rol', 403); }
        }
        if (!empty($_POST['id_empleado']) && in_array($ruta, ['usuario_agregar.php','usuario_editar.php'], true)) {
            nucleo_referencia($core, $ctx, 'empleados', (int) $_POST['id_empleado'], !empty($_POST['id_sucursal']) ? (int) $_POST['id_sucursal'] : null);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $ctx['sucursal'] && in_array($ruta, ['usuario_agregar.php','usuario_editar.php'], true) && (int) ($_POST['id_sucursal'] ?? 0) !== $ctx['sucursal']) {
            throw new RuntimeException('No puede ampliar una cuenta fuera de su sucursal', 403);
        }
        foreach (['id_modelo' => ['modelos','id_marca'], 'id_cargo' => ['cargos','id_area']] as $campo => [$t,$padre]) {
            if (!empty($_POST[$campo]) && !empty($_POST[$padre])) {
                $relacion = nucleo_referencia($core,$ctx,$t,(int) $_POST[$campo]);
                if ((int) $relacion[$padre] !== (int) $_POST[$padre]) { throw new RuntimeException('Catálogos relacionados de forma inconsistente', 422); }
            }
        }
    } catch (Throwable $e) { nucleo_error($e); }
}
