app_task-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         8.4.3 - MySQL Community Server - GPL
-- SO del servidor:              Win64
-- HeidiSQL Versión:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Volcando estructura de base de datos para inventario_ti
CREATE DATABASE IF NOT EXISTS `inventario_ti` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `inventario_ti`;

-- Volcando estructura para tabla inventario_ti.areas
CREATE TABLE IF NOT EXISTS `areas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `estado` enum('Activo','Inactivo') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla inventario_ti.areas: ~0 rows (aproximadamente)
DELETE FROM `areas`;

-- Volcando estructura para tabla inventario_ti.asignaciones
CREATE TABLE IF NOT EXISTS `asignaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_equipo` int NOT NULL,
  `id_empleado` int NOT NULL,
  `fecha_entrega` datetime NOT NULL,
  `fecha_devolucion` datetime DEFAULT NULL,
  `estado_asignacion` enum('Activa','Finalizada') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Activa',
  `observaciones_entrega` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `observaciones_devolucion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `acta_firmada_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `acta_devolucion_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `imagen_devolucion_1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `imagen_devolucion_2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `imagen_devolucion_3` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_equipo_asignado` (`id_equipo`),
  KEY `fk_empleado_asignado` (`id_empleado`),
  CONSTRAINT `fk_empleado_asignado` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_equipo_asignado` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla inventario_ti.asignaciones: ~0 rows (aproximadamente)
DELETE FROM `asignaciones`;

-- Volcando estructura para tabla inventario_ti.bajas
CREATE TABLE IF NOT EXISTS `bajas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_equipo` int NOT NULL,
  `fecha_baja` date NOT NULL,
  `motivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `acta_baja_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion_motivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `id_usuario_responsable` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_equipo_baja` (`id_equipo`),
  CONSTRAINT `fk_equipo_baja` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla inventario_ti.bajas: ~0 rows (aproximadamente)
DELETE FROM `bajas`;

-- Volcando estructura para tabla inventario_ti.cargos
CREATE TABLE IF NOT EXISTS `cargos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_area` int NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `estado` enum('Activo','Inactivo') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`),
  KEY `fk_cargo_area` (`id_area`),
  CONSTRAINT `fk_cargo_area` FOREIGN KEY (`id_area`) REFERENCES `areas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla inventario_ti.cargos: ~0 rows (aproximadamente)
DELETE FROM `cargos`;

-- Volcando estructura para tabla inventario_ti.configuracion
CREATE TABLE IF NOT EXISTS `configuracion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clave` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `valor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave_unica` (`clave`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla inventario_ti.configuracion: ~0 rows (aproximadamente)
DELETE FROM `configuracion`;
INSERT INTO `configuracion` (`id`, `clave`, `valor`) VALUES
	(1, 'moneda_simbolo', 'S/');

-- Volcando estructura para tabla inventario_ti.empleados
CREATE TABLE IF NOT EXISTS `empleados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_sucursal` int NOT NULL,
  `dni` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nombres` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `apellidos` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_cargo` int DEFAULT NULL,
  `id_area` int DEFAULT NULL,
  `estado` enum('Activo','Inactivo') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `dni` (`dni`),
  KEY `fk_empleado_cargo` (`id_cargo`),
  KEY `fk_empleado_area` (`id_area`),
  KEY `fk_empleado_sucursal` (`id_sucursal`),
  CONSTRAINT `fk_empleado_area` FOREIGN KEY (`id_area`) REFERENCES `areas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_empleado_cargo` FOREIGN KEY (`id_cargo`) REFERENCES `cargos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_empleado_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla inventario_ti.empleados: ~0 rows (aproximadamente)
DELETE FROM `empleados`;

-- Volcando estructura para tabla inventario_ti.equipos
CREATE TABLE IF NOT EXISTS `equipos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_sucursal` int NOT NULL,
  `codigo_inventario` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_tipo_equipo` int NOT NULL,
  `id_marca` int NOT NULL,
  `id_modelo` int NOT NULL,
  `numero_serie` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `caracteristicas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `tipo_adquisicion` enum('Propio','Arrendado','Prestamo') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fecha_adquisicion` date DEFAULT NULL,
  `proveedor` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` enum('Disponible','Asignado','En Reparacion','De Baja') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Disponible',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_inventario` (`codigo_inventario`),
  UNIQUE KEY `numero_serie` (`numero_serie`),
  KEY `fk_equipo_tipo` (`id_tipo_equipo`),
  KEY `fk_equipo_marca` (`id_marca`),
  KEY `fk_equipo_modelo` (`id_modelo`),
  KEY `fk_equipo_sucursal` (`id_sucursal`),
  CONSTRAINT `fk_equipo_marca` FOREIGN KEY (`id_marca`) REFERENCES `marcas` (`id`),
  CONSTRAINT `fk_equipo_modelo` FOREIGN KEY (`id_modelo`) REFERENCES `modelos` (`id`),
  CONSTRAINT `fk_equipo_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id`),
  CONSTRAINT `fk_equipo_tipo` FOREIGN KEY (`id_tipo_equipo`) REFERENCES `tipos_equipo` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla inventario_ti.equipos: ~1 rows (aproximadamente)
DELETE FROM `equipos`;

-- Volcando estructura para tabla inventario_ti.marcas
CREATE TABLE IF NOT EXISTS `marcas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `estado` enum('Activo','Inactivo') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla inventario_ti.marcas: ~0 rows (aproximadamente)
DELETE FROM `marcas`;

-- Volcando estructura para tabla inventario_ti.modelos
CREATE TABLE IF NOT EXISTS `modelos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_marca` int NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `estado` enum('Activo','Inactivo') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id`),
  KEY `fk_modelo_marca` (`id_marca`),
  CONSTRAINT `fk_modelo_marca` FOREIGN KEY (`id_marca`) REFERENCES `marcas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla inventario_ti.modelos: ~0 rows (aproximadamente)
DELETE FROM `modelos`;

-- Volcando estructura para tabla inventario_ti.reparaciones
CREATE TABLE IF NOT EXISTS `reparaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_equipo` int NOT NULL,
  `fecha_ingreso` date NOT NULL,
  `fecha_salida` date DEFAULT NULL,
  `motivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `proveedor_servicio` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `costo` decimal(10,2) DEFAULT '0.00',
  `observaciones_salida` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `estado_reparacion` enum('En Proceso','Finalizada') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'En Proceso',
  PRIMARY KEY (`id`),
  KEY `id_equipo` (`id_equipo`),
  CONSTRAINT `reparaciones_ibfk_1` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla inventario_ti.reparaciones: ~0 rows (aproximadamente)
DELETE FROM `reparaciones`;

-- Volcando estructura para tabla inventario_ti.roles
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre_rol` (`nombre_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla inventario_ti.roles: ~2 rows (aproximadamente)
DELETE FROM `roles`;
INSERT INTO `roles` (`id`, `nombre_rol`) VALUES
	(1, 'Administrador'),
	(2, 'Usuario');

-- Volcando estructura para tabla inventario_ti.sucursales
CREATE TABLE IF NOT EXISTS `sucursales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `direccion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `telefono` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` enum('Activo','Inactivo') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla inventario_ti.sucursales: ~0 rows (aproximadamente)
DELETE FROM `sucursales`;

-- Volcando estructura para tabla inventario_ti.tipos_equipo
CREATE TABLE IF NOT EXISTS `tipos_equipo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `estado` enum('Activo','Inactivo') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla inventario_ti.tipos_equipo: ~0 rows (aproximadamente)
DELETE FROM `tipos_equipo`;

-- Volcando estructura para tabla inventario_ti.usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_sucursal` int DEFAULT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_usuario_sucursal` (`id_sucursal`),
  CONSTRAINT `fk_usuario_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla inventario_ti.usuarios: ~2 rows (aproximadamente)
DELETE FROM `usuarios`;
INSERT INTO `usuarios` (`id`, `id_sucursal`, `nombre`, `email`, `password`, `activo`, `fecha_creacion`) VALUES
	(1, NULL, 'Admin', 'admin@correo.com', '$2y$10$67jd7Gb0Tlp/FDj/43CQdOLmEj3eKVnP7uLeJ4X9Zp7y3BnOdywgW', 1, '2025-08-03 06:56:20'),
	(2, NULL, 'usuario', 'usuario@correo.com', '$2y$10$Wrwl40s7ITMYkNmuzYtl/.Alx4Wh0fdPtz7mJg7mj5yBm5ljRDwUy', 1, '2025-12-16 16:33:54');

-- Volcando estructura para tabla inventario_ti.usuario_roles
CREATE TABLE IF NOT EXISTS `usuario_roles` (
  `id_usuario` int NOT NULL,
  `id_rol` int NOT NULL,
  PRIMARY KEY (`id_usuario`,`id_rol`),
  KEY `fk_rol` (`id_rol`),
  CONSTRAINT `fk_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla inventario_ti.usuario_roles: ~2 rows (aproximadamente)
DELETE FROM `usuario_roles`;
INSERT INTO `usuario_roles` (`id_usuario`, `id_rol`) VALUES
	(1, 1),
	(2, 2);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
