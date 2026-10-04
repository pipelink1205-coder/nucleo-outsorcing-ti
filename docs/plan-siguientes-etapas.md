# Plan posterior a la revisión de integración

Estado: diseño pendiente de implementación. No se crearon estas funcionalidades. Primero cerrar los pendientes de despliegue del [informe de revisión](revision-integracion-2026-10-04.md); preservar los IDs, datos históricos y contratos actuales.

## Etapa 0 — Base estable

Revisar y desplegar las correcciones del informe en staging, probar Apache real, navegación y adjuntos históricos autorizados. Respaldo verificable y ensayo de restauración antes de cualquier migración futura. Confirmar quién podrá leer notas internas (propuesta: operador/administrador y auditor autorizado), cómo comprobar contacto del solicitante y qué datos exige cada empresa.

Criterio de salida: suite existente verde, recorrido de las dos empresas y pendientes relevantes resueltos/documentados. Activar cada etapa posterior por empresa mediante configuración; desarrollar y probar primero con fixtures.

## Etapa 1 — Solicitud por enlace de empresa y seguimiento individual

**1A. Datos y autorización.** Introducir configuración del portal público por empresa y origen explícito del ticket. El servidor resuelve empresa desde el enlace habilitado; no confía en IDs enviados. Solicitante público es un contacto del ticket, no una cuenta del núcleo creada automáticamente. Mantener el creador autenticado de los tickets existentes; permitir vínculo posterior revisado a empleado/usuario sin coincidencias automáticas por correo. Sucursal/equipo opcionales deben pertenecer a esa empresa; evitar exponer listados de empleados o inventario al público.

**1B. Creación y seguimiento.** Formulario separado de las rutas autenticadas. Nombre/contacto y descripción mínimos; Documento solo si hay una necesidad definida. Token individual aleatorio de alta entropía (al menos 128 bits), almacenado como hash, revocable y con caducidad definida; no usar el ID secuencial como autorización. Mostrar acceso individual al crear y diseñar verificación del contacto antes de habilitar envío automático. Limitar frecuencia/tamaño de solicitudes y cargas, validar contenido/MIME y almacenar adjuntos en ubicación privada. No habilitar acceso público a listados o exportaciones.

**1C. Separación de conversaciones.** Lecturas y escrituras públicas permiten únicamente estado, mensajes y adjuntos explícitamente públicos del ticket autorizado por token. Notas internas, costos, acciones administrativas y sus archivos siguen sujetos a identidad y permisos del núcleo. Respuestas y serialización usan campos permitidos; nunca filtrar notas internas en HTML, JSON, PDF o descargas públicas.

Migración aditiva: configuración pública, origen/contacto y tabla de credenciales de seguimiento. Rellenar origen autenticado para registros existentes sin cambiar su empresa/solicitante. Revocación por empresa y por ticket. Rollback desactiva rutas públicas conservando solicitudes y metadatos.

Aceptación: crear sin login en A; enlace desactivado rechazado; token A no sirve para otro ticket ni B; enumeración, token revocado y manipulación de empresa rechazados; notas/adjuntos internos invisibles en todas las respuestas públicas; usuarios autenticados mantienen permisos actuales.

## Etapa 2 — Plantilla Excel e inventario inicial por lotes

**2A. Plantilla y contrato.** Plantilla versionada con código inventario, serie, sede, tipo, marca, modelo y campos opcionales explícitos. Tratar códigos/documentos como texto para conservar ceros. Empresa se determina por sesión; no se admite un campo que cambie el tenant. Permitir carga solo a roles autorizados y dentro de su sede. Utilizar la biblioteca Excel ya instalada, con límites de archivo, filas y expansión ZIP; rechazar macros, fórmulas y vínculos externos sin evaluarlos.

**2B. Validación y vista previa.** Leer a un área temporal y mostrar resumen y errores por fila antes de escribir equipos. Validar versión/columnas, tipos/fechas, catálogos, marca-modelo, sede autorizada y duplicados dentro del archivo y contra la empresa. No crear catálogos ni sobrescribir equipos existentes silenciosamente. Propuesta inicial: lote completo válido o sin aplicación; duplicados se resuelven en la vista previa.

**2C. Aplicación.** Lote con empresa/sede, autor, archivo/hash, versión de plantilla, filas, resultados y fechas. Vista previa ligada a contenido inmutable y con caducidad. Aplicación transaccional e idempotente, revalidando conflictos al confirmar. Cada equipo nuevo referencia el lote inicial; guardar trazabilidad por fila. Medir volumen antes de decidir procesamiento en cola; evitar transacciones excesivas sin perder la política explícita de aplicación.

Migración aditiva: lotes/filas y referencia nullable desde equipos; equipos existentes conservan IDs y quedan sin lote hasta una vinculación revisada. Rollback desactiva importación; un lote aplicado no se borra si sus equipos ya tienen asignaciones/tickets: corrección auditable, no eliminación masiva.

Aceptación: archivo válido → vista previa → lote aplicado una vez; errores y duplicados → cero escrituras; falla intermedia → rollback; doble confirmación/concurrencia → sin duplicados; catálogo/sede B en sesión A rechazados; códigos con ceros preservados; equipos existentes intactos.

## Etapa 3 — Acta de inventario inicial y documento firmado

**3A. Verificación.** Crear acta por empresa/sede/lote con instantánea versionada de sus equipos. Estado de verificación independiente del estado operativo del equipo: `reportado` = registrado aún sin comprobación; `verificado` = comprobado por responsable con fecha/evidencia; `pendiente` = comprobación o discrepancia sin resolver, con motivo. Definir transiciones, permisos y tratamiento de faltantes/sobrantes sin reemplazar Disponible/Asignado/Reparación. Registrar autor/fecha de cada cambio.

**3B. Documento.** Generar PDF con empresa, sede, lote, versión, responsables, conteos y detalle por estado. El cierre congela la instantánea; modificaciones posteriores generan revisión vinculada. Los totales del acta deben corresponder exactamente a sus filas, aunque el inventario cambie después.

**3C. Firma adjunta.** Adjuntar documento firmado a la versión correspondiente, con carga privada validada, hash, autor/fecha y descarga autorizada. Conservar historial y permitir nueva versión sin sobrescribir la anterior. Adjuntar una firma escaneada no equivale a implementar firma electrónica verificable.

Migración aditiva: actas, instantáneas/filas, historial de verificación y documentos por versión. No reclasificar automáticamente todos los equipos existentes como verificados. Rollback desactiva edición/generación sin borrar actas ni firmas; mantener acceso autorizado a documentos históricos.

Aceptación: estados/transiciones válidos y motivos obligatorios; usuarios sin permiso no verifican ni cierran; empresa B no lee acta, PDF o firma A; acta cerrada permanece consistente frente a cambios de equipos; PDF con tablas largas se renderiza y revisa visualmente; archivo firmado asociado a versión exacta y protegido.

## Entrega de cada etapa

PR separado, migración repetible con simulación cuando corresponda, fixtures A/B, pruebas de conservación/aislamiento, documentación de configuración y rollback. Orden recomendado: base estable → portal público → importación y lote → acta. No ejecutar migraciones de estas etapas sobre datos reales antes de revisar resultados en copia/staging y contar con respaldo restaurable.
