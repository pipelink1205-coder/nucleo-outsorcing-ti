<?php
// Soportar tanto aliases /inventario_ti + /inventario_ti/soporte como el checkout completo.
function bases_modulos(): array
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/public/index.php');
    foreach (['/soporte/public/' => true, '/soporte/' => false] as $segmento => $checkout) {
        $pos = strpos($script, $segmento);
        if ($pos !== false) {
            $base = substr($script, 0, $pos);
            return [$base . ($checkout ? '/public' : ''), $base . ($checkout ? '/soporte/public' : '/soporte')];
        }
    }
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/.');
    if (substr($base, -7) === '/public') {
        return [$base, substr($base, 0, -7) . '/soporte/public'];
    }
    if (substr($base, -9) === '/includes') {
        $base = substr($base, 0, -9);
        return [$base . '/public', $base . '/soporte/public'];
    }
    return [$base, $base . '/soporte'];
}

function inventario_url(string $ruta): string { return bases_modulos()[0] . '/' . ltrim($ruta, '/'); }
function soporte_url(string $ruta): string { return bases_modulos()[1] . '/' . ltrim($ruta, '/'); }
