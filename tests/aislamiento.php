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
$log = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '.log';
$mapPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '.json';
$excelPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '.xlsx';
$checks = 0;
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
function sesion_prueba(int $usuario): string {
    $id = bin2hex(random_bytes(16)); session_id($id); session_start();
    $_SESSION = ['user_id'=>$usuario, 'soporte_csrf'=>'token-prueba']; session_write_close(); return $id;
}
function solicitar(string $url, string $sesion = '', ?array $datos = null): array {
    $headers = $sesion ? 'Cookie: PHPSESSID=' . $sesion . "\r\n" : '';
    $opciones = ['method'=>$datos === null ? 'GET':'POST','ignore_errors'=>true,'follow_location'=>0,'timeout'=>15,'header'=>$headers . ($datos === null ? '':'Content-Type: application/x-www-form-urlencoded'), 'content'=>$datos === null ? '':http_build_query($datos)];
    $body = file_get_contents($url, false, stream_context_create(['http'=>$opciones]));
    preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $m);
    return [(int) ($m[1] ?? 0), $body ?: ''];
}
function clasificar_cli(string $archivo, bool $aplicar): int {
    $cmd=[PHP_BINARY,__DIR__.'/../migrations/clasificar_historicos.php',$archivo];
    if ($aplicar) { $cmd[]='--aplicar'; }
    $p=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]); stream_get_contents($pipes[1]); fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]);
    return proc_close($p);
}
try {
    clonar($admin, getenv('DB_NAME') ?: 'inventario_ti', $coreName);
    clonar($admin, getenv('SUPPORT_DB_NAME') ?: 'soporte_db', $supportName);
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
        $fixture[$letra]=compact('empresa','sucursal','empleado','usuario','cliente','ctx','ticket','idSoporte','equipo');
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
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr); $address=stream_socket_get_name($socket,false); fclose($socket);
    $port=(int)substr(strrchr($address,':'),1);
    $proceso=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',dirname(__DIR__)],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,dirname(__DIR__));
    $base='http://127.0.0.1:'.$port;
    for($i=0;$i<30;$i++){ $s=@fsockopen('127.0.0.1',$port); if($s){fclose($s);break;} usleep(100000); }
    $sa=sesion_prueba($a['usuario']); $sb=sesion_prueba($b['usuario']);
    comprobar(solicitar($base.'/soporte/public/index.php')[0]===403,'HTTP anónimo rechazado');
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
    comprobar(solicitar($base.'/soporte/public/reset_sistema.php',$sa)[0]===403,'HTTP reset global bloqueado');
    $core->exec("UPDATE usuario_roles SET id_rol=(SELECT id FROM roles WHERE nombre_rol='Auditor') WHERE id_usuario=".$a['usuario']);
    comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$a['ticket'],$sa,['csrf'=>'token-prueba','cambiar_estado'=>1,'nuevo_estado'=>'Cerrado'])[0]===403,'HTTP auditor no puede escribir aunque conserve sesión admin');
    $core->exec("UPDATE usuario_roles SET id_rol=(SELECT id FROM roles WHERE nombre_rol='Empleado') WHERE id_usuario=".$a['usuario']);
    comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$historico,$sa)[0]===404,'HTTP empleado no accede histórico sin creador conocido');
    comprobar(solicitar($base.'/soporte/public/exportar_excel.php',$sa)[0]===403,'HTTP empleado no exporta costos');
    comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$a['ticket'],$sa,['csrf'=>'token-prueba','agregar_comentario'=>1,'comentario'=>'Empleado comenta'])[0]===302,'HTTP empleado comenta su ticket');
    comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$a['ticket'],$sa,['csrf'=>'token-prueba','cambiar_estado'=>1,'nuevo_estado'=>'Cerrado'])[0]===403,'HTTP empleado no escala permisos');
    $core->exec('UPDATE usuarios SET activo=0 WHERE id='.$a['usuario']);
    comprobar(solicitar($base.'/soporte/public/index.php',$sa)[0]===403,'HTTP usuario desactivado pierde acceso inmediatamente');
    echo "\n$checks comprobaciones correctas. Bases originales sin modificaciones.\n";
} finally {
    if (is_resource($proceso)) { proc_terminate($proceso); proc_close($proceso); }
    foreach ([$supportName,$coreName] as $nombre) { if (preg_match('/^test_outsourcing_[a-f0-9]{8}_(core|support)$/',$nombre)) { $admin->exec("DROP DATABASE IF EXISTS `$nombre`"); } }
    if (is_file($log)) { unlink($log); }
    foreach ([$mapPath,$excelPath] as $archivo) { if (is_file($archivo)) { unlink($archivo); } }
}
