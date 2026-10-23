# Inicio definitivo — revisión de entrega, 06/10/2026

## 1. Rama utilizada

`ai/sidebar-navigation-redesign`. Se continúa sobre el trabajo local del sidebar y shell sin mover cambios entre ramas. Develop no se modifica. No se crea PR ni se modifica el historial. Vista local: http://localhost:8001/dashboard.

## 2. Archivos de esta fase

- resources/js/components/dashboard/financial-home.tsx
- resources/js/components/dashboard/home-heading.tsx (nuevo)
- resources/js/pages/dashboard.tsx
- resources/js/types/financial-dashboard.ts
- resources/css/app.css (estilos de Inicio)
- app/Services/Accounting/FinancialDashboardSummary.php
- tests/Feature/FinancialDashboardTest.php
- lang/en.json, lang/es.json, resources/js/i18n/catalog.json
- docs/roadmap.md y este informe

Los demás archivos pendientes en git status corresponden al trabajo previo del sidebar/shell. No se modifican en esta fase.

## 3. Componentes

FinancialHome mantiene la composición financiera existente, reorganizada según la referencia. HomeHeading obtiene usuario/empresa/fecha dinámicos y se comparte con la vista operativa. FinancialEvolution separa Recharts y una tabla accesible desplegable de los doce meses. RecentDocuments separa tabla desktop y lista compacta móvil. Panel reutiliza la envolvente de los bloques. PageShell y PageHeader aprobados se consumen sin modificar el shell ni el sidebar.

## 4. Fuentes de datos

| Bloque | Fuente real y alcance |
| --- | --- |
| Ingresos | FinancialDashboardSummary: JournalLine + JournalEntry posted + LedgerAccount tipo income; crédito menos débito, ejercicio solicitado hasta as_of, sin cierre |
| Gastos | Misma fuente, tipo expense, débito menos crédito |
| Resultado | Convención contable existente: ingresos menos gastos, mismas líneas y alcance |
| Comparación | Mismo corte del año anterior; divisor absoluto, sin porcentaje con base cero ni año futuro |
| IVA a ingresar | Contrato vat_payable null; no conectado a una liquidación validada. VatReport/VatSettlement/Model303Preview sí existen, pero no se reinterpretan ni cargan sus libros completos para rellenar el KPI |
| Evolución | Resumen contable existente por cuenta/fecha; doce agregados mensuales. Meses transcurridos sin movimientos = cero; meses futuros = null, nunca valores aleatorios |
| Estado del ejercicio | FiscalYear status/starts_on/ends_on; progreso diario limitado a 0–100, fechas/progreso null si no hay ejercicio. Contadores de JournalEntry por posted/draft |
| Saldos | Líneas contables existentes: 57 tesorería, 430 clientes, 400/410 proveedores, 472/477/4700/4750 IVA; saldo firmado débito menos crédito del ejercicio, sin cierre. No se presenta como posición bancaria externa |
| Documentos | Expense, SalesDocument facturas/rectificativas si manage-installations y PurchaseDocument facturas/rectificativas si view-purchases. Máximo cinco por fuente, combinado en cinco filas recientes por fecha documental; enlaces a registros existentes y moneda real |
| Tareas | BankTransaction pending del periodo; conciliación bancaria, sin fechas de vencimiento inventadas ni checkbox para completar una tarea inexistente |
| Actividad | JournalEntry posted_at y número real; últimos cinco eventos de contabilización, sin presentarlo como auditoría completa |
| Empresa/usuario | workspaceContext.companyName y auth.user.name; contexto existente de un ERP/empresa, sin selector o filtrado multiempresa ficticio |

## 5. Datos no disponibles

Pendientes: KPI fiscal a ingresar validado y periodo elegido; contador unificado de documentos por procesar; otras tareas/obligaciones; actividad de cobros/pagos/documentos. Null se diferencia de cero consultado. Nuevo proyecto se omite porque falta creación de entidad independiente. Roadmap UI-HOME-03 a 09 documenta dependencias y aceptación. El enlace documental “Ver gastos” nombra su destino real; no promete un índice unificado de todas las fuentes.

## 6. Permisos

DashboardController y FinancialDashboardSummary mantienen view-finance en servidor, también para reload parcial. El cliente conserva la vista operativa sin datos financieros para usuarios sin ese permiso, evitando huecos financieros. Accesos a cliente, presupuesto, factura, orden y conciliación usan abilities existentes; nuevo asiento requiere manageAccounting y ejercicio abierto. Las rutas siguen aplicando sus middleware/gates. No hay roles hardcodeados nuevos ni ampliación de permisos.

## 7. Responsive y accesibilidad

Desktop: cuatro KPI, fila central 2:1:1, accesos rápidos a todo el ancho y tres bloques inferiores 2:1:1. Tablet: KPI 2×2, gráfico ancho completo, estado/saldos en dos columnas. Móvil: KPI y bloques en una columna, tareas antes de documentos, acciones en grid adaptable y documentos en filas compactas. Fecha dinámica visible en Inicio financiero móvil. Focus visible, links semánticos, badges con texto y gráfico con alternativa tabular.

Comprobados con sesión autenticada: desktop grande 1536×1024, estándar 1440×900/1280×900, tablet landscape 1024×768, portrait 834×1112 y móvil 390×844. Sin overflow global; tabla documental desktop sustituida por lista en móvil. Se comprobó ejercicio 2027 sin movimientos/ejercicio configurado y se restauró 2026. No se envían formularios de negocio.

## 8. Performance

Se reutilizan las consultas de FinancialDashboardSummary, sin endpoint nuevo, dependencia nueva ni segundo cálculo contable. Relaciones de cliente/proveedor eager; documentos/actividad limitados y doce valores mensuales en frontend. FiscalYear pasa de consultar status a obtener sus fechas/status una sola vez: no se agrega una consulta duplicada. Revisión estática sin N+1 introducido. La agregación existente por cuenta/fecha puede crecer con el volumen: queda documentada para profiling, sin afirmar mediciones de producción que no se han realizado.

La carga financiera es síncrona en Inertia; no se añade skeleton artificial. Los estados vacíos están implementados. Aislar errores de consulta/reintentos por widget requiere carga parcial real y queda planificado; no se presenta como infraestructura ya implantada.

## 9. Validación técnica

- DashboardTest + FinancialDashboardTest: 45 pruebas correctas, 457 aserciones (SQLite aislado en memoria).
- Nueva prueba: periodo fiscal personalizado, avance de 50%, límite antes/después y fechas/progreso ausentes.
- Pruebas existentes: contabilizados/cierre, comparativas, ejercicio futuro, año inválido, privacidad y documentos por permisos.
- TypeScript: correcto.
- ESLint dirigido a los tres componentes/página: correcto.
- Prettier dirigido: correcto.
- Pint de servicio y test: correcto.
- Catálogo: 4.103 textos visibles, 4.549 entradas bilingües.
- Build: correcto, 13,37 s; persiste warning de chunk superior a 500 KB.

## 10. Git status

Cambios locales sin staging. El árbol conserva las fases previas: 32 archivos tracked modificados, más archivos nuevos anteriores y HomeHeading/este informe. No se modifican .env, lockfiles ni dependencias; no hay cambio de routing en esta fase. El número acumulado no corresponde solo al dashboard.

## 11. Resumen del diff

Composición y encabezado de Inicio, extracción del gráfico/documentos, accesos reales condicionados, badges, alternativa textual, lista móvil y estados vacíos. Contrato aditivo fiscal_dates/fiscal_progress y prueba de regresión. Traducciones/roadmap. Diff tracked acumulado al revisar: 2.359 inserciones y 820 eliminaciones en 32 archivos, excluyendo archivos nuevos; incluye sidebar/shell previos.

## 12. Problemas y deuda técnica detectados

P&G existente consulta todo el año sin filtro posted; Inicio conserva posted hasta as_of. Comparten signos, tipos de cuenta y exclusión del cierre, pero pueden diferir por borradores/fechas futuras. Se documenta la armonización futura, sin modificar AccountingController ni inventar una nueva interpretación. El resumen económico sigue un año calendario; fechas reales se usan para el progreso de FiscalYear, sin cambiar el modelo de informes. Otros pendientes: KPI IVA, tareas/actividad ampliadas, multiempresa, profiling y warning de bundle. No se amplía el alcance para resolverlos.

## 13. Confirmación

Commit: NO realizado
Push: NO realizado
Merge: NO realizado
