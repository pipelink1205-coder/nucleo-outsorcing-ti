<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__.'/compartir_nucleo.php';
function migrar_solicitantes(PDO $db): void {
    agregar_columna($db,'tickets','solicitante_nombre','VARCHAR(200) NULL');
    agregar_columna($db,'tickets','solicitante_contacto','VARCHAR(255) NULL');
    $db->exec('ALTER TABLE tickets MODIFY id_cliente INT NULL');
    // La clave única existente de nombre_tipo evita duplicados incluso entre ejecuciones concurrentes.
    $q=$db->prepare('INSERT INTO tiposdecaso(nombre_tipo,descripcion) VALUES (?,?) ON DUPLICATE KEY UPDATE id_tipo_caso=id_tipo_caso');
    foreach (['Incidente'=>'Falla o interrupción de un servicio TI','Solicitud de servicio'=>'Solicitud de atención o configuración','Consulta'=>'Orientación sobre servicios TI','Mantenimiento'=>'Mantenimiento de equipos o servicios'] as $nombre=>$descripcion) { $q->execute([$nombre,$descripcion]); }
}
if (realpath($_SERVER['SCRIPT_FILENAME'])===realpath(__FILE__)) { migrar_solicitantes($pdo); echo "Solicitantes y catálogo inicial preparados.\n"; }
