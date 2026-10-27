# STAB-03 — Recuperación y preflight

Verificación local del 26/10/2026. Este procedimiento no autoriza operaciones en producción.

## Ensayo reproducible

Desde el repositorio: `python3 scripts/testing/stab03-recovery.py`. Requiere el contenedor local `apple-dev-postgres` y sus clientes PostgreSQL. El ejecutor solo admite `arevantes_stab03_recovery_test` como origen sintético y `arevantes_stab03_restore_verify_test` como destino desechable. Antes de `migrate:fresh`, comprueba el identificador del clúster local y el comentario `arevantes:synthetic-recovery-source:v1`. Solo etiqueta bases recién creadas; rechaza bases existentes sin marca.

El verificador exige nombre terminado en `_restore_verify_test`, comentario `arevantes:disposable-restore-verification:v1`, conexión primaria y nombre distinto del origen y de la aplicación, incluso cambiando host o puerto. No acepta nombres de negocio, producción o demo. La opción de mismo host no elimina estas barreras. Las URL no pueden sobrescribir parámetros de conexión; se eliminan variables heredadas de servicio y enrutamiento de libpq.

Un bloqueo asesor de sesión impide verificaciones concurrentes sobre el mismo destino. Antes de limpiar, vuelve a comprobar marca, base y PID. Si se pierde la autorización o sesión, conserva el destino y comunica el fallo. Valida firma, origen, tamaño y checksum del respaldo antes de tocar el esquema. Nunca establece automáticamente la marca de autorización.

La restauración compara inventarios y huellas de esquema/datos sintéticos, comprueba restricciones y guardas. Si las migraciones declaran protecciones temporales o económicas ausentes/deshabilitadas, rechaza el resultado. Las comprobaciones de dominio y archivos del ensayo complementan al inventario genérico; este no garantiza por sí solo la integridad de todos los datos reales.

## Archivos y tiempos

El respaldo lógico PostgreSQL no contiene los PDF del almacenamiento. El ensayo recupera separadamente un paquete sintético, verifica bytes originales y detecta alteración/ausencia. `pdf_files_recovered=false` sigue siendo correcto en el resultado del verificador de base de datos. Antes de un despliegue hay que verificar el respaldo independiente del almacenamiento real, claves, permisos y acceso.

RPO/RTO: segundos enteros redondeados hacia arriba; microsegundos exactos disponibles en métricas. RTO usa reloj monótono y cubre esta operación, no la recuperación completa del ERP. La precisión del RPO está limitada por la precisión original de `completed_at`. Un respaldo sin fecha de finalización o con fecha futura se rechaza. No se reescriben métricas históricas.

## Diagnóstico económico previo

`php artisan received-invoices:audit --strict` ejecuta una transacción PostgreSQL `REPEATABLE READ, READ ONLY`. Funciona antes de las cuatro migraciones ECO-02, sobre el esquema económico previo. Sin snapshot histórico, identifica expresamente la identidad inferida. No reserva identidades ni hace backfill.

Distingue colisiones que bloquean índices, identidades incompletas/inferidas, incoherencias de pagos, asientos desequilibrados, referencias huérfanas, originales ausentes/alterados y almacenamiento no disponible. `--strict` devuelve error si necesita revisión; los contadores de hallazgos pueden solaparse y no representan documentos únicos. No limpia archivos sin referencia automáticamente.

## Puertas de despliegue

Requiere autorización expresa, corte revisado, preflight sobre datos reales autorizado, resolución individual de históricos, respaldo independiente de archivos, ensayo en staging con volumen representativo y destino desechable exclusivo. Conservar claves, originales y auditoría. No retirar guardas con evidencia ni volver a escritores temporales incompatibles. No usar bases de negocio o demo como destino. La verificación local no prueba el proveedor ni los datos de producción.
