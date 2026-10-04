<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/modulos.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
if (empresa_id_activa() === null) {
    header('Location: ' . (es_operador() ? 'plataforma.php' : 'login.php'));
    exit();
}

$modulos = modulos_disponibles();
$empresa = $_SESSION['empresa_nombre'] ?? 'Empresa';
$rol = etiqueta_rol(rol_actual());
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulos | <?php echo htmlspecialchars($empresa); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/identidad.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="app-hero py-4 mb-4">
    <div class="container d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <img src="img/smarttech-logo.png" alt="" width="48" height="48" class="bg-white rounded-3 p-1">
            <div>
                <div class="text-white-50 small">Empresa activa</div>
                <h1 class="h3 mb-0"><?php echo htmlspecialchars($empresa); ?></h1>
                <div class="small"><?php echo htmlspecialchars($rol); ?> · Qué vas a operar</div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <?php if (es_operador()): ?>
                <a class="btn btn-outline-light btn-sm" href="empresa_salir.php">Mis empresas</a>
            <?php endif; ?>
            <a class="btn btn-outline-light btn-sm" href="logout.php">Salir</a>
        </div>
    </div>
</div>
<div class="container pb-5">
    <p class="text-muted">Elige el módulo. El inventario y los tickets son de esta empresa. Tu rol define lo que puedes hacer adentro.</p>
    <div class="row g-4">
        <?php foreach ($modulos as $modulo): ?>
            <div class="col-md-6 col-xl-4">
                <a href="<?php echo htmlspecialchars($modulo['href']); ?>" class="card shadow-sm h-100 text-decoration-none text-body">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <i class="bi <?php echo htmlspecialchars($modulo['icono']); ?> fs-4"></i>
                            </span>
                            <h2 class="h4 mb-0"><?php echo htmlspecialchars($modulo['titulo']); ?></h2>
                        </div>
                        <p class="text-muted flex-grow-1"><?php echo htmlspecialchars($modulo['texto']); ?></p>
                        <span class="btn btn-primary">Entrar</span>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
