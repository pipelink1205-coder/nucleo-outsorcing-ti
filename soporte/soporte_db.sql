-- --------------------------------------------------------
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


-- Volcando estructura de base de datos para soporte_db
CREATE DATABASE IF NOT EXISTS `soporte_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `soporte_db`;

-- Volcando estructura para tabla soporte_db.adjuntos
CREATE TABLE IF NOT EXISTS `adjuntos` (
  `id_adjunto` int NOT NULL AUTO_INCREMENT,
  `id_ticket` int NOT NULL,
  `id_comentario` int DEFAULT NULL,
  `nombre_archivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ruta_archivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_adjunto`),
  KEY `id_ticket` (`id_ticket`),
  KEY `id_comentario` (`id_comentario`),
  CONSTRAINT `adjuntos_ibfk_1` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`),
  CONSTRAINT `adjuntos_ibfk_2` FOREIGN KEY (`id_comentario`) REFERENCES `comentarios` (`id_comentario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla soporte_db.adjuntos: ~0 rows (aproximadamente)
DELETE FROM `adjuntos`;

-- Volcando estructura para tabla soporte_db.agentes
CREATE TABLE IF NOT EXISTS `agentes` (
  `id_agente` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `puesto` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_contratacion` date DEFAULT NULL,
  PRIMARY KEY (`id_agente`),
  UNIQUE KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `agentes_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla soporte_db.agentes: ~4 rows (aproximadamente)
DELETE FROM `agentes`;
INSERT INTO `agentes` (`id_agente`, `id_usuario`, `puesto`, `fecha_contratacion`) VALUES
	(1, 3, 'Agente de Soporte', '2025-08-29'),
	(2, 4, 'Agente de Soporte', '2025-08-29'),
	(3, 5, 'Supervisor', '2025-08-29'),
	(4, 2, 'Administrador', '2025-08-29');

-- Volcando estructura para tabla soporte_db.archivos_adjuntos
CREATE TABLE IF NOT EXISTS `archivos_adjuntos` (
  `id_adjunto` int NOT NULL AUTO_INCREMENT,
  `id_ticket` int NOT NULL,
  `id_comentario` int DEFAULT NULL,
  `nombre_original` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nombre_guardado` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ruta_archivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tipo_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fecha_subida` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_adjunto`),
  KEY `id_ticket` (`id_ticket`),
  CONSTRAINT `archivos_adjuntos_ibfk_1` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla soporte_db.archivos_adjuntos: ~0 rows (aproximadamente)
DELETE FROM `archivos_adjuntos`;

-- Volcando estructura para tabla soporte_db.clientes
CREATE TABLE IF NOT EXISTS `clientes` (
  `id_cliente` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `correo_electronico` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `empresa` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pais` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ciudad` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `whatsapp` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telegram` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `correo_electronico` (`correo_electronico`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla soporte_db.clientes: ~2 rows (aproximadamente)
DELETE FROM `clientes`;

-- Volcando estructura para tabla soporte_db.comentarios
CREATE TABLE IF NOT EXISTS `comentarios` (
  `id_comentario` int NOT NULL AUTO_INCREMENT,
  `id_ticket` int NOT NULL,
  `id_autor` int NOT NULL,
  `tipo_autor` enum('Agente','Cliente') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `comentario` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `es_privado` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id_comentario`),
  KEY `id_ticket` (`id_ticket`),
  CONSTRAINT `comentarios_ibfk_1` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla soporte_db.comentarios: ~7 rows (aproximadamente)
DELETE FROM `comentarios`;

-- Volcando estructura para tabla soporte_db.roles
CREATE TABLE IF NOT EXISTS `roles` (
  `id_rol` int NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre_rol` (`nombre_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla soporte_db.roles: ~3 rows (aproximadamente)
DELETE FROM `roles`;
INSERT INTO `roles` (`id_rol`, `nombre_rol`, `descripcion`) VALUES
	(1, 'Administrador', 'Acceso total al sistema, gestión de usuarios y configuraciones.'),
	(2, 'Agente de Soporte', 'Puede gestionar tickets asignados y ver los de su equipo.'),
	(3, 'Supervisor', 'Puede ver todos los tickets y generar reportes, pero no gestiona usuarios.');

-- Volcando estructura para tabla soporte_db.tickets
CREATE TABLE IF NOT EXISTS `tickets` (
  `id_ticket` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int NOT NULL,
  `id_agente_asignado` int DEFAULT NULL,
  `id_tipo_caso` int DEFAULT NULL,
  `asunto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `estado` enum('Abierto','En Progreso','En Espera','Resuelto','Cerrado','Anulado') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Abierto',
  `prioridad` enum('Baja','Media','Alta','Urgente') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Media',
  `fecha_vencimiento` datetime DEFAULT NULL,
  `costo` decimal(10,2) DEFAULT '0.00',
  `moneda` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'USD',
  `estado_facturacion` enum('Pendiente','Facturado','Pagado') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendiente',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultima_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `medio_pago` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id_ticket`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_agente_asignado` (`id_agente_asignado`),
  KEY `id_tipo_caso` (`id_tipo_caso`),
  CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`id_agente_asignado`) REFERENCES `agentes` (`id_agente`),
  CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`id_tipo_caso`) REFERENCES `tiposdecaso` (`id_tipo_caso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla soporte_db.tickets: ~2 rows (aproximadamente)
DELETE FROM `tickets`;

-- Volcando estructura para tabla soporte_db.tiposdecaso
CREATE TABLE IF NOT EXISTS `tiposdecaso` (
  `id_tipo_caso` int NOT NULL AUTO_INCREMENT,
  `nombre_tipo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_tipo_caso`),
  UNIQUE KEY `nombre_tipo` (`nombre_tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla soporte_db.tiposdecaso: ~5 rows (aproximadamente)
DELETE FROM `tiposdecaso`;

-- Volcando estructura para tabla soporte_db.usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id_usuario` int NOT NULL AUTO_INCREMENT,
  `id_rol` int NOT NULL,
  `nombre_completo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `whatsapp` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telegram` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ruta_foto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `email` (`email`),
  KEY `id_rol` (`id_rol`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla soporte_db.usuarios: ~4 rows (aproximadamente)
DELETE FROM `usuarios`;
INSERT INTO `usuarios` (`id_usuario`, `id_rol`, `nombre_completo`, `email`, `telefono`, `whatsapp`, `telegram`, `ruta_foto`, `password_hash`, `activo`, `fecha_creacion`) VALUES
	(2, 1, 'Administrador del Negocio', 'admin@correo.com', '', '', '', 'uploads/avatars/688ae90605525_usuario1.jpg', '$2y$10$AVfKtFBS05wnc99PLQwSOeakMlCYmTZ5Jk1jkaMsruN2J77fOon8i', 1, '2025-07-27 05:53:49'),
	(3, 2, 'Soporte de campo 1', 'soporte1@correo.com', '', '', '', 'uploads/avatars/688ae92d7e3c5_usuarios2.png', '$2y$10$u37WkUxy4AXeEDd9rMkc6eCGr3jCs53H2M0CTTOeDR8dP/xMAoJlW', 1, '2025-07-27 06:18:50'),
	(4, 2, 'Soporte de campo 2', 'soporte2@correo.com', '', '', '', 'uploads/avatars/688ae937b55b0_usuarios2.png', '$2y$10$KuQA7by/s34bMW9TFqGFp.v0yHZhnHzyZPulqsGvWXnQzW5BFLJGC', 1, '2025-07-27 17:21:40'),
	(5, 3, 'supervisor', 'supervisor@correo.com', '', '', '', 'uploads/avatars/6916d449cd175_688ae937b55b0_usuarios2.png', '$2y$10$32OcKTF9skC3M7HjWxmI/u0zzkLs45IL5.PVe97rDw5xBZLr1ELru', 1, '2025-07-28 09:14:07');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
