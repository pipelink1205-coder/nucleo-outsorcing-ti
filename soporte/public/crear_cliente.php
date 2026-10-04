<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

$mensaje_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $correo_electronico = trim($_POST['correo_electronico']);
    $telefono = trim($_POST['telefono']) ?: null;
    $empresa = trim($_POST['empresa']) ?: null;
    $pais = trim($_POST['pais']) ?: null;
    $ciudad = trim($_POST['ciudad']) ?: null;
    $whatsapp = trim($_POST['whatsapp']) ?: null;
    $telegram = trim($_POST['telegram']) ?: null;
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (empty($nombre) || empty($correo_electronico)) {
        $mensaje_error = "Los campos 'Nombre Completo' y 'Correo Electrónico' son obligatorios.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO Clientes (nombre, empresa, correo_electronico, telefono, pais, ciudad, whatsapp, telegram, activo) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$nombre, $empresa, $correo_electronico, $telefono, $pais, $ciudad, $whatsapp, $telegram, $activo]);
            header("Location: gestionar_clientes.php?status=created");
            exit();
        } catch (Exception $e) {
            $mensaje_error = "Error al crear el cliente: " . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-person-plus-fill"></i> Añadir Nuevo Cliente</h2>
    <a href="gestionar_clientes.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Volver a la lista</a>
</div>

<?php if ($mensaje_error): ?>
    <div class="alert alert-danger"><?php echo $mensaje_error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-4">
        <form action="crear_cliente.php" method="POST">
            <div class="row g-3">
                <div class="col-12">
                    <label for="nombre" class="form-label">Nombre Completo *</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                </div>
                <div class="col-12">
                    <label for="correo_electronico" class="form-label">Correo Electrónico *</label>
                    <input type="email" class="form-control" id="correo_electronico" name="correo_electronico" required>
                </div>
                <div class="col-12">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="text" class="form-control" id="telefono" name="telefono">
                </div>
                <div class="col-12">
                    <label for="empresa" class="form-label">Empresa (Opcional)</label>
                    <input type="text" class="form-control" id="empresa" name="empresa">
                </div>
                <div class="col-md-6">
                    <label for="pais" class="form-label">País</label>
                    <input type="text" class="form-control" id="pais" name="pais">
                </div>
                <div class="col-md-6">
                    <label for="ciudad" class="form-label">Ciudad</label>
                    <input type="text" class="form-control" id="ciudad" name="ciudad">
                </div>
                <div class="col-md-6">
                    <label for="whatsapp" class="form-label">WhatsApp</label>
                    <input type="text" class="form-control" id="whatsapp" name="whatsapp" placeholder="+51987654321">
                </div>
                <div class="col-md-6">
                    <label for="telegram" class="form-label">Telegram</label>
                    <input type="text" class="form-control" id="telegram" name="telegram" placeholder="@usuario">
                </div>
                <div class="col-12">
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" checked>
                        <label class="form-check-label" for="activo">
                            Cliente Activo
                        </label>
                    </div>
                </div>
                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary">Crear Cliente</button>
                    <a href="gestionar_clientes.php" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>