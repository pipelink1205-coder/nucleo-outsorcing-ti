# Tickets internos en empresas sin inventario

## Resultado y bloqueos

El formulario anterior exigía sucursal, empleado solicitante y cliente heredado. El catálogo vacío impedía seleccionar un tipo válido. Equipo ya era opcional. Ahora el ticket utiliza empresa del contexto autorizado, sin selector de empresa o cliente.

- Sin sucursales: usuarios con alcance empresarial guardan con sucursal NULL. No se amplía el alcance de usuarios de sede; contexto inválido se rechaza.
- Una sucursal: asignación automática tanto en formulario como servidor, incluso si se omite del POST. Usuario de sede queda vinculado a su sede. Un ID ajeno nunca se reemplaza silenciosamente: se rechaza.
- Varias sucursales: usuario empresarial debe elegir una de su empresa; usuario de sede permanece en su sede.
- Solicitante: empleado autorizado o persona no registrada con nombre (hasta 200 caracteres) y contacto (correo o teléfono, hasta 255). Se guarda una instantánea del nombre; contacto libre no se convierte en cuenta ni se usa para autenticar.
- No se crean empleados, usuarios del núcleo o clientes para la persona solicitante. La proyección existente del creador autenticado en soporte permanece como adaptador de identidad.
- `id_solicitante` conserva el vínculo al empleado cuando existe; `id_solicitante_usuario` conserva su significado histórico de **creador autenticado** y siempre se toma del contexto, independientemente del solicitante. El detalle muestra ambos por separado.
- Equipo opcional; cuando se envía se valida empresa/sucursal. Empleados solo pueden elegir su propio empleado, igual que antes; pueden registrar nombre/contacto libre sin obtener acceso a tickets de otros creadores. Auditor continúa sin crear tickets.
- Los tickets nuevos pueden tener `id_cliente=NULL`; los históricos conservan sus IDs. Consultas de listado/detalle/PDF/Excel/impresión usan LEFT JOIN y nombre de solicitante con fallback al cliente histórico, para no ocultar tickets nuevos.

## Migración necesaria antes de usar el formulario

Inicialmente la migración se ensayó solo en copias. Posteriormente se aplicó al entorno local con respaldo restaurado y verificado; ver [puesta en uso local](puesta-uso-local-tickets.md). Desplegar el formulario sin ella produciría errores de columnas inexistentes. Orden: respaldo restaurable → integración compartida existente → nueva migración → código actualizado → prueba manual autorizada en staging.

```powershell
# Con DB_NAME y SUPPORT_DB_NAME configurados para el entorno deseado:
& C:/xampp/php/php.exe migrations/tickets_solicitantes.php
```

La migración CLI agrega `tickets.solicitante_nombre VARCHAR(200) NULL` y `solicitante_contacto VARCHAR(255) NULL`, y hace nullable `id_cliente`, conservando claves foráneas y valores históricos. No rellena nombres/contactos históricos ni reclasifica tickets.

Catálogo global inicial: Incidente, Solicitud de servicio, Consulta y Mantenimiento. La clave única existente `nombre_tipo` y el upsert sin modificación garantizan repetibilidad y evitan duplicados concurrentes por nombre. Se conservan IDs, descripciones y estado activo de categorías existentes: no se reactiva una categoría deshabilitada deliberadamente. No se siembra desde cada visita HTTP.

DDL de MariaDB hace commit implícito; no prometer rollback transaccional de esquema. Para revertir: desactivar creación nueva y restaurar código compatible con tickets sin cliente; conservar columnas y registros. El código antiguo con INNER JOIN no es compatible con tickets nuevos sin cliente. No volver a NOT NULL ni eliminar columnas sin resolver/exportar los registros nuevos. El contador antiguo de “pendientes de clasificación” de la migración compartida considera NULL de sucursal/empleado; esos NULL ahora pueden ser legítimos y no deben autocompletarse.

## Validación reproducible

```powershell
& C:/xampp/php/php.exe tests/aislamiento.php
```

**189 comprobaciones correctas** en XAMPP local. Ambas bases originales se copian a esquemas aleatorios con FK restauradas. Migraciones repetidas, escritura únicamente en clones y adjuntos en carpeta temporal. Huellas de todas las tablas reales antes/después idénticas; sin Warning/Fatal/Parse PHP durante HTTP.

Casos añadidos: empresa vacía sin cliente/sede/empleado/equipo; nombre/contacto y creador distintos; cero empleados/cuentas/clientes nuevos por solicitante; formulario sin selector de empresa/cliente; ticket nuevo visible en listado/detalle/Excel/PDF/impresión; manipulación de empresa/creador ignorada; referencias a sede/equipo/empleado ajenos rechazadas; acceso cruzado denegado; contacto obligatorio; sede única automática en servidor; solicitante libre en empresa con datos y usuario de sede; empresa con varias sedes exige selección; catálogo repetido sin duplicados. Permanecen las pruebas previas de empleado existente, equipo opcional, CSRF, auditor/empleado, notas internas, adjuntos privados y revocación de identidad.

Puesta en uso local completada: migración aplicada y 31 comprobaciones HTTP en Apache real. Pendiente: revisión visual interactiva en navegador. No se implementó portal público, importación Excel ni acta inicial.
