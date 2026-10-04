<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
if (!es_operador()) {
    header('Location: index.php');
    exit();
}

$mensaje = '';
$tipo = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'empresa') {
    $nombre = trim($_POST['nombre'] ?? '');
    $nit = trim($_POST['nit'] ?? '');
    $contacto = trim($_POST['contacto'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $admin_nombre = trim($_POST['admin_nombre'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';

    if ($nombre === '' || $admin_nombre === '' || $admin_email === '' || $admin_password === '') {
        $mensaje = 'Completa el nombre de la empresa y los datos de su administrador.';
        $tipo = 'danger';
    } else {
        $slug = slug_empresa($nombre);
        $base = $slug;
        $n = 2;
        $check = $conexion->prepare("SELECT id FROM empresas WHERE slug = ?");
        while (true) {
            $check->bind_param('s', $slug);
            $check->execute();
            $check->store_result();
            if ($check->num_rows === 0) {
                break;
            }
            $slug = $base . '-' . $n;
            $n++;
        }
        $check->close();

        $conexion->begin_transaction();
        try {
            $stmt = $conexion->prepare("INSERT INTO empresas (nombre, slug, nit, contacto, correo) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('sssss', $nombre, $slug, $nit, $contacto, $correo);
            $stmt->execute();
            $id_empresa = $stmt->insert_id;
            $stmt->close();

            $hash = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("INSERT INTO usuarios (id_empresa, nombre, email, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('isss', $id_empresa, $admin_nombre, $admin_email, $hash);
            $stmt->execute();
            $id_usuario = $stmt->insert_id;
            $stmt->close();

            $rol = $conexion->query("SELECT id FROM roles WHERE nombre_rol = 'Administrador'")->fetch_assoc();
            $id_rol = (int) $rol['id'];
            $stmt = $conexion->prepare("INSERT INTO usuario_roles (id_usuario, id_rol) VALUES (?, ?)");
            $stmt->bind_param('ii', $id_usuario, $id_rol);
            $stmt->execute();
            $stmt->close();

            $conexion->commit();
            $mensaje = 'Empresa creada. Ya puedes compartir su enlace.';
        } catch (mysqli_sql_exception $e) {
            $conexion->rollback();
            $mensaje = 'No se pudo crear la empresa. Revisa que el correo del administrador no exista.';
            $tipo = 'danger';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'usuario') {
    $id_empresa = (int) ($_POST['id_empresa'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol_nombre = $_POST['rol'] ?? 'Empleado';
    $roles_validos = ['Administrador', 'Auditor', 'Empleado'];
    if (!in_array($rol_nombre, $roles_validos, true) || $nombre === '' || $email === '' || $password === '' || $id_empresa < 1) {
        $mensaje = 'Completa los datos del usuario.';
        $tipo = 'danger';
    } else {
        $conexion->begin_transaction();
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("INSERT INTO usuarios (id_empresa, nombre, email, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('isss', $id_empresa, $nombre, $email, $hash);
            $stmt->execute();
            $id_usuario = $stmt->insert_id;
            $stmt->close();
            $stmt = $conexion->prepare("SELECT id FROM roles WHERE nombre_rol = ?");
            $stmt->bind_param('s', $rol_nombre);
            $stmt->execute();
            $id_rol = (int) $stmt->get_result()->fetch_assoc()['id'];
            $stmt->close();
            $stmt = $conexion->prepare("INSERT INTO usuario_roles (id_usuario, id_rol) VALUES (?, ?)");
            $stmt->bind_param('ii', $id_usuario, $id_rol);
            $stmt->execute();
            $stmt->close();
            $conexion->commit();
            $mensaje = 'Usuario agregado a la empresa.';
        } catch (mysqli_sql_exception $e) {
            $conexion->rollback();
            $mensaje = 'No se pudo crear el usuario. El correo ya está registrado.';
            $tipo = 'danger';
        }
    }
}

$empresas = $conexion->query("SELECT e.*, (SELECT COUNT(*) FROM usuarios u WHERE u.id_empresa = e.id) AS usuarios
    FROM empresas e ORDER BY e.nombre");
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Núcleo de operaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<div class="bg-primary text-white py-4 mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <div class="text-white-50 small">Outsourcing de TI</div>
            <h1 class="h3 mb-0">Núcleo de operaciones</h1>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-light btn-sm" href="informes.php">Informes de servicio</a>
            <a class="btn btn-outline-light btn-sm" href="logout.php">Salir</a>
        </div>
    </div>
</div>
<div class="container pb-5">
    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-<?php echo $tipo; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header">Nueva empresa</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="accion" value="empresa">
                        <div class="mb-2"><label class="form-label">Empresa</label><input class="form-control" name="nombre" required></div>
                        <div class="mb-2"><label class="form-label">NIT</label><input class="form-control" name="nit"></div>
                        <div class="mb-2"><label class="form-label">Contacto</label><input class="form-control" name="contacto"></div>
                        <div class="mb-2"><label class="form-label">Correo de la empresa</label><input type="email" class="form-control" name="correo"></div>
                        <hr>
                        <div class="small text-muted mb-2">Administrador de esa empresa</div>
                        <div class="mb-2"><input class="form-control" name="admin_nombre" placeholder="Nombre" required></div>
                        <div class="mb-2"><input type="email" class="form-control" name="admin_email" placeholder="Correo de acceso" required></div>
                        <div class="mb-3"><input type="password" class="form-control" name="admin_password" placeholder="Contraseña" required></div>
                        <button class="btn btn-primary w-100" type="submit">Crear empresa y enlace</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <?php if ($empresas && $empresas->num_rows === 0): ?>
                <div class="alert alert-info">Todavía no hay empresas. Crea la primera para generar su enlace.</div>
            <?php endif; ?>
            <?php if ($empresas): while ($empresa = $empresas->fetch_assoc()): ?>
                <?php $enlace = $base . '/login.php?empresa=' . urlencode($empresa['slug']); ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <h2 class="h5 mb-1"><?php echo htmlspecialchars($empresa['nombre']); ?></h2>
                                <div class="text-muted small mb-2"><?php echo (int) $empresa['usuarios']; ?> usuarios · <?php echo htmlspecialchars($empresa['estado']); ?></div>
                                <div class="input-group input-group-sm">
                                    <input class="form-control" readonly value="<?php echo htmlspecialchars($enlace); ?>">
                                </div>
                            </div>
                            <a class="btn btn-primary btn-sm text-nowrap" href="empresa_entrar.php?id=<?php echo (int) $empresa['id']; ?>">Abrir módulos</a>
                        </div>
                        <form method="POST" class="row g-2 mt-3">
                            <input type="hidden" name="accion" value="usuario">
                            <input type="hidden" name="id_empresa" value="<?php echo (int) $empresa['id']; ?>">
                            <div class="col-md-3"><input class="form-control form-control-sm" name="nombre" placeholder="Nombre" required></div>
                            <div class="col-md-3"><input type="email" class="form-control form-control-sm" name="email" placeholder="Correo" required></div>
                            <div class="col-md-2"><input type="password" class="form-control form-control-sm" name="password" placeholder="Clave" required></div>
                            <div class="col-md-2">
                                <select class="form-select form-select-sm" name="rol">
                                    <option>Administrador</option>
                                    <option>Auditor</option>
                                    <option>Empleado</option>
                                </select>
                            </div>
                            <div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100" type="submit">Agregar</button></div>
                        </form>
                    </div>
                </div>
            <?php endwhile; endif; ?>
        </div>
    </div>
</div>
</body>
</html>
