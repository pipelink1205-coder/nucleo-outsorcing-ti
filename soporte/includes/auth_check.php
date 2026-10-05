<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/tenant.php';
try {
    $core = nucleo_db();
    $soporte_ctx = nucleo_contexto($core, $_SESSION);
    $_SESSION['portal_empresa_id'] = $soporte_ctx['empresa'];
    $_SESSION['portal_rol'] = $soporte_ctx['rol'];
    $_SESSION['portal_empresa_nombre'] = $soporte_ctx['empresa_nombre'];
    $_SESSION['user_nombre'] = $soporte_ctx['nombre'];
    $_SESSION['user_email'] = $soporte_ctx['email'];
    $_SESSION['user_sucursal_id'] = $soporte_ctx['sucursal'];
    $_SESSION['id_rol'] = in_array($soporte_ctx['rol'], ['operador', 'administrador'], true) ? 1 : ($soporte_ctx['rol'] === 'auditor' ? 3 : 2);
    $_SESSION['nombre_completo'] = $soporte_ctx['nombre'];
    $ruta = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $permitidas = ['entrar.php', 'index.php', 'crear_ticket.php', 'ver_ticket.php', 'exportar_excel.php', 'exportar_pdf.php', 'imprimir_tickets.php', 'descargar_adjunto.php', 'enlace_empresa.php', 'logout.php'];
    if (!in_array($ruta, $permitidas, true)) { throw new RuntimeException('Gestione identidad y organización desde el núcleo compartido', 403); }
    if ($ruta==='enlace_empresa.php' && !soporte_notas_internas($soporte_ctx)) { throw new RuntimeException('Acceso exclusivo del operador SmartTech',403); }
    if ($ruta === 'crear_ticket.php' && $soporte_ctx['rol'] === 'auditor') { throw new RuntimeException('Auditor: solo lectura', 403); }
    if ($soporte_ctx['rol'] === 'empleado' && in_array($ruta, ['exportar_excel.php','exportar_pdf.php','imprimir_tickets.php'], true)) { throw new RuntimeException('Exportación no autorizada', 403); }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (isset($_POST['es_privado']) && !soporte_notas_internas($soporte_ctx)) { throw new RuntimeException('Notas internas exclusivas de SmartTech',403); }
        if ($soporte_ctx['rol'] === 'auditor') { throw new RuntimeException('Auditor: solo lectura', 403); }
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['soporte_csrf'] ?? '', $_POST['csrf']) || empty($_SESSION['soporte_csrf'])) { throw new RuntimeException('Formulario no válido', 403); }
        if ($soporte_ctx['rol'] === 'empleado' && $ruta === 'ver_ticket.php' && (!isset($_POST['agregar_comentario']) || isset($_POST['es_privado']) || array_intersect(['cambiar_estado', 'asignar_ticket', 'guardar_costo', 'anular_ticket'], array_keys($_POST)))) { throw new RuntimeException('Acción no autorizada', 403); }
    }
    $_SESSION['id_usuario'] = soporte_identidad($pdo, $soporte_ctx);
    $_SESSION['soporte_csrf'] = $_SESSION['soporte_csrf'] ?? bin2hex(random_bytes(32));
    if (in_array($ruta, ['index.php','crear_ticket.php','ver_ticket.php'], true)) {
        ob_start(function ($html) {
            return preg_replace('/(<form\b[^>]*method=["\x27]POST["\x27][^>]*>)/i', '$1<input type="hidden" name="csrf" value="' . $_SESSION['soporte_csrf'] . '">', $html);
        });
    }
} catch (Throwable $e) { nucleo_error($e); }
