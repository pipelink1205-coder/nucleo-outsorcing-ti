<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__.'/../includes/adjuntos.php';
try {
    $q = $pdo->prepare('SELECT a.*, c.es_privado, c.id_ticket AS ticket_comentario FROM archivos_adjuntos a LEFT JOIN comentarios c ON c.id_comentario=a.id_comentario WHERE a.id_adjunto=?');
    $q->execute([(int) ($_GET['id'] ?? 0)]);
    $a = $q->fetch();
    if (!$a) { throw new RuntimeException('Archivo no encontrado', 404); }
    soporte_ticket($pdo, $soporte_ctx, (int) $a['id_ticket']);
    if ($a['id_comentario'] && (int) $a['ticket_comentario'] !== (int) $a['id_ticket']) { throw new RuntimeException('Archivo no encontrado', 404); }
    if ($a['es_privado'] && !soporte_notas_internas($soporte_ctx)) { throw new RuntimeException('Archivo no encontrado', 404); }
    soporte_emitir_archivo($a);
} catch (Throwable $e) { nucleo_error($e); }
