# Solicitudes públicas de soporte — Etapa 1

## Alcance y estado

Implementación en `codex/portal-publico-soporte`. Se conservan los tickets internos y los IDs históricos. **No se aplicó la migración del portal a `inventario_ti` ni `soporte_db` del entorno de uso.** Enlaces nuevos deshabilitados por defecto. Sin correo automático, importación Excel, actas o rediseño de navegación.

Dos accesos separados:

| Acceso | Identidad y equipo | Autorización |
| --- | --- | --- |
| Empleado autenticado | `usuarios.id_empleado` explícito de su empresa; sede derivada de ese empleado. Solo equipos en asignaciones Activa sin fecha de devolución. Sin equipo permitido. | Identidad/rol/empresa del núcleo revalidados en cada petición; asignación revalidada y bloqueada dentro de la transacción de creación. |
| Formulario público | Nombre y correo declarados, teléfono opcional; no representan identidad verificada. Código de equipo escrito, sin catálogo ni detalles. | Empresa resuelta exclusivamente por enlace habilitado. No se crean ni vinculan empleados, usuarios o clientes por nombre/correo. |

Una cuenta Empleado sin vínculo explícito se rechaza. Al crear/editar una cuenta con ese rol, el servidor exige empleado de la empresa y valida sede. Nunca se corrige automáticamente por correo. Se pueden conservar usuarios legados incompletos sin borrar datos; un administrador autorizado debe vincularlos antes de que usen el flujo de empleado.

## Modelo y permisos

La migración `migrations/portal_publico.php` agrega:

- Tickets: `origen` (interno por defecto), correo, teléfono y código de equipo reportado. Solicitante/creador siguen separados: los públicos dejan empleado, cliente y creador autenticado NULL; los internos conservan `id_solicitante_usuario` como creador.
- Comentarios: `visible_portal` y `autor_publico`. Los históricos quedan sin publicar al portal; no se reutiliza indiscriminadamente `es_privado=0` para exponer logs antiguos. Mensajes escritos deliberadamente como conversación pública se marcan visibles; logs de costos/asignación no se publican.
- `soporte_enlaces_empresa`: un enlace por empresa, hash y token cifrado para copiarlo nuevamente, habilitación y autor/fecha.
- `soporte_seguimiento`: hash único por ticket, caducidad y revocación explícita.
- `soporte_limites_publicos`: contadores persistentes compartidos por IP y ventana.
- `soporte_eventos_portal`: auditoría de habilitar, renovar, desactivar y revocar.

**Personal SmartTech autorizado**, con los roles actuales, significa exclusivamente Operador global (`usuarios.id_empresa IS NULL`) validado por el núcleo y con empresa activa seleccionada. Administradores/auditores de una empresa cliente nunca leen, crean ni descargan notas internas. Un Operador vinculado a una empresa tampoco recibe este privilegio. No se infiere pertenencia a SmartTech por nombre o dominio de correo. La regla central está en `soporte_notas_internas`; futuros roles de personal requieren un permiso explícito en el núcleo antes de ampliarla.

El operador selecciona una empresa desde la plataforma; contexto determina cuál se administra. Ningún ID de empresa enviado por navegador cambia el ámbito. La gestión de enlaces y revocación exige login, rol y CSRF. No se han agregado privilegios de gestión pública a clientes.

## Configuración

Requiere PHP 8.2, PDO MySQL, fileinfo, mbstring y sodium (disponibles en XAMPP revisado), inventario y soporte en el mismo servidor y la integración/solicitantes previamente migradas. Para preparar solo archivos locales, sin conectar a bases:

```powershell
& C:/xampp/php/php.exe migrations/configurar_portal_local.php
```

Genera `.local/portal.key` de 32 bytes codificados en hexadecimal si no existe y prepara `.local/soporte_adjuntos`. No reemplaza claves existentes ni imprime secretos. Ambas ubicaciones quedan excluidas de Git y con `.htaccess` de denegación; Apache local sirve los directorios public mediante aliases, sin exponer `.local`. Restringir permisos NTFS al usuario que ejecuta Apache y respaldar clave junto con configuración privada. Cambiar/perder la clave impide recuperar/copiar enlaces de empresa guardados: conservarla o renovar explícitamente los enlaces; los hashes de seguimiento existentes no dependen de esa clave.

Variables opcionales (configurar tanto CLI como Apache cuando corresponda):

| Variable | Predeterminado / efecto |
| --- | --- |
| `SUPPORT_PORTAL_KEY` | 64 caracteres hex; sustituye archivo local de clave. No guardar en Git. |
| `SUPPORT_PRIVATE_UPLOAD_DIR` | `.local/soporte_adjuntos`; debe estar fuera de directorios públicos y de cualquier alias web. |
| `SUPPORT_PUBLIC_BASE_URL` | URL absoluta del directorio público de soporte, por ejemplo `http://localhost/inventario_ti/soporte`. Sin ella se generan rutas relativas al origen y el botón de copiar las convierte en URL completa. No se usa Host enviado para construir un origen absoluto en el servidor. |
| `SUPPORT_TRACKING_DAYS` | 90 días desde creación, entre 1 y 365; solo afecta tickets nuevos. Fecha almacenada/comparada en UTC. |
| `SUPPORT_UPLOAD_DIR` | Raíz de archivos históricos `soporte/uploads`, solo lectura de descargas legadas. |

Para compartir enlaces fuera del equipo, configurar URL canónica HTTPS y permisos/red apropiados. No asumir que un link localhost sirve a otra computadora. Cabeceras públicas: no-store, no-referrer, nosniff y CSP sin contenido incrustado ni recursos de terceros. Los tokens pueden aparecer en historial y logs de URLs: proteger esos logs y configurar su redacción en cualquier despliegue expuesto; no enviarlos a analítica.

## Migración y conservación

Orden para un entorno **autorizado posteriormente**:

1. Respaldo conjunto de ambas bases y restauración verificada en copias; mantener la clave y archivos privados separados del dump.
2. Integración compartida y `tickets_solicitantes.php` ya aplicadas. No ejecutar clasificación histórica implícita.
3. Ejecutar la nueva migración, con DB_NAME/SUPPORT_DB_NAME del entorno correcto:

```powershell
& C:/xampp/php/php.exe migrations/portal_publico.php
```

4. Configurar clave/almacenamiento y probar antes de habilitar un enlace.

Es aditiva y repetible: no elimina IDs, tickets, comentarios o vínculos históricos, no crea enlaces habilitados y no publica comentarios históricos. DDL MySQL/MariaDB tiene commit implícito; no afirmar rollback transaccional de esquema. Se ensayó dos veces en copias, comparando campos históricos y las huellas de los originales.

Si el esquema del portal no está instalado, sus rutas responden 503; los tickets internos conservan compatibilidad con el esquema local actual. Esta entrega no cambia configuración Apache ni aplica migraciones reales.

## Habilitar y probar en incógnito, Apache local

Después de configurar/migrar el entorno autorizado (o una copia de staging), con el alias local existente:

1. Entrar por `http://localhost/inventario_ti/login.php` como Operador global de SmartTech.
2. Seleccionar empresa desde plataforma y abrir soporte.
3. Pulsar **Enlace de solicitudes de esta empresa** → **Habilitar** → **Copiar enlace**. La página también está en `/inventario_ti/soporte/enlace_empresa.php`; no cambia el menú.
4. Abrir el enlace copiado en incógnito, sin login. Se muestra empresa sin selector. Con cero sedes habilitadas se continúa sin sede; con una se asigna; con varias se elige una habilitada.
5. Escribir nombre/correo, tipo, asunto y descripción. Opcionalmente teléfono, código conocido y archivos. No se muestran empleados o inventario. Código no encontrado/ajeno se conserva como texto sin vincularlo ni revelar si existe; código único de empresa y sede se vincula en servidor.
6. Al enviar se muestra número y enlace individual de seguimiento. Copiarlo y guardarlo: **quien tenga el enlace puede acceder**; no se envía correo y el número solo no autoriza nada.
7. En otra ventana, desactivar o renovar enlace de creación: deja de admitir solicitudes por el enlace anterior; seguimiento ya emitido sigue funcionando.
8. Como operador, abrir detalle del ticket y usar **Revocar seguimiento público de este ticket**. Caducidad/revocación bloquean consulta, respuesta y descarga; no borran ticket ni conversaciones.

Una empresa desactivada en el núcleo tampoco admite acceso público; esto es independiente de desactivar su enlace de creación. Sin tipos activos el formulario explica que contacte a soporte. Sin JavaScript se puede conservar el URL del navegador; el botón de copiar convierte el enlace mostrado a URL absoluta.

## Caducidad, abuso y adjuntos

- Tokens de creación/seguimiento: 32 bytes aleatorios (256 bits); seguimiento guarda únicamente SHA-256. El enlace de creación no tiene caducidad automática: operador lo desactiva/renueva explícitamente.
- Seguimiento predeterminado 90 días; revocable individualmente. Perder enlace requiere intervención de soporte, nunca recuperación por coincidencia de email ni envío automático.
- Por IP real (`REMOTE_ADDR`, sin confiar en X-Forwarded-For): 10 creaciones/hora, 30 respuestas/hora y 60 consultas/minuto (incluye descargas y tokens inválidos). Ventanas fijas; límites persistentes entre sesiones; 429 y Retry-After. Tras proxy todas las peticiones compartirán el IP del proxy hasta configurar conscientemente la capa de entrada; no aceptar cabeceras arbitrarias del público.
- Hasta 5 archivos, 5 MiB por archivo, 15 MiB total; solicitud HTTP hasta 15.3125 MiB para datos/multipart. Extensiones PDF, PNG, JPG/JPEG, TXT: MIME real, firma PDF, imagen decodificable y texto UTF-8 sin controles binarios. No equivale a un antivirus o saneamiento de contenido activo PDF.
- PHP debe permitir el límite de aplicación: recomendación local `upload_max_filesize=5M`, `post_max_size=16M`; no se cambiaron automáticamente. Si la configuración es menor, prevalece ese límite. Aplicación rechaza cantidades mayores; mantener `max_file_uploads` suficientemente alto para que la aplicación detecte y rechace el exceso.
- Nuevos adjuntos internos y públicos se guardan con nombre aleatorio en raíz privada, con validación común y eliminación en rollback. Descarga devuelve attachment/octet-stream/nosniff después de autorización. Los históricos se conservan donde estaban; deben seguir fuera de cualquier acceso web directo/protegidos por la configuración existente.
- El token no autoriza listados, exportaciones, otros tickets, costos, notas o campos administrativos. Consulta pública usa columnas y mensajes permitidos; adjuntos requieren comentario público del mismo ticket. Tickets Cerrado/Anulado no aceptan respuestas; el seguimiento puede seguir consultándose mientras sea válido.
- Contadores son metadatos técnicos de límites, sin IP/correo en claro. Operación puede depurar contadores de ventanas antiguas mediante tarea autorizada; no se configuró una automatización ni se borran automáticamente historiales/tokens.

## Pruebas reproducibles

```powershell
& C:/xampp/php/php.exe tests/aislamiento.php
& C:/xampp/php/php.exe tests/aislamiento.php --apache
```

**290 comprobaciones correctas en ambos modos**, con empresas A/B y una sin sedes habilitadas. Las bases reales se leen para clonarlas; todas las migraciones/escrituras ocurren en `test_outsourcing_<hex>_*`. Se restauran FK en clones y se comparan huellas de todas las tablas originales antes/después. Archivos quedan en directorio temporal, y copias/archivos/sesiones de prueba se retiran al finalizar para evitar que identidades de los clones sobrevivan en el servidor local.

Modo Apache utiliza alias local y un front controller temporal con nombre aleatorio, loopback y cabecera secreta. `apache_setenv` configura únicamente la petición para las copias (no putenv global ni cambios de config). Se invocan los archivos reales con sesión/login, y se elimina el puente al terminar. No habilita un portal en las bases de uso. Requiere XAMPP Apache local y acceso CREATE/DROP para clones; no utilizarlo contra producción.

Cobertura: flujo previo de tickets internos/exportaciones; migración repetida/conservación; operador/cliente y CSRF; enlace/empresa falsificados; código ajeno sin exposición; ausencia de asociaciones por email; creación sin login; tokens inválidos/caducados/revocados; ID de ticket no autoriza; renovación/desactivación independientes; respuesta/adjuntos públicos; notas y archivos internos invisibles para público y administradores/auditores de cliente; escape de HTML; ejecutables/MIME falso/tamaño/cantidad rechazados; límites de consultas/creación/respuestas; empleado precargado, sin equipo, equipo propio activo, equipo devuelto/asignado a otro y revocación de asignación tras abrir formulario; cuenta sin vínculo rechazada; login real de empleado. Sin Warning/Fatal/Parse en solicitudes; lint de aplicación verificado.

Límites de verificación: no se hizo revisión visual completa en navegador, análisis antivirus o prueba de estrés/concurrencia masiva; no se modificó configuración productiva ni se implementó entrega de correo. Incógnito sobre las bases de uso requiere primero migración autorizada, aún pendiente.

## Rollback

Desactivar enlaces de creación; si el incidente afecta seguimiento, revocarlo explícitamente y documentarlo, pues desactivar creación no lo hace. Desactivar rutas públicas manteniendo controladores internos compatibles. Conservar tablas/columnas nuevas y adjuntos; no eliminar registros públicos ni volver id_cliente a NOT NULL. No restaurar indiscriminadamente dumps anteriores sobre tickets nuevos. Respaldo conjunto de bases, clave y archivos; ensayar recuperación en copias.

Al volver a código antiguo podrían reaparecer notas internas a clientes o listas de equipos no asignados: mantener la validación y filtro de privacidad aunque se deshabilite el portal. Los comentarios históricos no se hacen visibles al público durante rollback.
