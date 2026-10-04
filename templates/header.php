<?php
// Solo iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/modulos.php';

$script_actual = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (empresa_id_activa() === null && !in_array($script_actual, ['plataforma.php', 'informes.php'], true)) {
    if (es_operador()) {
        header('Location: plataforma.php');
        exit();
    }
    http_response_code(403);
    exit('Tu usuario no está asignado a una empresa.');
}

if (!isset($_SESSION['configuracion'])) {
    $_SESSION['configuracion'] = [];
    $sql_config = "SELECT clave, valor FROM configuracion";
    $resultado_config = $conexion->query($sql_config);
    if ($resultado_config) {
        while ($fila = $resultado_config->fetch_assoc()) {
            $_SESSION['configuracion'][$fila['clave']] = $fila['valor'];
        }
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
$rol = rol_actual();
$paginas_empresa = [
    'index.php', 'equipos.php', 'equipo_agregar.php', 'empleados.php', 'empleado_agregar.php',
    'gestion_catalogos.php', 'catalogo_editar.php', 'tickets.php', 'cambiar_password.php', 'logout.php',
    'equipo_editar.php', 'equipo_detalle.php', 'empleado_editar.php',
    'asignaciones.php', 'asignacion_agregar.php', 'asignacion_devolver.php',
    'asignacion_detalle_devolucion.php', 'asignacion_subir_acta.php', 'asignacion_subir_acta_devolucion.php',
    'devoluciones.php', 'reparaciones.php', 'reparacion_finalizar.php', 'bajas.php',
    'equipo_dar_de_baja.php', 'equipo_enviar_reparacion.php'
];
$paginas_operador = [
    'backup.php', 'generar_backup.php', 'restaurar_backup.php', 'reset_system.php', 'procesar_reset.php',
    'configuracion.php', 'gestion_usuarios.php', 'usuario_agregar.php', 'usuario_editar.php'
];
if (!in_array($current_page, $paginas_empresa, true)
    && !(in_array(rol_actual(), ['operador','administrador'], true) && in_array($current_page, ['gestion_usuarios.php','usuario_agregar.php','usuario_editar.php'], true))
    && !(es_operador() && in_array($current_page, $paginas_operador, true))) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Tech | Outsourcing TI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="css/identidad.css?ver=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/style.css?ver=<?php echo time(); ?>">
</head>
<body>

<div id="sidebar-overlay" class="sidebar-overlay"></div>

<header class="mobile-header d-lg-none app-mobile-bar text-white p-3 d-flex align-items-center shadow-sm sticky-top">
    <button class="btn text-white me-3 p-0 border-0" type="button" id="menu-toggle" aria-label="Abrir menú">
        <i class="bi bi-list fs-1"></i>
    </button>
    <span class="brand-lockup">
        <img src="img/smarttech-logo.png" alt="" width="36" height="36">
        <span>
            <span class="brand-name">Smart Tech</span>
            <span class="brand-tag">Outsourcing TI</span>
        </span>
    </span>
</header>

<div class="sidebar d-flex flex-column flex-shrink-0 p-3 text-white" id="sidebar">
    <a href="index.php" class="brand-lockup mb-4 mb-md-0 me-md-auto text-white text-decoration-none">
        <img src="img/smarttech-logo.png" alt="Smart Tech Security" width="46" height="46">
        <span>
            <span class="brand-name">Smart Tech</span>
            <span class="brand-tag">Outsourcing TI</span>
        </span>
    </a>
    <hr>
    
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="index.php" class="nav-link <?php if($current_page == 'index.php') echo 'active'; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="equipos.php" class="nav-link <?php if(in_array($current_page, ['equipos.php', 'equipo_agregar.php', 'equipo_editar.php', 'equipo_detalle.php'])) echo 'active'; ?>">
                <i class="bi bi-laptop"></i> Equipos
            </a>
        </li>
        <li>
            <a href="empleados.php" class="nav-link <?php if(in_array($current_page, ['empleados.php', 'empleado_agregar.php', 'empleado_editar.php'])) echo 'active'; ?>">
                <i class="bi bi-people"></i> Empleados
            </a>
        </li>
        <?php if ($rol !== 'empleado'): ?>
        <li>
            <a href="gestion_catalogos.php" class="nav-link <?php if($current_page == 'gestion_catalogos.php') echo 'active'; ?>">
                <i class="bi bi-tags"></i> Catálogos
            </a>
        </li>
        <?php endif; ?>

        <li>
            <a href="modulos.php" class="nav-link">
                <i class="bi bi-grid"></i> Módulos
            </a>
        </li>
        
        <?php if (in_array($rol, ['operador','administrador'], true)): ?>
        <hr class="my-2 border-white opacity-25">
        <div class="small text-uppercase text-white-50 mb-2 px-3">Administración</div>
        
        <li>
            <a href="gestion_usuarios.php" class="nav-link <?php if(str_starts_with($current_page, 'usuario')) echo 'active'; ?>">
                <i class="bi bi-shield-lock"></i> Usuarios
            </a>
        </li>
        <?php if ($rol === 'operador'): ?>
        <li>
            <a href="configuracion.php" class="nav-link <?php if($current_page == 'configuracion.php') echo 'active'; ?>">
                <i class="bi bi-gear"></i> Configuración
            </a>
        </li>
        <?php endif; ?>
        <?php endif; ?>
    </ul>
    
    <hr>
    
    <div class="dropdown">
        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="rounded-circle bg-white text-primary d-flex justify-content-center align-items-center me-2" style="width: 40px; height: 40px;">
                <i class="bi bi-person-fill fs-5"></i>
            </div>
            <div>
                <strong class="d-block lh-1"><?php echo htmlspecialchars($_SESSION['user_nombre'] ?? 'Usuario'); ?></strong>
                <small class="text-white-50" style="font-size: 0.8rem;"><?php echo htmlspecialchars($_SESSION['user_rol'] ?? 'Rol'); ?></small>
            </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark text-small shadow border-0">
            <li><a class="dropdown-item" href="cambiar_password.php"><i class="bi bi-key me-2"></i> Cambiar Contraseña</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión</a></li>
        </ul>
    </div>
</div>

<main class="main-content">
<?php
$modulo_nav = ($current_page === 'tickets.php') ? 'tickets' : 'inventario';
?>
<div class="module-switch" role="navigation" aria-label="Módulos">
    <?php foreach (modulos_disponibles() as $modulo): ?>
        <?php $activo = ($modulo['id'] === $modulo_nav) ? ' active' : ''; ?>
        <a class="module-switch-link<?php echo $activo; ?>" href="<?php echo htmlspecialchars($modulo['href']); ?>">
            <i class="bi <?php echo htmlspecialchars($modulo['icono']); ?>"></i>
            <?php echo htmlspecialchars($modulo['titulo']); ?>
        </a>
    <?php endforeach; ?>
    <a class="module-switch-link module-switch-all" href="modulos.php">Todos</a>
</div>
<?php if (!empty($_SESSION['empresa_nombre'])): ?>
<div class="empresa-bar">
    <div>
        <span class="empresa-kicker">Empresa activa</span>
        <strong><?php echo htmlspecialchars($_SESSION['empresa_nombre']); ?></strong>
        <span class="empresa-rol"><?php echo htmlspecialchars(etiqueta_rol($rol)); ?></span>
    </div>
    <?php if (es_operador()): ?>
        <a href="empresa_salir.php" class="btn btn-sm btn-outline-primary">Volver a mis empresas</a>
    <?php endif; ?>
</div>
<?php endif; ?>
