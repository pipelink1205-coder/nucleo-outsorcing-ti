<?php
require_once __DIR__ . '/../includes/auth_check.php';
try {
    $q = $pdo->prepare('SELECT a.*, c.es_privado, c.id_ticket AS ticket_comentario FROM archivos_adjuntos a LEFT JOIN comentarios c ON c.id_comentario=a.id_comentario WHERE a.id_adjunto=?');
    $q->execute([(int) ($_GET['id'] ?? 0)]);
    $a = $q->fetch();
    if (!$a) { throw new RuntimeException('Archivo no encontrado', 404); }
    soporte_ticket($pdo, $soporte_ctx, (int) $a['id_ticket']);
    if ($a['id_comentario'] && (int) $a['ticket_comentario'] !== (int) $a['id_ticket']) { throw new RuntimeException('Archivo no encontrado', 404); }
    if ($a['es_privado'] && $soporte_ctx['rol'] === 'empleado') { throw new RuntimeException('Archivo no encontrado', 404); }
    $raiz = realpath(soporte_raiz_adjuntos());
    $ruta = str_replace('\\', '/', $a['ruta_archivo']);
    if (strpos($ruta, 'uploads/') !== 0) { throw new RuntimeException('Archivo no encontrado', 404); }
    $path = $raiz ? realpath($raiz . '/' . substr($ruta, 8)) : false;
    if (!$raiz || !$path || strpos($path, $raiz . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) { throw new RuntimeException('Archivo no encontrado', 404); }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', basename($a['nombre_original'])) . '"');
    readfile($path);
} catch (Throwable $e) { nucleo_error($e); }
