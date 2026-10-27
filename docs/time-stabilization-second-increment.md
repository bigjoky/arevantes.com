# Segundo incremento de estabilización temporal

Estado: implementación local; no autoriza publicación. Alcance: los 35 campos y diez modelos del [primer informe](time-stabilization-2026-10-25.md), sus escritores y consumidores. La revisión no convierte los demás timestamps del ERP ni modifica fechas civiles, importes o lógica fiscal.

## Preservación y diagnóstico

Se inspeccionaron rama `fix/work-order-layout`, worktrees, archivos modificados y no versionados antes de editar. Se mantuvo la copia que sirve `localhost:8000`. El trabajo previo de expedientes, marca, PDF, móvil y PWA permaneció en su sitio; no se hicieron reset, checkout, limpieza, commit, merge ni publicación. Las modificaciones en archivos compartidos son aditivas o localizadas en el tratamiento temporal. La validación integrada utiliza una copia desechable de **todo el estado local**, incluidos archivos no versionados y eliminaciones de marca, sin copiar `.env`, bases locales o credenciales.

Problemas adicionales confirmados:

1. Escrituras masivas Eloquent normalizadas en el primer incremento, pero `insert` y `upsert` no estaban cubiertos. Los escritores SQL o de versiones anteriores podían dejar auxiliares obsoletos.
2. Filtros, ordenaciones y `pluck` ordinarios todavía podían leer hora civil en lugar del instante. Proyecciones avanzadas podían omitir el auxiliar. Consultas analíticas y cronologías con union requerían tratamiento explícito.
3. Formularios secundarios y navegación de calendario dependían de la zona del dispositivo; una reprogramación podía normalizar el salto de primavera antes de llegar al servidor.
4. Cuatro lugares bloqueaban `MAX(version)`/`MAX(document_version)`: tres fallos originales observados, con una cuarta ruta de evaluación con el mismo defecto. Un agregado no es una fila bloqueable y una serie vacía tampoco queda protegida bloqueando solo hijos.
5. La auditoría clasificaba como preciso cualquier auxiliar presente, sin contrastarlo con el original. Los down iniciales podían eliminar evidencia UTC nueva.

## Matriz de escritores auditados

| Entidad | Entradas y procesos del repositorio | Garantía aplicada |
| --- | --- | --- |
| DocumentDelivery | DocumentDeliveryController; AcceptanceInvoiceNotifier | Cast en create/save; reloj empresarial; actualización masiva normalizada; guard SQL PostgreSQL |
| CustomerPortalAccess | CustomerPortalController; revocación por relación portalAccesses | Cast individual y builder de relaciones; guard SQL |
| WorkOrderAttachment | carga y publicación de WorkOrderController; relaciones attachments | Pares UTC; publicación con bloqueo de instalación; guard SQL |
| PortfolioProject | PortfolioProjectAdminController | Cast, reloj empresarial y guard SQL |
| PlanningScenario | SharedPortfolioScenario, PlanningScenarioApproval, PlanningScenarioLifecycle y controladores de escenarios | Cast para los tres instantes; almacenamiento original timestamptz conservado donde ya existía; guard SQL |
| Opportunity | captación web/manual/móvil; OpportunityController; LeadResponseSla y LeadResponseSlaNotifier; observers | created_at preciso; update/updateQuietly/relaciones y reloj; insert/upsert normalizados; guard SQL |
| WorkOrder | WorkOrderFactory, WorkOrderRescheduler, WorkOrderStateManager, QuoteController, ingeniería, ServiceCycleWorkWorkflow y APIs móviles | Validación antes de convertir entradas civiles; cast, builder y guard SQL; sin modificar reglas de estados |
| TimeEntry | WorkOrderController y API Mobile V1 WorkOrderController, relaciones timeEntries | Cast y escritura pareada; no calcular un inicio histórico ambiguo; guard SQL |
| CrmActivity | OperationsController, API ActivityController/CommercialLeadController, QuoteOperationalPlanner, WorkOrderFactory/Rescheduler y sincronizaciones | Cast individual y builder masivo; reloj; guard SQL |
| WorkOrderReschedule | WorkOrderRescheduler | Captura de ambos instantes antiguos/nuevos y guard SQL |

Se buscaron referencias de los diez modelos, relaciones y los 35 nombres de campo, escrituras `insert`, `upsert`, `updateOrInsert`, SQL directo, jobs, comandos, observers y automatizaciones. No se encontró un importador o integración del repositorio que escriba esos campos por SQL directo. Los jobs con campos homónimos de otras entidades (backup, generación IA, sesiones comerciales) no se confunden con este alcance. El comando de SLA llega al servicio auditado. No existe directorio `app/Listeners` en esta copia.

La revisión del código no descubre clientes externos que operen directamente contra producción: su inventario es una condición expresa de publicación. No se presume que un observer cubra SQL directo.

### Protección de escritores antiguos

La migración `040000` instala un trigger PostgreSQL en las diez tablas, con 35 argumentos de campo. Comprueba solo pares modificados, no reinterpreta filas históricas al cambiar otro dato. Rechaza con SQLSTATE `23514`:

- original cambiado y auxiliar antiguo incompatible;
- eliminación aislada del auxiliar que dejaría un original con pérdida de evidencia;
- auxiliar sin original;
- original y auxiliar que representan horas distintas.

La rama de escenarios compara instantes porque dos columnas originales ya eran timestamptz. Las demás comparan la hora civil de Madrid derivada del UTC. No se elige automáticamente una ocurrencia para escrituras antiguas. Un par antiguo sin auxiliar puede seguir siendo antiguo y aparecerá así en la auditoría; el trigger no inventa ese dato.

**No se admite despliegue con escritores de versiones mixtas.** Un escritor antiguo que falla de forma explícita es preferible a corrupción silenciosa, pero puede provocar errores de negocio. Además, una escritura antigua de una hora civil idéntica no permite distinguir la intención de cambiar de ocurrencia. La publicación exige mantenimiento, parada de consumidores/automatizaciones e integraciones de escritura y arranque coordinado de una única versión UTC compatible.

SQLite es útil para regresión local, pero no acredita la protección de SQL externo: el trigger y las pruebas de integridad definitivas son PostgreSQL.

## Lectores, consultas y presentación

`InstantSql` genera una expresión de instante real con alternativa histórica explícita. `InstantBuilder` la aplica a comparaciones, rangos positivos/negativos, orden ascendente/descendente, latest/oldest y pluck de campos del alcance. Se conservan `whereDate` y filtros civiles como tales. Una hora histórica inexistente no se normaliza silenciosamente. Los auxiliares parcialmente poblados conviven con las columnas antiguas.

Los grupos/distinct/unions que proyectan un campo temporal deben seleccionar su auxiliar explícitamente. En vez de devolver una fecha potencialmente errónea, la proyección incompleta produce una excepción explícita. Grupos de estados, conteos y proyecciones de identificadores conservan su contrato y están probados.

Se corrigieron filtros y fechas de salida de AnalyticsService y RecurringServiceHealth; cronologías union de CustomerWorkspace y QuoteWorkspace; orden de DashboardController; clasificación Kanban y filtro de obsolescencia en OpportunityController. Las ramas históricas sin auxiliar se convierten únicamente para consultar/presentar, sin reescritura del origen y conservando las limitaciones de la información antigua. No se recalculan importes ni costes.

En React se mantienen la interfaz y sus acciones. Los formularios de operaciones preservan el ISO original si la hora no cambia. Los calendarios usan la fecha empresarial; sumar días civiles usa un calendario independiente de la zona del dispositivo. Los movimientos con duración conservan tiempo transcurrido real a través del salto horario. Una entrada repetida o inexistente permanece sin normalizar para que el servidor la rechace.

No se ha encontrado una necesidad acreditada de asignar habitualmente instalaciones durante la hora repetida nocturna. Se mantiene el rechazo comprensible sin añadir selector. Las APIs aceptan ISO con desplazamiento explícito; sigue disponible la edición sin cambio de ocurrencia de una asignación precisa existente.

## Integridad y migraciones

- `030000` y `031000`: 35 `timestamp with time zone(6)` nullable, sin conversión ni backfill. Ahora se niegan a eliminar columnas si cualquiera contiene evidencia UTC. El guard de down comprueba antes de empezar a eliminar columnas.
- `040000`: función y diez triggers; ninguna transformación histórica. `CREATE OR REPLACE` permite reconstruir esquemas desechables después de `migrate:fresh`, que elimina tablas pero no funciones PostgreSQL. Su down solo elimina protección, nunca columnas de evidencia.
- `041000`: nueve índices de expresión para planificación, partes, SLA, actividades y publicación. Misma expresión que las consultas; alternativa de invierno calculada en UTC para que sea inmutable e independiente de la zona de sesión. PostgreSQL rechaza la primera forma con resta sobre timestamptz por no ser inmutable: se corrigió, no se etiquetó artificialmente una función como IMMUTABLE.

Los índices se construyen dentro de la ventana de mantenimiento. No se promete una migración online sin bloqueos. Medir duración en una copia representativa del volumen de producción antes de fijar la ventana. No ejecutar rollback de `030000`/`031000` para volver al código antiguo.

## Versionado y concurrencia

- Documentos: se bloquea la instalación padre antes de calcular el máximo y crear la versión, incluso con serie vacía. Publicar también bloquea primero esa instalación, para serializar reemplazos entre distintas órdenes del mismo proyecto. El almacenamiento del archivo queda fuera del bloqueo.
- Propuestas de calibración: advisory lock transaccional por línea de negocio y clave métrica, incluso sin filas. Tras adquirirlo se vuelve a comprobar la huella. Los índices únicos originales siguen siendo la última defensa.
- Evaluaciones y gobernanza: bloqueo de la propuesta padre antes de deduplicar y calcular versión. No se elimina la garantía de exclusión para hacer pasar pruebas.
- Cuatro pruebas PostgreSQL lanzan dos procesos y comprueban mediante `pg_stat_activity` que ambos esperan el bloqueo antes de liberarlo. Comprueban versiones documentales 1/2 y una única propuesta/evaluación/revisión para evidencia repetida.

## Auditoría y revisión manual

`time:audit-instants --json` emite solo totales por tabla/campo: precise, legacy_unambiguous, ambiguous, invalid_or_nonexistent, divergent y empty. Contrasta ambos valores para acreditar precisión. Captura entradas inválidas sin imprimir su contenido. No incluye email, token, ID de cliente ni registro. Lee por lotes de 200 y no actualiza datos. `--strict` devuelve salida 1 si hay ambigüedad, invalidez o divergencia; salida 0 no transforma valores antiguos en precisos.

La revisión manual autorizada se realiza fuera del informe agregado:

1. Abrir incidencia restringida con tabla/campo/ID interno y copia protegida de valores anteriores, huella del informe y evidencia externa del instante (firma, log de proveedor, agenda con offset, etc.). No trasladar datos personales a informes públicos.
2. Registrar responsable administrador, fecha UTC, motivo, evidencia y desplazamiento elegido. Sin evidencia suficiente, mantener la ambigüedad y la política conservadora; no aprobar la corrección como exacta.
3. Corregir un único registro en transacción bloqueada, comprobando que los valores anteriores no hayan cambiado. Usar el cast o `BusinessTime::storage` para ambos valores. Registrar antes/después mediante el AuditLog existente y el ID del responsable, evento `temporal_manual_review`, motivo y referencia de evidencia; no crear otro historial paralelo.
4. Repetir la auditoría, verificar consumidores y conservar informe anterior/posterior. Revisar documentación afectada con el negocio; no modificar retroactivamente un documento emitido ni sus importes por esta operación.

## Procedimiento de publicación futura — no ejecutado

1. Obtener autorización expresa. Preparar un corte de código revisado que incluya dependencias reales de los cambios concurrentes y los artefactos probados. Hasta entonces no hacer commit, push, merge ni despliegue.
2. En staging PostgreSQL aislado, restaurar una copia autorizada y protegida con la misma versión, extensiones y tzdata de destino. Ejecutar migraciones y pruebas; medir bloqueos/tiempo de los nueve índices. Documentar backup y restauración verificada.
3. Confirmar `APP_TIMEZONE=Europe/Madrid`, PHP/PostgreSQL/tzdata, 35 columnas (tipo/precisión/nullable), nueve índices y diez triggers habilitados. Inventariar todos los procesos de escritura, incluidas colas, scheduler, workers persistentes e integraciones externas.
4. En la ventana autorizada, suspender tráfico de escritura, scheduler, colas e integraciones; esperar a que terminen transacciones. No confiar solo en `artisan down`, que no detiene workers ni scripts externos. Conservar copia de seguridad que incluya columnas y audit logs.
5. Ejecutar el código candidato en el entorno detenido. Sobre la conexión de producción verificada por el operador autorizado: `php artisan migrate:status`; aplicar únicamente las migraciones revisadas pendientes con `php artisan migrate --force`. No usar `migrate:fresh`, `db:wipe` ni rollback.
6. Ejecutar **solo tras autorización de auditoría**: `php artisan time:audit-instants --json --strict > temporal-audit-production.json`. Conservar salida, exit code, fecha UTC, versión exacta, zona y huella SHA-256 del informe en almacenamiento restringido. El informe agregado no cambia datos. No publicar si strict devuelve error: seguir revisión manual autorizada y repetir.
7. Confirmar metadatos: `information_schema.columns` para los 35 campos; `pg_indexes` para nombres terminados `_instant_idx`; `pg_trigger` con `tgname='precise_instants_guard'`, `tgenabled='O'`, diez tablas y suma `tgnargs=35`. Verificar en staging escrituras válidas y rechazo 23514 de un escritor antiguo; no insertar fixtures de prueba en producción.
8. Actualizar caches/artefactos y reiniciar todos los procesos a la misma versión. Comprobar salud, acceso, apertura de documentos, planificación y próximas caducidades con acciones de lectura autorizadas. Reanudar escrituras, scheduler, integraciones y colas de forma coordinada; monitorizar 23514, 500, SLA y disponibilidad.

Este documento prepara el procedimiento; **no acredita que esos pasos se hayan ejecutado en producción**.

## Recuperación sin pérdida temporal

Si la auditoría o la migración falla: mantener mantenimiento y procesos detenidos; las migraciones PostgreSQL son transaccionales. No eliminar auxiliares. Corregir el problema y repetir la verificación.

Si falla la aplicación tras registrar UTC nuevo: mantener esquema, triggers, datos y evidencia; volver únicamente a un artefacto probado que lea UTC y mantenga los pares, o aplicar una corrección hacia delante. La versión de producción anterior al primer incremento no cumple ese requisito: **no es un rollback seguro con escrituras activas**. En ese caso conservar mantenimiento hasta disponer de artefacto compatible.

Una restauración completa del backup solo puede autorizarse tras reconciliar todas las escrituras posteriores, incluidos sus UTC y audit logs. No perder esa evidencia para recuperar servicio. Retirar exclusivamente índices no elimina evidencia, pero requiere nueva revisión de rendimiento. No deshabilitar triggers para hacer funcionar un escritor antiguo.

## Verificación y decisión

Los resultados exactos de las ejecuciones finales se registran en el roadmap. Las ejecuciones intermedias detectaron y permitieron corregir una expresión de índice no inmutable, un fixture concurrente inválido y una sustitución de reloj mal formada; no se contabilizan como aprobadas.

La publicación permanece supeditada a revisión del corte, prueba de volumen y auditoría autorizada de producción. Las 79 traducciones pendientes son previas/concurrentes y se registran aparte. No se declara P0 transversal cerrado ni se acredita auditoría de otros modelos temporales.

## Archivos del alcance temporal revisados

La relación incluye los archivos del primer incremento que siguen formando parte del corte local y los ampliados en este segundo incremento. No representa una publicación Git.

- `app/Casts/PreciseInstant.php`
- `app/Console/Commands/AuditTemporalInstants.php`
- `app/Database/InstantBuilder.php`
- `app/Http/Controllers/Api/Mobile/V1/ActivityController.php`
- `app/Http/Controllers/Api/Mobile/V1/CommercialLeadController.php`
- `app/Http/Controllers/Api/Mobile/V1/WorkOrderController.php`
- `app/Http/Controllers/CustomerPortalController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/DocumentDeliveryController.php`
- `app/Http/Controllers/OperationsController.php`
- `app/Http/Controllers/OpportunityController.php`
- `app/Http/Controllers/PlanningScenarioController.php`
- `app/Http/Controllers/PortfolioProjectAdminController.php`
- `app/Http/Controllers/PortfolioProjectImageController.php`
- `app/Http/Controllers/QuoteController.php`
- `app/Http/Controllers/WorkOrderController.php`
- `app/Models/Concerns/HasPreciseInstants.php`
- `app/Models/CrmActivity.php`
- `app/Models/CustomerPortalAccess.php`
- `app/Models/DocumentDelivery.php`
- `app/Models/Opportunity.php`
- `app/Models/PlanningScenario.php`
- `app/Models/PortfolioProject.php`
- `app/Models/TimeEntry.php`
- `app/Models/WorkOrder.php`
- `app/Models/WorkOrderAttachment.php`
- `app/Models/WorkOrderReschedule.php`
- `app/Observers/WorkOrderObserver.php`
- `app/Services/AfterSales/ServiceCycleWorkWorkflow.php`
- `app/Services/Analytics/AnalyticsService.php`
- `app/Services/Analytics/RecurringServiceHealth.php`
- `app/Services/Crm/LeadResponseSla.php`
- `app/Services/Crm/LeadResponseSlaNotifier.php`
- `app/Services/Customers/CustomerWorkspace.php`
- `app/Services/Documents/AcceptanceInvoiceNotifier.php`
- `app/Services/Engineering/TechnicalScheduleAvailability.php`
- `app/Services/Planning/PlanningCalibrationEvaluationService.php`
- `app/Services/Planning/PlanningCalibrationGovernanceService.php`
- `app/Services/Planning/PlanningCalibrationProposalService.php`
- `app/Services/Planning/PlanningScenarioApproval.php`
- `app/Services/Planning/PlanningScenarioLifecycle.php`
- `app/Services/Planning/PlanningScenarioRequestWorkflow.php`
- `app/Services/Quotes/QuoteOperationalPlanner.php`
- `app/Services/Quotes/QuoteWorkspace.php`
- `app/Services/Work/TechnicianSchedule.php`
- `app/Services/Work/WorkOrderRescheduler.php`
- `app/Services/Work/WorkOrderStateManager.php`
- `app/Support/Time/BusinessTime.php`
- `app/Support/Time/InstantSql.php`
- `database/migrations/2026_10_25_030000_preserve_critical_instants_with_utc_companions.php`
- `database/migrations/2026_10_25_031000_preserve_reschedule_instants.php`
- `database/migrations/2026_10_25_040000_guard_precise_instant_writes.php`
- `database/migrations/2026_10_25_041000_index_precise_instant_queries.php`
- `tests/Feature/TemporalConcurrencyTest.php`
- `tests/Feature/TemporalInstantsTest.php`
- `tests/Feature/TemporalIntegrityTest.php`
- `tests/Support/temporal-concurrency-worker.php`
- `resources/js/lib/business-time.ts`
- `resources/js/components/operations/workspace.tsx`
- `resources/js/pages/work-orders/index.tsx`
- `resources/js/pages/operations/index.tsx`
- `tests/business-time.test.mjs`

La inspección previa al down adquiere bloqueos exclusivos de las tablas dentro de una transacción PostgreSQL antes de comprobar los auxiliares, para impedir una escritura entre la comprobación y el DDL. Una invocación directa sin transacción se rechaza. No se ejecutó down en la base local ni en producción.

Los índices dependen de las reglas IANA de Madrid: una futura actualización de tzdata requiere revisar las fechas futuras y reconstruir los índices de expresión en mantenimiento. No cambiar de zona empresarial ni de tzdata de forma aislada entre PHP, PostgreSQL y los procesos persistentes.

## Resultados finales ejecutados

| Verificación | Resultado |
| --- | --- |
| Suite completa en copia aislada del estado local combinado | **963 aprobadas, 15 omitidas, 14.554 aserciones** (978 casos) |
| PostgreSQL 18.6, temporal/documentos/portal/SLA/escenarios/publicación/disponibilidad | **47 aprobadas, 874 aserciones** |
| PostgreSQL, órdenes de trabajo en ejecución separada | **9 aprobadas, 76 aserciones** |
| PostgreSQL, analítica/clientes/CRM/dashboard/servicios recurrentes | **95 aprobadas, 1.473 aserciones** |
| PostgreSQL, cuatro carreras con procesos independientes | **4 aprobadas, 20 aserciones** |
| PostgreSQL, repetición final temporal y migraciones tras protección transaccional del down | **20 aprobadas, 200 aserciones**, sin omisiones |
| Copia aislada, comprobación temporal focalizada final | **17 aprobadas, 3 omitidas, 81 aserciones**; omisiones exclusivas de PostgreSQL |
| Frontend | **50 aprobadas**, incluidas las pruebas nuevas de zona del dispositivo, salto y hora repetida |
| TypeScript y ESLint global | Aprobados |
| PHP syntax y formato del alcance | Aprobados |
| Compilación de producción | Aprobada; advertencia de tamaño de chunks, sin error |
| Comprobación global de traducciones | **No aprobada: 79 cadenas previas/concurrentes pendientes** |
| Diff y acceso localhost | Sin errores de espacios; login HTTP 200; sin instancia adicional |

Las ejecuciones focalizadas repiten casos de los grupos anteriores: no se suman como pruebas únicas nuevas. Las 15 omisiones de la suite SQLite incluyen pruebas que exigen PostgreSQL/concurrencia y otras condiciones del entorno. Los tres errores PostgreSQL originales están corregidos y sus recorridos están incluidos entre los aprobados; no se mantienen como deuda pendiente.

La auditoría local final strict devuelve éxito: 21 valores antiguos inequívocos, 0 ambiguos, 0 inválidos, 0 divergentes y 40 pares vacíos. No acredita el estado de producción.

Evidencia resumida y huellas de los archivos temporales: [time-stabilization-02-verification.json](time-stabilization-02-verification.json). La suite integrada incluye el trabajo concurrente de interfaz, identidad/PDF, móvil/PWA y expedientes presente en esta copia. No equivale a probar cualquier cambio futuro ni los dispositivos físicos.

Reproducción: `php artisan test --compact` en la copia aislada con base de pruebas SQLite; `npm run test:frontend`, `npm run types:check`, `npm run lint:check`, `npm run build`, `npm run i18n:check`. Para PostgreSQL usar **solo** APP_ENV=testing, conexión local y una base desechable verificada, con credenciales por entorno seguro y sin DB_URL de producción. Ejecutar los archivos de los tres grupos de la tabla con `php artisan test --compact tests/Feature/<nombre>Test.php ...`; separar WorkOrderTest para no confundir secuencias PostgreSQL no transaccionales con fallos del dominio. TemporalConcurrencyTest requiere explícitamente una base local terminada en `_temporal_concurrency_test` y crea el esquema desde cero: nunca apuntarla a una base de aplicación.

**Recomendación: APTO PARA PREPARAR DESPLIEGUE.** El alcance técnico local está verificado. **Publicación todavía bloqueada** por autorización, corte revisado, staging de volumen, inventario/parada de escritores y auditoría strict de producción. P0 transversal no se marca cerrado. No se ha realizado commit, push, merge, despliegue, auditoría ni migración en producción.
