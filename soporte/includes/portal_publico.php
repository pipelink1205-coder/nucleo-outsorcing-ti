<?php
require_once __DIR__.'/tenant.php';require_once __DIR__.'/adjuntos.php';
function portal_requerir(PDO $db): void {if(!soporte_portal_instalado($db)){throw new RuntimeException('Este acceso no está habilitado',503);}}
function portal_clave(): string {
    $hex=getenv('SUPPORT_PORTAL_KEY');$file=__DIR__.'/../../.local/portal.key';
    if(!$hex&&is_file($file)){$hex=trim(file_get_contents($file));}
    if(!is_string($hex)||!preg_match('/^[a-f0-9]{64}$/i',$hex)||!function_exists('sodium_crypto_secretbox')){throw new RuntimeException('Este acceso no está configurado',503);}return hex2bin($hex);
}
function portal_cifrar(string $token): string {$nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);return base64_encode($nonce.sodium_crypto_secretbox($token,$nonce,portal_clave()));}
function portal_descifrar(string $valor): string {$bytes=base64_decode($valor,true);if(!$bytes||strlen($bytes)<SODIUM_CRYPTO_SECRETBOX_NONCEBYTES+SODIUM_CRYPTO_SECRETBOX_MACBYTES){throw new RuntimeException('Enlace no disponible',503);}$token=sodium_crypto_secretbox_open(substr($bytes,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),substr($bytes,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),portal_clave());if($token===false){throw new RuntimeException('Enlace no disponible',503);}return $token;}
function portal_dias(): int {$d=(int)(getenv('SUPPORT_TRACKING_DAYS')?:90);if($d<1||$d>365){throw new RuntimeException('Caducidad mal configurada',503);}return $d;}
function portal_token(string $valor): string {if(!preg_match('/^[a-f0-9]{64}$/',$valor)){throw new RuntimeException('Enlace no disponible',404);}return hash('sha256',$valor);}
function portal_base(): string {
    $base=getenv('SUPPORT_PUBLIC_BASE_URL');
    if($base){if(!filter_var($base,FILTER_VALIDATE_URL)||!in_array(parse_url($base,PHP_URL_SCHEME),['http','https'],true)){throw new RuntimeException('URL pública mal configurada',503);}return rtrim($base,'/');}
    // Evitar generar enlaces absolutos a partir de una cabecera Host controlada por el solicitante.
    return bases_modulos()[1];
}
function portal_url(string $ruta): string {return portal_base().'/'.$ruta;}
function portal_empresa(PDO $core,PDO $db,string $token): array {
    $q=$db->prepare('SELECT id_empresa FROM soporte_enlaces_empresa WHERE token_hash=? AND habilitado=1');$q->execute([portal_token($token)]);$id=$q->fetchColumn();
    $q=$core->prepare("SELECT id,nombre FROM empresas WHERE id=? AND estado='Activa'");$q->execute([$id?:0]);$empresa=$q->fetch();if(!$empresa){throw new RuntimeException('Enlace no disponible',404);}$empresa['credencial_hash']=portal_token($token);return $empresa;
}
function portal_sedes(PDO $core,int $empresa): array {$q=$core->prepare("SELECT id,nombre FROM sucursales WHERE id_empresa=? AND estado='Activo' ORDER BY nombre");$q->execute([$empresa]);return $q->fetchAll();}
function portal_sucursal(PDO $core,int $empresa,array $datos): ?int {
    $sedes=array_map('intval',array_column(portal_sedes($core,$empresa),'id'));$id=empty($datos['id_sucursal'])?null:(int)$datos['id_sucursal'];
    if($id&&!in_array($id,$sedes,true)){throw new RuntimeException('Seleccione una sucursal habilitada',422);}
    if(count($sedes)===1){return $sedes[0];}if(count($sedes)>1&&!$id){throw new RuntimeException('Seleccione una sucursal habilitada',422);}return $id;
}
function portal_limite(PDO $db,string $accion,int $max,int $segundos,string $extra=''): void {
    $ip=$_SERVER['REMOTE_ADDR']??'sin-ip';$clave=hash_hmac('sha256',$accion.'|'.$ip.'|'.$extra,portal_clave());$ventana=intdiv(time(),$segundos);
    $q=$db->prepare('INSERT INTO soporte_limites_publicos(clave,ventana,solicitudes) VALUES (?,?,1) ON DUPLICATE KEY UPDATE solicitudes=IF(ventana=VALUES(ventana),solicitudes+1,1),ventana=VALUES(ventana)');$q->execute([$clave,$ventana]);
    $q=$db->prepare('SELECT solicitudes FROM soporte_limites_publicos WHERE clave=?');$q->execute([$clave]);if((int)$q->fetchColumn()>$max){header('Retry-After: '.$segundos);throw new RuntimeException('Demasiadas solicitudes. Inténtelo más tarde.',429);}
}
function portal_sesion(): void {
    if(session_status()!==PHP_SESSION_ACTIVE){session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Lax','cookie_secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off']);}
    $_SESSION['portal_publico_csrf']=$_SESSION['portal_publico_csrf']??bin2hex(random_bytes(32));
}
function portal_csrf(array $datos): void {if(!is_string($datos['csrf']??null)||!hash_equals($_SESSION['portal_publico_csrf'],$datos['csrf'])){throw new RuntimeException('Formulario no válido',403);}}
function portal_ticket(PDO $core,PDO $db,string $token): array {
    $q=$db->prepare("SELECT t.id_ticket,t.id_empresa_portal,t.asunto,t.descripcion,t.estado,t.solicitante_nombre,s.vence_en,s.token_hash AS credencial_hash FROM soporte_seguimiento s JOIN tickets t ON t.id_ticket=s.id_ticket WHERE s.token_hash=? AND s.revocado_en IS NULL AND s.vence_en>UTC_TIMESTAMP() AND t.origen='publico'");$q->execute([portal_token($token)]);$t=$q->fetch();
    if(!$t){throw new RuntimeException('Enlace no disponible',404);}$q=$core->prepare("SELECT nombre FROM empresas WHERE id=? AND estado='Activa'");$q->execute([$t['id_empresa_portal']]);$nombre=$q->fetchColumn();if(!$nombre){throw new RuntimeException('Enlace no disponible',404);}$t['empresa_nombre']=$nombre;return $t;
}
function portal_crear(PDO $core,PDO $db,array $empresa,array $datos,array $archivos): string {
    foreach(['nombre','correo','telefono','asunto','descripcion','codigo_equipo','id_sucursal','id_tipo_caso'] as $campo){if(isset($datos[$campo])&&!is_string($datos[$campo])&&!is_int($datos[$campo])){throw new RuntimeException('Formulario no válido',422);}}
    $nombre=trim((string)($datos['nombre']??''));$correo=trim((string)($datos['correo']??''));$telefono=trim((string)($datos['telefono']??''));$asunto=trim((string)($datos['asunto']??''));$descripcion=trim((string)($datos['descripcion']??''));$codigo=trim((string)($datos['codigo_equipo']??''));
    if(!$nombre||mb_strlen($nombre)>200||!filter_var($correo,FILTER_VALIDATE_EMAIL)||strlen($correo)>254||mb_strlen($telefono)>40||!$asunto||mb_strlen($asunto)>255||!$descripcion||mb_strlen($descripcion)>20000||mb_strlen($codigo)>100){throw new RuntimeException('Revise los campos obligatorios y su longitud',422);}
    $sucursal=portal_sucursal($core,(int)$empresa['id'],$datos);$tipo=(int)($datos['id_tipo_caso']??0);$q=$db->prepare('SELECT id_tipo_caso FROM tiposdecaso WHERE id_tipo_caso=? AND activo=1');$q->execute([$tipo]);if(!$q->fetchColumn()){throw new RuntimeException('Seleccione un tipo de caso válido',422);}
    $equipo=null;if($codigo!==''){$q=$core->prepare('SELECT id FROM equipos WHERE id_empresa=? AND id_sucursal <=> ? AND codigo_inventario=? LIMIT 2');$q->execute([$empresa['id'],$sucursal,$codigo]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);if(count($ids)===1){$equipo=(int)$ids[0];}}
    $validos=soporte_validar_archivos($archivos);portal_limite($db,'crear',10,3600);
    $token=bin2hex(random_bytes(32));$guardados=[];$db->beginTransaction();
    try {
        $q=$db->prepare('SELECT id_empresa FROM soporte_enlaces_empresa WHERE id_empresa=? AND token_hash=? AND habilitado=1 FOR UPDATE');$q->execute([$empresa['id'],$empresa['credencial_hash']]);if(!$q->fetchColumn()){throw new RuntimeException('Enlace no disponible',404);}
        $q=$db->prepare("INSERT INTO tickets(id_tipo_caso,asunto,descripcion,id_empresa_portal,id_sucursal,id_equipo,solicitante_nombre,solicitante_contacto,solicitante_email,solicitante_telefono,codigo_equipo_reportado,origen) VALUES (?,?,?,?,?,?,?,?,?,?,?,'publico')");$q->execute([$tipo,$asunto,$descripcion,$empresa['id'],$sucursal,$equipo,$nombre,$correo,$correo,$telefono?:null,$codigo?:null]);$id=(int)$db->lastInsertId();
        $q=$db->prepare("INSERT INTO comentarios(id_ticket,id_autor,tipo_autor,comentario,es_privado,visible_portal,autor_publico) VALUES (?,0,'Cliente',?,0,1,1)");$q->execute([$id,$descripcion]);$comentario=(int)$db->lastInsertId();
        $q=$db->prepare('INSERT INTO soporte_seguimiento(id_ticket,token_hash,vence_en,creado_en) VALUES (?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? DAY),UTC_TIMESTAMP())');$q->execute([$id,hash('sha256',$token),portal_dias()]);
        soporte_guardar_archivos($db,$id,$comentario,$validos,$guardados);$db->commit();return $token;
    }catch(Throwable $e){if($db->inTransaction()){$db->rollBack();}soporte_borrar_archivos($guardados);throw $e;}
}
function portal_responder(PDO $db,array $ticket,array $datos,array $files): void {
    if(!is_string($datos['mensaje']??null)){throw new RuntimeException('Mensaje no válido',422);}
    $texto=trim((string)($datos['mensaje']??''));if(!$texto||mb_strlen($texto)>20000){throw new RuntimeException('Escriba un mensaje de hasta 20000 caracteres',422);}
    if(in_array($ticket['estado'],['Cerrado','Anulado'],true)){throw new RuntimeException('Este ticket ya no admite respuestas',403);}
    $validos=soporte_validar_archivos($files);portal_limite($db,'responder',30,3600);$guardados=[];$db->beginTransaction();
    try{$q=$db->prepare('SELECT id_ticket FROM soporte_seguimiento WHERE id_ticket=? AND token_hash=? AND revocado_en IS NULL AND vence_en>UTC_TIMESTAMP() FOR UPDATE');$q->execute([$ticket['id_ticket'],$ticket['credencial_hash']]);if(!$q->fetchColumn()){throw new RuntimeException('Enlace no disponible',404);}$q=$db->prepare("INSERT INTO comentarios(id_ticket,id_autor,tipo_autor,comentario,es_privado,visible_portal,autor_publico) VALUES (?,0,'Cliente',?,0,1,1)");$q->execute([$ticket['id_ticket'],$texto]);$id=(int)$db->lastInsertId();soporte_guardar_archivos($db,(int)$ticket['id_ticket'],$id,$validos,$guardados);$db->commit();}catch(Throwable $e){if($db->inTransaction()){$db->rollBack();}soporte_borrar_archivos($guardados);throw $e;}
}
function portal_headers(): void {header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");}
function portal_h($v): string {return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function portal_inicio(string $titulo): void {echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.portal_h($titulo).'</title><link rel="stylesheet" href="'.portal_h(portal_url('portal_publico.css')).'"><script defer src="'.portal_h(portal_url('portal_publico.js')).'"></script></head><body><main><h1>'.portal_h($titulo).'</h1>';}
function portal_fin(): void {echo '</main></body></html>';}
function portal_error(Throwable $e): void {$code=in_array($e->getCode(),[403,404,413,422,429,503],true)?$e->getCode():500;http_response_code($code);portal_inicio('Solicitudes de soporte');echo '<p>'.portal_h($code===500?'No se pudo procesar la solicitud.':$e->getMessage()).'</p>';portal_fin();}
