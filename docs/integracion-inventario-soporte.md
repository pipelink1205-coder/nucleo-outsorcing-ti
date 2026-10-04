# Núcleo compartido de outsourcing TI

## Revisión del proyecto

Las aplicaciones usan PHP, MySQL/MariaDB, MySQLi en inventario y PDO en soporte. Inventario ya tenía `empresas`, `id_empresa`, roles y selección de empresa. Soporte mantenía sus propios `usuarios`, `agentes`, `clientes` y `tickets`. El puente asignaba IDs fijos 2, 5 o 3 según el rol; no identificaba a la persona real. El detalle permitía modificar tickets antes de comprobar su empresa. Las exportaciones e impresión omitían el control de sesión. Varios detalles y catálogos de inventario aceptaban IDs sin verificar su pertenencia.

Los dumps incluidos son fotografías antiguas: contienen `DELETE FROM` y no representan el esquema multiempresa actual. No deben importarse sobre las bases existentes para actualizar el proyecto.

## Decisión y modelo

`inventario_ti` es el núcleo común: empresas → sucursales → empleados/equipos, usuarios → empresa/sucursal y empleado asociado opcional. Ambos módulos consultan estos mismos registros; no mantienen copias de sucursales ni empleados.

Soporte conserva sus clientes, usuarios y agentes históricos para mantener IDs, autores, perfiles y asignaciones. `usuarios.id_usuario_nucleo` es un vínculo único y explícito con el usuario autenticado del núcleo. Una identidad nueva obtiene una proyección con contraseña aleatoria y correo técnico; esas credenciales no permiten iniciar sesión. No se vinculan personas por email, nombre ni coincidencia de IDs. El acceso se inicia únicamente en el login del núcleo; los dos handlers antiguos de inventario usan ese mismo login.

Los tickets incorporan `id_empresa_portal`, `id_sucursal`, `id_solicitante` (empleado), `id_solicitante_usuario` (usuario creador autenticado) y `id_equipo` opcional. Se conserva el cliente histórico. Empresa y creador se toman del servidor. Sucursal, empleado y equipo se validan contra el núcleo y la misma sucursal; claves foráneas compuestas protegen además estas relaciones en MySQL. Los comentarios nuevos guardan `id_usuario_nucleo`; los autores históricos no se reescriben.

Un creador y un solicitante pueden ser personas distintas: un administrador puede registrar una solicitud para un empleado. Los tickets históricos cuyo usuario creador se desconoce mantienen ese campo NULL y no aparecen en la vista personal de un empleado.

Se mantiene una sola cuenta de empresa por usuario y una sucursal opcional. El operador global (`Operador`, empresa NULL) puede seleccionar una empresa activa. Una cuenta de empresa nunca obtiene otra empresa desde parámetros o sesión manipulada. Usuarios con roles múltiples o desconocidos se rechazan en soporte hasta corregir su configuración.

## Etapas implementadas

1. **Preparación y datos:** migración CLI aditiva, repetible, índices de ámbito, DNI único por empresa, relaciones compuestas y herramienta de clasificación explícita con simulación predeterminada. Se elimina la reasignación del usuario 1 a operador en la migración antigua.
2. **Identidad común:** contexto validado en cada solicitud contra usuarios activos y roles del núcleo; puente sin IDs fijos; administración de usuarios compartida, vínculo usuario-empleado en alta/edición.
3. **Tickets:** formulario y detalle con vínculos organizativos, validación de pertenencia, creador real y autoría de comentarios. Tickets antiguos se conservan sin inventar solicitante ni equipo.
4. **Permisos:** control previo a lecturas/escrituras, filtro de listado/estadísticas/exportaciones, descarga autenticada de adjuntos, bloqueo de herramientas globales antiguas y pruebas entre dos empresas.

## Permisos aplicados

| Rol | Inventario | Soporte | Administración |
|---|---|---|---|
| Operador global | Lectura/escritura en empresa seleccionada | Gestión de tickets de la empresa seleccionada | Plataforma y organización; configuración global |
| Administrador | Lectura/escritura en su empresa/sucursal | Crear, comentar, asignar, estados y costos dentro de su ámbito | Usuarios y catálogos de su empresa; no concede Operador |
| Auditor | Lectura | Lectura y exportaciones dentro de su ámbito | Sin escritura |
| Empleado | Lectura en su empresa/sucursal | Crear para su empleado asociado, ver/comentar tickets que creó | Sin notas privadas, cambios de estado, costos, asignación ni exportación |

La sucursal de la cuenta restringe referencias y tickets. El aislamiento de empresa se comprueba también en detalles, catálogos, APIs y edición de inventario. Los historiales operativos de inventario se filtran por empresa del equipo; la propiedad de asignaciones/reparaciones/bajas se verifica mediante su equipo incluso cuando una fila antigua no tenga `id_empresa`.

Soporte valida CSRF en POST. Los endpoints antiguos de clientes/usuarios/perfiles, backups, resets y limpieza se rechazan por servidor. El menú dirige la gestión de organización, usuarios y contraseña al núcleo. Los tipos de caso existentes continúan disponibles para crear tickets; su antigua administración global queda bloqueada. La restauración y limpieza deben ejecutarse fuera del portal por el responsable de las bases.

## Ejecutar migraciones conservando datos

Requisitos: ambos esquemas en el mismo servidor MySQL/MariaDB con InnoDB, PHP con PDO MySQL/MySQLi, y usuario de migración con permisos ALTER/REFERENCES sobre ambos esquemas. Variables opcionales: `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` (predeterminado `inventario_ti`), `SUPPORT_DB_NAME` (predeterminado `soporte_db`). No incluir credenciales en Git.

1. Detener escrituras y respaldar **ambas** bases y los archivos subidos. Verificar que la copia se pueda restaurar en bases separadas.
2. Si se parte del esquema antiguo, ejecutar `php includes/migrar_nucleo.php` primero. En el proyecto local ya existe el esquema multiempresa. Este paso no asigna los datos antiguos a una empresa ni otorga roles automáticamente.
3. Ejecutar `php migrations/compartir_nucleo.php`. Agrega columnas nullable sin borrar ni renumerar. Completa la empresa de empleados/equipos desde una sucursal ya clasificada, y de historiales desde su equipo; completa la empresa de tickets desde clientes ya clasificados. No deduce empresas por texto. Informa cuántos tickets tienen empresa, sucursal o solicitante pendientes.
4. Revisar registros con `id_empresa IS NULL` en el núcleo y `id_empresa_portal IS NULL` en clientes/tickets. Preparar un archivo propio tomando `migrations/mapa.ejemplo.json` como estructura. Mapear únicamente identificadores confirmados.
5. Ejecutar `php migrations/clasificar_historicos.php mapa.json` para validar y revertir la simulación. Después, ejecutar el mismo comando con `--aplicar`. Las modificaciones de clasificación se realizan en una sola transacción entre esquemas, y cualquier inconsistencia revierte todo.
6. Asociar usuarios con empleados desde Usuarios compartidos. Iniciar sesión de nuevo y ejecutar las pruebas antes de habilitar acceso de clientes.

Ejemplo de clasificación (reemplazar TODOS los IDs por IDs revisados):

```json
{
  "entidades": [
    {"tabla":"sucursales","id":10,"id_empresa":2},
    {"tabla":"empleados","id":20,"id_empresa":2}
  ],
  "clientes": [{"id_cliente":30,"id_empresa":2}],
  "usuarios_soporte": [{"id_usuario":40,"id_usuario_nucleo":50}],
  "tickets": [{"id_ticket":60,"id_empresa":2,"id_sucursal":10,"id_solicitante":20,"id_equipo":null}]
}
```

La clasificación no cambia roles, contraseñas, asuntos, descripciones ni IDs. Un usuario de soporte histórico solo puede vincularse a una identidad del núcleo que aún no tenga otra proyección; hacer este mapeo antes de su primer acceso a soporte. Los tickets sin empresa se mantienen fuera de todos los portales. Los tickets con empresa conocida y sucursal/solicitante pendientes son visibles solo a cuentas de esa empresa sin restricción de sucursal; los empleados tampoco ven tickets sin creador conocido.

DDL de MySQL no es transaccional: si una clave foránea encuentra datos contradictorios, corregir los vínculos revisados y repetir la migración. No desactivar `FOREIGN_KEY_CHECKS`. Antes de volver a código antiguo, detener escrituras y restaurar conjuntamente ambos respaldos y archivos; no borrar las columnas nuevas después de que se hayan creado tickets con vínculos. Una reversión de aplicación al puente antiguo volvería a introducir la suplantación de usuarios y debe hacerse solo durante mantenimiento.

Los adjuntos se entregan por `soporte/public/descargar_adjunto.php`. Apache debe respetar `soporte/uploads/.htaccess` (`Require all denied`); para otro servidor, denegar acceso HTTP directo a esa carpeta. No servir backups, dumps, migraciones ni pruebas como archivos públicos.

## Pruebas reproducibles

```powershell
& C:/xampp/php/php.exe tests/aislamiento.php
```

La prueba clona ambas bases en nombres aleatorios `test_outsourcing_*`, ejecuta dos veces la migración, agrega un ticket histórico sin empresa y fixtures de dos empresas. Inicia un servidor PHP en un puerto libre y verifica solicitudes HTTP reales con sesiones independientes. Elimina únicamente las bases de prueba y el servidor al finalizar. Requiere permisos CREATE/DROP para esas bases; las originales se leen, no se escriben. Los snapshots originales no se imprimen.

Cobertura: conservación de columnas históricas y usuarios de soporte, repetibilidad, identidades distintas y estables, sesión con rol/empresa falsificados, detalle/POST cruzados, listas e impresión aisladas, APIs y edición por ID, FK entre empresas, creación con y sin equipo, equipo manipulado, autor real de comentarios, CSRF, auditor de solo lectura, comentarios de empleado y revocación de acceso de una sesión ya abierta.

Validación de esta implementación: **126 comprobaciones correctas** con MySQL/MariaDB local y PHP de XAMPP. La base local de soporte estaba vacía de tickets; por ello el historial de tickets se comprueba con un fixture explícito, además de conservar las identidades históricas reales copiadas. También se validó la sintaxis de los archivos PHP de la aplicación. Se verificaron exportaciones PDF/Excel reales, simulación y aplicación de clasificación histórica, y el login con credenciales del núcleo. Las pruebas automatizadas no equivalen a una revisión visual de todos los formularios antiguos ni a un ensayo de restauración en producción.

La migración aditiva se ejecutó también en las bases locales el 3 de octubre de 2026, después de respaldar ambos esquemas en `.local/backups/nucleo-927b21e697e04895b3b5551dbc510dd1/`. Reportó cero tickets pendientes. Los respaldos contienen datos privados, están excluidos de Git y protegidos de acceso HTTP directo en Apache. No se aplicó clasificación manual a los datos reales ni se cambiaron sus roles.

## Límites y evolución

Esta etapa mantiene dos esquemas en el mismo servidor; las FK cruzadas hacen que separarlos requiera un contrato de API y validación equivalente. Las tablas legadas de clientes/agentes son adaptadores de compatibilidad, no fuentes nuevas de identidad. Migrar los usuarios de soporte sin cuenta común requiere darles una cuenta y un rol revisados en el núcleo. Las relaciones nullable permiten conservar historia incompleta; no habilitar NOT NULL masivo sin terminar la clasificación.

Si se necesitan empleados con acceso a solicitudes creadas por terceros para ellos, definir esa regla explícitamente y extender el vínculo usuario-empleado. Si una persona necesita pertenecer a varias empresas, introducir membresías explícitas antes de permitir selección de empresa a cuentas de cliente. Ninguno de estos permisos se concede por coincidencias de correo ni por IDs enviados por el navegador.
