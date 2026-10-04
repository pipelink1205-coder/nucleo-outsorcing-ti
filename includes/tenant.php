<?php

function es_operador(): bool
{
    return strtolower($_SESSION['user_rol'] ?? '') === 'operador';
}

function rol_actual(): string
{
    return strtolower($_SESSION['user_rol'] ?? '');
}

function empresa_id_activa(): ?int
{
    if (!empty($_SESSION['user_empresa_id'])) {
        return (int) $_SESSION['user_empresa_id'];
    }
    if (!empty($_SESSION['empresa_activa_id'])) {
        return (int) $_SESSION['empresa_activa_id'];
    }
    return null;
}

function puede_escribir(): bool
{
    return in_array(rol_actual(), ['operador', 'administrador'], true);
}

function slug_empresa(string $nombre): string
{
    $map = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u'];
    $s = strtr(mb_strtolower(trim($nombre), 'UTF-8'), $map);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'empresa';
}

function tenant_bloquear_escritura(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    if (empty($_SESSION['user_id']) || puede_escribir()) {
        return;
    }
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($script, ['login.php', 'cambiar_password.php'], true)) {
        return;
    }
    header('Location: index.php');
    exit;
}
