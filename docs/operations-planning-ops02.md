# OPS-02 — Planificación y disponibilidad

Estado: publicado y verificado en producción el 30/10/2026. Código `c41df6d649a7e8043bf75307ad7e287b5cc3077b`, deployment `depl-a31aaf63-8a12-4280-9a91-0bcd25d97b7d` succeeded (08:49:20 UTC). Mantenimiento retirado. [Evidencia](operations-planning-publication-2026-10-30.json).

## Diagnóstico y solución

La planificación existente ya tenía órdenes, franjas, perfiles técnicos, capacidades, habilidades, reservas y un historial de cambios. RR. HH. tenía ausencias, relación laboral y un calendario global de festivos, pero no franjas semanales efectivas. Ingeniería comprobaba algunas condiciones por separado; algunas creaciones directas y sustituciones podían saltarse las comprobaciones compartidas.

Se amplía el perfil de servicio existente con `weekly_schedule` nullable: días ISO 1–7 y hasta cuatro franjas locales por día. No se infieren horarios a partir de horas semanales o capacidad. `null` indica desconocido; una semana/día sin franjas indica no laborable. Se configura en Operaciones → Servicio de campo, junto a habilidades y capacidad. Horas en Europe/Madrid, instantes y comparaciones mediante BusinessTime/columnas UTC existentes. `24:00` es el final del día; los límites ambiguos o inexistentes no confirman disponibilidad.

Una decisión compartida comprueba habilidades, cobertura, disponibilidad del perfil, vigencia laboral existente, ausencias pendientes/aprobadas, órdenes activas, actividades CRM independientes, capacidad diaria y jornada/festivos. Las ausencias pendientes bloquean confirmación hasta resolverse, también en Ingeniería. La interfaz distingue disponible, no disponible y desconocido. Un horario incompleto impide confirmar; los motivos privados de RR. HH. no se exponen. Se pueden conservar órdenes sin fecha como pendientes, sin presentarlas como ejecutables.

Planificación conserva su pantalla y añade día/semana/mes, filtros reales de técnico/proyecto/tipo/estado, duración, prioridad vinculada y visitas CRM. Agenda comparte la selección de actividades; una actividad vinculada a orden no aparece duplicada. La consulta de técnicos muestra motivos, capacidad y carga; guardar exige una consulta vigente para la franja y vuelve a validar en servidor. Los locks ordenados de técnicos serializan reservas de órdenes concurrentes.

La reprogramación admite órdenes planificadas o bloqueadas. Rechaza ejecución/finalización/cancelación y ejecución de instalaciones finalizadas o expedientes cerrados. Conserva antes/después, motivo, actor y fecha en el historial existente; una confirmación repetida sin cambios no añade otra revisión. El historial se consulta en Orden → Actividad (últimas 50 revisiones, las anteriores permanecen conservadas). La revisión del ciclo de servicio y sincronización de instalación quedan en la misma transacción. Las sustituciones por ausencia usan el mismo servicio y vuelven a comprobar cobertura antes de aprobar. La conversión CRM reutiliza su actividad bajo lock y no se considera un conflicto consigo misma.

## Verificaciones

- Suite general SQLite: 1.114 aprobadas y 53 omitidas; después, regresión específica final y PostgreSQL 17.6. Los datos antiguos de las pruebas se actualizan con horarios sintéticos expresos; no se cambian registros productivos.
- PostgreSQL 17.6: 41 pruebas/435 aserciones, incluidas tres carreras con procesos independientes (órdenes distintas para el mismo técnico, doble confirmación de una orden y visita CRM simultánea con una orden). Base local marcada desechable; limpieza posterior para no contaminar otros escenarios.
- Casos: jornada partida/no configurada, festivos, ausencias, habilidades, capacidad y actividad CRM, hora inexistente/repetida, filtros/calendarios, historial, permisos, estados cerrados y reintentos.
- Frontend: 88 pruebas existentes, tipos, ESLint dirigido, Pint, catálogo ES/EN y build. Verificación visual con páginas del controlador real sobre una transacción PostgreSQL sintética revertida y recursos recién compilados; toda la red interceptada. Ninguna operación real de producción en QA.
- Migración aditiva propia: `2026_10_29_220000_add_weekly_schedule_to_field_service_profiles`; solo añade JSON nullable. Sin actualización de perfiles existentes ni cambios económicos/documentales.

## Límites concretos

- RR. HH. dispone de un calendario global de festivos; no existe asignación territorial/individual de calendarios. No se inventa esa relación. Los festivos registrados se aplican al calendario existente.
- No hay reservas de vehículos/equipos con relación operativa: no se crean conflictos de recursos ficticios. Se conservan las advertencias de materiales/reservas y desplazamiento existentes.
- No se generan alternativas automáticas de fecha: la consulta ofrece candidatos y razones para cambiar la franja o resolver configuración. Ningún técnico sin evidencia de disponibilidad se recomienda como disponible.
- No se cambia ninguna jornada histórica automáticamente. Los perfiles anteriores necesitan configuración expresa antes de confirmar nuevas franjas.
- Notificaciones y automatismos OPS siguen deshabilitados; no se consumen los nueve jobs pendientes ni se activan workers, scheduler, integraciones o push-to-deploy.

## Publicación efectiva

Migración explícita254→255, sin hook genérico: las columnas originales de seis tablas operativas conservan sus hashes y las trece fuentes PHP modificadas coinciden con el candidato. Nueve GET privados200 y contratos nuevos presentes; /up, /login y bundle público200; acceso anónimo a planificación302. RLS del perfil conservada. Dos perfiles existentes mantienen jornada desconocida; no se rellenan datos.

Nueve jobs con payload, cola, intentos0 y ausencia de reserva idénticos al corte anterior;3/4/5 siguen en cuarentena. Workers, scheduler, push-to-deploy, hook e integraciones OPS desactivados; deployCommand vacío y patches-repatch conservado. Backups, retención, originales, facturación y contabilidad no modificados. Sin archivos de logs de aplicación accesibles en el contenedor: se han comprobado las rutas afectadas y el estado/log de despliegue, sin afirmar ausencia global de excepciones históricas.
