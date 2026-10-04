<br />
<b>Deprecated</b>:  addslashes(): Passing null to parameter #1 ($string) of type string is deprecated in <b>C:\laragon\www\sistema_soporte\public\copia_seguridad.php</b> on line <b>44</b><br />
<br />
<b>Deprecated</b>:  addslashes(): Passing null to parameter #1 ($string) of type string is deprecated in <b>C:\laragon\www\sistema_soporte\public\copia_seguridad.php</b> on line <b>44</b><br />
<br />
<b>Deprecated</b>:  addslashes(): Passing null to parameter #1 ($string) of type string is deprecated in <b>C:\laragon\www\sistema_soporte\public\copia_seguridad.php</b> on line <b>44</b><br />
<br />
<b>Deprecated</b>:  addslashes(): Passing null to parameter #1 ($string) of type string is deprecated in <b>C:\laragon\www\sistema_soporte\public\copia_seguridad.php</b> on line <b>44</b><br />
<br />
<b>Deprecated</b>:  addslashes(): Passing null to parameter #1 ($string) of type string is deprecated in <b>C:\laragon\www\sistema_soporte\public\copia_seguridad.php</b> on line <b>44</b><br />
<br />
<b>Deprecated</b>:  addslashes(): Passing null to parameter #1 ($string) of type string is deprecated in <b>C:\laragon\www\sistema_soporte\public\copia_seguridad.php</b> on line <b>44</b><br />
<br />
<b>Deprecated</b>:  addslashes(): Passing null to parameter #1 ($string) of type string is deprecated in <b>C:\laragon\www\sistema_soporte\public\copia_seguridad.php</b> on line <b>44</b><br />
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `adjuntos`;
CREATE TABLE `adjuntos` (
  `id_adjunto` int NOT NULL AUTO_INCREMENT,
  `id_ticket` int NOT NULL,
  `id_comentario` int DEFAULT NULL,
  `nombre_archivo` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `ruta_archivo` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_adjunto`),
  KEY `id_ticket` (`id_ticket`),
  KEY `id_comentario` (`id_comentario`),
  CONSTRAINT `adjuntos_ibfk_1` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`),
  CONSTRAINT `adjuntos_ibfk_2` FOREIGN KEY (`id_comentario`) REFERENCES `comentarios` (`id_comentario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



DROP TABLE IF EXISTS `agentes`;
CREATE TABLE `agentes` (
  `id_agente` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `puesto` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_contratacion` date DEFAULT NULL,
  PRIMARY KEY (`id_agente`),
  UNIQUE KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `agentes_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `agentes` VALUES("1","3","Agente de Soporte","2025-08-29");
INSERT INTO `agentes` VALUES("2","4","Agente de Soporte","2025-08-29");
INSERT INTO `agentes` VALUES("3","5","Supervisor","2025-08-29");
INSERT INTO `agentes` VALUES("4","2","Administrador","2025-08-29");


DROP TABLE IF EXISTS `archivos_adjuntos`;
CREATE TABLE `archivos_adjuntos` (
  `id_adjunto` int NOT NULL AUTO_INCREMENT,
  `id_ticket` int NOT NULL,
  `id_comentario` int DEFAULT NULL,
  `nombre_original` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `nombre_guardado` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `ruta_archivo` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `tipo_mime` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `fecha_subida` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_adjunto`),
  KEY `id_ticket` (`id_ticket`),
  CONSTRAINT `archivos_adjuntos_ibfk_1` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `archivos_adjuntos` VALUES("1","2","5","SuperStore DataSet.xlsx","ticket2_692233043c3376.31290327.xlsx","uploads/ticket2_692233043c3376.31290327.xlsx","application/vnd.openxmlformats-officedocument.spreadsheetml.sheet","2025-11-22 17:02:44");
INSERT INTO `archivos_adjuntos` VALUES("2","3","11","2025-05-20_21h16_13.png","ticket3_692b1eb7ae0449.36300730.png","uploads/ticket3_692b1eb7ae0449.36300730.png","image/png","2025-11-29 11:26:31");


DROP TABLE IF EXISTS `clientes`;
CREATE TABLE `clientes` (
  `id_cliente` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `correo_electronico` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `empresa` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pais` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ciudad` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `whatsapp` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telegram` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `correo_electronico` (`correo_electronico`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `clientes` VALUES("1","CARLOS RAMIREZ","carlosramirez@correo.com","12121213","EMPRESA 1","PERÚ","ICA","966666666","X12","1","2025-11-14 02:05:26");
INSERT INTO `clientes` VALUES("2","MARIA SOLIS","mariasolis@correo.com","23232323","EMPRESA2","PERÚ","LIMA","","","1","2025-11-22 16:54:21");


DROP TABLE IF EXISTS `comentarios`;
CREATE TABLE `comentarios` (
  `id_comentario` int NOT NULL AUTO_INCREMENT,
  `id_ticket` int NOT NULL,
  `id_autor` int NOT NULL,
  `tipo_autor` enum('Agente','Cliente') COLLATE utf8mb4_general_ci NOT NULL,
  `comentario` text COLLATE utf8mb4_general_ci NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `es_privado` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id_comentario`),
  KEY `id_ticket` (`id_ticket`),
  CONSTRAINT `comentarios_ibfk_1` FOREIGN KEY (`id_ticket`) REFERENCES `tickets` (`id_ticket`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `comentarios` VALUES("1","1","1","Cliente","Ticket creado con la siguiente descripción:\n\nno se puede emitir facturas","2025-11-14 02:06:34","0");
INSERT INTO `comentarios` VALUES("2","2","2","Cliente","Ticket creado con la siguiente descripción:\n\nEl sistema no permite generar facturación electrónica, sale error","2025-11-22 16:57:55","0");
INSERT INTO `comentarios` VALUES("3","2","4","Agente","Ticket reasignado de \'Nadie\' a \'Soporte de campo 2\' por Administrador del Negocio.","2025-11-22 16:59:43","1");
INSERT INTO `comentarios` VALUES("4","2","4","Agente","Estado cambiado a \'En Progreso\' por Administrador del Negocio.\n\nEstoy procediendo a realizar la atención de la tarea indicada","2025-11-22 17:01:18","0");
INSERT INTO `comentarios` VALUES("5","2","4","Agente","Se realizó la prueba de validación 1- resuelto","2025-11-22 17:02:44","0");
INSERT INTO `comentarios` VALUES("6","2","4","Agente","Estado cambiado a \'Resuelto\' por Administrador del Negocio.\n\nLa tarea ha sido resuelta. El ciente realiza las validaciones del caso. Todo conforme","2025-11-22 17:03:53","0");
INSERT INTO `comentarios` VALUES("7","2","4","Agente","Se registró el pago del ticket por Administrador del Negocio con los siguientes detalles:\n- Monto: PEN 150.00\n- Medio de Pago: Efectivo\n- Estado: Pagado","2025-11-22 17:05:34","1");
INSERT INTO `comentarios` VALUES("8","3","2","Cliente","Ticket creado con la siguiente descripción:\n\nEl sistema no permite generar la facturación electrónica, genera error. El cliente indica atención inmediata","2025-11-29 11:21:14","0");
INSERT INTO `comentarios` VALUES("9","3","4","Agente","Ticket reasignado de \'Nadie\' a \'Soporte de campo 1\' por Administrador del Negocio.","2025-11-29 11:22:17","1");
INSERT INTO `comentarios` VALUES("10","3","4","Agente","Estado cambiado a \'En Progreso\' por Administrador del Negocio.\n\nEstoy procediendo a resolver la atención de la tarea reportada","2025-11-29 11:24:41","0");
INSERT INTO `comentarios` VALUES("11","3","4","Agente","Se realiza la validación de prueba1 - resuelto","2025-11-29 11:26:31","0");
INSERT INTO `comentarios` VALUES("12","3","4","Agente","Estado cambiado a \'Resuelto\' por Administrador del Negocio.\n\nLa tarea ha sido resuelta, el cliente realiza las validaciones del caso. Todo conforme","2025-11-29 11:27:04","0");
INSERT INTO `comentarios` VALUES("13","3","4","Agente","Se registró el pago del ticket por Administrador del Negocio con los siguientes detalles:\n- Monto: PEN 150.00\n- Medio de Pago: Efectivo\n- Estado: Pagado","2025-11-29 11:27:49","1");


DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id_rol` int NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre_rol` (`nombre_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `roles` VALUES("1","Administrador","Acceso total al sistema, gestión de usuarios y configuraciones.");
INSERT INTO `roles` VALUES("2","Agente de Soporte","Puede gestionar tickets asignados y ver los de su equipo.");
INSERT INTO `roles` VALUES("3","Supervisor","Puede ver todos los tickets y generar reportes, pero no gestiona usuarios.");


DROP TABLE IF EXISTS `tickets`;
CREATE TABLE `tickets` (
  `id_ticket` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int NOT NULL,
  `id_agente_asignado` int DEFAULT NULL,
  `id_tipo_caso` int DEFAULT NULL,
  `asunto` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci,
  `estado` enum('Abierto','En Progreso','En Espera','Resuelto','Cerrado','Anulado') COLLATE utf8mb4_general_ci DEFAULT 'Abierto',
  `prioridad` enum('Baja','Media','Alta','Urgente') COLLATE utf8mb4_general_ci DEFAULT 'Media',
  `fecha_vencimiento` datetime DEFAULT NULL,
  `costo` decimal(10,2) DEFAULT '0.00',
  `moneda` varchar(3) COLLATE utf8mb4_general_ci DEFAULT 'USD',
  `estado_facturacion` enum('Pendiente','Facturado','Pagado') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendiente',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultima_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `medio_pago` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id_ticket`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_agente_asignado` (`id_agente_asignado`),
  KEY `id_tipo_caso` (`id_tipo_caso`),
  CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`id_agente_asignado`) REFERENCES `agentes` (`id_agente`),
  CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`id_tipo_caso`) REFERENCES `tiposdecaso` (`id_tipo_caso`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tickets` VALUES("1","1","","5","SISTEMA DE VENTAS - NO EMITE FACTURA","no se puede emitir facturas","Abierto","Alta","","0.00","USD","Pendiente","2025-11-14 02:06:34","2025-11-14 02:06:34","");
INSERT INTO `tickets` VALUES("2","2","2","5","SISTEMA DE VENTAS - NO SE PUEDE FACTURAR","El sistema no permite generar facturación electrónica, sale error","Resuelto","Alta","","150.00","PEN","Pagado","2025-11-22 16:57:55","2025-11-22 17:05:34","Efectivo");
INSERT INTO `tickets` VALUES("3","2","1","5","SISTEMA DE VENTAS - NO LE PERMITE GENERAR FACTURA","El sistema no permite generar la facturación electrónica, genera error. El cliente indica atención inmediata","Resuelto","Alta","","150.00","PEN","Pagado","2025-11-29 11:21:14","2025-11-29 11:27:49","Efectivo");


DROP TABLE IF EXISTS `tiposdecaso`;
CREATE TABLE `tiposdecaso` (
  `id_tipo_caso` int NOT NULL AUTO_INCREMENT,
  `nombre_tipo` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_tipo_caso`),
  UNIQUE KEY `nombre_tipo` (`nombre_tipo`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tiposdecaso` VALUES("1","SERVIDORES DE DATOS","MANTENIMIENTO A SERVIDORES","1");
INSERT INTO `tiposdecaso` VALUES("2","INSTALACIÓN DE REDES","INSTALACIONES DE INFRAESTRUCTURA","1");
INSERT INTO `tiposdecaso` VALUES("3","MANTENIMIENTO DE IMPRESORAS","MANTENIMIENTO","1");
INSERT INTO `tiposdecaso` VALUES("4","MANTENIMIENTO DE EQUIPO DE CÓMPUTO","MANTENIMIENTO","1");
INSERT INTO `tiposdecaso` VALUES("5","APLICACIONES","APLICACIONES VARIOS","1");


DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id_usuario` int NOT NULL AUTO_INCREMENT,
  `id_rol` int NOT NULL,
  `nombre_completo` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `whatsapp` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telegram` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ruta_foto` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `email` (`email`),
  KEY `id_rol` (`id_rol`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `usuarios` VALUES("2","1","Administrador del Negocio","admin@correo.com","","","","uploads/avatars/688ae90605525_usuario1.jpg","$2y$10$AVfKtFBS05wnc99PLQwSOeakMlCYmTZ5Jk1jkaMsruN2J77fOon8i","1","2025-07-27 00:53:49");
INSERT INTO `usuarios` VALUES("3","2","Soporte de campo 1","soporte1@correo.com","","","","uploads/avatars/688ae92d7e3c5_usuarios2.png","$2y$10$u37WkUxy4AXeEDd9rMkc6eCGr3jCs53H2M0CTTOeDR8dP/xMAoJlW","1","2025-07-27 01:18:50");
INSERT INTO `usuarios` VALUES("4","2","Soporte de campo 2","soporte2@correo.com","","","","uploads/avatars/688ae937b55b0_usuarios2.png","$2y$10$KuQA7by/s34bMW9TFqGFp.v0yHZhnHzyZPulqsGvWXnQzW5BFLJGC","1","2025-07-27 12:21:40");
INSERT INTO `usuarios` VALUES("5","3","supervisor","supervisor@correo.com","","","","uploads/avatars/6916d449cd175_688ae937b55b0_usuarios2.png","$2y$10$32OcKTF9skC3M7HjWxmI/u0zzkLs45IL5.PVe97rDw5xBZLr1ELru","1","2025-07-28 04:14:07");


SET FOREIGN_KEY_CHECKS=1;