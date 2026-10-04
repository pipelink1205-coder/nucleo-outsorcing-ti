<?php
// Configuración de la conexión a la base de datos
$host = 'localhost';
$dbname = 'soporte_db'; // El nombre que usaste al crear la BD
$username = 'root'; // Usuario por defecto en XAMPP
$password = '';     // Contraseña por defecto en XAMPP es vacía
$charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=$charset", $username, $password, $options);
} catch (PDOException $e) {
    throw new PDOException($e->getMessage(), (int)$e->getCode());
}
?>