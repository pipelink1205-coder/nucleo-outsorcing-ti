# Etapa 1 preparada en XAMPP local

## Resultado

Preparación completada el 4 de octubre de 2026 en America/Bogota. Rama `codex/portal-publico-soporte`, checkout `F:/PROYECTOS PROGRAMACION/inventario_ti`; se conservaron los cambios existentes. Clave y almacenamiento preparados mediante `migrations/configurar_portal_local.php`; migración `migrations/portal_publico.php` aplicada al entorno local autorizado. **Cero enlaces creados y cero habilitados**: el usuario elegirá qué empresa habilitar.

No se implementaron importación, actas ni correo automático. No se modificó configuración de Apache ni se necesitó reiniciarlo.

## Entorno confirmado desde Apache

`apache2handler`, Apache/2.4.58 y PHP/8.2.12 de XAMPP; ambas conexiones efectivas a `localhost`: `inventario_ti` y `soporte_db`. PHP y ambos servidores MySQL informaron hostname `Felipe`. Sin indicadores de producción ni destino remoto. Los aliases sirven exclusivamente los directorios `public` de inventario y soporte. No hay enlace del proyecto desde DocumentRoot que exponga `.local`.

Datos existentes tratados como reales: 2 empresas, 5 usuarios del núcleo, 1 ticket, 2 clientes, 5 usuarios de soporte y 4 tipos de caso. No había equipos o empleados. Campos y valores históricos se compararon antes/después y permanecen iguales; nuevos campos usan sus defaults sin publicar mensajes históricos al portal.

## Respaldo verificado

Directorio privado anterior a la migración:

`F:/PROYECTOS PROGRAMACION/inventario_ti/.local/backups/tickets-20261005-023312-5dbca176`

Incluye dumps de ambas bases, hashes SHA-256, manifest de restauración, huellas y evidencias de aplicación/verificación. También se respaldó la nueva clave de configuración dentro de ese directorio, sin imprimirla. Datos, clave y evidencias privadas no se agregaron a Git.

Los dumps se restauraron en dos bases temporales, remapeando las FK entre inventario y soporte hacia las copias. Coincidieron las filas y SHOW CREATE TABLE de las 16 tablas de inventario y 9 de soporte, con índices y FK. Copias retiradas en orden soporte → núcleo. Antes de aplicar, se verificó nuevamente hash de dumps, identidad de bases/máquina y huellas originales sin cambios concurrentes.

Los timestamps en nombres de respaldo y JSON usan la configuración de PHP del equipo (+02:00); no deben interpretarse como UTC ni como fecha de America/Bogota.

## Clave, archivos y privacidad

- Clave: `.local/portal.key`, 32 bytes codificados en 64 caracteres hexadecimales; existente se conservaría, no se regeneraría silenciosamente.
- Raíz privada: `.local/soporte_adjuntos`. Apache pudo escribir un archivo temporal, leer la clave y completar cifrado/descifrado de verificación.
- `.local/.htaccess` contiene `Require all denied`; las rutas de la aplicación no sirven ese directorio. URLs de clave, copia de clave, archivo privado y recorridos relativos/encoded fueron rechazadas en nueve comprobaciones HTTP (400/403/404, nunca contenido privado).
- Archivo de verificación escrito por Apache y diagnósticos temporales fueron eliminados. Los archivos históricos originales no se movieron ni borraron.
- PHP local ya permite upload/post 40M; la aplicación conserva límites menores de 5 archivos, 5 MiB por archivo y 15 MiB total. No se cambió php.ini.

## Migración y pruebas

Se comprobó que el esquema del portal no estaba aplicado y que la migración de solicitantes era previa. La nueva migración agrega cuatro tablas y los campos de origen/contacto/visibilidad; el inventario no requiere cambios adicionales. El ticket original, sus comentarios y demás registros permanecen conservados. Nuevas tablas de enlaces/seguimiento/eventos vacías al finalizar.

```powershell
& C:/xampp/php/php.exe tests/aislamiento.php
& C:/xampp/php/php.exe tests/aislamiento.php --apache
```

**298 comprobaciones correctas en cada ejecución.** Estos flujos de creación/seguimiento/gestión se prueban únicamente con empresas y datos en copias, sin habilitar ninguna empresa real. Incluyen regresiones, empleo/asignaciones, privacidad, archivos, caducidad/revocación, límites y separación A/B. Comparación SHA-256 de todas las tablas de uso antes/después de las suites sin cambios.

En Apache del entorno de uso se verificaron además ocho páginas/rutas: login y recursos CSS/JS responden 200; gestión sin login responde 403; solicitud sin enlace, seguimiento/descarga sin credencial válida y acceso directo a archivo privado responden 404. Antes de elegir empresa esos rechazos son el comportamiento previsto, no un formulario público habilitado accidentalmente. Respuestas públicas con no-store/no-referrer; sin Warning/Fatal en esas respuestas. Se retiró exclusivamente el contador técnico de las tres consultas públicas de prueba y sus sesiones. Huellas de todas las tablas iguales al estado posterior a la migración y cero enlaces habilitados al cierre.

No se realizó recorrido visual completo en navegador ni se habilitó un formulario para una empresa real; corresponde al usuario elegirla. La guía siguiente permite probarlo en incógnito sin correo automático.

## Habilitar y copiar el enlace

1. Abrir [login local](http://localhost/inventario_ti/login.php) e iniciar sesión con una cuenta de **Operador global SmartTech**.
2. En plataforma, elegir la empresa y abrir **Soporte**.
3. Pulsar **Enlace de solicitudes de esta empresa** (o abrir `http://localhost/inventario_ti/soporte/enlace_empresa.php` con esa empresa activa).
4. Pulsar **Habilitar** y después **Copiar enlace**.
5. Abrir lo copiado en incógnito: completar nombre/correo, tipo, asunto y descripción; no hace falta usuario, empleado ni equipo.
6. Al guardar, copiar el enlace individual de seguimiento y conservarlo; quien lo tenga puede acceder. No se envía correo. Caduca a los 90 días, salvo configuración diferente, o por revocación explícita del operador.

Desactivar/renovar el enlace de creación no revoca el seguimiento ya emitido. Notas internas y sus archivos permanecen exclusivos del Operador global autorizado de SmartTech. Administradores/auditores del cliente reciben conversación pública dentro de su ámbito.

## Recuperación

Consultar [configuración y rollback del portal](portal-publico-soporte.md). Mantener clave y archivos privados junto con respaldos de ambas bases. Ante fallo, desactivar creación manteniendo columnas/datos; no restaurar el dump anterior sobre solicitudes nuevas sin preservarlas primero. Ensayar recuperación en copias y respetar dependencias entre esquemas. No eliminar tickets históricos para revertir una característica.
