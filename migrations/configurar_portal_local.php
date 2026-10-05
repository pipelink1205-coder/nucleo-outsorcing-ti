<?php
// Configura solo almacenamiento y clave; no conecta a ninguna base ni ejecuta migraciones.
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$root=__DIR__.'/../.local';if(!is_dir($root)){mkdir($root,0700,true);}
if(!is_file($root.'/portal.key')){$h=fopen($root.'/portal.key','x');fwrite($h,bin2hex(random_bytes(32)).PHP_EOL);fclose($h);chmod($root.'/portal.key',0600);}
if(!is_dir($root.'/soporte_adjuntos')){mkdir($root.'/soporte_adjuntos',0700,true);}
file_put_contents($root.'/.htaccess','Require all denied');echo "Clave y almacenamiento local preparados, sin tocar bases.\n";
