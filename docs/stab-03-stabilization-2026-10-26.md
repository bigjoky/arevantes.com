# STAB-03 — Cierre de regresiones y recuperación

26/10/2026. Código local integrado; sin publicación, commit, push ni merge. Sin cambios de interfaz, Nexo o contabilidad futura. Datos de ensayo sintéticos y PostgreSQL local aislado. Se conservaron los cambios pendientes del repositorio.

## Diagnóstico y correcciones

1. **Arevia:** `LIKE` de PostgreSQL distinguía mayúsculas en la búsqueda de referencias. `whereTokens` usa `orWhereLike`, con semántica insensible a mayúsculas nativa del framework. Los permisos financieros y límites existentes permanecen; las pruebas comprueban búsqueda autorizada y ocultación.
2. **Fecha de compra:** el fallo comparaba atributos crudos recién creados (`121`) con atributos recargados (`121.00`). La prueba compara la representación decimal canónica y exige `121.00`. No se cambió precisión monetaria ni redondeo. Una prueba adicional conserva `1234567890.10` en snapshot y JSON.
3. **Deye:** el diagnóstico global fallaba por ausencia de respaldo/restauración en el fixture PostgreSQL; la prueba específica aporta evidencia sintética fresca y mantiene los cuatro campos obligatorios. Además, salud consulta la última restauración completada, incluida una fallida posterior: no oculta fallos seleccionando solo éxitos. La prueba de configuración no acredita conectividad Deye real.
4. **Recuperación:** Carbon podía producir segundos fraccionarios incompatibles con bigint. Se calculan microsegundos con aritmética entera y segundos conservadores por techo; RTO monótono. No se convierten registros históricos. Fechas de respaldo ausentes/futuras se rechazan.

La restauración queda limitada a destino explícitamente desechable, con marca externa, comprobación de identidad y bloqueo de sesión. Valida firma, checksum, tamaño y origen antes de limpiar. Una pérdida de sesión/marca impide limpieza posterior y conserva evidencia. Se sanea el entorno libpq y no se pasan contraseñas en argumentos. Un fallo al guardar el diagnóstico no sustituye el rechazo original.

Se añadió rechazo de restauraciones cuyas migraciones declaran guardas temporales/económicas ausentes o deshabilitadas, o índice de identidad inválido. No se modificaron las migraciones ni las guardas originales.

## Evidencia de recuperación

Siete casos reales PostgreSQL aprobados, 94 aserciones: restauración válida, marca ausente, respaldo alterado, fallo parcial con limpieza autorizada, pérdida de autorización con conservación, exclusión concurrente y guarda económica ausente. Cifrado, firma, clientes reales `pg_dump`/`pg_restore`, origen y destino marcados exclusivamente sintéticos.

Se comparan filas de 20 tablas críticas, 248 migraciones, inventario de 213 tablas, 2.279 restricciones, 728 índices y 16 triggers habilitados (10 temporales y 6 económicos). Se prueban rechazos efectivos de duplicación fiscal, FK huérfana y evidencia UTC ausente. Incluye dos instantes de la hora repetida de Madrid, importes, pagos, conciliación, asientos y auditoría.

La comparación de definiciones normaliza únicamente casts equivalentes de literales de texto que `pg_dump` vuelve a expresar al reconstruir arrays. No elimina casts numéricos, predicados, tipos/escala, nombres ni indicadores de validez. Se comparan también columnas, índices, triggers y restricciones por separado.

El ensayo de archivos es independiente: recupera bytes originales, rechaza PDF alterado y detecta ausencia. La restauración SQL sigue declarando `pdf_files_recovered=false`. No equivale a probar el almacenamiento ni las claves de producción.

## Preflight y compatibilidad

El diagnóstico económico es SELECT-only, con transacción PG de solo lectura y snapshot repetible. Funciona sin las cuatro migraciones ECO-02. Separa identidad inferida, datos incompletos, colisiones que impedirían migraciones, referencias huérfanas, pagos, equilibrio contable y custodia. No completa identidades ni altera históricos.

La ejecución estricta sobre el entorno de pruebas integrado señaló archivos sintéticos sin referencia dejados por pruebas transaccionales; exige revisión correctamente. No es un inventario de producción ni una autorización para borrar. Las pruebas verifican datos inalterados y funcionamiento tanto antes como después de ECO-02.

Las 62 huellas TIME-02 permanecen intactas. Del manifiesto ECO-02 solo cambian diagnóstico de lectura y dos fixtures afectados por las regresiones descritas. Guardas, migraciones e identidad originales conservadas. Sin nuevas migraciones STAB-03.

## Verificación integrada

Resultados exactos y huellas en [evidencia JSON](stab-03-verification-2026-10-26.json). Las ejecuciones focalizadas repetidas no se suman como casos nuevos. Las omisiones no se consideran aprobadas.

- Suite general: 1.058 casos, 1.020 aprobadas, 38 omitidas, 0 fallos, 14.902 aserciones.
- PostgreSQL ampliado: 296 aprobadas, 0 fallos, 3.887 aserciones.
- Concurrencia económica: 11 aprobadas, 69 aserciones; identidad ECO-02: 4 aprobadas, 25 aserciones, procesos reales separados.
- Recuperación real: 7 aprobadas, 94 aserciones.
- Frontend: 56 aprobadas; TypeScript, ESLint y build aprobados. Build mantiene aviso de tamaño de chunks.
- Formato PHP de los archivos afectados, sintaxis Python y diff comprobados.
- i18n: 211 candidatos anteriores pendientes; inventario completo por módulo/origen en [JSON](stab-03-i18n-inventory.json). No se modificaron JS ni catálogos. La coincidencia literal con HEAD no atribuye autoría y requiere revisión de posibles identificadores.

## Riesgos y dictamen

**APTO PARA PREPARAR DESPLIEGUE**, condicionado al corte final revisado y a las puertas de [recuperación y preflight](stab-03-recovery-preflight.md). No es aprobación de publicación. Permanecen pendientes el diagnóstico autorizado de producción, decisiones individuales sobre históricos/identidades y originales ausentes, ensayo de staging con volumen realista, custodia independiente real y coordinación de escritores UTC. No se modificó comportamiento fiscal o contractual ni se fusionaron históricos.

Siguiente incremento: preparación controlada de staging y preflight autorizado; resolver sus hallazgos antes de publicar. No iniciar ECO-03 hasta cerrar estas puertas.

## Archivos de la intervención

- `app/Console/Commands/AuditReceivedInvoices.php`
- `app/Console/Commands/CheckSystemHealth.php`
- `app/Services/Ai/RelevantBusinessContext.php`
- `app/Services/Backup/PostgresConnection.php`
- `app/Services/Backup/PostgresRestoreVerifier.php`
- `app/Services/Backup/RecoveryDuration.php`
- `app/Services/Finance/ReceivedInvoiceAudit.php`
- `config/backup.php`
- `tests/Feature/AccountingTest.php`
- `tests/Feature/EconomicDeploymentPreflightTest.php`
- `tests/Feature/PostgresRecoveryRehearsalTest.php`
- `tests/Feature/PostgresRecoverySafetyTest.php`
- `tests/Feature/PurchaseOrderDateCorrectionTest.php`
- `tests/Feature/SecurityAdministrationTest.php`
- `tests/Feature/StabilizationHealthTest.php`
- `scripts/testing/stab03-recovery.py`
- `tests/Support/recovery-pg-client.py`

Documentación: este informe, procedimiento de recuperación/preflight, evidencia JSON, inventario i18n y roadmap.
