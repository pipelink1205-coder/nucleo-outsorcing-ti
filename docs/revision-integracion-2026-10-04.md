# Revisión de integración — 4 de octubre de 2026

La integración pasó **165 comprobaciones automatizadas** después de corregir los defectos descritos abajo. No se modificaron las tablas originales ni se aplicaron migraciones a ellas en esta revisión. Las tres funcionalidades futuras no se implementaron.

## Entorno e instrucciones

- Carpeta: `F:/PROYECTOS PROGRAMACION/inventario_ti`.
- Repositorio: `https://github.com/pipelink1205-coder/nucleo-outsorcing-ti.git`.
- Rama: `main`. Punto inicial: `12ebb6e`. El árbol estaba limpio al comenzar; se conservaron los cambios ya integrados y la localización colombiana.
- Se revisaron README, la documentación de integración, código de autorización, rutas, modelos y migraciones. No se encontró AGENTS.md en el proyecto ni en las carpetas ascendentes revisadas.
- PHP y MariaDB de XAMPP. La configuración Apache existente contiene aliases `/inventario_ti` y `/inventario_ti/soporte`.

## Método y reproducción

```powershell
& C:/xampp/php/php.exe tests/aislamiento.php
```

Requiere acceso local de lectura a ambas bases y permisos CREATE/DROP para las bases temporales. La prueba copia inventario y soporte a esquemas aleatorios `test_outsourcing_<hex>_core` y `_support`. Restaura también sus claves foráneas: CREATE TABLE LIKE por sí solo no las copia. Todas las escrituras, clasificaciones históricas y migraciones se ejecutan en esas copias. Crea usuarios y empresas A/B de prueba y una segunda sede A; no reutiliza credenciales de personas reales.

El servidor PHP usa un puerto libre y `tests/router.php`, que reproduce los aliases observados. Login real y sesiones independientes prueban los endpoints. Los adjuntos se almacenan en una carpeta temporal mediante `SUPPORT_UPLOAD_DIR`; no se escriben archivos en uploads del proyecto. Al terminar se eliminan las bases de prueba y los archivos temporales. SHA-256 de las filas de todas las tablas originales antes/después confirma que sus datos permanecieron iguales. No se imprimen datos originales.

## Cobertura y resultado

| Área | Evidencia |
| --- | --- |
| Organización | Relaciones empresa/sucursal/empleado/equipo, referencias manipuladas, equipo opcional y FK que rechaza ticket con sucursal ajena. Lectura de consistencia original: sin discrepancias en usuario/sucursal, empleado/sucursal y equipo/sucursal respecto de empresa. |
| Identidad | Login con credenciales de fixture, misma cookie en soporte, proyección estable por usuario del núcleo, creador real y autores de comentarios. Empresa/rol falsos en sesión no gobiernan acceso. |
| Tickets | Listados A/B aislados, detalle propio permitido y cruzado rechazado en ambas direcciones; creación con/sin equipo y empresa/creador falsificados ignorados. Historia sin clasificar permanece aislada. |
| Permisos | POST cruzado, CSRF, auditor sin escritura, empleado limitado, cambio de rol revalidado y usuario desactivado revocado. Administrador de sede no amplía cuentas a alcance empresarial ni accede a otra sede A. Agente B rechazado en ticket A. |
| Detalles y adjuntos | Detalles de ticket y edición de empleado propios; edición de equipo y API de empleados ajenos denegadas. Carga temporal, descarga exacta, bloqueo anónimo/cruzado/privado, metadatos incoherentes y recorrido fuera de uploads rechazados. |
| Exportaciones | Excel real abierto como ZIP y PDF real con contenido inspeccionado, ambos de A sin ticket B; impresión aislada. Empleado rechazado en Excel/PDF/impresión. |
| Rutas | Portal, puente y retorno al núcleo mediante rutas del checkout y aliases simulados. Listado de devoluciones operativo. AJAX de modelos con CSRF. |
| Regresión y conservación | Migración repetible e historia conservada; clasificación histórica en simulación y aplicación solo sobre copias. 165 comprobaciones; solicitudes sin Warning/Fatal/Parse PHP; huellas originales idénticas. Lint correcto en 133 archivos PHP de la aplicación y git diff --check sin errores. |

## Defectos corregidos

1. **Rutas:** resolver común para enlaces entre módulos y retorno, recursos y gestión compartida; elimina referencias relativas incompatibles con los aliases.
2. **Identidad y ámbito:** núcleo exige exactamente un rol activo; valida sucursal del usuario aunque no tenga empleado asociado. Refresca nombre/correo/empresa de sesión. No permite que un administrador de sede edite una cuenta sin sede o cree una con alcance empresarial. Listado de usuarios filtrado por sede.
3. **Escrituras de inventario:** CSRF en formularios POST y consultas AJAX existentes; activación/desactivación de catálogos pasa de GET a POST. Validación marca/modelo y área/cargo en servidor.
4. **Soporte:** asignación solo a agentes autorizados; identidad proyectada después de autorizar ruta/acción, con recuperación de carrera de inserción por ID del núcleo. Comentarios muestran el autor real preservando fallback histórico.
5. **Adjuntos:** verifica que comentario y archivo pertenecen al mismo ticket y que la ruta queda dentro de la raíz configurada. Mantiene protección de notas privadas para empleados.
6. **Devoluciones y reparación:** listado consulta asignaciones activas con ámbito de empresa/sede y conduce al formulario existente de devolución. Estado persistido `En Reparacion` coincide con el ENUM; su registro y transacción se comprobaron en la copia.
7. **Prueba:** claves foráneas restauradas en clones, prueba de login con cookie real, aliases, adjuntos temporales y huellas de originales.

## Migraciones y puesta en servicio

Estas correcciones no agregan columnas ni requieren una migración nueva. Las migraciones de integración existentes se ensayaron únicamente sobre copias; la clasificación histórica sigue exigiendo un mapa explícito, nunca coincidencias automáticas por correo. Los valores históricos de Documento y moneda no se reinterpretaron.

Antes de desplegar: revisar el diff, ejecutar el comando de prueba y lint, respaldar código/configuración y repetir el recorrido autenticado en staging Apache. Mantener el esquema aditivo actual. Si se revierte el código, revertir juntos los formularios POST y el guard CSRF; no restaurar ni borrar datos para revertir estas correcciones.

## Pendiente de prueba

- Recorrido visual interactivo en navegador y Apache real, incluidos `.htaccess`, recursos estáticos, HTTPS y configuración de despliegue. El router de prueba simula rutas/política y no demuestra la ejecución de reglas Apache.
- Adjuntos históricos originales, evidencias de bajas y generación/descarga visual de todas las actas antiguas; no se abrieron archivos privados ni se generaron archivos en carpetas reales.
- Flujo completo de devolución con fotografías y archivos, restauración de respaldo, rendimiento/carga, concurrencia efectiva de proyección, PHP/Linux con nombres de tabla sensibles a mayúsculas.
- Matriz exhaustiva de todas las pantallas antiguas, selección/cambio de empresa del operador global y aislamiento de exportaciones en sentido B→A. Se comprobó A→B para PDF/Excel y A/B para listados y detalles.

La evidencia cubre las rutas y casos enumerados; no representa certificación de todos los endpoints o del despliegue productivo.
