<?php
require_once __DIR__.'/tenant.php';
const SOPORTE_ARCHIVOS_MAX=5;
const SOPORTE_ARCHIVO_BYTES=5242880;
const SOPORTE_TOTAL_BYTES=15728640;
const SOPORTE_REQUEST_BYTES=16056320;
function soporte_raiz_privada(): string { return getenv('SUPPORT_PRIVATE_UPLOAD_DIR') ?: __DIR__.'/../../.local/soporte_adjuntos'; }
function soporte_validar_archivos(array $files): array {
    if(empty($files['name'])){return [];}
    if(!is_array($files['name'])||count($files['name'])>SOPORTE_ARCHIVOS_MAX){throw new RuntimeException('Se permiten hasta 5 archivos',413);}
    $r=[];$total=0;$mime=new finfo(FILEINFO_MIME_TYPE);
    foreach($files['name'] as $i=>$nombre){
        $error=$files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if($error===UPLOAD_ERR_NO_FILE){continue;}
        if($error!==UPLOAD_ERR_OK){throw new RuntimeException('No se pudo recibir el archivo',413);}
        $tmp=$files['tmp_name'][$i] ?? '';$size=is_file($tmp)?filesize($tmp):0;$total+=$size;
        if(!is_uploaded_file($tmp)||!$size||$size>SOPORTE_ARCHIVO_BYTES||$total>SOPORTE_TOTAL_BYTES){throw new RuntimeException('Cada archivo admite 5 MiB; el total admite 15 MiB',413);}
        $nombre=basename(str_replace('\\','/',(string)$nombre));$ext=strtolower(pathinfo($nombre,PATHINFO_EXTENSION));$tipo=$mime->file($tmp);
        $permitidos=['pdf'=>'application/pdf','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','txt'=>'text/plain'];
        if(!isset($permitidos[$ext])||$tipo!==$permitidos[$ext]||mb_strlen($nombre)>255||preg_match('/[\x00-\x1F\x7F]/',$nombre)){throw new RuntimeException('Archivos permitidos: PDF, PNG, JPG o TXT con contenido válido',422);}
        if(in_array($ext,['png','jpg','jpeg'],true)&&!@getimagesize($tmp)){throw new RuntimeException('Imagen no válida',422);}
        if($ext==='pdf'&&file_get_contents($tmp,false,null,0,5)!=='%PDF-'){throw new RuntimeException('PDF no válido',422);}
        if($ext==='txt'&&(!mb_check_encoding(file_get_contents($tmp),'UTF-8')||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',file_get_contents($tmp)))){throw new RuntimeException('Texto no válido',422);}
        $r[]=compact('tmp','nombre','ext','tipo');
    }return $r;
}
function soporte_guardar_archivos(PDO $db,int $ticket,int $comentario,array $validos,array &$guardados): void {
    if(!$validos){return;}
    $root=soporte_raiz_privada();if(!is_dir($root)&&!@mkdir($root,0700,true)){throw new RuntimeException('No se pudo almacenar el archivo',500);}
    $root=realpath($root);$public=realpath(__DIR__.'/../public');
    $corePublic=realpath(__DIR__.'/../../public');
    if(!$root||$root===$public||$root===$corePublic||strpos($root,$public.DIRECTORY_SEPARATOR)===0||strpos($root,$corePublic.DIRECTORY_SEPARATOR)===0){throw new RuntimeException('Almacenamiento privado mal configurado',503);}
    $q=$db->prepare('INSERT INTO archivos_adjuntos(id_ticket,id_comentario,nombre_original,nombre_guardado,ruta_archivo,tipo_mime) VALUES (?,?,?,?,?,?)');
    foreach($validos as $f){$nombre=bin2hex(random_bytes(24)).'.'.$f['ext'];$path=$root.DIRECTORY_SEPARATOR.$nombre;if(!move_uploaded_file($f['tmp'],$path)){throw new RuntimeException('No se pudo almacenar el archivo',500);}$guardados[]=$path;$q->execute([$ticket,$comentario,$f['nombre'],$nombre,'privado/'.$nombre,$f['tipo']]);}
}
function soporte_borrar_archivos(array $paths): void {foreach($paths as $p){if(is_file($p)){unlink($p);}}}
function soporte_archivo_path(array $a): string {
    $ruta=str_replace('\\','/',$a['ruta_archivo']);
    if(strpos($ruta,'privado/')===0){$root=realpath(soporte_raiz_privada());$rel=substr($ruta,8);}
    elseif(strpos($ruta,'uploads/')===0){$root=realpath(soporte_raiz_adjuntos());$rel=substr($ruta,8);}
    else{throw new RuntimeException('Archivo no encontrado',404);}
    $path=$root?realpath($root.DIRECTORY_SEPARATOR.$rel):false;
    if(!$root||!$path||strpos($path,$root.DIRECTORY_SEPARATOR)!==0||!is_file($path)){throw new RuntimeException('Archivo no encontrado',404);}return $path;
}
function soporte_emitir_archivo(array $a): void {
    $path=soporte_archivo_path($a);
    header('Content-Type: application/octet-stream');header('X-Content-Type-Options: nosniff');header('Cache-Control: no-store');
    header('Content-Disposition: attachment; filename="'.str_replace(['"',"\r","\n"],'',basename($a['nombre_original'])).'"');readfile($path);
}
