<?php
require_once __DIR__ . '/includes/auth_check.php';
// Este es un script de utilidad. Úsalo una vez para crear tu admin/agente
// y luego considera eliminarlo de tu servidor por seguridad.

require_once 'config/database.php';

$message = '';
$message_type = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_completo = $_POST['nombre_completo'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $id_rol = (int)$_POST['id_rol'];
    $puesto = $_POST['puesto'];

    // Validaciones básicas
    if (empty($nombre_completo) || empty($email) || empty($password) || empty($id_rol)) {
        $message = "Error: Todos los campos son obligatorios.";
    } else {
        // Cifrar la contraseña de forma segura
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Usar una transacción para asegurar que ambas inserciones se completen
        $pdo->beginTransaction();
        
        try {
            // 1. Insertar en la tabla Usuarios
            $stmt = $pdo->prepare(
                "INSERT INTO Usuarios (id_rol, nombre_completo, email, password_hash) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$id_rol, $nombre_completo, $email, $password_hash]);
            
            // Obtener el ID del usuario recién creado
            $id_usuario = $pdo->lastInsertId();

            // 2. Insertar en la tabla Agentes, vinculando con el usuario
            $stmt = $pdo->prepare(
                "INSERT INTO Agentes (id_usuario, puesto, fecha_contratacion) VALUES (?, ?, CURDATE())"
            );
            $stmt->execute([$id_usuario, $puesto]);

            // Si todo fue bien, confirma la transacción
            $pdo->commit();
            
            $message = "¡Usuario y Agente creados con éxito! Ya puedes iniciar sesión.";
            $message_type = 'success';

        } catch (Exception $e) {
            // Si algo falla, revierte la transacción
            $pdo->rollBack();
            // Verifica si es un error de duplicado (código 23000)
            if($e->getCode() == 23000){
                 $message = "Error: El correo electrónico ya existe en la base de datos.";
            } else {
                 $message = "Error al crear el usuario: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Usuario Agente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-4">Crear Nuevo Agente</h2>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form action="crear_usuario.php" method="post">
                    <div class="mb-3">
                        <label for="nombre_completo" class="form-label">Nombre Completo:</label>
                        <input type="text" id="nombre_completo" name="nombre_completo" class="form-control" required>
                    </div>
                     <div class="mb-3">
                        <label for="email" class="form-label">Email (para login):</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña:</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="puesto" class="form-label">Puesto:</label>
                        <input type="text" id="puesto" name="puesto" class="form-control" placeholder="Ej: Soporte Nivel 2" required>
                    </div>
                    <div class="mb-3">
                        <label for="id_rol" class="form-label">Rol:</label>
                        <select name="id_rol" id="id_rol" class="form-select" required>
                            <option value="1">Administrador</option>
                            <option value="2" selected>Agente de Soporte</option>
                            <option value="3">Supervisor</option>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success">Crear Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>