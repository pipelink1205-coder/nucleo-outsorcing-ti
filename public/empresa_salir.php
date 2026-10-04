<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
if (!es_operador()) {
    header('Location: index.php');
    exit();
}
unset($_SESSION['empresa_activa_id'], $_SESSION['empresa_nombre'], $_SESSION['empresa_slug']);
header('Location: plataforma.php');
exit();
