<?php
require_once '../includes/auth_check.php';
if ($_SESSION['id_rol'] != 1) { header('Location: index.php'); exit(); }
require_once '../config/database.php';

$id_usuario = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_usuario) { header('Location: gestionar_usuarios.php'); exit(); }

$stmt = $pdo->prepare("SELECT * FROM Usuarios WHERE id_usuario = ?");
$stmt->execute([$id_usuario]);
$usuario = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_completo = $_POST['nombre_completo'];
    $email = $_POST['email'];
    $id_rol = $_POST['id_rol'];
    $activo = isset($_POST['activo']) ? 1 : 0;
    $telefono = $_POST['telefono'];
    $whatsapp = $_POST['whatsapp'];
    $telegram = $_POST['telegram'];
    $ruta_foto = $usuario['ruta_foto'];

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        if (!empty($ruta_foto) && file_exists($ruta_foto)) { unlink($ruta_foto); }
        $upload_dir = 'uploads/avatars/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
        $file_name = uniqid() . '_' . basename($_FILES['foto']['name']);
        $target_path = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_path)) {
            $ruta_foto = $target_path;
        }
    }
    
    $stmt = $pdo->prepare("UPDATE Usuarios SET nombre_completo = ?, email = ?, id_rol = ?, activo = ?, telefono = ?, whatsapp = ?, telegram = ?, ruta_foto = ? WHERE id_usuario = ?");
    $stmt->execute([$nombre_completo, $email, $id_rol, $activo, $telefono, $whatsapp, $telegram, $ruta_foto, $id_usuario]);
    
    header('Location: gestionar_usuarios.php');
    exit();
}

$roles = $pdo->query("SELECT * FROM Roles")->fetchAll();
require_once '../includes/header.php';
?>

<h2 class="mb-4">Editar Usuario</h2>
<div class="card">
    <div class="card-body">
        <form action="editar_usuario.php?id=<?php echo $id_usuario; ?>" method="POST" enctype="multipart/form-data">
            <div class="row">
                 <div class="col-md-6 mb-3"><label for="nombre_completo" class="form-label">Nombre Completo <span class="text-danger">*</span></label><input type="text" class="form-control" id="nombre_completo" name="nombre_completo" value="<?php echo htmlspecialchars($usuario['nombre_completo']); ?>" required></div>
                <div class="col-md-6 mb-3"><label for="email" class="form-label">Email <span class="text-danger">*</span></label><input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required></div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-4 mb-3"><label for="telefono" class="form-label">Teléfono</label><input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($usuario['telefono']); ?>"></div>
                <div class="col-md-4 mb-3"><label for="whatsapp" class="form-label">WhatsApp</label><input type="text" class="form-control" id="whatsapp" name="whatsapp" value="<?php echo htmlspecialchars($usuario['whatsapp']); ?>"></div>
                <div class="col-md-4 mb-3"><label for="telegram" class="form-label">Telegram</label><input type="text" class="form-control" id="telegram" name="telegram" value="<?php echo htmlspecialchars($usuario['telegram']); ?>"></div>
            </div>
             <div class="mb-3">
                <label class="form-label">Foto Actual</label><br>
                <img src="<?php echo !empty($usuario['ruta_foto']) ? $usuario['ruta_foto'] : 'assets/img/default-avatar.png'; ?>" alt="Avatar" class="rounded-circle" width="60" height="60">
            </div>
            <div class="mb-3">
                <label for="foto" class="form-label">Cambiar Foto de Perfil</label>
                <input class="form-control" type="file" id="foto" name="foto">
            </div>
            <hr>
            <div class="row">
                 <div class="col-md-6 mb-3">
                    <label for="id_rol" class="form-label">Rol <span class="text-danger">*</span></label>
                    <select id="id_rol" name="id_rol" class="form-select" required>
                        <?php foreach ($roles as $rol): ?>
                            <option value="<?php echo $rol['id_rol']; ?>" <?php echo ($usuario['id_rol'] == $rol['id_rol']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($rol['nombre_rol']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="col-md-6 mb-3 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" <?php echo ($usuario['activo']) ? 'checked' : ''; ?>><label class="form-check-label" for="activo">Usuario Activo</label></div></div>
            </div>
            <p class="form-text">La gestión de contraseñas se debe realizar por separado por seguridad.</p>
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            <a href="gestionar_usuarios.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>