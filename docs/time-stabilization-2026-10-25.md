# Estabilización temporal — primer incremento local, 25/10/2026

## Diagnóstico y alcance

Laravel mantiene `APP_TIMEZONE=Europe/Madrid`. El esquema mezcla fechas civiles (`date`), instantes guardados como `timestamp/datetime` sin zona e instantes `timestamp with time zone`. Los casts `datetime` no recuperan el desplazamiento perdido por una columna sin zona. En el retorno del horario de verano, `2026-10-25 02:30:00` representa dos instantes separados por una hora. Se reprodujeron desplazamientos de 60 minutos y seis fallos de caducidad, publicación y SLA también en el código de referencia.

React mezclaba conversiones mediante la zona del dispositivo y ordenación lexicográfica de cadenas con desplazamientos distintos. Un formulario `datetime-local` tampoco identifica cuál de las dos horas repetidas se desea. En primavera, PHP podía normalizar silenciosamente una hora inexistente.

El reloj simulado de Carbon presenta además una ambigüedad al construir `now()` en la primera hora repetida. Las nuevas pruebas fijan un instante UTC y contrastan el instante esperado, evitando que el propio reloj de pruebas oculte el problema.

No se cambia globalmente la zona de Laravel ni la sesión PostgreSQL, ni se transforma el histórico. No se cambian importes, reglas fiscales, fechas contractuales de documentos ni sus huellas. Este incremento no convierte todos los timestamps del ERP.

## Estrategia aplicada

- **Instante:** entrada ISO con desplazamiento explícito; almacenamiento canónico UTC; comparación por instante; presentación en la zona empresarial. Una entrada civil sin desplazamiento solo se acepta cuando identifica un instante único.
- **Fecha civil:** permanece `date`/`Y-m-d`, sin convertir a UTC. Emisión, vencimientos por día y horizontes de planificación conservan su contrato. La política existente de cada módulo sigue determinando cuándo vence un día completo.
- **Compatibilidad:** dos migraciones aditivas crean 35 columnas nullable `*_utc` (`timestampTz`, precisión 6) en diez modelos. Se conserva la columna original. Nuevas escrituras Eloquent individuales y masivas mantienen ambas representaciones juntas; la API conserva los nombres originales y oculta los auxiliares.
- **Resolución:** se mantiene la resolución de segundos del dominio existente. La precisión del tipo no implica cambiar las reglas de redondeo de horas o documentos.
- **Histórico sin desplazamiento:** se lee sin reescritura. Caducidades ambiguas usan el primer instante; publicación y avisos usan el último cuando procede, para evitar ampliar accesos o publicar/avisar antes de tiempo. Intervalos antiguos de disponibilidad se consideran conservadoramente y se verifican antes de descartar un conflicto. Un cronómetro antiguo ambiguo requiere revisión antes de calcular horas.

Estas decisiones conservadoras afectan únicamente a valores antiguos que no permiten reconstruir el instante real; no se presentan como recuperación exacta del histórico.

### Campos cubiertos

| Modelo | Campos |
| --- | --- |
| DocumentDelivery | expires_at, sent_at, first_viewed_at, last_downloaded_at, responded_at, revoked_at |
| CustomerPortalAccess | expires_at, sent_at, first_accessed_at, last_accessed_at, revoked_at |
| WorkOrderAttachment | published_at, reviewed_at |
| PortfolioProject | published_at |
| PlanningScenario | reference_valid_until, approved_at, closed_at |
| Opportunity | created_at, first_response_due_at, first_responded_at, first_response_sla_alerted_at, first_response_sla_escalated_at |
| WorkOrder | scheduled_start, scheduled_end, signed_at, completed_at |
| TimeEntry | started_at, ended_at |
| CrmActivity | starts_at, due_at, completed_at |
| WorkOrderReschedule | old_scheduled_start, old_scheduled_end, new_scheduled_start, new_scheduled_end |

Los campos `reference_valid_until` y `closed_at` que ya eran timestamptz conservan también el desplazamiento en la representación original. Las reprogramaciones conservan los instantes anterior y nuevo; el observador detecta un cambio de ocurrencia aunque la hora civil sea idéntica.

## Cambios funcionales verificados

`BusinessTime`, `PreciseInstant`, `HasPreciseInstants` e `InstantBuilder` centralizan resolución, lectura compatible, escritura y comparación. Las consultas seleccionadas de publicación, SLA y disponibilidad usan los auxiliares UTC con alternativa para datos antiguos. Las proyecciones Eloquent ordinarias incluyen automáticamente el auxiliar cuando seleccionan su campo asociado.

Caducidad exacta significa `expires_at <= ahora`: el acceso devuelve 410 al llegar al límite. Una caducidad histórica inválida no concede acceso. Las referencias de escenarios requieren un instante estrictamente posterior al reloj actual. Se mantienen las demás condiciones de estado y permisos.

La creación y edición de planificación, visitas, actividades comerciales móviles y partes rechazan horas inexistentes, fechas inválidas y horas repetidas sin desplazamiento mediante validación. Un ISO explícito permite distinguir `+02:00` de `+01:00`. Editar sin cambiar una hora ya precisa conserva su desplazamiento original.

Las pantallas operativas afectadas presentan Madrid explícitamente; la agenda ordena por instante. No se rediseñan formularios ni se añade un selector de ocurrencia. No se modifica la facturación ni se generan documentos nuevos.

`php artisan time:audit-instants --json` permite inventariar valores precisos, antiguos inequívocos, ambiguos e inválidos, sin identificadores personales ni modificación de datos. Ejecutado sobre la base local de demostración: **21 valores antiguos inequívocos, cero ambiguos y cero inválidos**. No se ha auditado producción.

## Verificación

Las pruebas se ejecutan con datos sintéticos y bases aisladas. Las dos migraciones están aplicadas únicamente en la base local. La instancia única sigue siendo `http://localhost:8000`.

La nueva suite `tests/Feature/TemporalInstantsTest.php` cubre ambas ocurrencias del 25/10/2026, salto de primavera, entradas inválidas, caducidad exacta, avisos sin duplicado, publicación futura, ida y vuelta con desplazamiento, proyecciones y actualizaciones masivas, conflictos reales frente a horas civiles coincidentes, duración efectiva, historial de reprogramación, auditoría sin reescritura y reversibilidad aditiva en base desechable. En PostgreSQL verifica el tipo real y una sesión en Pacific/Honolulu.

Resultados finales: suite temporal PostgreSQL **13/13 y 55 aserciones**; regresión completa aislada **952 aprobadas, 9 omitidas y 14.453 aserciones**; PostgreSQL de módulos relacionados **46 aprobadas y 3 fallos previos** en dos ejecuciones separadas. Los detalles se registran en el roadmap. La suite completa se contrasta en una copia aislada de `origin/main` con los archivos de esta intervención superpuestos: la copia compartida contiene trabajo concurrente de interfaz y marca que debe conservarse. Esta comprobación no certifica la combinación de todos esos cambios pendientes.

Los controles de tipos, lint del alcance, formato PHP, pruebas frontend y compilación se han ejecutado. La comprobación global de traducciones sigue señalando 79 cadenas pendientes del trabajo previo/concurrente; no se declara aprobada.

## Riesgos y trabajo pendiente

1. **Cobertura incremental:** quedan timestamps de otros modelos, auditorías genéricas, posventa, suscripciones y consultas analíticas sin migrar. Las fechas civiles no deben convertirse por extensión automática.
2. **Histórico ambiguo:** no puede deducirse el desplazamiento ausente. Antes de publicar se necesita auditoría autorizada de producción y un procedimiento de revisión con evidencia, actor y motivo. No se propone backfill automático de horas repetidas.
3. **Escritores externos y convivencia de versiones:** SQL directo, integraciones o una versión anterior pueden modificar solo la columna original y dejar un auxiliar obsoleto. Revisar los escritores y coordinar el despliegue antes de publicar; no asumir seguridad con versiones mixtas. Las escrituras masivas Eloquent del alcance están cubiertas, no todas las herramientas externas.
4. **Consultas avanzadas:** proyecciones agrupadas, distinct y unions no reciben auxiliares automáticamente. Revisar explícitamente cada consumidor antes de ampliarlo. Consultas SQL históricas conservan semántica civil mientras no se migren.
5. **Interfaz de desambiguación:** el servidor rechaza una hora repetida sin desplazamiento. Queda pendiente una selección explícita de primera/segunda ocurrencia si el negocio requiere programar manualmente esa hora. No sustituirla por una elección silenciosa.
6. **Datos inválidos:** la auditoría los identifica y las puertas de acceso protegidas fallan de forma conservadora. Revisar las listas y consultas antiguas antes de certificar saneamiento general.
7. **PostgreSQL preexistente:** tres pruebas de versiones documentales/calibraciones fallan con `FOR UPDATE is not allowed with aggregate functions`; reproducidas también en la referencia sin este incremento. Requieren corrección independiente y no se ocultan como aprobadas.
8. **Publicación y recuperación:** antes de publicar, revisar un diff separado del trabajo concurrente, migraciones, escritores y auditoría. Revertir código dejando columnas es menos destructivo que eliminar UTC ya registrado. El down se prueba solo en bases desechables; no ejecutar rollback de columnas con evidencia nueva sin preservarla.

Estado: **primer incremento implementado y verificado localmente; estabilización transversal todavía abierta. Sin commit, push ni despliegue de esta intervención.**

Referencias de tipos y serialización: [PostgreSQL — Date/Time Types](https://www.postgresql.org/docs/current/datatype-datetime.html), [Laravel — Date Casting](https://laravel.com/framework/docs/13.x/eloquent-mutators#date-casting).
