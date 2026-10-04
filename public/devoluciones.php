<?php
require_once '../templates/header.php';

$ctx = $GLOBALS['inventario_ctx'];
$sql = "SELECT a.id AS id_asignacion, emp.nombres, emp.apellidos, eq.codigo_inventario,
               ma.nombre AS marca_nombre, mo.nombre AS modelo_nombre
        FROM asignaciones a JOIN empleados emp ON emp.id=a.id_empleado
        JOIN equipos eq ON eq.id=a.id_equipo
        LEFT JOIN marcas ma ON ma.id=eq.id_marca LEFT JOIN modelos mo ON mo.id=eq.id_modelo
        WHERE a.estado_asignacion='Activa' AND eq.id_empresa=?";
$parametros = [$ctx['empresa']];
if ($ctx['sucursal'] !== null) { $sql .= ' AND eq.id_sucursal=?'; $parametros[]=$ctx['sucursal']; }
$stmt = $conexion->prepare($sql);
$stmt->bind_param(str_repeat('i',count($parametros)), ...$parametros);
$stmt->execute();
$resultado = $stmt->get_result();

?>

<h1 class="h2 mb-3">Devolución de Equipos</h1>

<div class="card">
    <div class="card-header">
        Equipos Actualmente Asignados
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Código Inventario</th>
                        <th>Equipo</th>
                        <th style="width: 30%;">Acción de Devolución</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultado && $resultado->num_rows > 0) : ?>
                        <?php while ($asignacion = $resultado->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($asignacion['apellidos'] . ', ' . $asignacion['nombres']); ?></td>
                                <td><?php echo htmlspecialchars($asignacion['codigo_inventario']); ?></td>
                                <td><?php echo htmlspecialchars($asignacion['marca_nombre'] . ' ' . $asignacion['modelo_nombre']); ?></td>
                                <td>
                                    <a class="btn btn-danger btn-sm" href="asignacion_devolver.php?id=<?php echo (int)$asignacion['id_asignacion']; ?>">Registrar devolución</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr><td colspan="4" class="text-center p-4">No hay equipos asignados en esta sucursal.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>