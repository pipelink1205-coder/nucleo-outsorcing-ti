<?php
require_once __DIR__.'/../includes/portal_publico.php';
portal_headers();
try {
    require __DIR__.'/../config/database.php';$core=nucleo_db();portal_requerir($pdo);portal_sesion();portal_limite($pdo,'lectura',60,60);
    $enlace=is_string($_GET['enlace']??null)?$_GET['enlace']:'';$empresa=portal_empresa($core,$pdo,$enlace);
    if($_SERVER['REQUEST_METHOD']==='POST') {
        if((int)($_SERVER['CONTENT_LENGTH']??0)>SOPORTE_REQUEST_BYTES){throw new RuntimeException('Solicitud demasiado grande',413);}
        portal_csrf($_POST);$token=portal_crear($core,$pdo,$empresa,$_POST,$_FILES['adjuntos']??[]);$_SESSION['portal_publico_csrf']=bin2hex(random_bytes(32));header('Location: '.portal_url('seguimiento.php?token='.$token),true,303);exit;
    }
    $sedes=portal_sedes($core,(int)$empresa['id']);$tipos=$pdo->query('SELECT id_tipo_caso,nombre_tipo FROM tiposdecaso WHERE activo=1 ORDER BY nombre_tipo')->fetchAll();
    if(!$tipos){throw new RuntimeException('No hay tipos de caso habilitados. Contacte a soporte.',503);}
    portal_inicio('Solicitar soporte');
    echo '<p>Empresa: <strong>'.portal_h($empresa['nombre']).'</strong></p><p>El correo que indique no se verifica ni identifica una cuenta de empleado.</p>';
?>
<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= portal_h($_SESSION['portal_publico_csrf']) ?>">
<label>Nombre *<input name="nombre" maxlength="200" required autocomplete="name"></label>
<label>Correo *<input name="correo" type="email" maxlength="254" required autocomplete="email"></label>
<label>Teléfono (opcional)<input name="telefono" type="tel" maxlength="40" autocomplete="tel"></label>
<?php if(count($sedes)===1): ?><p>Sucursal: <?= portal_h($sedes[0]['nombre']) ?> (asignada automáticamente)</p>
<?php elseif(count($sedes)>1): ?><label>Sucursal *<select name="id_sucursal" required><option value="">Seleccione...</option><?php foreach($sedes as $s): ?><option value="<?= (int)$s['id'] ?>"><?= portal_h($s['nombre']) ?></option><?php endforeach; ?></select></label>
<?php else: ?><p>Puede continuar sin sucursal.</p><?php endif; ?>
<label>Asunto *<input name="asunto" maxlength="255" required></label>
<label>Tipo de caso *<select name="id_tipo_caso" required><option value="">Seleccione...</option><?php foreach($tipos as $t): ?><option value="<?= (int)$t['id_tipo_caso'] ?>"><?= portal_h($t['nombre_tipo']) ?></option><?php endforeach; ?></select></label>
<label>Descripción *<textarea name="descripcion" maxlength="20000" rows="6" required></textarea></label>
<label>Código de equipo (opcional)<input name="codigo_equipo" maxlength="100"></label>
<p>Puede escribir el código que conozca. No se mostrarán datos de inventario.</p>
<label>Adjuntos (opcional)<input name="adjuntos[]" type="file" multiple accept=".pdf,.png,.jpg,.jpeg,.txt"></label>
<p>Hasta 5 archivos PDF, PNG, JPG o TXT; 5 MiB por archivo y 15 MiB en total.</p>
<p>Al guardar recibirá un enlace de seguimiento. No se enviará correo: guarde su enlace. Quien lo tenga podrá acceder a su solicitud.</p>
<button>Enviar solicitud</button></form>
<?php portal_fin();
} catch(Throwable $e) {portal_error($e);}
