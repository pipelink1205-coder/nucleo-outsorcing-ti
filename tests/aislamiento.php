<?php
// Prueba de integración sobre copias efímeras; jamás escribir en las bases originales.
if (PHP_SAPI !== 'cli') { exit; }
ob_start();
require_once __DIR__ . '/../includes/nucleo.php';
require_once __DIR__ . '/../soporte/includes/tenant.php';
$admin = new PDO('mysql:host=' . (getenv('DB_HOST') ?: 'localhost') . ';charset=utf8mb4', getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$prefix = 'test_outsourcing_' . bin2hex(random_bytes(4));
$coreName = $prefix . '_core'; $supportName = $prefix . '_support';
$proceso = null;
$bridgeFile=null;$bridgeUrl=null;$bridgeHeader='';$modoApache=in_array('--apache',$argv,true);$apacheLog='C:/xampp/apache/logs/error.log';$apacheLogInicio=0;
$log = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '.log';
$mapPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '.json';
$excelPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '.xlsx';
$uploadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '_adjuntos';
$origenCore = getenv('DB_NAME') ?: 'inventario_ti';
$origenSupport = getenv('SUPPORT_DB_NAME') ?: 'soporte_db';
$checks = 0;$sesionesPrueba=[];
function comprobar(bool $condicion, string $mensaje): void {
    global $checks;
    if (!$condicion) { throw new RuntimeException($mensaje); }
    $checks++; echo "OK $mensaje\n";
}
function denegado(callable $accion, string $mensaje): void {
    $negado = false;
    try { $accion(); } catch (RuntimeException $e) { $negado = in_array($e->getCode(), [403,404], true); }
    comprobar($negado, $mensaje);
}
function clonar(PDO $db, string $origen, string $destino): void {
    foreach ([$origen,$destino] as $nombre) { if (!preg_match('/^[a-z0-9_]+$/i', $nombre)) { throw new RuntimeException('Nombre inválido'); } }
    $db->exec("CREATE DATABASE `$destino` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    foreach ($db->query("SHOW FULL TABLES FROM `$origen` WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $db->exec("CREATE TABLE `$destino`.`$t` LIKE `$origen`.`$t`");
        $db->exec("INSERT INTO `$destino`.`$t` SELECT * FROM `$origen`.`$t`");
    }
}
function restaurar_fks(PDO $db, array $mapa): void {
    foreach ($mapa as $origen=>$destino) {
        $q=$db->prepare('SELECT k.*,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.CONSTRAINT_SCHEMA=? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME,k.CONSTRAINT_NAME,k.ORDINAL_POSITION');
        $q->execute([$origen]); $grupos=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $fila){$grupos[$fila['TABLE_NAME'].'/'.$fila['CONSTRAINT_NAME']][]=$fila;}
        foreach($grupos as $filas){
            $f=$filas[0]; $esquema=$mapa[$f['REFERENCED_TABLE_SCHEMA']] ?? null;
            if(!$esquema){throw new RuntimeException('FK externa fuera de las bases de prueba');}
            $columnas='`'.implode('`,`',array_column($filas,'COLUMN_NAME')).'`';
            $referencia='`'.implode('`,`',array_column($filas,'REFERENCED_COLUMN_NAME')).'`';
            $db->exec("ALTER TABLE `$destino`.`{$f['TABLE_NAME']}` ADD CONSTRAINT `{$f['CONSTRAINT_NAME']}` FOREIGN KEY ($columnas) REFERENCES `$esquema`.`{$f['REFERENCED_TABLE_NAME']}` ($referencia) ON UPDATE {$f['UPDATE_RULE']} ON DELETE {$f['DELETE_RULE']}");
        }
    }
}
function huellas(PDO $db, array $esquemas): array {
    $resultado=[];
    foreach($esquemas as $schema){
        foreach($db->query("SHOW FULL TABLES FROM `$schema` WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $t){
            $pk=$db->query("SHOW INDEX FROM `$schema`.`$t` WHERE Key_name='PRIMARY'")->fetchAll(PDO::FETCH_ASSOC);
            $orden=$pk ? ' ORDER BY `'.implode('`,`',array_column($pk,'Column_name')).'`' : '';
            $filas=$db->query("SELECT * FROM `$schema`.`$t`".$orden)->fetchAll(PDO::FETCH_ASSOC);
            $resultado[$schema.'.'.$t]=hash('sha256',serialize($filas));
        }
    }
    ksort($resultado); return $resultado;
}
function sesion_prueba(int $usuario): string {
    global $sesionesPrueba;
    $id = bin2hex(random_bytes(16));$sesionesPrueba[]=$id; session_id($id); session_start();
    $_SESSION = ['user_id'=>$usuario, 'soporte_csrf'=>'token-prueba', 'inventario_csrf'=>'token-prueba']; session_write_close(); return $id;
}
function url_prueba(string $url): string {
    global $bridgeUrl;
    if(!$bridgeUrl){return $url;}
    $path=parse_url($url,PHP_URL_PATH);$query=parse_url($url,PHP_URL_QUERY);
    return $bridgeUrl.'?__ruta='.rawurlencode($path).($query?'&'.$query:'');
}
function solicitar(string $url, string $sesion = '', ?array $datos = null): array {
    global $bridgeHeader;$url=url_prueba($url);
    $headers = $bridgeHeader.($sesion ? 'Cookie: PHPSESSID=' . $sesion . "\r\n" : '');
    $opciones = ['method'=>$datos === null ? 'GET':'POST','ignore_errors'=>true,'follow_location'=>0,'timeout'=>15,'header'=>$headers . ($datos === null ? '':'Content-Type: application/x-www-form-urlencoded'), 'content'=>$datos === null ? '':http_build_query($datos)];
    $body = file_get_contents($url, false, stream_context_create(['http'=>$opciones]));
    preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $m);
    global $sesionesPrueba;foreach($http_response_header as $cabecera){if(preg_match('/^Set-Cookie: PHPSESSID=([a-zA-Z0-9,-]+)/i',$cabecera,$cookiePrueba)){$sesionesPrueba[]=$cookiePrueba[1];}}
    return [(int) ($m[1] ?? 0), $body ?: '', $http_response_header];
}
function solicitar_archivo(string $url,string $sesion,array $datos,string $nombre,string $contenido): array {
    global $bridgeHeader;$url=url_prueba($url);
    $boundary='prueba'.bin2hex(random_bytes(8)); $body='';
    foreach($datos as $campo=>$valor){$body.="--$boundary\r\nContent-Disposition: form-data; name=\"$campo\"\r\n\r\n$valor\r\n";}
    $body.="--$boundary\r\nContent-Disposition: form-data; name=\"adjuntos[]\"; filename=\"$nombre\"\r\nContent-Type: text/plain\r\n\r\n$contenido\r\n--$boundary--\r\n";
    $r=file_get_contents($url,false,stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'follow_location'=>0,'header'=>$bridgeHeader."Cookie: PHPSESSID=$sesion\r\nContent-Type: multipart/form-data; boundary=$boundary",'content'=>$body,'timeout'=>15]]));
    preg_match('/\s(\d{3})\s/',$http_response_header[0] ?? '',$m);return[(int)($m[1] ?? 0),$r ?: ''];
}
function clasificar_cli(string $archivo, bool $aplicar): int {
    $cmd=[PHP_BINARY,__DIR__.'/../migrations/clasificar_historicos.php',$archivo];
    if ($aplicar) { $cmd[]='--aplicar'; }
    $p=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]); stream_get_contents($pipes[1]); fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]);
    return proc_close($p);
}
try {
    $antesReales=huellas($admin,[$origenCore,$origenSupport]);
    clonar($admin, $origenCore, $coreName);
    clonar($admin, $origenSupport, $supportName);
    restaurar_fks($admin,[$origenCore=>$coreName,$origenSupport=>$supportName]);
    mkdir($uploadDir);
    putenv('SUPPORT_UPLOAD_DIR='.$uploadDir);
    putenv('SUPPORT_PRIVATE_UPLOAD_DIR='.$uploadDir);
    putenv('SUPPORT_PORTAL_KEY='.bin2hex(random_bytes(32)));
    putenv('DB_NAME=' . $coreName); putenv('SUPPORT_DB_NAME=' . $supportName);
    $core = nucleo_db();
    require __DIR__ . '/../migrations/compartir_nucleo.php';
    $pdo->prepare('INSERT INTO clientes(nombre,correo_electronico,empresa) VALUES (?,?,?)')->execute(['Histórico sin clasificación',$prefix.'@legacy.invalid','Legado']);
    $clienteHistorico=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tickets(id_cliente,asunto,descripcion) VALUES (?,?,?)')->execute([$clienteHistorico,'HISTORICO_SIN_EMPRESA','Conservar descripción']);
    $historico=(int)$pdo->lastInsertId();
    $antes = $pdo->query('SELECT * FROM tickets ORDER BY id_ticket')->fetchAll();
    $usuariosAntes = $pdo->query('SELECT * FROM usuarios ORDER BY id_usuario')->fetchAll();
    migrar_compartido($core, $pdo); migrar_compartido($core, $pdo);
    require __DIR__.'/../migrations/tickets_solicitantes.php';
    migrar_solicitantes($pdo); migrar_solicitantes($pdo);
    require __DIR__.'/../migrations/portal_publico.php';migrar_portal_publico($core,$pdo);migrar_portal_publico($core,$pdo);
    require __DIR__ . '/../migrations/localizacion_colombia.php';
    migrar_colombia($core,$pdo); migrar_colombia($core,$pdo);
    $moneda=$pdo->query("SHOW COLUMNS FROM tickets LIKE 'moneda'")->fetch(PDO::FETCH_ASSOC);
    comprobar($moneda['Default']==='COP','Moneda predeterminada COP tras migración repetible');
    $despues = $pdo->query('SELECT * FROM tickets ORDER BY id_ticket')->fetchAll();
    comprobar(count($antes) === count($despues), 'Migración repetible conserva cantidad de tickets');
    foreach ($antes as $i=>$fila) { foreach ($fila as $col=>$valor) { if ($col !== 'id_empresa_portal' && $col !== 'ultima_actualizacion') { comprobar($despues[$i][$col] === $valor, "Dato histórico conservado: ticket {$fila['id_ticket']}.$col"); } } }
    foreach ($usuariosAntes as $fila) { $q=$pdo->prepare('SELECT * FROM usuarios WHERE id_usuario=?'); $q->execute([$fila['id_usuario']]); $actual=$q->fetch(); foreach ($fila as $col=>$valor) { comprobar($actual[$col] === $valor, "Identidad histórica conservada: {$fila['id_usuario']}.$col"); } }
    $fixture = [];
    foreach (['A','B'] as $letra) {
        $core->prepare('INSERT INTO empresas(nombre,slug) VALUES (?,?)')->execute(['Empresa '.$letra,$prefix.'-'.$letra]); $empresa=(int)$core->lastInsertId();
        $core->prepare('INSERT INTO sucursales(nombre,id_empresa) VALUES (?,?)')->execute(['Sede '.$letra,$empresa]); $sucursal=(int)$core->lastInsertId();
        $core->prepare('INSERT INTO empleados(id_sucursal,dni,nombres,apellidos,id_empresa) VALUES (?,?,?,?,?)')->execute([$sucursal,substr($prefix,-8).$letra,'Persona',$letra,$empresa]); $empleado=(int)$core->lastInsertId();
        $core->prepare('INSERT INTO tipos_equipo(nombre,id_empresa) VALUES (?,?)')->execute(['Tipo '.$letra,$empresa]); $tipo=(int)$core->lastInsertId();
        $core->prepare('INSERT INTO marcas(nombre,id_empresa) VALUES (?,?)')->execute(['Marca '.$letra,$empresa]); $marca=(int)$core->lastInsertId();
        $core->prepare('INSERT INTO modelos(nombre,id_marca,id_empresa) VALUES (?,?,?)')->execute(['Modelo '.$letra,$marca,$empresa]); $modelo=(int)$core->lastInsertId();
        $core->prepare('INSERT INTO equipos(id_sucursal,codigo_inventario,id_tipo_equipo,id_marca,id_modelo,numero_serie,tipo_adquisicion,id_empresa) VALUES (?,?,?,?,?,?,?,?)')->execute([$sucursal,'EQ_'.$letra,$tipo,$marca,$modelo,'SERIE_'.$letra,'Propio',$empresa]); $equipo=(int)$core->lastInsertId();
        $core->prepare('INSERT INTO usuarios(nombre,email,password,id_empresa,id_sucursal,id_empleado) VALUES (?,?,?,?,?,?)')->execute(['Admin '.$letra,$prefix.$letra.'@test.invalid',password_hash('Prueba123!',PASSWORD_DEFAULT),$empresa,$sucursal,$empleado]); $usuario=(int)$core->lastInsertId();
        $core->exec("INSERT INTO usuario_roles(id_usuario,id_rol) SELECT $usuario,id FROM roles WHERE nombre_rol='Administrador'");
        $pdo->prepare('INSERT INTO clientes(nombre,correo_electronico,empresa,id_empresa_portal) VALUES (?,?,?,?)')->execute(['Cliente '.$letra,$prefix.$letra.'@cliente.invalid','Empresa '.$letra,$empresa]); $cliente=(int)$pdo->lastInsertId();
        $ctx=nucleo_contexto($core,['user_id'=>$usuario,'user_empresa_id'=>999,'user_rol'=>'Operador']);
        comprobar($ctx['empresa']===$empresa && $ctx['rol']==='administrador', 'Contexto '.$letra.' ignora empresa y rol falsificados');
        $idSoporte=soporte_identidad($pdo,$ctx);
        comprobar(soporte_identidad($pdo,$ctx)===$idSoporte, 'Identidad real '.$letra.' estable');
        $pdo->prepare('INSERT INTO tickets(id_cliente,asunto,id_empresa_portal,id_sucursal,id_solicitante,id_solicitante_usuario) VALUES (?,?,?,?,?,?)')->execute([$cliente,'TICKET_EMPRESA_'.$letra,$empresa,$sucursal,$empleado,$usuario]); $ticket=(int)$pdo->lastInsertId();
        $fixture[$letra]=compact('empresa','sucursal','empleado','usuario','cliente','ctx','ticket','idSoporte','equipo','tipo','marca','modelo');
    }
    $a=$fixture['A']; $b=$fixture['B'];
    comprobar($a['idSoporte'] !== $b['idSoporte'], 'Dos personas tienen identidades de soporte diferentes');
    denegado(fn()=>soporte_ticket($pdo,$a['ctx'],$b['ticket']), 'Empresa A no lee ticket B');
    denegado(fn()=>soporte_ticket($pdo,$b['ctx'],$a['ticket']), 'Empresa B no lee ticket A');
    denegado(fn()=>nucleo_referencia($core,$a['ctx'],'empleados',$b['empleado']), 'Empresa A no accede empleado B');
    denegado(fn()=>soporte_validar_vinculos($core,$pdo,$a['ctx'],['id_sucursal'=>$b['sucursal'],'id_solicitante'=>$a['empleado'],'id_cliente'=>$a['cliente']]), 'Sucursal ajena rechazada');
    denegado(fn()=>soporte_validar_vinculos($core,$pdo,$a['ctx'],['id_sucursal'=>$a['sucursal'],'id_solicitante'=>$a['empleado'],'id_cliente'=>$b['cliente']]), 'Cliente ajeno rechazado');
    denegado(fn()=>soporte_validar_vinculos($core,$pdo,$a['ctx'],['id_sucursal'=>$a['sucursal'],'id_solicitante'=>$a['empleado'],'id_cliente'=>$a['cliente'],'id_equipo'=>$b['equipo']]), 'Equipo ajeno rechazado');
    comprobar(soporte_validar_vinculos($core,$pdo,$a['ctx'],['id_sucursal'=>$a['sucursal'],'id_solicitante'=>$a['empleado'],'id_cliente'=>$a['cliente']])[2]===null, 'Ticket acepta equipo opcional');
    $fk=false; try { $pdo->prepare('UPDATE tickets SET id_sucursal=? WHERE id_ticket=?')->execute([$b['sucursal'],$a['ticket']]); } catch (PDOException $e) { $fk=$e->getCode()==='23000'; }
    comprobar($fk,'Base de datos rechaza vínculo entre empresas');
    if($modoApache){
        if(!in_array(getenv('DB_HOST')?:'localhost',['localhost','127.0.0.1'],true)){throw new RuntimeException('Apache de prueba exige MySQL local');}
        $bridgeFile=dirname(__DIR__).'/public/prueba_portal_'.bin2hex(random_bytes(12)).'.php';$secreto=bin2hex(random_bytes(32));
        $env=['DB_NAME'=>$coreName,'SUPPORT_DB_NAME'=>$supportName,'DB_HOST'=>getenv('DB_HOST')?:'localhost','DB_USER'=>getenv('DB_USER')?:'root','DB_PASS'=>getenv('DB_PASS')?:'','SUPPORT_PORTAL_KEY'=>getenv('SUPPORT_PORTAL_KEY'),'SUPPORT_PRIVATE_UPLOAD_DIR'=>$uploadDir,'SUPPORT_UPLOAD_DIR'=>$uploadDir];
        $codigo=<<<'PHP'
<?php
if(!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)||!hash_equals(__SECRETO__,$_SERVER['HTTP_X_PRUEBA_LOCAL']??'')){http_response_code(404);exit;}
if(PHP_SAPI!=='apache2handler'||!function_exists('apache_setenv')){http_response_code(503);exit;}
// apache_setenv cambia el entorno de esta petición; no usar putenv en un Apache con hilos.
foreach(__ENV__ as $k=>$v){if(!apache_setenv($k,$v)||getenv($k)!==$v){http_response_code(503);exit;}}
$ruta=$_GET['__ruta']??'';
if(!is_string($ruta)||!preg_match('~^/(public|soporte/public|inventario_ti|inventario_ti/soporte)/[a-zA-Z0-9_]+\.php$~',$ruta)){http_response_code(404);exit;}
if(strpos($ruta,'/inventario_ti/soporte/')===0){$rel='/soporte/public/'.substr($ruta,strlen('/inventario_ti/soporte/'));}
elseif(strpos($ruta,'/inventario_ti/')===0){$rel='/public/'.substr($ruta,strlen('/inventario_ti/'));}else{$rel=$ruta;}
$root=dirname(__DIR__);$archivo=realpath($root.$rel);
if(!$archivo||!is_file($archivo)||strpos($archivo,realpath($root).DIRECTORY_SEPARATOR)!==0){http_response_code(404);exit;}
unset($_GET['__ruta']);$_SERVER['SCRIPT_NAME']=$ruta;$_SERVER['PHP_SELF']=$ruta;$_SERVER['SCRIPT_FILENAME']=$archivo;
chdir(dirname($archivo));require $archivo;
PHP;
        $codigo=str_replace(['__SECRETO__','__ENV__'],[var_export($secreto,true),var_export($env,true)],$codigo);file_put_contents($bridgeFile,$codigo);
        $bridgeUrl='http://127.0.0.1/inventario_ti/'.basename($bridgeFile);$bridgeHeader='X-Prueba-Local: '.$secreto."\r\n";$base='http://127.0.0.1';
        $apacheLogInicio=is_file($apacheLog)?filesize($apacheLog):0;
        file_put_contents($log,'');
    }else{
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr); $address=stream_socket_get_name($socket,false); fclose($socket);
    $port=(int)substr(strrchr($address,':'),1);
    $proceso=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',dirname(__DIR__),__DIR__.'/router.php'],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,dirname(__DIR__));
    $base='http://127.0.0.1:'.$port;
    for($i=0;$i<30;$i++){ $s=@fsockopen('127.0.0.1',$port); if($s){fclose($s);break;} usleep(100000); }
    }
    $sa=sesion_prueba($a['usuario']); $sb=sesion_prueba($b['usuario']);
    $core->prepare('INSERT INTO usuarios(nombre,email,password) VALUES (?,?,?)')->execute(['Operador SmartTech',$prefix.'staff@test.invalid',password_hash('Prueba123!',PASSWORD_DEFAULT)]);$staff=(int)$core->lastInsertId();
    $core->exec("INSERT INTO usuario_roles(id_usuario,id_rol) SELECT $staff,id FROM roles WHERE nombre_rol='Operador'");
    $ss=sesion_prueba($staff);session_id($ss);session_start();$_SESSION['empresa_activa_id']=$a['empresa'];session_write_close();

    comprobar(solicitar($base.'/soporte/public/index.php')[0]===403,'HTTP anónimo rechazado');
    [$status,$body,$headers]=solicitar($base.'/inventario_ti/login.php','',['email'=>$prefix.'A@test.invalid','password'=>'Prueba123!']);
    preg_match_all('/PHPSESSID=([^;]+)/',implode("\n",$headers),$cookies);$cookie=[null,end($cookies[1])];
    comprobar($status===302 && !empty($cookie[1]),'Login entrega sesión real');
    $loginSesion=$cookie[1];
    comprobar(solicitar($base.'/inventario_ti/soporte/index.php',$loginSesion)[0]===200,'Misma sesión de login abre soporte por alias');
    foreach (['/public/modulos.php','/inventario_ti/modulos.php'] as $ruta) {
        [$status,$body]=solicitar($base.$ruta,$sa);
        $esperada=strpos($ruta,'/public/')!==false ? '/soporte/public/entrar.php' : '/inventario_ti/soporte/entrar.php';
        comprobar($status===200 && strpos($body,$esperada)!==false,'Enlace a soporte correcto: '.$ruta);
    }
    [$status,$body]=solicitar($base.'/inventario_ti/soporte/index.php',$sa);
    comprobar($status===200 && strpos($body,'/inventario_ti/gestion_usuarios.php')!==false && strpos($body,'../../public/')===false,'Soporte retorna al núcleo usando alias');
    comprobar(solicitar($base.'/inventario_ti/soporte/entrar.php',$sa)[0]===302,'Puente de soporte funciona por alias');
    comprobar(solicitar($base.'/public/tickets.php',$sa)[0]===302,'Ruta de tickets del inventario funciona');
    comprobar(solicitar($base.'/public/devoluciones.php',$sa)[0]===200,'Listado de devoluciones funciona');
    [$status,$body]=solicitar($base.'/public/gestion_catalogos.php?type=marcas',$sa);
    comprobar($status===200 && strpos($body,'name="csrf"')!==false,'Formularios de inventario incluyen CSRF');
    comprobar(solicitar($base.'/public/gestion_catalogos.php?type=marcas&action=deactivate&id='.$b['equipo'],$sa)[0]===405,'Catálogos no cambian mediante GET');
    comprobar(solicitar($base.'/public/gestion_catalogos.php',$sa,['type'=>'marcas','action'=>'deactivate','id'=>1])[0]===403,'Escritura de inventario exige CSRF');

    comprobar(solicitar($base.'/public/login.php','',['email'=>$prefix.'A@test.invalid','password'=>'Prueba123!'])[0]===302,'HTTP login común autentica credenciales reales');
    [$status,$body]=solicitar($base.'/soporte/public/index.php',$sa);
    comprobar($status===200 && strpos($body,'TICKET_EMPRESA_A')!==false && strpos($body,'TICKET_EMPRESA_B')===false,'HTTP listado A aislado');
    [$status,$body]=solicitar($base.'/soporte/public/index.php',$sb);
    comprobar($status===200 && strpos($body,'TICKET_EMPRESA_B')!==false && strpos($body,'TICKET_EMPRESA_A')===false,'HTTP listado B aislado');
    comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$b['ticket'],$sa)[0]===404,'HTTP detalle B rechazado para A');
    comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$a['ticket'],$sa)[0]===200,'HTTP detalle autorizado funciona');
    comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$historico,$sa)[0]===404,'HTTP histórico sin empresa permanece aislado');
    file_put_contents($mapPath,json_encode(['clientes'=>[['id_cliente'=>$clienteHistorico,'id_empresa'=>$a['empresa']]],'tickets'=>[['id_ticket'=>$historico,'id_empresa'=>$a['empresa'],'id_sucursal'=>$a['sucursal'],'id_solicitante'=>$a['empleado']]]]));
    comprobar(clasificar_cli($mapPath,false)===0,'Clasificación histórica valida en simulación');
    comprobar($pdo->query('SELECT id_empresa_portal FROM tickets WHERE id_ticket='.$historico)->fetchColumn()===null,'Simulación no modifica el ticket histórico');
    comprobar(clasificar_cli($mapPath,true)===0,'Clasificación histórica explícita aplicada');
    comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$historico,$sa)[0]===200,'Histórico clasificado queda disponible a su empresa');
    comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$b['ticket'],$sa,['csrf'=>'token-prueba','cambiar_estado'=>1,'nuevo_estado'=>'Cerrado'])[0]===404,'HTTP modificación B rechazada antes de escribir');
    comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$a['ticket'],$sa,['cambiar_estado'=>1,'nuevo_estado'=>'Cerrado'])[0]===403,'HTTP escritura sin CSRF rechazada');
    [$status,$body]=solicitar($base.'/soporte/public/imprimir_tickets.php',$sa);
    comprobar($status===200 && strpos($body,'TICKET_EMPRESA_B')===false,'HTTP impresión aislada');
    [$status,$body]=solicitar($base.'/soporte/public/exportar_excel.php',$sa);
    file_put_contents($excelPath,$body); $zip=new ZipArchive(); $abierto=$zip->open($excelPath);
    $xml=$abierto===true ? $zip->getFromName('xl/sharedStrings.xml') : ''; if($abierto===true){$zip->close();}
    comprobar($status===200 && strpos($xml,'TICKET_EMPRESA_A')!==false && strpos($xml,'TICKET_EMPRESA_B')===false,'HTTP Excel contiene solo empresa A');
    [$status,$body]=solicitar($base.'/soporte/public/exportar_pdf.php',$sa);
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s',$body,$streams); $pdfTexto='';
    foreach($streams[1] as $stream){$pdfTexto.=(@gzuncompress($stream) ?: $stream);}
    comprobar($status===200 && strpos($body,'%PDF')===0 && strpos($pdfTexto,'TICKET_EMPRESA_A')!==false && strpos($pdfTexto,'TICKET_EMPRESA_B')===false,'HTTP PDF contiene solo empresa A');
    comprobar(solicitar($base.'/public/empleado_editar.php?id='.$b['empleado'],$sa)[0]===404,'HTTP edición de empleado ajeno rechazada');
    comprobar(solicitar($base.'/public/empleado_editar.php?id='.$a['empleado'],$sa)[0]===200,'HTTP edición de empleado propio funciona');
    [$status,$html]=solicitar($base.'/public/empleado_editar.php?id='.$a['empleado'],$sa);
    comprobar(strpos($html,'Documento')!==false && strpos($html,'DNI')===false,'Formulario utiliza Documento');
    comprobar(solicitar($base.'/public/gestion_usuarios.php',$sa)[0]===200,'HTTP administrador gestiona usuarios comunes');
    comprobar(solicitar($base.'/public/equipo_editar.php?id='.$b['equipo'],$sa)[0]===404,'HTTP edición de equipo ajeno rechazada');
    comprobar(solicitar($base.'/public/obtener_empleados_por_sucursal.php?id_sucursal='.$b['sucursal'],$sa)[0]===404,'HTTP API sucursal ajena rechazada');

    comprobar(solicitar($base.'/public/obtener_modelos.php',$sa,['csrf'=>'token-prueba','id_marca'=>$a['marca']])[0]===200,'AJAX de modelos funciona con CSRF');
    comprobar(solicitar($base.'/public/gestion_catalogos.php',$sa,['csrf'=>'token-prueba','type'=>'marca','action'=>'deactivate','id'=>$a['marca']])[0]===302,'Catálogo propio se modifica mediante POST autorizado');
    comprobar($core->query('SELECT estado FROM marcas WHERE id='.$a['marca'])->fetchColumn()==='Inactivo','Cambio autorizado queda guardado');
    comprobar(solicitar($base.'/public/gestion_catalogos.php',$sa,['csrf'=>'token-prueba','type'=>'marca','action'=>'deactivate','id'=>$b['marca']])[0]===404,'POST de catálogo ajeno rechazado');
    $core->prepare('INSERT INTO marcas(nombre,id_empresa) VALUES (?,?)')->execute(['Otra marca A',$a['empresa']]);$otraMarca=(int)$core->lastInsertId();
    comprobar(solicitar($base.'/public/equipo_agregar.php',$sa,['csrf'=>'token-prueba','id_modelo'=>$a['modelo'],'id_marca'=>$otraMarca])[0]===422,'Modelo no puede asociarse con marca diferente');
    $core->prepare('INSERT INTO sucursales(nombre,id_empresa) VALUES (?,?)')->execute(['Otra sede A',$a['empresa']]);$otraSede=(int)$core->lastInsertId();
    denegado(fn()=>nucleo_referencia($core,$a['ctx'],'sucursales',$otraSede),'Administrador de sucursal no accede a otra sede de su empresa');
    comprobar(solicitar($base.'/public/usuario_agregar.php',$sa,['csrf'=>'token-prueba','id_sucursal'=>''])[0]===403,'Administrador de sucursal no crea cuenta con alcance empresarial');
    $core->prepare('INSERT INTO usuarios(nombre,email,password,id_empresa) VALUES (?,?,?,?)')->execute(['Cuenta empresarial A',$prefix.'global@test.invalid',password_hash('Prueba123!',PASSWORD_DEFAULT),$a['empresa']]);$cuentaGlobal=(int)$core->lastInsertId();
    denegado(fn()=>nucleo_referencia($core,$a['ctx'],'usuarios',$cuentaGlobal),'Administrador de sucursal no edita cuenta empresarial');
    $core->exec('UPDATE usuarios SET id_empleado=NULL,id_sucursal='.$b['sucursal'].' WHERE id='.$a['usuario']);
    denegado(fn()=>nucleo_contexto($core,['user_id'=>$a['usuario']]),'Identidad rechaza sucursal de otra empresa sin empleado asociado');
    $core->exec('UPDATE usuarios SET id_empleado='.$a['empleado'].',id_sucursal='.$a['sucursal'].' WHERE id='.$a['usuario']);
    comprobar(solicitar($base.'/public/equipo_enviar_reparacion.php',$sa,['csrf'=>'token-prueba','id_equipo'=>$a['equipo'],'fecha_ingreso'=>'2026-10-04','motivo'=>'Prueba aislada','proveedor'=>'Prueba'])[0]===302,'Equipo propio pasa a reparación');
    comprobar($core->query('SELECT estado FROM equipos WHERE id='.$a['equipo'])->fetchColumn()==='En Reparacion','Estado de reparación coincide con ENUM de base de datos');
    $pdo->prepare('INSERT INTO tiposdecaso(nombre_tipo) VALUES (?)')->execute([$prefix]); $tipoCaso=(int)$pdo->lastInsertId();
    $datos=['csrf'=>'token-prueba','id_cliente'=>$a['cliente'],'id_sucursal'=>$a['sucursal'],'id_solicitante'=>$a['empleado'],'id_tipo_caso'=>$tipoCaso,'asunto'=>'CREADO_HTTP_A','prioridad'=>'Media','descripcion'=>'Descripción desde sesión real','id_empresa_portal'=>$b['empresa'],'id_solicitante_usuario'=>$b['usuario']];
    comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$sa,$datos)[0]===302,'HTTP crea ticket sin equipo');
    $nuevo=$pdo->query("SELECT * FROM tickets WHERE asunto='CREADO_HTTP_A'")->fetch(PDO::FETCH_ASSOC);
    comprobar((int)$nuevo['id_empresa_portal']===$a['empresa'] && (int)$nuevo['id_solicitante_usuario']===$a['usuario'] && $nuevo['id_equipo']===null,'HTTP persiste empresa y creador reales');
    comprobar($nuevo['moneda']==='COP','Ticket nuevo guarda pesos colombianos');
    $datos['asunto']='CREADO_HTTP_EQUIPO'; $datos['id_equipo']=$a['equipo'];
    comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$sa,$datos)[0]===302,'HTTP crea ticket con equipo');
    $datos['id_equipo']=$b['equipo'];
    comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$sa,$datos)[0]===404,'HTTP rechaza equipo manipulado');
    comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$a['ticket'],$sa,['csrf'=>'token-prueba','agregar_comentario'=>1,'comentario'=>'Comentario real'])[0]===302,'HTTP registra comentario');
    $q=$pdo->prepare('SELECT id_usuario_nucleo FROM comentarios WHERE id_ticket=? AND comentario=?'); $q->execute([$a['ticket'],'Comentario real']);
    comprobar((int)$q->fetchColumn()===$a['usuario'],'Comentario conserva autor real del núcleo');

    [$status,$body]=solicitar($base.'/soporte/public/ver_ticket.php?id='.$nuevo['id_ticket'],$sa);
    comprobar($status===200 && strpos($body,'Admin A')!==false,'Primer comentario muestra identidad real');
    comprobar(solicitar_archivo($base.'/soporte/public/ver_ticket.php?id='.$a['ticket'],$ss,['csrf'=>'token-prueba','agregar_comentario'=>1,'comentario'=>'NOTA_INTERNA_PRUEBA','es_privado'=>1],'prueba.txt','CONTENIDO_PRIVADO_PRUEBA')[0]===302,'Adjunto privado se carga en carpeta temporal');
    $adjunto=(int)$pdo->query("SELECT id_adjunto FROM archivos_adjuntos WHERE nombre_original='prueba.txt' ORDER BY id_adjunto DESC LIMIT 1")->fetchColumn();
    [$status,$body]=solicitar($base.'/soporte/public/descargar_adjunto.php?id='.$adjunto,$ss);
    comprobar($status===200 && $body==='CONTENIDO_PRIVADO_PRUEBA','Operador SmartTech descarga adjunto privado exacto');
    comprobar(solicitar($base.'/soporte/public/descargar_adjunto.php?id='.$adjunto,$sb)[0]===404,'Empresa B no descarga adjunto A');
    comprobar(solicitar($base.'/soporte/public/descargar_adjunto.php?id='.$adjunto)[0]===403,'Descarga anónima rechazada');
    $pdo->exec('UPDATE archivos_adjuntos SET id_ticket='.$b['ticket'].' WHERE id_adjunto='.$adjunto);
    comprobar(solicitar($base.'/soporte/public/descargar_adjunto.php?id='.$adjunto,$sb)[0]===404,'Adjunto con comentario de otro ticket rechazado');
    $pdo->exec('UPDATE archivos_adjuntos SET id_ticket='.$a['ticket'].' WHERE id_adjunto='.$adjunto);
    $rutaAdjunto=$pdo->query('SELECT ruta_archivo FROM archivos_adjuntos WHERE id_adjunto='.$adjunto)->fetchColumn();
    $pdo->prepare('UPDATE archivos_adjuntos SET ruta_archivo=? WHERE id_adjunto=?')->execute(['uploads/../../config/database.php',$adjunto]);
    comprobar(solicitar($base.'/soporte/public/descargar_adjunto.php?id='.$adjunto,$ss)[0]===404,'Descarga rechaza recorrido fuera de carpeta');
    $pdo->prepare('UPDATE archivos_adjuntos SET ruta_archivo=? WHERE id_adjunto=?')->execute([$rutaAdjunto,$adjunto]);
    $agenteB=(int)$pdo->query('SELECT id_agente FROM agentes WHERE id_usuario='.$b['idSoporte'])->fetchColumn();
    comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$a['ticket'],$sa,['csrf'=>'token-prueba','asignar_ticket'=>1,'id_nuevo_agente'=>$agenteB])[0]===403,'No se asignan agentes de otra empresa');

    comprobar((int)$pdo->query("SELECT COUNT(*) FROM tiposdecaso WHERE nombre_tipo IN ('Incidente','Solicitud de servicio','Consulta','Mantenimiento')")->fetchColumn()===4,'Catálogo inicial repetido sin duplicados');
    $core->prepare('INSERT INTO empresas(nombre,slug,estado) VALUES (?,?,?)')->execute(['Empresa vacía',$prefix.'-vacia','Activa']);$empresaVacia=(int)$core->lastInsertId();
    $core->prepare('INSERT INTO usuarios(nombre,email,password,id_empresa) VALUES (?,?,?,?)')->execute(['Admin vacío',$prefix.'vacio@test.invalid',password_hash('Prueba123!',PASSWORD_DEFAULT),$empresaVacia]);$usuarioVacio=(int)$core->lastInsertId();
    $core->exec("INSERT INTO usuario_roles(id_usuario,id_rol) SELECT $usuarioVacio,id FROM roles WHERE nombre_rol='Administrador'");
    $sv=sesion_prueba($usuarioVacio);
    [$status,$html]=solicitar($base.'/soporte/public/crear_ticket.php',$sv);
    comprobar($status===200 && strpos($html,'Empresa vacía')!==false && strpos($html,'Sin sucursal registrada')!==false && strpos($html,'name="id_cliente"')===false,'Formulario admite empresa vacía sin selector de empresa/cliente');
    $cantidadEmpleados=$core->query('SELECT COUNT(*) FROM empleados')->fetchColumn();$cantidadUsuarios=$core->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();$cantidadClientes=$pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
    $tipoInicial=(int)$pdo->query("SELECT id_tipo_caso FROM tiposdecaso WHERE nombre_tipo='Incidente'")->fetchColumn();
    $libre=['csrf'=>'token-prueba','asunto'=>'TICKET_VACIO_PRUEBA','descripcion'=>'Solicitud sin inventario','prioridad'=>'Media','id_tipo_caso'=>$tipoInicial,'solicitante_nombre'=>'Persona externa','solicitante_contacto'=>'persona@test.invalid','id_empresa_portal'=>$b['empresa'],'id_solicitante_usuario'=>$b['usuario']];
    comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$sv,$libre)[0]===302,'Empresa sin sucursal, empleados, equipos ni clientes guarda ticket');
    $tv=$pdo->query("SELECT * FROM tickets WHERE asunto='TICKET_VACIO_PRUEBA'")->fetch();
    comprobar((int)$tv['id_empresa_portal']===$empresaVacia && (int)$tv['id_solicitante_usuario']===$usuarioVacio,'Empresa vacía conserva contexto y creador real');
    comprobar($tv['id_sucursal']===null && $tv['id_solicitante']===null && $tv['id_equipo']===null && $tv['id_cliente']===null,'Relaciones opcionales permanecen NULL');
    comprobar($tv['solicitante_nombre']==='Persona externa' && $tv['solicitante_contacto']==='persona@test.invalid','Solicitante libre conserva nombre/contacto separado');
    comprobar($core->query('SELECT COUNT(*) FROM empleados')->fetchColumn()===$cantidadEmpleados && $core->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()===$cantidadUsuarios && $pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn()===$cantidadClientes,'No se crean empleados, cuentas ni clientes para solicitante');
    [$status,$body]=solicitar($base.'/soporte/public/ver_ticket.php?id='.$tv['id_ticket'],$sv);
    comprobar($status===200 && strpos($body,'Persona externa')!==false && strpos($body,'persona@test.invalid')!==false,'Detalle de ticket sin cliente muestra solicitante/contacto');
    [$status,$body]=solicitar($base.'/soporte/public/index.php',$sv);
    comprobar($status===200 && strpos($body,'TICKET_VACIO_PRUEBA')!==false,'Listado incluye ticket sin cliente heredado');
    comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$tv['id_ticket'],$sa)[0]===404,'Empresa A no accede ticket de empresa vacía');
    comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$a['ticket'],$sv)[0]===404,'Empresa vacía no accede ticket A');
    [$status,$body]=solicitar($base.'/soporte/public/exportar_excel.php',$sv);file_put_contents($excelPath,$body);$zip=new ZipArchive();$abierto=$zip->open($excelPath);$xml=$abierto===true ? $zip->getFromName('xl/sharedStrings.xml') : '';if($abierto===true){$zip->close();}
    comprobar($status===200 && strpos($xml,'TICKET_VACIO_PRUEBA')!==false && strpos($xml,'TICKET_EMPRESA_A')===false,'Excel incluye ticket sin cliente y conserva aislamiento');
    [$status,$body]=solicitar($base.'/soporte/public/exportar_pdf.php',$sv);comprobar($status===200 && strpos($body,'%PDF')===0,'PDF admite ticket sin cliente');
    comprobar(solicitar($base.'/soporte/public/imprimir_tickets.php',$sv)[0]===200,'Impresión admite ticket sin cliente');
    $invalido=$libre;unset($invalido['solicitante_contacto']);comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$sv,$invalido)[0]===422,'Persona no registrada requiere contacto');
    $invalido=$libre;$invalido['id_sucursal']=$b['sucursal'];comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$sv,$invalido)[0]===404,'Empresa vacía rechaza sucursal B');
    $invalido=$libre;$invalido['id_equipo']=$b['equipo'];comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$sv,$invalido)[0]===404,'Empresa vacía rechaza equipo B');
    $invalido=$libre;$invalido['id_solicitante']=$b['empleado'];comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$sv,$invalido)[0]===404,'Empresa vacía rechaza empleado B');
    $core->prepare('INSERT INTO sucursales(nombre,id_empresa) VALUES (?,?)')->execute(['Única sede',$empresaVacia]);$sedeUnica=(int)$core->lastInsertId();
    $libre['asunto']='TICKET_SEDE_AUTOMATICA';comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$sv,$libre)[0]===302,'Una sucursal se asigna sin enviarla por POST');
    comprobar((int)$pdo->query("SELECT id_sucursal FROM tickets WHERE asunto='TICKET_SEDE_AUTOMATICA'")->fetchColumn()===$sedeUnica,'Servidor guarda sucursal única automáticamente');
    $libre['asunto']='TICKET_LIBRE_EN_A';comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$sa,$libre)[0]===302,'Empresa con datos admite solicitante libre y equipo opcional');
    $libreA=$pdo->query("SELECT * FROM tickets WHERE asunto='TICKET_LIBRE_EN_A'")->fetch();comprobar((int)$libreA['id_sucursal']===$a['sucursal'] && (int)$libreA['id_solicitante_usuario']===$a['usuario'],'Usuario de sede conserva sucursal y creador');
    $ctxVacio=nucleo_contexto($core,['user_id'=>$usuarioVacio]);
    $core->prepare('INSERT INTO sucursales(nombre,id_empresa) VALUES (?,?)')->execute(['Segunda sede',$empresaVacia]);
    comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$sv,$libre)[0]===422,'Usuario empresarial con varias sedes debe seleccionar una');
    $libre['id_sucursal']=$sedeUnica;$libre['asunto']='TICKET_MULTISEDE';comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$sv,$libre)[0]===302,'Usuario empresarial selecciona sede propia');
    require __DIR__.'/portal_publico_casos.php';
    comprobar(solicitar($base.'/soporte/public/reset_sistema.php',$sa)[0]===403,'HTTP reset global bloqueado');
    $core->exec("UPDATE usuario_roles SET id_rol=(SELECT id FROM roles WHERE nombre_rol='Auditor') WHERE id_usuario=".$a['usuario']);
    comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$a['ticket'],$sa,['csrf'=>'token-prueba','cambiar_estado'=>1,'nuevo_estado'=>'Cerrado'])[0]===403,'HTTP auditor no puede escribir aunque conserve sesión admin');
    $core->exec("UPDATE usuario_roles SET id_rol=(SELECT id FROM roles WHERE nombre_rol='Empleado') WHERE id_usuario=".$a['usuario']);
    comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$historico,$sa)[0]===404,'HTTP empleado no accede histórico sin creador conocido');
    comprobar(solicitar($base.'/soporte/public/exportar_excel.php',$sa)[0]===403,'HTTP empleado no exporta costos');
    foreach(['exportar_pdf.php','imprimir_tickets.php'] as $ruta){comprobar(solicitar($base.'/soporte/public/'.$ruta,$sa)[0]===403,'Empleado no accede a '.$ruta);}
    [$status,$body]=solicitar($base.'/soporte/public/ver_ticket.php?id='.$a['ticket'],$sa);
    comprobar($status===200 && strpos($body,'NOTA_INTERNA_PRUEBA')===false,'Empleado no ve notas internas');
    comprobar(solicitar($base.'/soporte/public/descargar_adjunto.php?id='.$adjunto,$sa)[0]===404,'Empleado no descarga adjunto privado');

    comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$a['ticket'],$sa,['csrf'=>'token-prueba','agregar_comentario'=>1,'comentario'=>'Empleado comenta'])[0]===302,'HTTP empleado comenta su ticket');
    comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$a['ticket'],$sa,['csrf'=>'token-prueba','cambiar_estado'=>1,'nuevo_estado'=>'Cerrado'])[0]===403,'HTTP empleado no escala permisos');
    $core->exec('UPDATE usuarios SET activo=0 WHERE id='.$a['usuario']);
    comprobar(solicitar($base.'/soporte/public/index.php',$sa)[0]===403,'HTTP usuario desactivado pierde acceso inmediatamente');
    comprobar(huellas($admin,[$origenCore,$origenSupport])===$antesReales,'Huellas de todas las tablas reales permanecen iguales');
    if($modoApache && is_file($apacheLog)){clearstatcache(true,$apacheLog);$offset=min($apacheLogInicio,filesize($apacheLog));$nuevo=file_get_contents($apacheLog,false,null,$offset);file_put_contents($log,$nuevo?:'');}
    comprobar(!preg_match('/PHP (Warning|Fatal error|Parse error)/',file_get_contents($log)), 'Sin advertencias ni errores PHP durante las solicitudes');
    echo "\n$checks comprobaciones correctas. Bases originales sin modificaciones.\n";
} finally {
    if(session_status()===PHP_SESSION_ACTIVE){session_write_close();}
    foreach(array_unique($sesionesPrueba) as $sid){if(preg_match('/^[a-zA-Z0-9,-]{16,128}$/',$sid)){session_id($sid);session_start();$_SESSION=[];session_destroy();}}
    if($bridgeFile&&is_file($bridgeFile)){unlink($bridgeFile);}
    if (is_resource($proceso)) { proc_terminate($proceso); proc_close($proceso); }
    foreach ([$supportName,$coreName] as $nombre) { if (preg_match('/^test_outsourcing_[a-f0-9]{8}_(core|support)$/',$nombre)) { $admin->exec("DROP DATABASE IF EXISTS `$nombre`"); } }
    if (is_file($log)) { unlink($log); }
    foreach ([$mapPath,$excelPath] as $archivo) { if (is_file($archivo)) { unlink($archivo); } }
    foreach (glob($uploadDir.DIRECTORY_SEPARATOR.'*') ?: [] as $archivo){if(is_file($archivo)){unlink($archivo);}}
    if(is_dir($uploadDir)){rmdir($uploadDir);}
}
