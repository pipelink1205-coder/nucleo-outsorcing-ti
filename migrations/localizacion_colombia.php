<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../includes/nucleo.php';
require_once __DIR__ . '/../soporte/config/database.php';
function migrar_colombia(PDO $core, PDO $support): void
{
    // Cambiar defaults, sin alterar importes ni monedas históricas.
    $support->exec("ALTER TABLE tickets ALTER COLUMN moneda SET DEFAULT 'COP'");
    $q = $core->prepare("UPDATE configuracion SET valor=? WHERE clave='moneda_simbolo' AND valor IN ('S/','S/.','PEN','soles','Sol')");
    $q->execute(['$ COP']);
    $q = $core->prepare('INSERT INTO configuracion (clave,valor) SELECT ?,? WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave=?)');
    $q->execute(['moneda_simbolo','$ COP','moneda_simbolo']);
    echo "Formato colombiano configurado: documento y COP. Importes históricos conservados.\n";
}
if (realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) { migrar_colombia(nucleo_db(), $pdo); }
