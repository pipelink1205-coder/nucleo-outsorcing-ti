<?php
if (PHP_SAPI !== 'cli') {
    exit("Solo consola\n");
}

require_once __DIR__ . '/../config/database.php';

function columna_existe(mysqli $db, string $tabla, string $columna): bool
{
    $tabla = $db->real_escape_string($tabla);
    $columna = $db->real_escape_string($columna);
    $res = $db->query("SHOW COLUMNS FROM `$tabla` LIKE '$columna'");
    return $res && $res->num_rows > 0;
}

function indice_existe(mysqli $db, string $tabla, string $nombre): bool
{
    $tabla = $db->real_escape_string($tabla);
    $nombre = $db->real_escape_string($nombre);
    $res = $db->query("SHOW INDEX FROM `$tabla` WHERE Key_name = '$nombre'");
    return $res && $res->num_rows > 0;
}

$db = $conexion;

$db->query("CREATE TABLE IF NOT EXISTS empresas (
    id INT NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    nit VARCHAR(30) DEFAULT NULL,
    contacto VARCHAR(150) DEFAULT NULL,
    correo VARCHAR(120) DEFAULT NULL,
    estado ENUM('Activa','Inactiva') NOT NULL DEFAULT 'Activa',
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

if (!columna_existe($db, 'usuarios', 'id_empresa')) {
    $db->query("ALTER TABLE usuarios ADD COLUMN id_empresa INT NULL AFTER id_sucursal");
    $db->query("ALTER TABLE usuarios ADD CONSTRAINT fk_usuario_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id)");
}

$tablas = ['sucursales', 'areas', 'cargos', 'empleados', 'equipos', 'tipos_equipo', 'marcas', 'modelos', 'asignaciones', 'reparaciones', 'bajas'];
foreach ($tablas as $tabla) {
    if (!columna_existe($db, $tabla, 'id_empresa')) {
        $db->query("ALTER TABLE `$tabla` ADD COLUMN id_empresa INT NULL, ADD KEY idx_{$tabla}_empresa (id_empresa)");
    }
}

$unicos = [
    'sucursales' => 'nombre',
    'areas' => 'nombre',
    'tipos_equipo' => 'nombre',
    'marcas' => 'nombre',
    'equipos' => 'codigo_inventario',
];
foreach ($unicos as $tabla => $indice) {
    if (indice_existe($db, $tabla, $indice)) {
        $db->query("ALTER TABLE `$tabla` DROP INDEX `$indice`");
    }
    $nuevo = "uk_{$tabla}_{$indice}_empresa";
    if (!indice_existe($db, $tabla, $nuevo)) {
        $db->query("ALTER TABLE `$tabla` ADD UNIQUE KEY `$nuevo` (id_empresa, `$indice`)");
    }
}
if (indice_existe($db, 'equipos', 'numero_serie')) {
    $db->query("ALTER TABLE equipos DROP INDEX numero_serie");
}
if (!indice_existe($db, 'equipos', 'uk_equipos_serie_empresa')) {
    $db->query("ALTER TABLE equipos ADD UNIQUE KEY uk_equipos_serie_empresa (id_empresa, numero_serie)");
}

$db->query("INSERT IGNORE INTO roles (nombre_rol) VALUES ('Operador'), ('Auditor'), ('Empleado')");
$db->query("UPDATE usuario_roles ur
    JOIN roles actual ON actual.id = ur.id_rol
    JOIN roles op ON op.nombre_rol = 'Operador'
    SET ur.id_rol = op.id
    WHERE ur.id_usuario = 1 AND actual.nombre_rol = 'Administrador'");

echo "Nucleo multiempresa listo\n";
