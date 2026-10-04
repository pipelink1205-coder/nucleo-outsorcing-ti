<?php
// 1. INICIO DE SESIÓN Y LÓGICA (ANTES DE CUALQUIER HTML)
session_start();
require_once __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Por favor, complete todos los campos.";
    } else {
        // Consultar usuario
        $stmt = $conexion->prepare("SELECT id, nombre, password, id_sucursal, id_empresa FROM usuarios WHERE email = ? AND activo = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($user = $resultado->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                // Obtener nombre del rol
                $stmt_rol = $conexion->prepare("SELECT r.nombre_rol FROM roles r JOIN usuario_roles ur ON r.id = ur.id_rol WHERE ur.id_usuario = ?");
                $stmt_rol->bind_param("i", $user['id']);
                $stmt_rol->execute();
                $res_rol = $stmt_rol->get_result();
                $rol = ($row_rol = $res_rol->fetch_assoc()) ? $row_rol['nombre_rol'] : 'Usuario';

                $slug_portal = trim($_GET['empresa'] ?? '');
                $empresa_portal = null;
                if ($slug_portal !== '') {
                    $stmt_portal = $conexion->prepare("SELECT id, nombre, slug FROM empresas WHERE slug = ? AND estado = 'Activa'");
                    $stmt_portal->bind_param("s", $slug_portal);
                    $stmt_portal->execute();
                    $empresa_portal = $stmt_portal->get_result()->fetch_assoc();
                    $stmt_portal->close();
                    if (!$empresa_portal) {
                        $error = "El enlace de esta empresa no es válido.";
                        $stmt->close();
                        goto render_login;
                    }
                }

                if (strtolower($rol) !== 'operador' && $empresa_portal && (int) $user['id_empresa'] !== (int) $empresa_portal['id']) {
                    $error = "Esta cuenta no pertenece a esta empresa.";
                    $stmt->close();
                    goto render_login;
                }

                // Guardar en sesión
                session_regenerate_id(true);
                unset($_SESSION['id_usuario'], $_SESSION['id_rol'], $_SESSION['portal_empresa_id'], $_SESSION['portal_rol'], $_SESSION['soporte_csrf']);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nombre'] = $user['nombre'];
                $_SESSION['user_email'] = $email;
                $_SESSION['user_rol'] = $rol;
                $_SESSION['user_sucursal_id'] = $user['id_sucursal'];
                $_SESSION['user_empresa_id'] = $user['id_empresa'];
                unset($_SESSION['empresa_activa_id'], $_SESSION['empresa_nombre'], $_SESSION['empresa_slug']);

                if (!empty($user['id_empresa'])) {
                    $stmt_emp = $conexion->prepare("SELECT nombre, slug FROM empresas WHERE id = ?");
                    $stmt_emp->bind_param("i", $user['id_empresa']);
                    $stmt_emp->execute();
                    if ($emp = $stmt_emp->get_result()->fetch_assoc()) {
                        $_SESSION['empresa_nombre'] = $emp['nombre'];
                        $_SESSION['empresa_slug'] = $emp['slug'];
                    }
                    $stmt_emp->close();
                } elseif ($empresa_portal && strtolower($rol) === 'operador') {
                    $_SESSION['empresa_activa_id'] = (int) $empresa_portal['id'];
                    $_SESSION['empresa_nombre'] = $empresa_portal['nombre'];
                    $_SESSION['empresa_slug'] = $empresa_portal['slug'];
                }
                
                // Cargar config global
                $sql_config = "SELECT clave, valor FROM configuracion";
                $res_config = $conexion->query($sql_config);
                if($res_config) {
                    $_SESSION['configuracion'] = [];
                    while($row = $res_config->fetch_assoc()) {
                        $_SESSION['configuracion'][$row['clave']] = $row['valor'];
                    }
                }

                $destino = (strtolower($rol) === 'operador' && empty($_SESSION['empresa_activa_id']))
                    ? 'plataforma.php'
                    : 'modulos.php';
                header("Location: " . $destino);
                exit();
            } else {
                $error = "La contraseña es incorrecta.";
            }
        } else {
            $error = "El usuario no existe o está inactivo.";
        }
        $stmt->close();
    }
}

render_login:
$portal_nombre = '';
$slug_portal = trim($_GET['empresa'] ?? '');
if ($slug_portal !== '') {
    $stmt_nombre = $conexion->prepare("SELECT nombre FROM empresas WHERE slug = ? AND estado = 'Activa'");
    $stmt_nombre->bind_param("s", $slug_portal);
    $stmt_nombre->execute();
    $fila_portal = $stmt_nombre->get_result()->fetch_assoc();
    $stmt_nombre->close();
    if ($fila_portal) {
        $portal_nombre = $fila_portal['nombre'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | Smart Tech</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/identidad.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
</head>
<body class="login-page">

    <div class="login-layout">
        
        <div class="login-left">
            <img class="login-mark" src="img/smarttech-logo.png" alt="Smart Tech Security">
            <h1 class="login-welcome-title">Smart Tech</h1>
            <p class="login-welcome-text">
                Un mundo de soluciones tecnológicas.<br>
                <?php if ($portal_nombre !== ''): ?>
                    Portal de <?php echo htmlspecialchars($portal_nombre); ?>.
                <?php else: ?>
                    Outsourcing de tecnología e inventario.
                <?php endif; ?>
            </p>
        </div>

        <div class="login-right">
            <div class="login-form-container">
                <h2 class="login-title">Iniciar Sesión</h2>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-4 text-small" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    
                    <div class="input-group-custom">
                        <input type="email" class="form-control-custom" id="email" name="email" placeholder="admin@correo.com" required autofocus>
                    </div>

                    <div class="input-group-custom">
                        <input type="password" class="form-control-custom" id="password" name="password" placeholder="Contraseña" required>
                    </div>

                    <button type="submit" class="btn-login-blue">
                        INGRESAR
                    </button>

                </form>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
