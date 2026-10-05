<?php
require_once __DIR__.'/../includes/auth_check.php';require_once __DIR__.'/../includes/portal_publico.php';
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
try{
    portal_requerir($pdo);portal_clave();
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $accion=$_POST['accion']??'';if(!in_array($accion,['habilitar','desactivar','renovar'],true)){throw new RuntimeException('Acción no válida',422);}
        $pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT * FROM soporte_enlaces_empresa WHERE id_empresa=? FOR UPDATE');$q->execute([$soporte_ctx['empresa']]);$actual=$q->fetch();
            if($accion==='desactivar'){$pdo->prepare('UPDATE soporte_enlaces_empresa SET habilitado=0,actualizado_por=?,actualizado_en=UTC_TIMESTAMP() WHERE id_empresa=?')->execute([$soporte_ctx['usuario'],$soporte_ctx['empresa']]);}
            else{
                $token=$accion==='renovar'||!$actual?bin2hex(random_bytes(32)):portal_descifrar($actual['token_cifrado']);
                $pdo->prepare('INSERT INTO soporte_enlaces_empresa(id_empresa,token_hash,token_cifrado,habilitado,actualizado_por,actualizado_en) VALUES (?,?,?,1,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),token_cifrado=VALUES(token_cifrado),habilitado=1,actualizado_por=VALUES(actualizado_por),actualizado_en=VALUES(actualizado_en)')->execute([$soporte_ctx['empresa'],hash('sha256',$token),portal_cifrar($token),$soporte_ctx['usuario']]);
            }
            $pdo->prepare('INSERT INTO soporte_eventos_portal(id_empresa,id_usuario,accion,creado_en) VALUES (?,?,?,UTC_TIMESTAMP())')->execute([$soporte_ctx['empresa'],$soporte_ctx['usuario'],$accion]);$pdo->commit();header('Location: enlace_empresa.php');exit;
        }catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}throw $e;}
    }
    $q=$pdo->prepare('SELECT * FROM soporte_enlaces_empresa WHERE id_empresa=?');$q->execute([$soporte_ctx['empresa']]);$enlace=$q->fetch();
    require __DIR__.'/../includes/header.php';
?>
<h2>Enlace de solicitudes — <?= portal_h($soporte_ctx['empresa_nombre']) ?></h2>
<p>Estado: <?= !empty($enlace['habilitado'])?'Habilitado':'Desactivado' ?></p>
<p>Desactivar o renovar este enlace solo afecta solicitudes nuevas. El seguimiento de tickets existentes conserva su caducidad y revocación individual.</p>
<?php if($enlace): ?><label class="form-label">Enlace para compartir<input class="form-control" id="enlace" readonly value="<?= portal_h(portal_url('solicitud.php?enlace='.portal_descifrar($enlace['token_cifrado']))) ?>"></label><button type="button" class="btn btn-secondary" id="copiar">Copiar enlace</button><?php endif; ?>
<form method="POST" class="mt-3"><input type="hidden" name="csrf" value="<?= portal_h($_SESSION['soporte_csrf']) ?>"><button class="btn btn-primary" name="accion" value="habilitar">Habilitar</button> <button class="btn btn-secondary" name="accion" value="renovar">Renovar enlace</button> <button class="btn btn-danger" name="accion" value="desactivar">Desactivar</button></form>
<a href="index.php">Volver a tickets</a>
<script>
const copiar=document.getElementById('copiar');if(copiar){copiar.addEventListener('click',async()=>{const input=document.getElementById('enlace');const url=new URL(input.value,window.location.origin).href;try{await navigator.clipboard.writeText(url);copiar.textContent='Copiado';}catch(e){input.value=url;input.select();document.execCommand('copy');}});}
</script>
<?php require __DIR__.'/../includes/footer.php';
}catch(Throwable $e){nucleo_error($e);}
