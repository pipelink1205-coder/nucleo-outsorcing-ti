<?php
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
require_once __DIR__.'/tickets_solicitantes.php';
function migrar_portal_publico(PDO $core,PDO $db): void {
    $schema=$core->query('SELECT DATABASE()')->fetchColumn();
    if(!preg_match('/^[a-zA-Z0-9_]+$/',$schema)){throw new RuntimeException('Esquema no válido');}
    foreach(['origen'=>"VARCHAR(20) NOT NULL DEFAULT 'interno'",'solicitante_email'=>'VARCHAR(254) NULL','solicitante_telefono'=>'VARCHAR(40) NULL','codigo_equipo_reportado'=>'VARCHAR(100) NULL'] as $c=>$def){agregar_columna($db,'tickets',$c,$def);}
    agregar_columna($db,'comentarios','visible_portal','TINYINT NOT NULL DEFAULT 0');
    agregar_columna($db,'comentarios','autor_publico','TINYINT NOT NULL DEFAULT 0');
    $db->exec("CREATE TABLE IF NOT EXISTS soporte_enlaces_empresa (id_empresa INT NOT NULL PRIMARY KEY, token_hash CHAR(64) NOT NULL UNIQUE, token_cifrado TEXT NOT NULL, habilitado TINYINT NOT NULL DEFAULT 0, actualizado_por INT NULL, actualizado_en DATETIME NOT NULL, CONSTRAINT fk_enlace_empresa FOREIGN KEY(id_empresa) REFERENCES `$schema`.empresas(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS soporte_seguimiento (id_ticket INT NOT NULL PRIMARY KEY, token_hash CHAR(64) NOT NULL UNIQUE, vence_en DATETIME NOT NULL, revocado_en DATETIME NULL, creado_en DATETIME NOT NULL, CONSTRAINT fk_seguimiento_ticket FOREIGN KEY(id_ticket) REFERENCES tickets(id_ticket) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS soporte_limites_publicos (clave CHAR(64) NOT NULL PRIMARY KEY, ventana BIGINT NOT NULL, solicitudes INT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS soporte_eventos_portal (id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, id_empresa INT NOT NULL, id_usuario INT NOT NULL, accion VARCHAR(40) NOT NULL, creado_en DATETIME NOT NULL, INDEX(id_empresa,creado_en)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
if(realpath($_SERVER['SCRIPT_FILENAME'])===realpath(__FILE__)) { migrar_portal_publico(nucleo_db(),$pdo);echo "Portal público preparado; todos los enlaces permanecen deshabilitados.\n"; }
