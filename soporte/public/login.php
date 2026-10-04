<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    if ($email && $password) {
        // Buscar usuario
        $stmt = $pdo->prepare("SELECT * FROM Usuarios WHERE email = ? AND activo = 1");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            // Guardar datos en sesión
            $_SESSION['id_usuario'] = $usuario['id_usuario'];
            $_SESSION['id_rol'] = $usuario['id_rol'];
            $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
            
            // Redirigir al dashboard
            header('Location: index.php');
            exit();
        } else {
            $error = "Credenciales incorrectas o usuario inactivo.";
        }
    } else {
        $error = "Por favor complete todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body, html {
            height: 100%;
            margin: 0;
            overflow: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            height: 100vh;
            display: flex;
        }

        /* --- LADO IZQUIERDO (FONDO GEOMÉTRICO) --- */
        .left-side {
            flex: 1.2;
            background: linear-gradient(135deg, #004e92 0%, #000428 100%);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            z-index: 1;
        }

        /* Crear efecto poligonal con CSS */
        .left-side::before, .left-side::after {
            content: "";
            position: absolute;
            width: 150%;
            height: 150%;
            background: rgba(255, 255, 255, 0.05);
            z-index: -1;
            transform: rotate(45deg);
        }
        .left-side::before { top: -50%; left: -50%; }
        .left-side::after { bottom: -50%; right: -50%; }

        .brand-text {
            font-size: 3rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.3);
            margin-bottom: 1rem;
        }
        
        .brand-subtext {
            font-size: 1.2rem;
            opacity: 0.8;
            font-weight: 300;
        }

        /* --- LADO DERECHO (FORMULARIO) --- */
        .right-side {
            flex: 0.8;
            background: #0d1b2a; /* Azul oscuro profundo */
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            box-shadow: -10px 0 20px rgba(0,0,0,0.5);
        }

        /* Línea separadora vertical sutil */
        .right-side::before {
            content: "";
            position: absolute;
            left: 0;
            top: 10%;
            bottom: 10%;
            width: 1px;
            background: linear-gradient(to bottom, transparent, rgba(255,255,255,0.2), transparent);
        }

        .login-form-box {
            width: 100%;
            max-width: 400px;
            padding: 40px;
            color: #fff;
        }

        .icon-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .icon-header i {
            font-size: 4rem;
            color: #fff;
            opacity: 0.9;
        }

        /* Estilos de los Inputs */
        .form-control {
            background-color: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 0 5px 5px 0;
            color: #fff;
            padding: 12px;
        }
        .form-control:focus {
            background-color: rgba(255, 255, 255, 0.2);
            color: #fff;
            box-shadow: none;
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .input-group-text {
            background-color: rgba(255, 255, 255, 0.2);
            border: none;
            color: #fff;
            border-radius: 5px 0 0 5px;
            width: 50px;
            justify-content: center;
        }

        .btn-login {
            background: linear-gradient(to right, #0062cc, #004e92);
            border: none;
            color: white;
            font-weight: bold;
            padding: 12px;
            border-radius: 25px;
            width: 100%;
            margin-top: 20px;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-login:hover {
            background: linear-gradient(to right, #004e92, #003366);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 78, 146, 0.4);
        }

        .links-bottom {
            margin-top: 20px;
            /* Modificado: Solo queda el checkbox, lo alineamos a la izquierda */
            display: flex;
            justify-content: flex-start; 
            font-size: 0.9rem;
        }
        .links-bottom label {
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
        }
        .links-bottom label:hover {
            color: #fff;
        }

        /* Ajustes para móviles */
        @media (max-width: 768px) {
            .login-container { flex-direction: column; }
            .left-side { flex: 0.3; padding: 20px; text-align: center; }
            .brand-text { font-size: 2rem; }
            .brand-subtext { display: none; }
            .right-side { flex: 1; }
            .right-side::before { display: none; }
        }
    </style>
</head>
<body>

<div class="login-container">
    
    <div class="left-side">
        <div class="text-center">
            <i class="bi bi-exclude mb-3" style="font-size: 5rem; opacity: 0.5;"></i>
            <div class="brand-text">Sistema<br>Integral</div>
            <div class="brand-subtext">Gestión de Tickets y Soporte</div>
        </div>
    </div>

    <div class="right-side">
        <div class="login-form-box">
            
            <div class="icon-header">
                <i class="bi bi-people"></i>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger text-center py-2" role="alert" style="font-size: 0.9rem;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                
                <div class="mb-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="email" class="form-control" name="email" placeholder="Correo Electrónico / Usuario" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" name="password" placeholder="Contraseña" required>
                    </div>
                </div>

                <div class="links-bottom">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="rememberMe">
                        <label class="form-check-label" for="rememberMe">Recordarme</label>
                    </div>
                    </div>

                <button type="submit" class="btn btn-login">
                    INGRESAR
                </button>

            </form>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>