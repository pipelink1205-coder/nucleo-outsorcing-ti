<?php
require_once '../includes/auth_check.php';
if ($_SESSION['id_rol'] != 1) { header('Location: index.php'); exit(); }
require_once '../config/database.php';

$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_completo = $_POST['nombre_completo'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $id_rol = (int)$_POST['id_rol'];
    $puesto = $_POST['puesto'];
    $telefono = $_POST['telefono'];
    $whatsapp = $_POST['whatsapp'];
    $telegram = $_POST['telegram'];
    $ruta_foto = null;

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/avatars/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
        $file_name = uniqid() . '_' . basename($_FILES['foto']['name']);
        $target_path = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_path)) {
            $ruta_foto = $target_path;
        }
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO Usuarios (id_rol, nombre_completo, email, password_hash, telefono, whatsapp, telegram, ruta_foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_rol, $nombre_completo, $email, $password_hash, $telefono, $whatsapp, $telegram, $ruta_foto]);
        $id_usuario = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO Agentes (id_usuario, puesto, fecha_contratacion) VALUES (?, ?, CURDATE())");
        $stmt->execute([$id_usuario, $puesto]);
        $pdo->commit();
        header('Location: gestionar_usuarios.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Error al crear el usuario: " . $e->getMessage();
    }
}
$roles = $pdo->query("SELECT * FROM Roles")->fetchAll();
require_once '../includes/header.php';
?>

<h2 class="mb-4">Crear Nuevo Usuario</h2>
<div class="card">
    <div class="card-body">
        <?php if ($error_msg): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>
        <form action="crear_usuario_admin.php" method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nombre_completo" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email (para login) <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                 <div class="col-md-6 mb-3">
                    <label for="id_rol" class="form-label">Rol <span class="text-danger">*</span></label>
                    <select id="id_rol" name="id_rol" class="form-select" required>
                        <?php foreach ($roles as $rol): ?><option value="<?php echo $rol['id_rol']; ?>"><?php echo htmlspecialchars($rol['nombre_rol']); ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
             <div class="mb-3">
                 <label for="puesto" class="form-label">Puesto del Agente <span class="text-danger">*</span></label>
                 <input type="text" class="form-control" id="puesto" name="puesto" placeholder="Ej: Soporte Nivel 1" required>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-4 mb-3"><label for="telefono" class="form-label">Teléfono</label><input type="text" class="form-control" id="telefono" name="telefono"></div>
                <div class="col-md-4 mb-3"><label for="whatsapp" class="form-label">WhatsApp</label><input type="text" class="form-control" id="whatsapp" name="whatsapp"></div>
                <div class="col-md-4 mb-3"><label for="telegram" class="form-label">Telegram</label><input type="text" class="form-control" id="telegram" name="telegram" placeholder="@usuario"></div>
            </div>
            <div class="mb-3">
                <label for="foto" class="form-label">Foto de Perfil</label>
                <input class="form-control" type="file" id="foto" name="foto">
            </div>
            <button type="submit" class="btn btn-primary">Crear Usuario</button>
            <a href="gestionar_usuarios.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>