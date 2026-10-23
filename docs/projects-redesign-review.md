# Proyectos — entrega local del rediseño funcional

Fecha: 7 de octubre de 2026. Se aplica el mock del área Proyectos sobre los
componentes y servicios existentes. Las cantidades visibles proceden del backend.
Este informe cubre los 28 puntos solicitados.

## 1. Rama utilizada

`ai/sidebar-navigation-redesign`, en el worktree `ab03/arevantes.com`. Se conservan
los cambios acumulados de las fases anteriores. Esta fase no se ha publicado en
`develop` ni en producción.

## 2. Archivos modificados

Cambios de esta fase respecto de los hashes registrados al iniciarla:

- Backend: `app/Http/Controllers/ProjectAreaController.php` (nuevo),
  `app/Services/Projects/ProjectWorkspace.php` (nuevo),
  `app/Http/Controllers/InstallationController.php`,
  `app/Http/Controllers/AfterSalesController.php`,
  `app/Support/WorkspaceNavigation.php`, `routes/web.php`.
- Componentes nuevos: `resources/js/components/projects/project-list.tsx`,
  `project-detail.tsx`, `project-charts.tsx` y `types.ts`.
- Páginas nuevas: `resources/js/pages/execution/dashboard.tsx`, `index.tsx`,
  `show.tsx`, `create.tsx`; `resources/js/pages/after-sales/overview.tsx`;
  `resources/js/pages/installations/legacy-show.tsx`, copia de la gestión original.
- Adaptadores: `resources/js/pages/installations/index.tsx` y `show.tsx`.
- Estilos/localización: reglas `.projects-*` añadidas a `resources/css/app.css`;
  entradas bilingües añadidas a `resources/js/i18n/catalog.json`.
- Pruebas: `tests/Feature/ProjectsRedesignTest.php` y
  `tests/frontend/project-navigation.mjs` nuevos; ajuste del número de padres
  navegables en `WorkspaceNavigationAuthorizationTest.php`; orden de dos imports
  en el test previo `tests/frontend/quote-navigation.mjs` para pasar ESLint global,
  sin cambios en su comportamiento.
- Documentación: `docs/roadmap.md`, este informe y
  `docs/projects-redesign-git-status.txt`.

Wayfinder regenera sus archivos ignorados de actions/routes durante el build.
No se añaden dependencias ni migraciones. Los archivos del AppShell, Sidebar,
Inicio, Comercial, CRM, Clientes y Presupuestos conservan sus hashes iniciales.
También se conservan las páginas públicas `resources/js/pages/projects/*`.

## 3. Modelo de proyecto encontrado

La entidad operativa es **Installation**, con `project_reference`. No existe un
Project operativo autónomo. **PortfolioProject** pertenece al escaparate público
 y no participa en la ejecución del ERP. El proyecto de esta fase es una
proyección de la unidad operativa existente: mismo ID, relaciones y datos, con
una navegación orientada a ejecución. No se crean copias ni se inventa una
relación de proyecto con varias instalaciones.

Campos relevantes: cliente `person_id`, referencia, emplazamiento, dirección,
`service_type`, `status`, comerciales/técnicos, `system_power_kw`, visita,
inicio programado, finalización, descripción y notas. Relaciones reales:
clientes/copropietarios, presupuestos, oportunidades, órdenes, activos, compras,
materiales, expedientes técnicos, planes y tickets.

La conversión existente genera `PRJ-año-ID` y conserva códigos históricos ya
registrados. No se reemplazan referencias importadas ni se introduce una nueva
secuencia. La numeración manual uniforme queda en el roadmap.

## 4. Dashboard y fuentes de los seis KPI

Ámbito común: instalaciones con referencia de proyecto no vacía, creadas en el
periodo seleccionado (`created_at`). Últimos 12 meses desde inicio del mes menos
11 meses hasta hoy; también mes, trimestre y año. Se muestra **estado actual de
esa cohorte**, no una evolución histórica de estados. Los enlaces de KPI y
leyenda conservan `created_from` y `created_to` en el listado.

| KPI | Fuente y cálculo |
|---|---|
| Proyectos totales | COUNT de la cohorte de Installation con project_reference |
| En preparación | lead, survey_booked, quoted, approved y scheduled |
| En ejecución | in_progress |
| Finalizados | completed sin plan activo ni ticket abierto accesible |
| En postventa | completed con plan activo o ticket distinto de resolved/closed, dentro del scope operativo del usuario |
| Valor total | Último Quote accepted por ID de cada instalación: amount; si no existe, estimated_budget. Solo view-finance |

Los registros sin ambas fuentes financieras se indican como «sin valor
registrado». El valor aceptado incluye IVA; la base histórica de estimated_budget
requiere normalización. La tarjeta identifica ambas fuentes y no se presenta
como ingreso facturado ni se calculan variaciones ficticias.

## 5. Estado de proyectos

Mapping excluyente de seis categorías: preparación, ejecución, finalizados,
postventa, cancelados y otros estados. `cancelled` queda separado; estados
históricos no reconocidos se muestran en Otros estados. La suma incluye todos
los proyectos del ámbito. Postventa se calcula solamente con operaciones
accesibles; si falta ese permiso, se oculta su KPI/filtro y no se consulta SAT.
Los porcentajes son participación en la cohorte, sin comparación inventada.

## 6. Potencia

Fuente: `Installation.system_power_kw`. Unidad: **kW**; incluye solar y el alias
histórico solar_pv. Total: SUM de la potencia de FV actualmente in_progress dentro
de la cohorte. Serie: misma selección agrupada por mes de scheduled_date, dentro
del intervalo. Potencia sin fecha contribuye al total y no a una barra. Se explica
esta diferencia al usuario. Servicios no FV muestran «—» y no contribuyen al
SUM, aunque tengan otro campo técnico numérico. No se usan kWh de batería,
potencia de cargador ni textos del nombre para fabricar potencia FV.

## 7. Tipo de instalación

Clasificación existente de línea de servicio: solar, ev_charger, hvac,
electrical y security. Se normaliza solar_pv a solar; valores desconocidos
conservan su categoría real y los nulos su ausencia. No se inventan categorías
residencial/industrial/comunidad basadas en cliente o nombre del emplazamiento.
Esa taxonomía distinta queda planificada en el roadmap.

## 8. Próximas fechas

Visitas e inicios programados de Installation; scheduled_start de WorkOrder
abiertas/no canceladas; next_due_on de MaintenancePlan activas sobre
instalaciones finalizadas. Siempre dentro de la cohorte y scope accesible.
Consultas independientes acotadas a seis por fuente/fecha, mezcla cronológica y
seis resultados finales. Los enlaces abren la ficha real, la orden o el
mantenimiento filtrado por instalación. No se generan fechas estimadas de cierre,
legalización ni revisiones anuales.

## 9. Listado de proyectos

`/execution/projects` ocupa todo el ancho del AppShell. Búsqueda por referencia,
emplazamiento, dirección y nombre/empresa del cliente. Filtros de estado real,
fase, línea de servicio, cliente, técnico/responsable, inicio desde/hasta y
cohorte de creación cuando se entra desde un KPI. Orden por referencia, nombre,
inicio, finalización, creación o estado, ascendente/descendente. Tamaños
10/15/30/50, paginación backend y parámetros persistidos en URL. Filas con enlaces
y navegación por clic/Enter; tarjetas en móvil. No se descarga todo el portfolio.

## 10. Detalle del proyecto

`/execution/projects/{installation}`; 404 si no hay referencia de proyecto.
Ficha independiente a todo el ancho, sin listado simultáneo. Tabs en URL:
Resumen, Presupuesto, Instalación, Planificación, Documentos, Equipos, Costes y
Actividad; se muestran según permisos. Cada tab pide solo sus registros
paginados y conserva filtros del listado. Su paginación usa `section_page`,
separada de `page` del listado. Regreso, refresh y atrás/adelante conservan contexto.
Hitos registrados: creación, aceptación, material solicitado, inicio programado
y finalización real; inicio programado usa icono de calendario, no confirmación
de trabajo completado. No se afirman hitos sin evidencia.

## 11. Presupuesto → Proyecto

Nuevo proyecto lleva a una selección paginada de Quote accepted sin instalación.
Desde su ficha se usa la acción de conversión existente. Se mantienen validación
de aceptación, permisos, bloqueo transaccional y protección contra duplicados.
La operación conserva cliente/oportunidad, servicio/emplazamiento, condiciones y
valor, requisitos de materiales y planificación operativa que ya genera el
backend. No se añaden otro formulario de presupuesto, otra mutación de conversión
ni un Project independiente. Solo se han ejecutado conversiones sobre fixtures
de tests, no sobre los presupuestos reales de la sesión.

## 12. Cliente

Customer/Person original y copropietarios de installation_person, con eager
loading. Enlace a `/people/{id}` solo cuando se permite manage-customers.
No se copian clientes ni se modifica su ficha canónica.

## 13. Oportunidad

Se prioriza la oportunidad explícita del presupuesto aceptado; si falta, se usa
la relación real de oportunidades de la instalación. Se muestra enlace al CRM
solo con permiso manage-customers. No se utilizan stages del CRM como estados
operativos o postventa de instalaciones.

## 14. Instalaciones

`/installations` y `/installations/{id}` conservados; listado físico completo,
filtros backend, relación mediante project_reference y enlace al proyecto.
El mismo Installation representa hoy ambas perspectivas, documentado sin fingir
1:N. El detalle nuevo utiliza la misma composición; `?tab=manage` abre, mediante
carga diferida, la gestión original con todas sus operaciones existentes.
Se mantienen alta y edición originales y se ocultan importes a quien carece de
view-finance también en esa gestión heredada.

## 15. Planificación

Tab paginado de WorkOrder de la instalación: número, título, estado, responsable
y fechas reales. Enlace a la orden contextual y a Operaciones. La planificación,
agendas y cambios de estado se gestionan en sus módulos originales. Para un
técnico se cargan solo órdenes asignadas a él, como en WorkOrderController.

## 16. Equipos y materiales

InstallationAsset original, relación Product y SerialNumber: identificación,
estado, serie, puesta en marcha y garantía registrada; se usa la serie/garantía
relacionada como fallback del activo. La gestión de equipos y requisitos de
material permanece en la instalación existente. Los consumos, compras y
reservas no se duplican; material solicitado procede de purchase_order real
que no sea draft/cancelled y requiere view-purchases.

## 17. Costes

Solo en el tab Costes y con **view-finance**, tanto en backend como frontend.
Se reutiliza ProjectProfitability → ProjectMarginPortfolio: ingreso neto
previsto del presupuesto (subtotal menos descuentos) o estimación; materiales
previstos/consumidos, horas registradas y coste horario, servicios de proveedores,
gastos, compras comprometidas e ingreso neto facturado. Se muestran las
incidencias de calidad del servicio existente, como coste previsto ausente u
horas/consumos sin valoración. Esta base neta es distinta del valor comercial
con IVA de la tarjeta del dashboard. No se crea contabilidad de proyectos nueva.

## 18. Documentos

WorkOrderAttachment privados de órdenes autorizadas, con descarga protegida por
el endpoint real. Expedientes EngineeringDossier relacionados como referencias
y acceso al workspace de ingeniería existente. Sin archivos registrados aparece
estado vacío. Informe PDF autónomo de proyecto y biblioteca consolidada no
existen: quedan en el roadmap, sin botones que apunten a endpoints simulados.

## 19. Actividad

AuditLog real de Installation y de WorkOrder accesibles, paginado, con evento,
usuario y fecha. No se envían IP ni snapshots de atributos antiguos/nuevos.
No se generan eventos al navegar ni se presenta un timeline comercial completo
que el dominio aún no consolida.

## 20. Postventa

`/after-sales?view=installations` muestra solo Installation completed.
MaintenancePlan activas aportan última revisión real, próxima revisión y
seguimiento. ServiceTicket no resolved/closed aporta incidencias abiertas.
Prioridad de badge: incidencia, revisión vencida/para hoy, seguimiento, sin plan.
Los filtros de revisión/seguimiento/incidencias son condiciones reales que pueden
solaparse; no son estados exclusivos de un workflow nuevo. El bloque de
revisiones incluye pendientes y próximas con su fecha original. Gestionar planes
/tickets conserva sus pantallas y aplica `?installation=id`. No se deriva una
revisión anual de completed_date ni se confunde con CRM Post-Installation.

## 21. Permisos y scopes

view-installations para dashboard/listados/fichas; manage-installations y
manage-quotes para continuar a la conversión; manage-quotes para el tab y enlace
del presupuesto; manage-customers para relaciones comerciales;
manage-operations para órdenes/equipos/postventa; view-finance para valor/costes;
view-purchases para el hito de compra. Gates backend son la autoridad.
Técnicos: WorkOrder solo asignada; planes/tickets asignados o sin asignar,
como sus controllers existentes. Se preserva el alcance original de lectura de
Installation. El modelo no tiene company_id: no se simula segregación multiempresa.

## 22. Performance

COUNT/SUM/GROUP BY en base de datos, subconsulta de un presupuesto aceptado por
instalación, relaciones mínimas con eager loading, fechas próximas acotadas,
páginas de 10–50 y tabs de 15. Sin carga de toda la ficha antigua al consultar
el resumen; sin query de rentabilidad hasta abrir Costes. Prueba de listado con
1 frente a 21 registros confirma complejidad de consultas estable por página.
Los selectores del universo relacionado siguen siendo colecciones acotadas por
relación, pero no autocomplete remoto: deuda registrada para gran volumen.

## 23. Responsive

Verificado en navegador local: escritorio 1536 × 1024 (seis KPI y cuatro paneles),
tablet 834 × 1194 (tres KPI y dos paneles por fila, ficha de dos columnas), móvil
390 × 844 (dos KPI, tarjetas, ficha vertical, tabs desplazables y filtros en
Sheet). body.scrollWidth coincide con viewport en los tres tamaños.
El Sidebar y las reglas responsive canónicas se conservan.

Evidencia local: `/tmp/projects-dashboard-desktop.jpg`,
`/tmp/projects-dashboard-tablet.jpg`, `/tmp/projects-dashboard-mobile.jpg`,
`/tmp/projects-list-mobile.jpg`, `/tmp/projects-detail-mobile.jpg` y
`/tmp/projects-detail-tablet.jpg`. Sesión local real, sin llenar los vacíos con
fixtures del mock. Sin errores de consola observados al terminar.

## 24. Tests y validación técnica

- 128 tests PHP y 2317 aserciones: ProjectsRedesign, QuotesRedesign,
  QuoteComposition, QuoteOptionSelection, QuoteWorkflowSecurity, QuotePdf,
  InstallationPeople, AfterSales, WorkspaceNavigationAuthorization,
  CommercialDashboard, CrmRedesign, CustomersRedesign, FinancialDashboard,
  Dashboard y PortfolioProject.
- 63 aserciones frontend: project-navigation (16), quote-navigation (16),
  customer-navigation (15) y workspace-navigation (16).
- `npm run lint:check`: pasa para todo el repositorio.
- `npm run types:check`: pasa.
- `npm run i18n:check`: 4366 strings visibles y 4899 entradas bilingües completas.
- Pint de los nuevos archivos PHP y Prettier del frontend editado: pasan.
- `npm run build`: pasa; conserva warning existente de chunks >500 kB.
- `git diff --check`: pasa.

Tests con SQLite en memoria; navegador sobre servidor local de este worktree,
con DB_CONNECTION=pgsql. Se comprobaron dashboard, periodo, listado, filtros,
ficha, costes/documentos, Postventa, deep links y atrás/adelante. No se enviaron
correos ni se crearon/convirtieron datos de negocio real.

## 25. Git status

Estado detallado en `docs/projects-redesign-git-status.txt`. Árbol con cambios
acumulados y sin staging; no corresponde exclusivamente a esta fase. Los hashes
anteriores permiten separar las modificaciones actuales de las fases canónicas
ya realizadas. Sin cambios de .env, manifests de dependencias ni secrets.

## 26. Resumen Git diff

El diff tracked acumulado antes de este informe muestra 56 archivos, 7155
inserciones y 7110 eliminaciones; no incluye archivos untracked. Estas cifras
incluyen todas las fases anteriores y no se atribuyen solo a Proyectos. La fase
actual añade servicio/controller, páginas/componentes/tests y documentación;
adapta navegación/rutas, las dos entradas de instalaciones y el acceso al
contexto de planes/tickets. El contenido de la gestión original de instalación
se conserva en legacy-show y se carga bajo demanda. El test de navegación espera
ahora dos padres navegables: Comercial y Proyectos.

## 27. Limitaciones / deuda técnica

Roadmap UI-PROJECTS-01…11 con estado, dependencias y aceptación: entidad de
proyecto autónoma/1:N, tipología del emplazamiento, homogeneidad financiera e
histórico, hitos/fin previsto/legalización, documentos e informe real, timeline
consolidado, payload heredado de gestión, segregación corporativa/individual,
selectores/índices a volumen real y aislamiento de fallos por widget, programa
postventa/garantías y numeración manual uniforme. La pantalla usa el manejo de
errores existente de Laravel/Inertia; no se afirma haber añadido aislamiento
independiente de cada consulta de widget. No se amplía el alcance con scheduling,
GPS, portal, firma, IA ni inventario paralelo.

## 28. Confirmación final

Commit: NO realizado
Push: NO realizado
Merge: NO realizado
