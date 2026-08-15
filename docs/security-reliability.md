# Administración, seguridad y fiabilidad

## Accesos

Los accesos se conceden mediante perfiles separados de Administración, Finanzas, Compras, Recursos Humanos, Técnicos y Comercial. La matriz de cada perfil y las excepciones por usuario se gestionan en `/admin/access`. Toda excepción tiene precedencia sobre el perfil y cada cambio queda auditado y genera una alerta. La revisión vence cada tres meses; debe registrarse aunque no haya cambios.

Las rutas de negocio sensibles usan una habilidad explícita (`can:*`). Los enlaces de navegación se generan a partir de las mismas habilidades, pero la autorización efectiva siempre se comprueba en el servidor. Nunca se debe usar la visibilidad de la interfaz como control de acceso.

## Auditoría y alertas

La auditoría registra usuario, operación, ruta, método, IP, valores modificados y un UUID de correlación. Los secretos se sustituyen por `[REDACTED]`. `/admin/audit/export` exporta el resultado filtrado como CSV. Inicios de sesión, cambios de perfiles/permisos y fallos operativos se registran en `security_events` y notifican a administración.

Cada respuesta incluye `X-Correlation-ID`; el mismo valor aparece en logs y auditoría para seguir una operación completa.

## Copias y restauración

`database:backup` crea una copia SQLite cifrada diaria. `database:verify-backup` descifra la última copia en un fichero temporal aislado, ejecuta `PRAGMA integrity_check`, comprueba el esquema de la aplicación y elimina el temporal. La prueba se ejecuta semanalmente y queda registrada en `backup_restorations`.

Una restauración real de producción requiere ventana de mantenimiento, copia de seguridad previa y `database:restore <fichero> --force`. Después se reinician workers y scheduler y se ejecutan migraciones, `/up`, `system:health-check` y una prueba de autenticación de solo lectura.

## Ficheros y retención

Las subidas sensibles usan una lista explícita de extensiones, límite de tamaño y detección MIME del framework. Si `CLAMAV_SOCKET` está configurado, la validación usa el protocolo INSTREAM de ClamAV y falla de forma cerrada si el escáner no responde.

`data:prune-retention --dry-run` muestra el impacto. La ejecución semanal conserva auditoría 7 años, eventos de seguridad 2 años, telemetría de salud 90 días y respuestas de idempotencia móvil 30 días. Los documentos fiscales/contractuales no se eliminan con este proceso; su borrado requiere el flujo funcional correspondiente y sus obligaciones legales.

## Despliegue y rollback

Antes de desplegar: ejecutar la suite, crear una copia cifrada y anotar el SHA desplegado. Después: ejecutar migraciones, reiniciar workers, verificar `/up`, ejecutar `system:heartbeat` y `system:health-check`, comprobar una cola, el envío de correo y una integración configurada.

Se revierte si falla una migración, hay errores sostenidos de cola/cron, una ruta crítica devuelve 5xx o fallan los controles de autorización. El rollback consiste en retirar tráfico, volver al SHA anterior, ejecutar `migrate:rollback --step=1` solo si la migración declara una reversión segura y, si hubo transformación irreversible, restaurar la copia previa. A continuación se repiten todas las comprobaciones posteriores y se documenta el incidente con su UUID de correlación.
