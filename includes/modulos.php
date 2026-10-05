<?php
require_once __DIR__ . '/rutas.php';

function modulos_disponibles(): array
{
    $rol = rol_actual();
    $modulos = [
        [
            'id' => 'inventario',
            'titulo' => 'Inventario',
            'texto' => 'Levantamiento de equipos, empleados, asignaciones y catálogos de la empresa.',
            'href' => 'index.php',
            'icono' => 'bi-laptop',
            'roles' => ['operador', 'administrador', 'auditor', 'empleado'],
        ],
        [
            'id' => 'tickets',
            'titulo' => 'Tickets',
            'texto' => 'Solicitudes y soporte de la operación que le damos a esta empresa.',
            'href' => soporte_url('entrar.php'),
            'icono' => 'bi-ticket-perforated',
            'roles' => ['operador', 'administrador', 'auditor', 'empleado'],
        ],
        [
            'id' => 'informes',
            'titulo' => 'Informes de servicio',
            'texto' => 'Los informes que el equipo de outsourcing prepara y presenta. El cliente no entra aquí.',
            'href' => 'informes.php',
            'icono' => 'bi-file-earmark-bar-graph',
            'roles' => ['operador'],
        ],
    ];

    return array_values(array_filter($modulos, function ($modulo) use ($rol) {
        return in_array($rol, $modulo['roles'], true);
    }));
}

function etiqueta_rol(string $rol): string
{
    $etiquetas = [
        'operador' => 'Operador de outsourcing',
        'administrador' => 'Administrador de la empresa',
        'auditor' => 'Auditor de la empresa',
        'empleado' => 'Empleado de la empresa',
    ];
    return $etiquetas[$rol] ?? ucfirst($rol);
}
