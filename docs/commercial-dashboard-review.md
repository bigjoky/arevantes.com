# Revisión del dashboard Comercial — 06/10/2026

1. **Rama:** ai/sidebar-navigation-redesign; cambios locales sobre trabajo previo, sin operaciones Git de publicación.
2. **Archivos:** nuevo CommercialDashboardController, CommercialDashboardSummary, página commercial/index, tipos commercial-dashboard y componentes commercial-evolution/commercial-activity. Cambios aditivos en rutas, WorkspaceNavigation, AnalyticsService, CommercialConversionAnalytics, OpportunityCatalog, OpportunityController y OpportunitySalesCycle; CSS, traducciones y pruebas. El diff acumulado incluye fases anteriores.
3. **Componentes:** PageShell/PageHeader y estilos de Inicio reutilizados. Panel existente únicamente se exporta; Inicio y shell no se rediseñan. Recharts ya instalado, sin dependencias nuevas.
4. **Routing:** GET /commercial autenticado; primer hijo Comercial del grupo Comercial. CRM /opportunities, Clientes /people y Presupuestos /quotes mantienen páginas y rutas.
5. **KPI:** ventas netas subtotal de facturas/abonos emitidos, excluye draft/cancelled; pendientes son presupuestos sent emitidos en periodo y vigentes hoy. Pipeline activo excluye won/lost entre oportunidades creadas en periodo. Sin cierres, conversión null, no cero inventado.
6. **Conversión exacta:** won / (won + lost) × 100; cierre más reciente en OpportunityStageHistory, estado terminal actual y exclusión de eliminados, reutilizando CommercialConversionAnalytics.
7. **Etapas:** las siete existentes: new, qualified, survey, proposal, negotiation, won, lost. Catálogo centralizado sin cambiar validación ni transiciones; las trece del diseño no existen en este backend.
8. **Objetivos:** FinancialBudget revenue prorrateado por días, con view-finance y equipo. No cuota individual ficticia; null si no hay presupuesto positivo. Sin botón de configuración hacia una ruta inexistente.
9. **Presupuestos:** draft/sent/accepted/rejected/expired; conteo y total amount por fecha de emisión y estado actual. amount incluye impuestos según fuente, distinto de ventas netas. Enlaces a filtros existentes.
10. **Clientes:** nuevos por created_at y estados lifecycle_status de esa misma cohorte. No se inventan clientes en seguimiento ni inactividad a 90 días.
11. **Actividad:** eventos CRM y historial de presupuestos con autor/fecha/enlace real. Seis por fuente, seis finales. Corregido y probado el merge de colección Eloquent vacía con historial de presupuestos.
12. **Tareas:** acciones CRM pendientes/en curso, vencimiento en periodo y oportunidad relacionada; enlace real, indicador vencido, sin acción de completar simulada.
13. **Permisos:** gates manage-customers/manage-quotes/manage-installations por dominio. Sin cualquiera, 403 incluso reload parcial. Scope propio heredado de AnalyticsFilter; propietarios/creadores/relaciones propios. Series no autorizadas null y widgets ocultos.
14. **Responsive:** escritorio cuatro KPI, gráfico/objetivo 2:1, tres resúmenes y actividad/tareas 2:1. Tablet dos KPI por fila y bloques apilados; móvil una columna, tareas primero y actividad en lista. Navegador autenticado comprobado a 1440×900, 1024×768, 834×1112 y 390×844, sin overflow global.
15. **Rendimiento:** agregación SQL por mes/estado, historial de cierre existente, doce valores mensuales; relaciones eager y listas limitadas. Prueba de crecimiento de consultas con veinte oportunidades. Sin cache ni índices especulativos.
16. **Pruebas:** 59 tests correctos, 1.444 aserciones: CommercialDashboard, Opportunity, WorkspaceNavigationAuthorization, ExecutiveAnalytics y QuoteWorkflowSecurity. TypeScript, ESLint dirigido, i18n y build correctos. Build conserva aviso existente de chunk grande. SQLite aislado en tests; lectura real PostgreSQL en navegador.
17. **Estado:** implantado localmente y abierto en localhost:8001/commercial. Cambio de periodo anual verificado con datos reales; resto de límites temporales cubiertos por tests. Ceros, null y estados vacíos diferenciados. No se insertaron datos de ejemplo en el entorno conectado.
18. **Diff:** git diff --check correcto. Trabajo previo conservado; ninguna modificación de env, dependencias o migraciones. No publicación a develop ni producción.
19. **Deuda:** UI-COMMERCIAL-02 a 10 en roadmap: catálogo de etapas, cuotas individuales, inactividad, snapshots, bases monetarias, actividad/tareas ampliadas, propiedad comercial y profiling. Dependencias y aceptación explícitas; no se amplía alcance.
20. **Confirmación:** implementación local terminada; las cifras visuales son datos del ERP, no números del mockup.

Commit: NO realizado
Push: NO realizado
Merge: NO realizado
