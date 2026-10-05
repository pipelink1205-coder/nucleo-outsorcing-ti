# Puesta en uso local de tickets internos

Realizada el 4 de octubre de 2026 (America/Bogota). Los timestamps de archivos de respaldo usan la zona horaria de PHP del equipo y pueden figurar como 5 de octubre UTC.

## Entorno confirmado

PHP ejecutado mediante `apache2handler` bajo Apache/2.4.58 y PHP/8.2.12 de XAMPP. Ruta física del proyecto: `F:/PROYECTOS PROGRAMACION/inventario_ti`; rama `main`. Apache utiliza los aliases `/inventario_ti` y `/inventario_ti/soporte`.

Las conexiones efectivas se comprobaron con un diagnóstico temporal limitado a loopback y cabecera secreta. El archivo diagnóstico se eliminó inmediatamente. `DB_HOST=localhost`; bases `inventario_ti` y `soporte_db`; hostname MySQL de ambas y hostname PHP: `Felipe`. No se encontraron indicadores de producción ni servidor remoto. Se trataron todos los registros existentes como reales: 2 empresas, 5 usuarios del núcleo, 1 cliente y 5 usuarios de soporte; empleados/equipos/tickets/tipos de caso vacíos antes de aplicar.

## Respaldo y restauración comprobados

Directorio privado, excluido de Git:

`F:/PROYECTOS PROGRAMACION/inventario_ti/.local/backups/tickets-20261005-013038-3d49d06d`

Contiene `inventario_ti.sql`, `soporte_db.sql`, hashes SHA-256, `manifest.json`, huellas de filas y resultados de migración/prueba. Dumps con datos, estructuras, triggers, rutinas y eventos; conexión local y `--single-transaction`. Los archivos contienen datos privados y no se adjuntan al repositorio. Acceso al SQL por URL del portal rechazado (404).

Ambos dumps se restauraron en bases temporales separadas, remapeando exclusivamente referencias entre esquemas a esas copias. Coincidieron todas las filas y estructuras de las 16 tablas de inventario y 9 de soporte, incluidos índices y claves foráneas. Los originales permanecieron iguales durante el respaldo. Las copias se eliminaron en orden soporte → inventario para respetar las FK cruzadas. Un primer intento de limpieza en orden contrario fue rechazado por una FK; se corrigió el orden y se repitió exitosamente el respaldo/restauración antes de migrar.

## Migración local aplicada

Estado inicial: `id_cliente` NOT NULL; `solicitante_nombre` y `solicitante_contacto` ausentes; catálogo de casos vacío.

Se ejecutó `migrations/tickets_solicitantes.php` solo después de verificar el respaldo y comprobar nuevamente que las conexiones CLI coincidían con las bases/máquina de Apache y que las huellas originales no habían cambiado. Resultado:

- `id_cliente` admite NULL.
- `solicitante_nombre VARCHAR(200) NULL` y `solicitante_contacto VARCHAR(255) NULL` agregados.
- Incidente, Solicitud de servicio, Consulta y Mantenimiento presentes y activos.
- Filas preexistentes conservadas; inventario no sufrió cambios de datos.

No se ejecutó otra migración sobre los originales. El ajuste continúa usando identidad real y empresa del contexto autorizado. No requiere reiniciar Apache: el código del checkout es el que sirve el alias.

## Prueba en Apache real

**31 comprobaciones correctas**, con solicitudes HTTP reales a `http://localhost/inventario_ti` y login mediante cuentas temporales. No se usó el router de pruebas ni el servidor PHP integrado.

Dos empresas temporales: una completamente vacía y otra con una sede, empleado y equipo de prueba. Se comprobaron formulario, empresa fija sin selector de cliente, guardado con solicitante libre o empleado existente, equipo opcional y vínculos al equipo, asignación automática de sede única, creador autenticado separado, listado y detalle. Se rechazaron detalle y POST cruzados en ambas direcciones, creación sin CSRF y soporte anónimo. Las respuestas utilizadas no mostraron Warning/Fatal/Parse PHP.

Los registros de prueba, proyecciones de identidad y sesiones autenticadas se retiraron al terminar. Huellas de todas las tablas de ambas bases iguales al estado posterior a la migración; quedan 2 empresas y 0 tickets de prueba. Los contadores AUTO_INCREMENT pueden haber avanzado al consumir y retirar IDs temporales; no se reajustaron.

Después de la puesta en uso se ejecutó nuevamente la suite aislada: **191 comprobaciones correctas**, sin modificar las bases originales.

La prueba valida flujo HTTP y contenido del formulario en Apache; no incluye revisión visual interactiva ni adjuntos históricos originales. Portal público, importación y actas siguen sin implementar.

## Uso y recuperación

Abrir `http://localhost/inventario_ti/login.php`, iniciar sesión con la cuenta habitual y entrar a soporte. Los tipos de caso ya están disponibles. Para empresa sin sedes y usuario con alcance empresarial, ingresar nombre/contacto del solicitante; no hace falta cargar empleados o equipos previamente. Usuarios limitados a sede conservan su ámbito.

El respaldo es anterior a la migración. Si se necesita recuperar, detener escrituras y restaurar primero inventario y luego soporte en una copia para verificar; solo después decidir recuperación de los originales. No restaurar el dump directamente sobre datos nuevos sin conservarlos. Las claves foráneas cruzadas requieren tratar ambas bases como un conjunto. Preferir conservar columnas aditivas y desactivar creación antes que borrar información de tickets nuevos.
