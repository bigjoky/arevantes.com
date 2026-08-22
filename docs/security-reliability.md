# Administración, seguridad y fiabilidad

## Accesos

Los accesos se conceden mediante perfiles separados de Administración, Finanzas, Compras, Recursos Humanos, Técnicos y Comercial. La matriz de cada perfil y las excepciones por usuario se gestionan en `/admin/access`. Toda excepción tiene precedencia sobre el perfil y cada cambio queda auditado y genera una alerta. La revisión vence cada tres meses; debe registrarse aunque no haya cambios.

Las rutas de negocio sensibles usan una habilidad explícita (`can:*`). Los enlaces de navegación se generan a partir de las mismas habilidades, pero la autorización efectiva siempre se comprueba en el servidor. Nunca se debe usar la visibilidad de la interfaz como control de acceso.

La API móvil exige `manage-operations` además de la capacidad `mobile` del token. Los perfiles técnicos solo pueden operar sobre órdenes asignadas. Los tokens de dispositivo caducan a los 30 días por defecto mediante `MOBILE_TOKEN_EXPIRATION_MINUTES`.

## Auditoría y alertas

La auditoría registra usuario, operación, ruta, método, IP, valores modificados y un UUID de correlación. Los secretos se sustituyen por `[REDACTED]`. `/admin/audit/export` exporta el resultado filtrado como CSV. Inicios de sesión, cambios de perfiles/permisos y fallos operativos se registran en `security_events` y notifican a administración.

Cada respuesta incluye `X-Correlation-ID`; el mismo valor aparece en logs y auditoría para seguir una operación completa.

## Copias y restauración

`database:backup` crea una copia SQLite cifrada diaria en instalaciones que utilicen ese motor. `database:verify-backup` descifra la última copia en un fichero temporal aislado, ejecuta `PRAGMA integrity_check`, comprueba el esquema de la aplicación y elimina el temporal. La prueba se ejecuta semanalmente y queda registrada en `backup_restorations`.

Para PostgreSQL, `database:backup-postgres` ejecuta `pg_dump` en formato custom, cifra el resultado por streaming con XChaCha20-Poly1305 y una clave independiente de `APP_KEY`, calcula SHA-256 y guarda el artefacto y su manifiesto firmado en almacenamiento de objetos privado. Las contraseñas solo se entregan al proceso mediante su entorno y nunca aparecen en argumentos, manifiestos o auditoría. El almacenamiento local queda bloqueado en producción.

`database:verify-postgres-backup` descarga y autentica el artefacto, lo restaura realmente en la conexión desechable configurada y comprueba tablas y migraciones. Rechaza el destino si coincide con producción, si comparte host sin autorización expresa o si el nombre no cumple el patrón de aislamiento. Tras la prueba elimina el esquema restaurado y registra RPO, RTO, resultado y métricas en `backup_restorations`. La creación es diaria y la restauración semanal; `system:health-check` falla cuando una copia o restauración supera su antigüedad máxima.

Variables imprescindibles: `POSTGRES_BACKUP_ENABLED`, `POSTGRES_BACKUP_DISK`, `POSTGRES_BACKUP_ENCRYPTION_KEY` y una conexión `POSTGRES_RESTORE_*` dedicada. Habilitar `POSTGRES_RESTORE_VERIFICATION_ENABLED` solo después de validar que esa base es desechable y está aislada. Después de una restauración de contingencia real se reinician workers y scheduler y se ejecutan migraciones, `/up`, `system:health-check` y una prueba de autenticación de solo lectura.

## Ficheros y retención

Las subidas sensibles usan una lista explícita de extensiones, límite de tamaño y detección MIME del framework. Si `CLAMAV_SOCKET` está configurado, la validación usa el protocolo INSTREAM de ClamAV y falla de forma cerrada si el escáner no responde.

`data:prune-retention --dry-run` muestra el impacto. La ejecución semanal conserva auditoría 7 años, eventos de seguridad 2 años, telemetría de salud 90 días y respuestas de idempotencia móvil 30 días. Los documentos fiscales/contractuales no se eliminan con este proceso; su borrado requiere el flujo funcional correspondiente y sus obligaciones legales.

## Despliegue y rollback

Antes de desplegar: ejecutar la suite, crear una copia cifrada y anotar el SHA desplegado. Después: ejecutar migraciones, reiniciar workers, verificar `/up`, ejecutar `system:heartbeat` y `system:health-check`, comprobar una cola, el envío de correo y una integración configurada.

Se revierte si falla una migración, hay errores sostenidos de cola/cron, una ruta crítica devuelve 5xx o fallan los controles de autorización. El rollback consiste en retirar tráfico, volver al SHA anterior, ejecutar `migrate:rollback --step=1` solo si la migración declara una reversión segura y, si hubo transformación irreversible, restaurar la copia previa. A continuación se repiten todas las comprobaciones posteriores y se documenta el incidente con su UUID de correlación.
