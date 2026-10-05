<?php
require_once __DIR__.'/../includes/portal_publico.php';portal_headers();
try {
    require __DIR__.'/../config/database.php';$core=nucleo_db();portal_requerir($pdo);portal_limite($pdo,'lectura',60,60);
    $token=is_string($_GET['token']??null)?$_GET['token']:'';$ticket=portal_ticket($core,$pdo,$token);
    $q=$pdo->prepare('SELECT a.* FROM archivos_adjuntos a JOIN comentarios c ON c.id_comentario=a.id_comentario AND c.id_ticket=a.id_ticket WHERE a.id_adjunto=? AND a.id_ticket=? AND c.es_privado=0 AND c.visible_portal=1');$q->execute([(int)($_GET['id']??0),$ticket['id_ticket']]);$a=$q->fetch();if(!$a){throw new RuntimeException('Archivo no encontrado',404);}soporte_emitir_archivo($a);
}catch(Throwable $e){portal_error($e);}
