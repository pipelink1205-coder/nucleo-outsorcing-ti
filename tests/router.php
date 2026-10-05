<?php
// Servidor de pruebas: reproduce los aliases locales sin tocar Apache ni bases reales.
$root = dirname(__DIR__);
$ruta = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (!preg_match('~^/[a-zA-Z0-9_./-]*$~', $ruta) || strpos($ruta, '..') !== false) { http_response_code(404); exit; }
if (preg_match('~/(uploads|\.local|migrations|tests|vendor)/|\.sql$~i', $ruta)) { http_response_code(403); exit; }
if (strpos($ruta, '/inventario_ti/soporte/') === 0) { $relativo = '/soporte/public/' . substr($ruta, strlen('/inventario_ti/soporte/')); }
elseif (strpos($ruta, '/inventario_ti/') === 0) { $relativo = '/public/' . substr($ruta, strlen('/inventario_ti/')); }
else { $relativo = $ruta; }
$archivo = realpath($root . $relativo);
if (!$archivo || !is_file($archivo) || strpos($archivo, realpath($root) . DIRECTORY_SEPARATOR) !== 0) { http_response_code(404); exit; }
if (substr($archivo,-4) === '.php') {
    $_SERVER['SCRIPT_NAME'] = $ruta;
    $_SERVER['PHP_SELF'] = $ruta;
    $_SERVER['SCRIPT_FILENAME'] = $archivo;
    chdir(dirname($archivo));
    require $archivo;
} else {
    $mime = ['css'=>'text/css','png'=>'image/png','js'=>'application/javascript'][pathinfo($archivo,PATHINFO_EXTENSION)] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    readfile($archivo);
}
