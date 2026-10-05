<?php
// Incluido por aislamiento.php: únicamente clones y archivos temporales.
require_once __DIR__.'/../soporte/includes/portal_publico.php';
function publico_csrf(string $html): string {preg_match('/name="csrf" value="([^"]+)"/',$html,$m);if(empty($m[1])){throw new RuntimeException('CSRF público ausente');}return html_entity_decode($m[1]);}
function token_redireccion(array $respuesta): string {preg_match('/token=([a-f0-9]{64})/',implode("\n",$respuesta[2]),$m);if(empty($m[1])){throw new RuntimeException('Token de seguimiento ausente');}return $m[1];}
function seleccion_staff(string $sesion,int $empresa):void{session_id($sesion);session_start();$_SESSION['empresa_activa_id']=$empresa;session_write_close();}
$anon=sesion_prueba(0);
comprobar(solicitar($base.'/inventario_ti/soporte/enlace_empresa.php',$sa)[0]===403,'Cliente administrador no habilita enlaces públicos');
comprobar(solicitar($base.'/inventario_ti/soporte/enlace_empresa.php',$ss,['accion'=>'habilitar'])[0]===403,'Gestión de enlaces exige CSRF');
$enlaces=[];
foreach(['A'=>$a['empresa'],'B'=>$b['empresa'],'Vacia'=>$empresaVacia] as $nombre=>$empresa){
    seleccion_staff($ss,$empresa);
    comprobar(solicitar($base.'/inventario_ti/soporte/enlace_empresa.php',$ss,['csrf'=>'token-prueba','accion'=>'habilitar','id_empresa'=>$b['empresa']])[0]===302,'Operador habilita enlace contextual '.$nombre);
    $q=$pdo->prepare('SELECT * FROM soporte_enlaces_empresa WHERE id_empresa=?');$q->execute([$empresa]);$config=$q->fetch();$enlaces[$nombre]=portal_descifrar($config['token_cifrado']);
    comprobar(strlen($enlaces[$nombre])===64&&$config['token_hash']===hash('sha256',$enlaces[$nombre])&&strpos($config['token_cifrado'],$enlaces[$nombre])===false,'Enlace '.$nombre.' cifrado y token de 256 bits');
}
seleccion_staff($ss,$a['empresa']);
[$status,$html]=solicitar($base.'/inventario_ti/soporte/enlace_empresa.php',$ss);comprobar($status===200&&strpos($html,'Copiar enlace')!==false,'Operador puede volver a copiar enlace');
$urlA=$base.'/inventario_ti/soporte/solicitud.php?enlace='.$enlaces['A'];
$urlB=$base.'/inventario_ti/soporte/solicitud.php?enlace='.$enlaces['B'];
[$status,$html]=solicitar($urlA,$anon);$csrf=publico_csrf($html);
comprobar($status===200&&strpos($html,'Empresa A')!==false&&strpos($html,'Persona A')===false&&strpos($html,'EQ_A')===false&&strpos($html,'Documento')===false&&strpos($html,'name="id_empresa"')===false,'Formulario público no revela empleado, documento, inventario ni selector de empresa');
comprobar(strpos($html,'name="id_sucursal"')!==false,'Empresa con varias sedes pide selección');
comprobar(solicitar($base.'/inventario_ti/soporte/solicitud.php?enlace='.str_repeat('0',64),$anon)[0]===404,'Enlace de empresa inválido rechazado');
$datos=['csrf'=>$csrf,'nombre'=>'<script>alert(1)</script> Persona','correo'=>$prefix.'A@test.invalid','asunto'=>'PUBLICO_A','descripcion'=>'Descripción <script>privada()</script>','id_tipo_caso'=>$tipoInicial,'id_sucursal'=>$a['sucursal'],'codigo_equipo'=>'EQ_A','id_empresa'=>$b['empresa'],'id_equipo'=>$b['equipo'],'id_solicitante'=>$b['empleado'],'id_solicitante_usuario'=>$a['usuario']];
$originalUsuarios=$core->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();$originalEmpleados=$core->query('SELECT COUNT(*) FROM empleados')->fetchColumn();$originalClientes=$pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn();$originalAdaptadores=$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$res=solicitar($urlA,$anon,$datos);comprobar($res[0]===303,'Portal público crea sin login');$tokenA=token_redireccion($res);
$tPublicoA=portal_ticket($core,$pdo,$tokenA);$idPublicoA=(int)$tPublicoA['id_ticket'];
$q=$pdo->prepare('SELECT * FROM tickets WHERE id_ticket=?');$q->execute([$idPublicoA]);$publico=$q->fetch();
comprobar((int)$publico['id_empresa_portal']===$a['empresa']&&(int)$publico['id_equipo']===$a['equipo']&&$publico['id_solicitante']===null&&$publico['id_solicitante_usuario']===null&&$publico['id_cliente']===null&&$publico['origen']==='publico','Origen público, equipo contextual y ausencia de identidad autenticada');
comprobar($core->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()===$originalUsuarios&&$core->query('SELECT COUNT(*) FROM empleados')->fetchColumn()===$originalEmpleados&&$pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn()===$originalClientes&&$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()===$originalAdaptadores,'Correo coincidente no crea ni vincula empleados, clientes o cuentas');
$stored=$pdo->query('SELECT token_hash FROM soporte_seguimiento WHERE id_ticket='.$idPublicoA)->fetchColumn();comprobar($stored===hash('sha256',$tokenA)&&$stored!==$tokenA,'Seguimiento guarda solo hash');
$followA=$base.'/inventario_ti/soporte/seguimiento.php?token='.$tokenA;
[$status,$html]=solicitar($followA,$anon);comprobar($status===200&&strpos($html,'Seguimiento del ticket #'.$idPublicoA)!==false&&strpos($html,'&lt;script&gt;')!==false&&strpos($html,'<script>')===false,'Seguimiento individual muestra número y escapa contenido');
comprobar(strpos($html,'No se enviará correo automático')!==false&&strpos($html,'Quien lo tenga')!==false,'Solicitante recibe advertencia sobre enlace sin correo automático');
$csrfA=publico_csrf($html);
comprobar(solicitar($followA,$anon,['mensaje'=>'Sin token CSRF'])[0]===403,'Respuesta pública exige CSRF');
comprobar(solicitar($followA,$anon,['csrf'=>$csrfA,'mensaje'=>'RESPUESTA_PUBLICA_A','id_ticket'=>$b['ticket'],'es_privado'=>1,'costo'=>999])[0]===303,'Respuesta queda en ticket del token e ignora campos administrativos');
comprobar((int)$pdo->query("SELECT id_ticket FROM comentarios WHERE comentario='RESPUESTA_PUBLICA_A'")->fetchColumn()===$idPublicoA,'Respuesta no cambia ticket por ID enviado');
[$status,$html]=solicitar($urlB,$anon);comprobar($status===200&&strpos($html,'asignada automáticamente')!==false&&strpos($html,'name="id_sucursal"')===false,'Portal con sede única la asigna automáticamente');
$datosB=['csrf'=>publico_csrf($html),'nombre'=>'Persona B','correo'=>'b@test.invalid','asunto'=>'PUBLICO_B','descripcion'=>'Solicitud B','id_tipo_caso'=>$tipoInicial,'codigo_equipo'=>'EQ_A'];
$res=solicitar($urlB,$anon,$datosB);comprobar($res[0]===303,'Equipo escrito de otra empresa no bloquea solicitud ni revela su existencia');$tokenB=token_redireccion($res);$tPublicoB=portal_ticket($core,$pdo,$tokenB);$idPublicoB=(int)$tPublicoB['id_ticket'];
comprobar($pdo->query('SELECT id_equipo FROM tickets WHERE id_ticket='.$idPublicoB)->fetchColumn()===null,'Código ajeno no vincula inventario');
[$status,$html]=solicitar($followA.'&id='.$idPublicoB,$anon);comprobar($status===200&&strpos($html,'PUBLICO_B')===false,'ID manipulado no cambia autorización del token');
comprobar(solicitar($base.'/inventario_ti/soporte/seguimiento.php?id='.$idPublicoA,$anon)[0]===404,'Número de ticket no autoriza seguimiento');
comprobar(solicitar($base.'/inventario_ti/soporte/seguimiento.php?token='.str_repeat('f',64),$anon)[0]===404,'Token aleatorio inválido rechazado');
foreach(['index.php','exportar_excel.php','exportar_pdf.php','ver_ticket.php?id='.$idPublicoA] as $ruta){comprobar(solicitar($base.'/inventario_ti/soporte/'.$ruta.(strpos($ruta,'?')===false?'?':'&').'token='.$tokenA,$anon)[0]===403,'Token público no autoriza ruta interna '.$ruta);}
$q=$pdo->prepare("INSERT INTO comentarios(id_ticket,id_autor,tipo_autor,comentario,es_privado,visible_portal) VALUES (?,0,'Agente',?,1,1)");$q->execute([$idPublicoA,'SECRETO_INTERNO_123']);$comentarioSecreto=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO comentarios(id_ticket,id_autor,tipo_autor,comentario,es_privado,visible_portal) VALUES (?,0,'Agente',?,0,0)")->execute([$idPublicoA,'LOG_ADMINISTRATIVO_COSTO_999']);
$pdo->exec('UPDATE tickets SET costo=98765.43 WHERE id_ticket='.$idPublicoA);
[$status,$html]=solicitar($followA,$anon);comprobar($status===200&&strpos($html,'SECRETO_INTERNO')===false&&strpos($html,'LOG_ADMINISTRATIVO')===false&&strpos($html,'98765')===false&&strpos($html,'Gestión de Costos')===false,'HTML público excluye notas, logs administrativos y costos');
[$status,$html]=solicitar($base.'/soporte/public/ver_ticket.php?id='.$idPublicoA,$sa);comprobar($status===200&&strpos($html,'SECRETO_INTERNO')===false,'Administrador del cliente no ve nota interna');
$core->exec("UPDATE usuario_roles SET id_rol=(SELECT id FROM roles WHERE nombre_rol='Auditor') WHERE id_usuario=".$a['usuario']);
[$status,$html]=solicitar($base.'/soporte/public/ver_ticket.php?id='.$idPublicoA,$sa);comprobar($status===200&&strpos($html,'SECRETO_INTERNO')===false,'Auditor del cliente no ve nota interna');
$core->exec("UPDATE usuario_roles SET id_rol=(SELECT id FROM roles WHERE nombre_rol='Administrador') WHERE id_usuario=".$a['usuario']);
comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$idPublicoA,$sa,['csrf'=>'token-prueba','agregar_comentario'=>1,'comentario'=>'Intento privado','es_privado'=>1])[0]===403,'Cliente no crea nota interna por POST manipulado');
[$status,$html]=solicitar($base.'/soporte/public/ver_ticket.php?id='.$idPublicoA,$ss);comprobar($status===200&&strpos($html,'SECRETO_INTERNO')!==false,'Operador global SmartTech ve nota interna');
[$status,$html]=solicitar($followA,$anon);$respuesta=solicitar_archivo($followA,$anon,['csrf'=>publico_csrf($html),'mensaje'=>'ARCHIVO_PUBLICO_A'],'mensaje.txt','ARCHIVO_PUBLICO_CONTENIDO');comprobar($respuesta[0]===303,'Respuesta pública admite adjunto válido');
$q=$pdo->prepare("SELECT * FROM archivos_adjuntos WHERE id_ticket=? AND nombre_original='mensaje.txt'");$q->execute([$idPublicoA]);$archivoPublico=$q->fetch();comprobar(strpos($archivoPublico['ruta_archivo'],'privado/')===0,'Archivo nuevo almacenado en raíz privada');
$downloadA=$base.'/inventario_ti/soporte/adjunto_publico.php?token='.$tokenA.'&id='.$archivoPublico['id_adjunto'];
[$status,$body]=solicitar($downloadA,$anon);comprobar($status===200&&$body==='ARCHIVO_PUBLICO_CONTENIDO','Token autorizado descarga su adjunto público');
comprobar(solicitar($base.'/inventario_ti/soporte/adjunto_publico.php?token='.$tokenB.'&id='.$archivoPublico['id_adjunto'],$anon)[0]===404,'Token B no descarga adjunto A');
comprobar(solicitar($base.'/inventario_ti/soporte/adjunto_publico.php?id='.$archivoPublico['id_adjunto'],$anon)[0]===404,'Descarga pública exige token válido');
$q=$pdo->prepare('INSERT INTO archivos_adjuntos(id_ticket,id_comentario,nombre_original,nombre_guardado,ruta_archivo,tipo_mime) VALUES (?,?,?,?,?,?)');$q->execute([$idPublicoA,$comentarioSecreto,'ARCHIVO_SECRETO.txt',$archivoPublico['nombre_guardado'],$archivoPublico['ruta_archivo'],'text/plain']);$archivoSecreto=(int)$pdo->lastInsertId();
comprobar(solicitar($base.'/inventario_ti/soporte/adjunto_publico.php?token='.$tokenA.'&id='.$archivoSecreto,$anon)[0]===404,'Token no descarga adjunto de nota interna de su ticket');
comprobar(solicitar($base.'/soporte/public/descargar_adjunto.php?id='.$archivoSecreto,$sa)[0]===404,'Administrador cliente no descarga nota interna');
[$status,$html]=solicitar($followA,$anon);comprobar(strpos($html,'ARCHIVO_SECRETO')===false,'Adjunto interno no aparece en HTML público');
function subir_seis(string $url,string $sesion,array $datos): int {
    global $bridgeHeader;$url=url_prueba($url);
    $b='multipart'.bin2hex(random_bytes(6));$body='';foreach($datos as $k=>$v){$body.="--$b\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";}
    for($i=0;$i<6;$i++){$body.="--$b\r\nContent-Disposition: form-data; name=\"adjuntos[]\"; filename=\"archivo$i.txt\"\r\nContent-Type: text/plain\r\n\r\nContenido de prueba\r\n";}$body.="--$b--\r\n";
    file_get_contents($url,false,stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'follow_location'=>0,'header'=>$bridgeHeader."Cookie: PHPSESSID=$sesion\r\nContent-Type: multipart/form-data; boundary=$b",'content'=>$body,'timeout'=>15]]));preg_match('/\s(\d{3})\s/',$http_response_header[0],$m);return(int)$m[1];
}
$antesInvalidos=(int)$pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
[$status,$html]=solicitar($urlA,$anon);$datos['csrf']=publico_csrf($html);$datos['id_sucursal']=$b['sucursal'];comprobar(solicitar($urlA,$anon,$datos)[0]===422,'Portal rechaza sucursal de empresa B');$datos['id_sucursal']=$a['sucursal'];
comprobar(subir_seis($urlA,$anon,$datos)===413,'Portal limita cantidad a cinco adjuntos');
$correoBueno=$datos['correo'];$datos['correo']='invalido';comprobar(solicitar($urlA,$anon,$datos)[0]===422,'Portal exige correo válido');$datos['correo']=$correoBueno;
comprobar(solicitar_archivo($urlA,$anon,$datos,'ataque.php','<?php echo 1;')[0]===422,'Portal rechaza archivo ejecutable');
comprobar(solicitar_archivo($urlA,$anon,$datos,'falso.pdf','Este archivo no es un PDF')[0]===422,'Portal rechaza extensión y contenido incompatibles');
comprobar(solicitar_archivo($urlA,$anon,$datos,'grande.txt',str_repeat('x',SOPORTE_ARCHIVO_BYTES+1))[0]===413,'Portal limita tamaño de archivo');
comprobar((int)$pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn()===$antesInvalidos,'Fallos de validación no crean tickets');
$pdo->exec('DELETE FROM soporte_limites_publicos');
comprobar(solicitar($base.'/inventario_ti/soporte/enlace_empresa.php',$ss,['csrf'=>'token-prueba','accion'=>'desactivar'])[0]===302,'Operador desactiva enlace de creación');
comprobar(solicitar($urlA,$anon)[0]===404,'Enlace desactivado no abre formulario');
comprobar(solicitar($urlA,$anon,$datos)[0]===404,'Enlace desactivado no guarda solicitudes');
comprobar(solicitar($followA,$anon)[0]===200,'Desactivar creación conserva seguimiento previo');
comprobar(solicitar($base.'/inventario_ti/soporte/enlace_empresa.php',$ss,['csrf'=>'token-prueba','accion'=>'renovar'])[0]===302,'Operador renueva enlace de creación');
comprobar(solicitar($urlA,$anon)[0]===404&&solicitar($followA,$anon)[0]===200,'Renovación invalida enlace anterior sin revocar seguimiento');
$q=$pdo->prepare('SELECT token_cifrado FROM soporte_enlaces_empresa WHERE id_empresa=?');$q->execute([$a['empresa']]);$renovado=portal_descifrar($q->fetchColumn());comprobar($renovado!==$enlaces['A']&&solicitar($base.'/inventario_ti/soporte/solicitud.php?enlace='.$renovado,$anon)[0]===200,'Enlace renovado distinto y utilizable');
$pdo->exec('UPDATE soporte_seguimiento SET vence_en=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id_ticket='.$idPublicoA);comprobar(solicitar($followA,$anon)[0]===404&&solicitar($downloadA,$anon)[0]===404,'Token caducado pierde seguimiento y descarga');
$pdo->exec('UPDATE soporte_seguimiento SET vence_en=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 90 DAY) WHERE id_ticket='.$idPublicoA);
comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$idPublicoA,$sa,['csrf'=>'token-prueba','revocar_seguimiento'=>1])[0]===403,'Cliente no revoca seguimiento');
comprobar(solicitar($base.'/soporte/public/ver_ticket.php?id='.$idPublicoA,$ss,['csrf'=>'token-prueba','revocar_seguimiento'=>1])[0]===302,'SmartTech revoca seguimiento individual');
comprobar(solicitar($followA,$anon)[0]===404&&solicitar($downloadA,$anon)[0]===404,'Token revocado pierde acceso y adjuntos');
// Empresa sin sucursales habilitadas: no listar sedes inactivas ni bloquear.
$core->exec("UPDATE sucursales SET estado='Inactivo' WHERE id_empresa=".$empresaVacia);
[$status,$html]=solicitar($base.'/inventario_ti/soporte/solicitud.php?enlace='.$enlaces['Vacia'],$anon);comprobar($status===200&&strpos($html,'Puede continuar sin sucursal')!==false,'Portal admite empresa sin sedes habilitadas');
$d=['csrf'=>publico_csrf($html),'nombre'=>'Sin sede','correo'=>'sin@test.invalid','asunto'=>'PUBLICO_SIN_SEDE','descripcion'=>'Sin empleados o inventario','id_tipo_caso'=>$tipoInicial];$res=solicitar($base.'/inventario_ti/soporte/solicitud.php?enlace='.$enlaces['Vacia'],$anon,$d);comprobar($res[0]===303,'Portal crea en empresa sin sedes habilitadas');$sinSede=portal_ticket($core,$pdo,token_redireccion($res));comprobar($pdo->query('SELECT id_sucursal FROM tickets WHERE id_ticket='.(int)$sinSede['id_ticket'])->fetchColumn()===null,'Ticket sin sedes guarda NULL');
// Contador compartido probado explícitamente (sin esperar una hora).
$_SERVER['REMOTE_ADDR']='127.0.0.1';$clave=hash_hmac('sha256','crear|127.0.0.1|',portal_clave());$q=$pdo->prepare('INSERT INTO soporte_limites_publicos(clave,ventana,solicitudes) VALUES (?,?,10) ON DUPLICATE KEY UPDATE ventana=VALUES(ventana),solicitudes=10');$q->execute([$clave,intdiv(time(),3600)]);
[$status,$html]=solicitar($urlB,$anon);$datosB['csrf']=publico_csrf($html);comprobar(solicitar($urlB,$anon,$datosB)[0]===429,'Límite persistente de creación por IP se aplica');$pdo->exec('DELETE FROM soporte_limites_publicos');
$followB=$base.'/inventario_ti/soporte/seguimiento.php?token='.$tokenB;
[$status,$html]=solicitar($followB,$anon);$csrfB=publico_csrf($html);
$clave=hash_hmac('sha256','responder|127.0.0.1|',portal_clave());$q=$pdo->prepare('INSERT INTO soporte_limites_publicos(clave,ventana,solicitudes) VALUES (?,?,30)');$q->execute([$clave,intdiv(time(),3600)]);
comprobar(solicitar($followB,$anon,['csrf'=>$csrfB,'mensaje'=>'Límite de respuestas'])[0]===429,'Límite de respuestas por IP se aplica');
$pdo->exec('DELETE FROM soporte_limites_publicos');$clave=hash_hmac('sha256','lectura|127.0.0.1|',portal_clave());$q->execute([$clave,intdiv(time(),60)]);$pdo->prepare('UPDATE soporte_limites_publicos SET solicitudes=60 WHERE clave=?')->execute([$clave]);
comprobar(solicitar($followB,$anon)[0]===429,'Límite de consultas públicas se aplica');$pdo->exec('DELETE FROM soporte_limites_publicos');
// Empleado autenticado: vínculo explícito y equipos activos propios.
$core->prepare('INSERT INTO usuarios(nombre,email,password,id_empresa,id_empleado) VALUES (?,?,?,?,?)')->execute(['Empleado real',$prefix.'emp@test.invalid',password_hash('Prueba123!',PASSWORD_DEFAULT),$a['empresa'],$a['empleado']]);$empleadoUsuario=(int)$core->lastInsertId();$core->exec("INSERT INTO usuario_roles(id_usuario,id_rol) SELECT $empleadoUsuario,id FROM roles WHERE nombre_rol='Empleado'");
$se=sesion_prueba($empleadoUsuario);$ctxEmpleado=nucleo_contexto($core,['user_id'=>$empleadoUsuario]);comprobar($ctxEmpleado['sucursal']===$a['sucursal'],'Empleado sin sede en cuenta deriva sede de su registro');
$core->prepare("INSERT INTO asignaciones(id_equipo,id_empleado,fecha_entrega,estado_asignacion,id_empresa) VALUES (?,?,NOW(),'Activa',?)")->execute([$a['equipo'],$a['empleado'],$a['empresa']]);
$core->prepare('INSERT INTO equipos(id_sucursal,codigo_inventario,id_tipo_equipo,id_marca,id_modelo,numero_serie,tipo_adquisicion,id_empresa) VALUES (?,?,?,?,?,?,?,?)')->execute([$a['sucursal'],'EQ_DEVUELTO',$a['tipo'],$a['marca'],$a['modelo'],'SERIE_DEVUELTO','Propio',$a['empresa']]);$devuelto=(int)$core->lastInsertId();
$core->prepare("INSERT INTO asignaciones(id_equipo,id_empleado,fecha_entrega,fecha_devolucion,estado_asignacion,id_empresa) VALUES (?,?,NOW(),NOW(),'Finalizada',?)")->execute([$devuelto,$a['empleado'],$a['empresa']]);
$core->prepare('INSERT INTO empleados(id_sucursal,dni,nombres,apellidos,id_empresa) VALUES (?,?,?,?,?)')->execute([$a['sucursal'],$prefix.'otro','Otro','Empleado',$a['empresa']]);$otroEmpleado=(int)$core->lastInsertId();
$core->prepare("INSERT INTO asignaciones(id_equipo,id_empleado,fecha_entrega,estado_asignacion,id_empresa) VALUES (?,?,NOW(),'Activa',?)")->execute([$devuelto,$otroEmpleado,$a['empresa']]);
[$status,$html]=solicitar($base.'/soporte/public/crear_ticket.php',$se);comprobar($status===200&&strpos($html,'value="'.$a['empleado'].'"')!==false&&strpos($html,'Sede A')!==false&&strpos($html,'EQ_A')!==false&&strpos($html,'EQ_DEVUELTO')===false&&strpos($html,'Persona no registrada')===false,'Formulario empleado precarga identidad/sede y solo asignación activa');
$de=['csrf'=>'token-prueba','id_tipo_caso'=>$tipoInicial,'asunto'=>'EMPLEADO_SIN_EQUIPO','descripcion'=>'Sin equipo','prioridad'=>'Media'];comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$se,$de)[0]===302,'Empleado solicita sin equipo ni IDs de identidad enviados');
$eTicket=$pdo->query("SELECT * FROM tickets WHERE asunto='EMPLEADO_SIN_EQUIPO'")->fetch();comprobar((int)$eTicket['id_solicitante']===$a['empleado']&&(int)$eTicket['id_sucursal']===$a['sucursal']&&(int)$eTicket['id_solicitante_usuario']===$empleadoUsuario&&$eTicket['id_equipo']===null,'Servidor persiste empleado/sede/creador derivados');
$de['id_equipo']=$a['equipo'];$de['asunto']='EMPLEADO_CON_EQUIPO';comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$se,$de)[0]===302,'Empleado selecciona equipo actualmente asignado');
$de['id_equipo']=$devuelto;comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$se,$de)[0]===403,'Empleado no selecciona equipo devuelto y asignado a otra persona');
$core->prepare("UPDATE asignaciones SET estado_asignacion='Finalizada',fecha_devolucion=NOW() WHERE id_equipo=? AND id_empleado=?")->execute([$a['equipo'],$a['empleado']]);$de['id_equipo']=$a['equipo'];
comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$se,$de)[0]===403,'Asignación revocada después de abrir formulario se rechaza al guardar');
$core->prepare("UPDATE asignaciones SET estado_asignacion='Activa',fecha_devolucion=NULL WHERE id_equipo=? AND id_empleado=?")->execute([$a['equipo'],$a['empleado']]);
$de['id_equipo']=$b['equipo'];comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$se,$de)[0]===404,'Empleado no selecciona equipo de empresa B');
$de['id_equipo']='';$de['id_solicitante']=$b['empleado'];comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$se,$de)[0]===403,'Empleado no suplanta otro solicitante');
[$status,$body,$headers]=solicitar($base.'/inventario_ti/login.php','',['email'=>$prefix.'emp@test.invalid','password'=>'Prueba123!']);preg_match_all('/PHPSESSID=([^;]+)/',implode("\n",$headers),$cookiesEmpleado);$cookieEmpleado=[null,end($cookiesEmpleado[1])];
comprobar($status===302&&!empty($cookieEmpleado[1])&&solicitar($base.'/inventario_ti/soporte/crear_ticket.php',$cookieEmpleado[1])[0]===200,'Login real de empleado conserva vínculo al abrir soporte');
$core->exec('UPDATE usuarios SET id_empleado=NULL WHERE id='.$empleadoUsuario);comprobar(solicitar($base.'/soporte/public/crear_ticket.php',$se)[0]===403,'Cuenta empleado sin vínculo explícito rechazada');
$pdo->exec('DELETE FROM soporte_limites_publicos');seleccion_staff($ss,$a['empresa']);
