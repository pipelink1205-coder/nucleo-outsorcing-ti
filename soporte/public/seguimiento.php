<?php
require_once __DIR__.'/../includes/portal_publico.php';portal_headers();
try {
    require __DIR__.'/../config/database.php';$core=nucleo_db();portal_requerir($pdo);portal_sesion();portal_limite($pdo,'lectura',60,60);
    $token=is_string($_GET['token']??null)?$_GET['token']:'';$ticket=portal_ticket($core,$pdo,$token);
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if((int)($_SERVER['CONTENT_LENGTH']??0)>SOPORTE_REQUEST_BYTES){throw new RuntimeException('Solicitud demasiado grande',413);}
        portal_csrf($_POST);portal_responder($pdo,$ticket,$_POST,$_FILES['adjuntos']??[]);$_SESSION['portal_publico_csrf']=bin2hex(random_bytes(32));header('Location: '.portal_url('seguimiento.php?token='.$token),true,303);exit;
    }
    $q=$pdo->prepare('SELECT id_comentario,comentario,fecha_creacion,autor_publico FROM comentarios WHERE id_ticket=? AND es_privado=0 AND visible_portal=1 ORDER BY id_comentario');$q->execute([$ticket['id_ticket']]);$mensajes=$q->fetchAll();
    $q=$pdo->prepare('SELECT a.id_adjunto,a.id_comentario,a.nombre_original FROM archivos_adjuntos a JOIN comentarios c ON c.id_comentario=a.id_comentario AND c.id_ticket=a.id_ticket WHERE a.id_ticket=? AND c.es_privado=0 AND c.visible_portal=1');$q->execute([$ticket['id_ticket']]);$archivos=[];foreach($q->fetchAll() as $a){$archivos[$a['id_comentario']][]=$a;}
    portal_inicio('Seguimiento del ticket #'.$ticket['id_ticket']);
?>
<p>Empresa: <?= portal_h($ticket['empresa_nombre']) ?></p>
<h2><?= portal_h($ticket['asunto']) ?></h2><p>Estado: <?= portal_h($ticket['estado']) ?></p>
<p>Solicitante: <?= portal_h($ticket['solicitante_nombre']) ?> (datos declarados, sin verificación de identidad)</p>
<p>Guarde este enlace. Quien lo tenga podrá acceder al seguimiento. No se enviará correo automático.</p>
<label>Enlace de seguimiento<input data-enlace readonly value="<?= portal_h(portal_url('seguimiento.php?token='.$token)) ?>" aria-label="Enlace de seguimiento"></label><button type="button" data-copiar>Copiar enlace</button>
<p>Vence el <?= portal_h($ticket['vence_en']) ?> UTC. El número de ticket por sí solo no permite consultar esta solicitud.</p>
<h2>Conversación pública</h2>
<?php foreach($mensajes as $m): ?><article><strong><?= $m['autor_publico']?'Solicitante (sin identidad verificada)':'Respuesta' ?></strong> <time><?= portal_h($m['fecha_creacion']) ?> UTC</time><p><?= nl2br(portal_h($m['comentario'])) ?></p>
<?php foreach($archivos[$m['id_comentario']]??[] as $a): ?><p><a href="<?= portal_h(portal_url('adjunto_publico.php?token='.$token.'&id='.$a['id_adjunto'])) ?>"><?= portal_h($a['nombre_original']) ?></a></p><?php endforeach; ?></article><?php endforeach; ?>
<?php if(!in_array($ticket['estado'],['Cerrado','Anulado'],true)): ?>
<form method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= portal_h($_SESSION['portal_publico_csrf']) ?>"><label>Responder *<textarea name="mensaje" maxlength="20000" rows="5" required></textarea></label><label>Adjuntos (opcional)<input type="file" name="adjuntos[]" multiple accept=".pdf,.png,.jpg,.jpeg,.txt"></label><p>Hasta 5 archivos; 5 MiB cada uno, 15 MiB total. PDF, PNG, JPG o TXT.</p><button>Enviar respuesta</button></form>
<?php endif; portal_fin();
} catch(Throwable $e){portal_error($e);}
