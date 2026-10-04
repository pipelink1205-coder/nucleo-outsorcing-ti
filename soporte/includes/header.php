<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
$portal_rol = $_SESSION['portal_rol'] ?? '';
$admin_total = ($portal_rol === 'operador') || ($portal_rol === '' && (int) ($_SESSION['id_rol'] ?? 0) === 1);
$puede_crear = $admin_total || in_array($portal_rol, ['administrador','empleado'], true) || ($portal_rol === '' && (int) ($_SESSION['id_rol'] ?? 0) === 1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets | Smart Tech</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/inventario_ti/css/identidad.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --sidebar-gradient: linear-gradient(180deg, #0b3d36 0%, #0a1c1a 55%, #0a0f14 100%);
            --btn-gradient: linear-gradient(135deg, #178f82 0%, #2ec4a0 52%, #5ee69a 100%);
            --btn-hover: linear-gradient(135deg, #126b61 0%, #178f82 100%);
        }

        body {
            overflow-x: hidden;
            background-color: #f8fafb;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        /* --- WRAPPER PRINCIPAL --- */
        /* Este contenedor envuelve sidebar y contenido */
        #wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
            transition: all 0.3s;
        }

        /* --- SIDEBAR (Menú Lateral) --- */
        #sidebar-wrapper {
            min-width: 260px;
            max-width: 260px;
            background: var(--sidebar-gradient);
            color: #fff;
            transition: all 0.3s;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
            min-height: 100vh;
        }

        /* COMPORTAMIENTO RESPONSIVO (CLAVE PARA CELULARES) */
        
        /* En pantallas GRANDES (PC): El menú está visible. Si activamos .toggled, se esconde */
        @media (min-width: 768px) {
            #wrapper.toggled #sidebar-wrapper {
                margin-left: -260px;
            }
        }

        /* En pantallas PEQUEÑAS (Móvil): El menú está oculto (-260px). Si activamos .toggled, se muestra (0px) */
        @media (max-width: 768px) {
            #sidebar-wrapper {
                margin-left: -260px;
                position: fixed; /* Flota sobre el contenido */
                height: 100%;
            }
            #wrapper.toggled #sidebar-wrapper {
                margin-left: 0;
            }
            /* Opcional: Oscurecer el contenido cuando se abre el menú en móvil */
            #wrapper.toggled #page-content-wrapper::before {
                content: "";
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }
        }

        /* --- ESTILOS DEL MENÚ --- */
        #sidebar-wrapper .sidebar-heading {
            padding: 2rem 1.5rem;
            font-size: 1.2rem;
            font-weight: 600;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            line-height: 1.4;
            background: rgba(0,0,0,0.2);
        }

        #sidebar-wrapper .list-group-item {
            background-color: transparent;
            color: rgba(255,255,255,0.8);
            border: none;
            padding: 1rem 1.5rem;
            transition: all 0.2s;
            border-left: 4px solid transparent;
        }

        #sidebar-wrapper .list-group-item:hover {
            background-color: rgba(255,255,255,0.1);
            color: #fff;
            padding-left: 1.8rem;
        }

        #sidebar-wrapper .list-group-item.active {
            background-color: rgba(255,255,255,0.15);
            color: #fff;
            font-weight: 600;
            border-left: 4px solid #5ee69a;
        }

        /* --- CONTENIDO --- */
        #page-content-wrapper {
            width: 100%;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            background: #fff !important;
        }
        
        .container-fluid.main-content {
            padding: 2rem;
        }

        /* --- BOTONES Y TARJETAS --- */
        .btn-primary { background: var(--btn-gradient); border: none; color: #fff; box-shadow: 0 4px 14px rgba(23, 143, 130, .22); }
        .btn-primary:hover { background: var(--btn-hover); color: #fff; transform: translateY(-1px); }
        .card { border: none; border-radius: 16px; box-shadow: 0 8px 24px rgba(11, 61, 54, .06); }
        .card-header:not(.bg-warning):not(.bg-danger):not(.bg-success):not(.bg-primary) {
            background-color: #fff; border-bottom: 1px solid #e7f6f2; font-weight: 700; color: #0b3d36; padding: 1rem;
        }
        @media (max-width: 768px) {
            .container-fluid.main-content { padding: 1rem !important; }
        }
    </style>
</head>
<body>

    <div id="wrapper">

        <div id="sidebar-wrapper">
            <div class="sidebar-heading">
                <a href="/inventario_ti/modulos.php" class="brand-lockup text-white text-decoration-none justify-content-center">
                    <img src="/inventario_ti/img/smarttech-logo.png" alt="Smart Tech Security" width="46" height="46">
                    <span class="text-start">
                        <span class="brand-name">Smart Tech</span>
                        <span class="brand-tag">Tickets</span>
                    </span>
                </a>
                <?php if (!empty($_SESSION['portal_empresa_nombre'])): ?>
                    <div class="small mt-2" style="color:#5ee69a;"><?php echo htmlspecialchars($_SESSION['portal_empresa_nombre']); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="list-group list-group-flush mt-3">
                <a href="/inventario_ti/modulos.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-grid me-2"></i> Módulos
                </a>
                <a href="index.php" class="list-group-item list-group-item-action <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
                
                <?php if ($puede_crear): ?>
                    <a href="crear_ticket.php" class="list-group-item list-group-item-action <?php echo ($current_page == 'crear_ticket.php') ? 'active' : ''; ?>">
                        <i class="bi bi-plus-circle me-2"></i> Crear Ticket
                    </a>
                <?php endif; ?>

                <?php if ($puede_crear && $portal_rol !== 'empleado'): ?>
                    <a href="../../public/gestion_usuarios.php" class="list-group-item list-group-item-action"><i class="bi bi-person-badge-fill me-2"></i> Usuarios compartidos</a>
                    <a href="../../public/gestion_catalogos.php" class="list-group-item list-group-item-action"><i class="bi bi-building me-2"></i> Organización compartida</a>
                <?php endif; ?>            </div>
        </div>
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-white py-3">
                <div class="container-fluid">
                    <button class="btn btn-outline-secondary border-0" id="menu-toggle" aria-label="Abrir menú">
                        <i class="bi bi-list fs-4"></i>
                    </button>
                    <?php if (!empty($_SESSION['portal_empresa_nombre'])): ?>
                        <span class="ms-2 d-none d-md-inline">
                            <span class="empresa-kicker">Empresa activa</span>
                            <strong><?php echo htmlspecialchars($_SESSION['portal_empresa_nombre']); ?></strong>
                        </span>
                    <?php endif; ?>
                    
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="navbar-nav ms-auto mt-2 mt-lg-0 align-items-center">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle fw-bold text-secondary d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                    <div class="bg-primary text-white rounded-circle d-flex justify-content-center align-items-center me-2" style="width: 35px; height: 35px;">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                    <?php echo htmlspecialchars($_SESSION['nombre_completo'] ?? 'Usuario'); ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item py-2" href="../../public/cambiar_password.php"><i class="bi bi-key me-2 text-primary"></i> Cambiar Contraseña</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item py-2" href="/inventario_ti/modulos.php"><i class="bi bi-grid me-2 text-primary"></i> Volver a módulos</a></li>
                                    <li><a class="dropdown-item py-2 text-danger" href="/inventario_ti/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión</a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <div class="container-fluid pt-3 px-3 px-md-4">
                <div class="module-switch" role="navigation" aria-label="Módulos">
                    <a class="module-switch-link" href="/inventario_ti/index.php"><i class="bi bi-laptop"></i> Inventario</a>
                    <a class="module-switch-link active" href="index.php"><i class="bi bi-ticket-perforated"></i> Tickets</a>
                    <?php if ($portal_rol === 'operador'): ?>
                        <a class="module-switch-link" href="/inventario_ti/informes.php"><i class="bi bi-file-earmark-text"></i> Informes</a>
                    <?php endif; ?>
                    <a class="module-switch-link module-switch-all" href="/inventario_ti/modulos.php">Todos</a>
                </div>
                <?php if (!empty($_SESSION['portal_empresa_nombre'])): ?>
                <div class="empresa-bar d-md-none">
                    <div>
                        <span class="empresa-kicker">Empresa activa</span>
                        <strong><?php echo htmlspecialchars($_SESSION['portal_empresa_nombre']); ?></strong>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="container-fluid main-content">
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        var el = document.getElementById("wrapper");
                        var toggleButton = document.getElementById("menu-toggle");

                        toggleButton.onclick = function () {
                            el.classList.toggle("toggled");
                        };
                    });
                </script>