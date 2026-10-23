# Operaciones — revisión de implantación local

Fecha: 7 octubre 2026. Referencia: especificación del usuario y mock de las cuatro
vistas. Los nombres, cantidades, referencias y equipos del mock no son datos de
negocio. Las carencias se registran en el roadmap único, UI-OPS-01 a UI-OPS-11.

## 1. Rama utilizada

`ai/sidebar-navigation-redesign`. Trabajo sobre la rama existente, con los
cambios anteriores preservados. No se ha integrado en develop ni publicado.

## 2. Archivos modificados

Nuevos: OperationsWorkspaceController, Services/Operations/OperationsWorkspace,
components/operations/workspace.tsx, operations/dashboard.tsx, planning.tsx,
agenda.tsx, work-orders/list.tsx, show.tsx y create.tsx; OperationsRedesignTest y
este informe/snapshot Git. Cambios: WorkspaceNavigation, routes/web.php,
WorkOrderController, WorkOrderRescheduler, work-orders/index.tsx (herramientas
existentes y protección financiera), project-detail.tsx (enlace a planificación
real y creación con instalación), CSS local y catálogo de traducciones.

Tests anteriores adaptados a las rutas explícitas retenidas: OperationsAdmin,
AdministrativeRecovery, PlanningScenario, CustomerProfile, CriticalMaterialRisk,
Inventory y WorkspaceNavigationAuthorization. No se cambia su dominio;
Inventory distingue ahora la consulta financiera autorizada de la técnica.
CSS normalizado con Prettier, incluidos estilos previos; los cambios funcionales
nuevos se acotan al prefijo ops y sus media queries.
Wayfinder regenera archivos ignorados de rutas/actions durante build; no son
cambios fuente para entregar. Snapshot completo: operations-redesign-git-status.txt.

## 3. Modelo operativo encontrado

Installation contiene la unidad física y project_reference (proyecto operativo
actual), customer vía person_id, dirección del emplazamiento y fechas.
Installation hasMany WorkOrder: no se añade una segunda OT ni se duplica cliente.
WorkOrder tiene responsable assigned_user_id, kind, status, plantilla/snapshot,
intervalo previsto, duración estimada, instrucciones, completion_notes,
completed_at y firma existente. WorkOrderChecklistItem contiene posición,
obligatoriedad y completed_at/completed_by. User + FieldServiceProfile son los
recursos reales; no se encontró Team/Crew/Vehicle asignable a OT.

CrmActivity es una fuente temporal real, con enlace opcional a OT/Opportunity/
Installation. No se crea PlanningEvent ni CalendarEvent. StockReservation,
InstallationMaterialRequirement, StockMovement/Lines, PurchaseDocument,
WorkOrderAttachment, TimeEntry, reschedules y AuditLog ya existen. PortfolioProject
es público y no se utiliza. No se inventa un tenant/company_id ausente.

## 4. Dashboard

/operations: cinco KPIs SQL con el scope de OT autorizado. Hoy: OT activa cuyo
intervalo previsto intersecta el día. Pendiente: OT activa sin responsable,
inicio o fin. En ejecución: status=in_progress. Retrasada: OT activa con fin
vencido, o planned con inicio pasado. Finalizada esta semana: completed y
completed_at entre inicio/fin de semana; updated_at no interviene.

La fecha de creación no sustituye a ejecución. Las tarjetas llevan al filtro
correspondiente. Atención incluye bloqueos, ausencia de asignación/planificación,
vencimientos e incidencias calculadas sobre la semana visible. Se reutiliza
CriticalMaterialRisk para próximos siete días, transmitiendo solo producto,
cantidad, severidad e ID de OT. Próximas instalaciones son OT de kind=installation
con inicio futuro; ejecución son OT in_progress. Ambas llevan a fuentes reales.

## 5. Carga de trabajo

Se muestra el número de trabajos con intervalo en la semana, sin porcentaje
inventado. FieldServiceProfile dispone de capacidad diaria y TechnicianSchedule
ya la valida al asignar; no se extrapola a disponibilidad semanal efectiva.
Ausencias/festivos y denominador comparable quedan en UI-OPS-03. La proyección
está limitada a 500 órdenes y avisa si se alcanza el límite.

## 6. Planificación

/operations/planning: matriz de técnicos × días, semana/rango en URL, anterior,
siguiente y Hoy, filtro de técnico, backlog paginado y formulario de asignación.
Las celdas muestran referencia, emplazamiento, tipo e intervalo real. La matriz
es legible en escritorio/tablet; móvil utiliza una lista, sin Gantt comprimido.

El formulario usa PATCH schedule existente y WorkOrderRescheduler: transacción,
lock de OT/técnicos, motivo, disponibilidad, habilidad, zona, solapamiento y
capacidad. Además valida expected_updated_at bajo el lock antes de modificar.
Sin DnD ni actualización optimista visual: un error conserva el formulario y
sus datos; no hay movimiento ficticio que revertir. Reschedule conserva historial
y actualiza CrmActivity enlazada mediante el servicio existente.

## 7. Equipos

No existen cuadrillas operativas relacionadas con OT. La UI muestra técnicos;
no crea «Equipo 1» ni grupos a partir de roles/departamentos. UI-OPS-02 documenta
el modelo y criterios para introducir equipos/vehículos reales.

## 8. Técnicos

Responsable individual existente. El técnico ve exclusivamente sus OT en panel,
planificación, listado, detalle y agenda de OT. El usuario con manage-installations
puede planificar/crear según Gate; manage-operations permite consultar y operar
sus órdenes. No se crea portal separado ni duplica Personal.

## 9. Órdenes de trabajo

/work-orders es lista de ancho completo, filtros GET (búsqueda por referencia,
título, emplazamiento/proyecto, estado, tipo, responsable, fechas, Mis trabajos),
quick filters y paginación backend 10/15/30/50. Más filtros se pliega en móvil;
filas pasan a tarjetas. /work-orders/{id} es ficha independiente, con regreso.
Los enlaces previos ?work_order=id redirigen a la ficha.

/work-orders/create utiliza instalaciones reales (máximo 100 con búsqueda),
preselección installation=id y plantillas activas; POST usa WorkOrderFactory,
sin duplicar cliente/dirección. Numeración conservada: TMP UUID dentro de
transacción y WO-año-ID persistido; no count()+1. Tipos existentes: survey,
installation, commissioning, maintenance y repair. Estados existentes: planned,
in_progress, blocked, completed y cancelled; snapshots de transición conservados.

## 10. Workflow OT

WorkOrderStateManager / WorkOrderWorkflow siguen validando transiciones,
preparación física de materiales, cualificación, clearance financiero y cierre.
No se sustituye el flujo por la lista conceptual del mock. La nueva UI envía
versión esperada; conflictos y requisitos de finalización se muestran inline en
peticiones Inertia. La API móvil mantiene su contrato de conflicto existente.
completed_at lo fija el servicio; actor/auditoría y ciclo de servicio conservados.
El inicio real con horas es TimeEntry mediante cronómetro existente; no se añade
un started_at supuesto por llegar la hora planificada.

## 11. Progreso

completed_tasks_count / checklist_items_count × 100, redondeado, calculado con
subconsultas COUNT. Sin checklist se muestra — y «Sin tareas registradas».
No hay pesos en este modelo; ninguna estimación porcentual manual.

## 12. Tareas

Pestaña de checklist paginada, obligatoriedad y acción completar/reabrir real
PATCH checklist. La creación de OT aplica las plantillas existentes del servicio;
no se añaden tareas del mock a todas las órdenes. No hay CRUD independiente de
checklist en la web existente: UI-OPS-05 concreta endpoints y aceptación pendientes.

## 13. Ubicación

Dirección y ciudad de Installation, nunca dirección fiscal del cliente. Sin
dirección se indica que no está registrada. El repositorio tiene MapLibre/map
component, pero en esta fase se conserva la ubicación textual de la fuente, sin
nuevo mapa/geocoding ni dependencia ni ruta falsa de cartografía.

## 14. Materiales

Pestaña paginada de StockReservation con Product, Warehouse, cantidad y estado
reales; no se inventan preparado/entregado. Reservar, pedir, liberar y consumir
siguen accesibles mediante gestión existente y sus controladores. El consumo
usa WorkOrderMaterialConsumer/stock movements y no modifica stock en frontend.
Los riesgos del panel usan el servicio de suministro existente, sin mostrar
exposición económica al técnico ni crear inventario paralelo.

## 15. Documentos

WorkOrderAttachment kind=document, metadatos paginados y descarga privada mediante
endpoint existente y autorización de OT. Subida/revisión/publicación conservadas
en herramientas existentes. Sin rutas de almacenamiento expuestas en nuevas vistas.
UI-OPS-06 planifica trasladar esos formularios a las nuevas pestañas.

## 16. Fotos

WorkOrderAttachment kind=photo, paginación y descarga privada; infraestructura
existente para subir evidencias tipadas. No se genera ninguna foto ni procesamiento
nuevo. Actualmente la pestaña lista archivos; gestión existente permite la subida.

## 17. Partes

TimeEntry real: técnico, inicio, fin, minutos y observaciones, paginado y sin
hourly_cost/mileage_rate/cost en la nueva proyección. Firma existente se conserva
en gestión y en la regla de cierre. No existe generador corporativo de PDF de OT:
no se habilita botón falso Generar/Imprimir; UI-OPS-07 registra esa implantación.

## 18. Actividad

AuditLog de la OT, fecha/evento paginados, sin snapshots financieros/IP privados.
No se crean eventos ficticios «llamada», «material», etc. Reschedules conservan
motivo/autor/fechas del servicio. La consulta es lazy, no carga historial al abrir
Resumen. Las granularidades futuras que requieran eventos nuevos no se simulan.

## 19. Agenda

/operations/agenda: proyección acotada de intervalos de OT y CrmActivity sin OT
asociada. Se excluye work_order_id de actividades para no duplicar un mismo
trabajo. Actividades mantienen el scope previo: global solo admin/commercial,
resto responsable propio. Eventos enlazan a la OT, oportunidad o actividad real.

Día/Semana/Mes/Lista seleccionan periodos de la lista; no se presenta una cuadrícula
mensual que no existe. Navegación usa incrementos de día/semana/mes coherentes y
zona config(app.timezone), actualmente Europe/Madrid. Entrada ISO con offset se
normaliza en servidor a la estrategia temporal local existente. Fechas de día de
las celdas se calculan en backend para no agrupar por el día UTC serializado.
Sin entidad CalendarEvent, segundo scheduler ni movimiento DnD independiente.
Fuentes adicionales/cuadrícula quedan documentadas en UI-OPS-08.

## 20. Proyectos / Instalaciones

Quote aceptado → Installation con project_reference → hasMany WorkOrder sigue
intacto. Enlaces de ficha a proyecto/instalación/cliente y creación desde proyecto
preseleccionan la instalación. Se preserva el sincronizador existente: una OT
completada no termina una instalación si quedan OT de ejecución abiertas; si la
última OT termina, su regla existente sí puede cerrarla. No se introduce cierre
incondicional nuevo ni una segunda entidad Project.

## 21. Permisos

Gates existentes manage-operations/manage-installations/view-finance; scope de
asignación conservado en cada query y acceso al detalle. No hay tenant artificial.
Técnicos no reciben presupuesto de Installation, costes/horas/rates ni importes
de compras en las nuevas vistas. También se redactan costes del material plan,
readiness, time entries, compras y defaults en el acceso a la gestión histórica.
Las tarifas para registrar horas sin view-finance se resuelven en servidor, sin
aceptar tarifas financieras enviadas por esos usuarios. Tests comprueban esto.

## 22. Performance

KPIs COUNT SQL; lista paginate + eager selectivo sin recuperar todas las OT;
matriz por solapamiento con rango (máximo 500), backlog paginate(10). Agenda
máximo 500 OT + 200 actividades, con aviso visible de límite. Detalle consulta
una sola OT, contadores de tareas y únicamente la pestaña solicitada; documentos,
fotos, materiales, partes y auditoría paginados. No se cargan todos los adjuntos
ni stock para Resumen. Riesgo de material reutiliza agregaciones batch del
servicio existente para siete días. Selectores de creación acotados a 100.

Herramientas históricas retenidas siguen siendo más pesadas; su migración completa
está en UI-OPS-06/11 y no forman el payload del listado/ficha nuevos. No se añade
biblioteca de calendario, scheduler, gráfica, mapa o dependencia de npm.

## 23. Responsive

Escritorio: seis tarjetas, matriz semanal/atención y tres bloques inferiores;
planificación matriz/backlog; tabla OT; ficha con columnas adaptables. Tablet:
KPIs 3 columnas y bloques 1/2 columnas. Móvil: KPI 2 columnas, planificación/lista,
OT en tarjetas, tabs deslizables, filtros plegables y formularios apilados.

Comprobación local real en 1536×1024, 834×1194 y 390×844; no se insertaron OT para
rellenar capturas. La sesión no contiene OT: detalle con datos se verifica mediante
tests de Inertia, no se afirma una revisión visual poblada que no se hizo.

## 24. Tests

Validación general: 154 tests PHP, 3059 aserciones, todos correctos (16 ficheros
Feature). Comprobación final adicional de la creación/plantillas y errores: 11 tests de
OperationsRedesign, 198 aserciones, correctos tras el último ajuste de UI.
Validación frontend: 63 aserciones en los cuatro scripts de navegación
workspace/project/customer/quote, todas correctas. ESLint completo, TypeScript,
catálogo i18n (4441 textos / 4976 entradas bilingües), Pint y Prettier dirigidos,
git diff --check y npm run build correctos. Build mantiene el aviso conocido de
chunks mayores de 500 kB, sin error de compilación.

Comandos: php artisan test --compact sobre OperationsRedesign, WorkOrder,
OperationsAdmin, Inventory, WorkspaceNavigationAuthorization, ProjectsRedesign,
CustomerProfile, CriticalMaterialRisk, AdministrativeRecovery, PlanningScenario,
QuotesRedesign, CustomersRedesign, CommercialDashboard, CrmRedesign,
FinancialDashboard y Dashboard; npm run lint:check/types:check/i18n:check/build;
node tests/frontend/{workspace,project,customer,quote}-navigation.mjs;
vendor/bin/pint --test y npx prettier --check sobre archivos afectados.

Fixtures solo en SQLite de tests;
la vista localhost utiliza su base existente. Cobertura nueva: KPIs por fechas,
scopes, filtros/paginación, lazy tabs/checklist, conflicto/reprogramación/versión,
jerarquía varias OT, auditoría/cierre, creación contextual, protección financiera,
Agenda deduplicada, rango día/mes y zona horaria.

## 25. Git status

Snapshot completo en operations-redesign-git-status.txt. Cambios anteriores ya
existían al iniciar esta fase y se conservan; no se atribuye todo el diff a Operaciones.

## 26. Resumen git diff

Diff acumulado de la rama (incluye fases previas): `64 files changed, 9616 insertions(+), 7168 deletions(-)`.
Git status: 120 entradas, con nuevos archivos sin seguimiento;
ningún archivo staged por esta fase. Se verifican huellas de 11 componentes/páginas
canónicos protegidos respecto al inicio: sin cambios. Ningún archivo previo eliminado.

Comparación de huellas al inicio de esta fase para identificar archivos propios,
además de git diff --check y diff acumulado. Solo se modifican área operativa,
integración puntual de proyecto, rutas/nav, estilos/traducciones y tests afectados.
No se alteran pantallas canónicas de Inicio, Comercial, CRM, Clientes, Presupuestos
y Libro Diario ni los componentes del shell/sidebar. Solo cambian los destinos
y la capacidad navegable de Operaciones en la definición backend. Los archivos de pruebas de Clientes solo cambian
la referencia al endpoint histórico de actividades.

## 27. Limitaciones / deuda técnica

UI-OPS-02…11 en roadmap con estado, dependencias y aceptación: equipos/vehículos,
capacidad efectiva porcentual, DnD accesible opcional, CRUD de tareas, traslado de
formularios existentes, PDF corporativo, calendario gráfico/fuentes adicionales,
versión obligatoria para todos los clientes, started_at específico si se decide
y autocomplete a gran escala. No hay botones habilitados para capacidades ausentes.
La adaptación utiliza datos reales y no pretende replicar cifras del mock.

## 28. Confirmación final

Commit: NO realizado
Push: NO realizado
Merge: NO realizado
