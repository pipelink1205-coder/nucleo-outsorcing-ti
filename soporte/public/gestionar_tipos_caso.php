<?php
require_once '../includes/auth_check.php';
// Solo los administradores pueden acceder
if ($_SESSION['id_rol'] != 1) {
    header('Location: index.php');
    exit();
}
require_once '../config/database.php';
require_once '../includes/header.php';

$mensaje = '';
$tipo_caso_actual = ['id_tipo_caso' => '', 'nombre_tipo' => '', 'descripcion' => '', 'activo' => 1];

// Lógica para procesar el formulario (crear o actualizar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tipo_caso = $_POST['id_tipo_caso'];
    $nombre_tipo = $_POST['nombre_tipo'];
    $descripcion = $_POST['descripcion'];
    $activo = isset($_POST['activo']) ? 1 : 0;

    try {
        if (empty($id_tipo_caso)) {
            // Crear nuevo
            $stmt = $pdo->prepare("INSERT INTO TiposDeCaso (nombre_tipo, descripcion, activo) VALUES (?, ?, ?)");
            $stmt->execute([$nombre_tipo, $descripcion, $activo]);
            $mensaje = '<div class="alert alert-success">Tipo de caso creado con éxito.</div>';
        } else {
            // Actualizar existente
            $stmt = $pdo->prepare("UPDATE TiposDeCaso SET nombre_tipo = ?, descripcion = ?, activo = ? WHERE id_tipo_caso = ?");
            $stmt->execute([$nombre_tipo, $descripcion, $activo, $id_tipo_caso]);
            $mensaje = '<div class="alert alert-success">Tipo de caso actualizado con éxito.</div>';
        }
    } catch (Exception $e) {
        $mensaje = '<div class="alert alert-danger">Error: '.$e->getMessage().'</div>';
    }
}

// Si se va a editar, cargar los datos del tipo de caso
if (isset($_GET['editar'])) {
    $id_editar = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
    $stmt = $pdo->prepare("SELECT * FROM TiposDeCaso WHERE id_tipo_caso = ?");
    $stmt->execute([$id_editar]);
    $tipo_caso_actual = $stmt->fetch();
}

// Obtener todos los tipos de caso para la tabla
$tipos_de_caso = $pdo->query("SELECT * FROM TiposDeCaso ORDER BY nombre_tipo")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Gestionar Tipos de Caso</h2>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header fw-bold">
                <?php echo empty($tipo_caso_actual['id_tipo_caso']) ? 'Añadir Nuevo Tipo de Caso' : 'Editando Tipo de Caso'; ?>
            </div>
            <div class="card-body">
                <?php echo $mensaje; ?>
                <form action="gestionar_tipos_caso.php" method="POST">
                    <input type="hidden" name="id_tipo_caso" value="<?php echo htmlspecialchars($tipo_caso_actual['id_tipo_caso']); ?>">
                    
                    <div class="mb-3">
                        <label for="nombre_tipo" class="form-label">Nombre del Tipo de Caso</label>
                        <input type="text" class="form-control" id="nombre_tipo" name="nombre_tipo" value="<?php echo htmlspecialchars($tipo_caso_actual['nombre_tipo']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($tipo_caso_actual['descripcion']); ?></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" <?php echo ($tipo_caso_actual['activo']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="activo">Activo</label>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <?php echo empty($tipo_caso_actual['id_tipo_caso']) ? 'Guardar' : 'Actualizar'; ?>
                        </button>
                        <?php if (!empty($tipo_caso_actual['id_tipo_caso'])): ?>
                            <a href="gestionar_tipos_caso.php" class="btn btn-secondary">Cancelar Edición</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header fw-bold">Lista de Tipos de Caso</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr><th>Nombre</th><th>Descripción</th><th>Estado</th><th>Acciones</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tipos_de_caso as $tipo): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tipo['nombre_tipo']); ?></td>
                                <td><?php echo htmlspecialchars($tipo['descripcion']); ?></td>
                                <td>
                                    <?php if ($tipo['activo']): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="gestionar_tipos_caso.php?editar=<?php echo $tipo['id_tipo_caso']; ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-pencil-square"></i> Editar
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>