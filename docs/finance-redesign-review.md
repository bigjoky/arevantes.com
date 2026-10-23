# Revisión local del rediseño de Finanzas · 11/10/2026

Implantación por secciones sobre el mockup suministrado. No publicado. Los importes proceden del dominio existente; no se han añadido datos de negocio de muestra ni ejecutado pagos, transferencias, conciliaciones o envíos reales durante la revisión visual.

## 1. Rama actual

`ai/sidebar-navigation-redesign`. Existía un checkout con trabajo acumulado de las fases anteriores: 90 archivos rastreados modificados y numerosos archivos nuevos. Se ha conservado; ninguna operación Git destructiva, commit, push, merge ni PR.

## 2. Archivos modificados en esta fase

El inventario siguiente compara contenido con la captura previa a Finanzas, no con HEAD, que incluye todos los módulos anteriores:

- `app/Http/Controllers/FinanceWorkspaceController.php`
- `app/Services/Finance/FinanceWorkspace.php`
- `database/migrations/2026_10_11_140000_protect_manual_financial_forecast_retries.php`
- `resources/css/finance.css`
- `resources/js/pages/banking/legacy-index.tsx`
- `resources/js/pages/finance/banks.tsx`
- `resources/js/pages/finance/workspace.tsx`
- `tests/Feature/FinanceRedesignTest.php`
- `app/Http/Controllers/BankingController.php`
- `app/Http/Controllers/FinanceController.php`
- `app/Models/FinancialForecastItem.php`
- `app/Services/Finance/OutstandingBalanceCalculator.php`
- `app/Services/Finance/TreasuryForecast.php`
- `app/Support/WorkspaceNavigation.php`
- `lang/es.json`
- `resources/js/i18n/catalog.json`
- `resources/js/pages/banking/index.tsx`
- `routes/web.php`
- `tests/Feature/FinanceTest.php`
- `tests/Feature/WorkspaceNavigationAuthorizationTest.php`

Se añaden también este informe, el apartado de Finanzas de `docs/roadmap.md` y `docs/finance-redesign-git-status.txt`. La copia `banking/legacy-index.tsx` conserva la pantalla original y exporta sus formularios para reutilizarlos; el índice original pasa a ser un selector de presentación.

## 3. Modelos reales encontrados

Existían y se reutilizan BankAccount, BankTransaction, BankStatementImport, BankReconciliation, BankTransactionPosting, BankTransfer, BankReconciliationRule/Version/Run, BankFinancialProduct, SalesDocument/Payment, PurchaseDocument, SupplierPayment, Expense, FinancialForecastItem, FinancialCategory, LedgerAccount, JournalEntry/Line y AuditLog. No nueva tabla de facturas, pagos o movimientos. Solo se añade `operation_key` nullable y único a previsiones manuales.

## 4. Arquitectura implementada

Existían FinanceController, BankingController, TreasuryForecast, OutstandingBalanceCalculator, importadores, motor de reglas, sincronización contable y proyecciones de Facturación. Se reutilizan. Nuevo FinanceWorkspace es una proyección de lectura con SQL y DTO de importes decimales; FinanceWorkspaceController sirve Inertia; React utiliza el shell, componentes y gráficos existentes. Se mantiene la gestión avanzada mediante `view=manage` y los accesos históricos por ejercicio y planificación.

## 5. Navegación

Finanzas tiene enlace propio `/finance` y chevron independiente. Exactamente tres hijos: Tesorería, Bancos y conciliación y Previsiones. Sus rutas son `/finance?area=treasury`, `/banking` y `/finance?area=forecasts`, con ejercicio cuando lo añade el shell. No nuevo Dashboard hijo. Navegación, logo y estructura del resto de áreas conservados.

## 6. Dashboard

Nueva composición de seis indicadores, dos gráficos y dos listas de vencimientos reales. Se muestran saldo bancario registrado, cobros pendientes, pagos pendientes, flujo bancario del periodo, previsión a 30 días y vencimientos en 7 días. No disponible confirmado: etiqueta correcta y contrato `availableBalance=false`. No porcentajes ficticios ni cifras del mockup. Gráfico 3/6/12 meses representa flujo de extractos, explícitamente no histórico de saldo disponible.

## 7. Tesorería

Nueva vista con movimientos, posición, categorías y análisis. Búsqueda, cuenta, tipo, fechas y ordenación se aplican en backend; páginas de 10 registros. Periodo mensual limita la consulta y sus agregados. Extractos y pagos de Facturación no vinculados se consultan por separado; estos últimos no alteran saldo ni flujo bancario. La búsqueda actual usa concepto, referencia y cuenta: contraparte/proyecto normalizados quedan pendientes.

## 8. Posición de tesorería

Saldo registrado = saldo inicial + extractos de la misma moneda con operación hasta hoy. Cuentas inactivas visibles por separado; consolidado solo cuentas EUR activas. No reinicio artificial por ejercicio. IBAN enmascarado, fecha de última operación, pendiente y estado reales. Disponible confirmado e históricos completos requieren datos que el modelo no contiene. El control bancario histórico existente conserva su fecha de último extracto; no se confunde con la posición actual.

## 9. Cuentas bancarias

Se reutiliza formulario de alta. Nuevo formulario de edición y endpoint transaccional permiten cambiar nombre, entidad y estado, sin eliminar historial. No se modifican moneda o saldo inicial de cuentas con movimientos. Consulta sujeta a `view-finance`; edición/alta a `manage-treasury`. No se abren cuentas reales en entidades bancarias. No se afirma segregación empresarial: faltan claves y políticas de entidad en todo el dominio financiero.

## 10. Movimientos bancarios

Se conservan concepto original, operación, valor, importe, moneda, referencia, importación y relaciones. Nueva lista paginada y filtros sobre consulta existente. Las fechas de calendario se serializan Y-m-d para impedir que UTC desplace el día. Lista bancaria conserva orden fecha/id original; ordenación configurable adicional y búsqueda de tercero quedan pendientes. No se introduce movimiento manual genérico sin backend; las contabilizaciones y transferencias autorizadas existentes siguen en herramientas avanzadas.

## 11. Importación de extractos

Reutilizados CSV configurable y Norma 43, vista previa, validación, hash normalizado y restricción única por cuenta. Nuevo acceso integrado reutiliza StatementImportPanel y muestra resultado/historial. El bloqueo de cuenta serializa importaciones sobre la misma cuenta. No se han subido extractos reales nuevos durante las comprobaciones. Historial detallado y rollback conservan la gestión existente; validación concurrente PostgreSQL simultánea pendiente.

## 12. Conciliación

Reutilizados candidatos explicables, enlace a pago existente y registro de pago desde factura cuando se selecciona expresamente. Nueva revisión con candidato, confianza, opción elegida y confirmación explícita. No se dispara conciliación automática al abrir la página. Se bloquean movimiento y pagos seleccionados; restricciones existentes previenen duplicados. Parciales usan las capacidades existentes; un movimiento con varias facturas no está soportado por el modelo 1:1 actual y queda pendiente.

## 13. Reglas de correspondencia

Se reutiliza motor determinista con versiones, preview, aplicación y reversión. Pestaña integrada reutiliza el componente original para usuarios con permisos de tesorería y contabilidad; otros ven reglas existentes sin aplicar. Backend mantiene gates originales. No IA nueva, conexiones bancarias ni conciliación de coincidencias ambiguas por mero importe.

## 14. Reversión de conciliaciones

Nuevo formulario pide motivo y muestra errores; backend valida un motivo suministrado y mantiene compatibilidad con llamadas históricas que no lo enviaban. Bloqueos en orden movimiento/conciliación y auditoría explícita almacenan usuario, fecha, motivo y snapshot de relaciones. Se reutilizan contraasientos existentes. El flujo antiguo elimina la relación activa y pagos generados; sus snapshots permanecen en auditoría. Migrar todo el ciclo a entidades de reversión conservadas y exigir motivo también a clientes históricos es deuda explícita, no se presenta como implantado.

## 15. Previsiones

Se reutiliza TreasuryForecast, con cálculos de agregación decimal y salida aditiva `exact=true`; consumidores anteriores mantienen tipos numéricos. Horizontes 7/30/60/90, cinco indicadores incluyendo cierres a 30 y 90 días, importes y fechas reales. Selector de 7 días verificado en navegador. Rectificativas emitidas reducen derechos de cobro; gastos en otra moneda se excluyen de EUR. Nuevo formulario autorizado de previsión manual con importe exacto, dirección, categoría real, fecha, probabilidad y recurrencia existente. Token único evita creación repetida del mismo envío; cancelación conserva la previsión. Observaciones adicionales/editor completo conservan gestión avanzada o quedan pendientes según campo.

## 16. Vencimientos

Se reutilizan saldos y vencimientos de Facturación, sin fechas inventadas. Listas de cobros/pagos enlazan al origen; detalle paginado de obligaciones y previsiones manuales. Obligaciones sin fecha no aparecen como vencimientos. Vencidos se trasladan a hoy únicamente en la proyección, no se modifica la fecha contractual. Tabla unificada de todos los eventos proyectados con impacto y filtros de estado/origen queda pendiente.

## 17. Escenarios

Se reutilizan probable/conservador/optimista del servicio existente, con supuestos visibles: cobros 90/70/100%, probabilidad/recurrencia manual real. No parámetros arbitrarios nuevos. Gráfico claramente estimado, mínimo real de 90 días y resumen mensual agregado con flujos separados de saldo de cierre. Escenarios configurables por fechas, comparación simultánea y alertas adicionales normalizadas quedan pendientes.

## 18. Integración con Facturación

Se reutiliza BillingWorkspace para obligaciones y vínculos de pago; ninguna segunda verdad. Crédito emitido, pagos parciales y cancelaciones conservan reglas actuales. El flujo efectivo nuevo usa extractos exclusivamente: pago vinculado se cuenta una sola vez y no vinculado se muestra aparte. KPI pendiente de pago corresponde a facturas; otras obligaciones de gasto con vencimiento participan en el servicio de previsión existente. Anticipos e imputaciones generales requieren modelo normalizado.

## 19. Integración con compras

Se reutiliza PurchaseCommitmentAnalysis en el motor existente: compromisos descuentan facturas derivadas y créditos según su regla; una orden no se trata como factura pagada. No motor nuevo de compras. La fuente conserva parte del cálculo float heredado; conversión completa a decimal y precedencia verificable para todos los casos de crédito/material requiere trabajo específico.

## 20. Integración con proyectos

Se reutilizan relaciones y documentos de origen de facturas/compromisos. No se proyecta ingreso por todo el presupuesto/proyecto ni oportunidad comercial. No nuevos hitos contractuales ni certificaciones. Relación financiera normalizada por proyecto/instalación y necesidades de facturación fiables quedan en roadmap.

## 21. Integración con contabilidad existente

Se conserva JournalPoster, AccountingSynchronizer, subcuentas bancarias, contabilización de comisiones y contraasientos. El rediseño de lectura no genera asientos. Se distingue saldo de extractos, posición registrada y contable. No se implanta Nexo ni Fiscal.

## 22. Permisos

Se consultan gates reales `view-finance`, `manage-treasury`, `manage-accounting` y los de endpoints existentes. No se infiere permiso del nombre del rol. IBAN oculto por defecto; gestión avanzada solo expone el completo a usuarios autorizados; raw_data y control_data ocultos en DTO de consulta. Tests de 403 y ocultación. Multientidad y permisos salariales granulares necesitan diseño de dominio; no se cargan nóminas individuales.

## 23. Auditoría

Se reutilizan observadores de bancos, conciliación, reglas y previsiones. Nueva reversión deja snapshot específico y nuevo endpoint de cuenta utiliza auditoría existente. Cancelación de previsión actualiza estado, sin borrarla desde interfaz nueva. La eliminación histórica sigue auditada, pero conservación íntegra de entidad anulada, retención y motivos obligatorios en todos los endpoints siguen pendientes.

## 24. Performance

SQL SUM para balances/KPIs, grupos mensuales y clasificación parcial de partidas con categoría contable explícita. Paginación backend de movimientos, vencimientos y previsiones manuales. Eager aggregate de rectificativas evita N+1 añadido a previsión. No suma de filas paginadas en React. Motor heredado aún carga obligaciones/eventos y herramientas avanzadas completas; falta medir con volumen y paginar candidatos/selectores/historial. Conversión numérica frontend solo para gráficos/formato, no para contabilidad.

## 25. Responsive

Reutilizado shell responsive. Comprobación real en 1440×1000, 820×1180 y 390×844; página sin overflow horizontal. Tesorería usa cards móviles; bancos transforma filas en tarjetas etiquetadas; filtros accesibles y pestañas con desplazamiento local. Capturas en /tmp/finance-dashboard-desktop.png y /tmp/finance-banks-mobile.png. Pantallas avanzadas preservadas no se declaran íntegramente rediseñadas.

## 26. Tests ejecutados

90 pruebas relacionadas pasan con 1.880 assertions: FinanceRedesign (11 nuevas), Finance, Banking, BankReconciliationMigration, ActionCenter, ActionReminder, WorkspaceNavigationAuthorization y TranslationCoverage. Incluyen separación de saldos/monedas, futuro excluido, créditos, deduplicación, transferencias, páginas/filtros/orden, horizonte, idempotencia manual, fechas de calendario y desactivación sin borrado. SQLite aislada; lectura y migración en PostgreSQL local. Carrera PostgreSQL simultánea NO comprobada. La suite global se ha ejecutado y se documenta su resultado final abajo; no se declara totalmente verde.


Resultado global final: 829 pruebas, 820 pasan, 3 fallos, 1 error y 5 omitidas, 13020 assertions. Incidencias todavía abiertas:

- `Tests\Feature\CatalogTest::test_commercial_users_can_view_the_catalog`: Unexpected Inertia page component.
- `Tests\Feature\ExecutiveAnalyticsTest::test_project_margins_reconcile_every_business_line_and_hide_finance_without_permission`: Property [profitability.planned_margin] does not exist.
- `Tests\Feature\SupplierIdentityTest::test_purchase_documents_freeze_supplier_identity_and_derived_documents_inherit_it`: Property [supplierIdentity.legal_name] does not exist.
- `Tests\Feature\PlanningScenarioTest::test_executed_purchase_and_finance_drafts_are_promoted_once_and_remain_traceable`: PHPUnit\Framework\Assert::assertCount(): Argument #2 ($haystack) must be of type Countable|Traversable|array, null given, called in /Users/joaquinarevalobueno/.codex/worktrees/ab03/arevantes.com/vendor/laravel/framework/src/Illuminate/Testing/Fluent/Concerns/Has.php on line 34

No se afirma que estas incidencias fueran previas: se detectan en el checkout acumulado y requieren diagnóstico propio. Las pruebas de Finanzas y sus integraciones cubiertas pasan. No se ha ejecutado una carrera simultánea en PostgreSQL.

## 27. Build

Tipos, lint global y catálogo ES/EN pasan. Build Vite completado; advertencias de chunks generales >500 kB y tiempos de plugins. No despliegue. Pint dirigido y git diff --check pasan.

## 28. Git status

La captura completa se adjunta en `finance-redesign-git-status.txt`. Acumula trabajos anteriores; no atribuir todas sus modificaciones a Finanzas. Sin commits, push o merge. No se ha usado reset/clean/checkout/restore/stash.

## 29. Resumen git diff

```text
app/Http/Controllers/FinanceWorkspaceController.php: +66 / -0
app/Services/Finance/FinanceWorkspace.php: +83 / -0
database/migrations/2026_10_11_140000_protect_manual_financial_forecast_retries.php: +21 / -0
resources/css/finance.css: +356 / -0
resources/js/pages/banking/legacy-index.tsx: +3610 / -0
resources/js/pages/finance/banks.tsx: +525 / -0
resources/js/pages/finance/workspace.tsx: +1103 / -0
tests/Feature/FinanceRedesignTest.php: +152 / -0
app/Http/Controllers/BankingController.php: +25 / -6
app/Http/Controllers/FinanceController.php: +38 / -11
app/Models/FinancialForecastItem.php: +1 / -1
app/Services/Finance/OutstandingBalanceCalculator.php: +19 / -3
app/Services/Finance/TreasuryForecast.php: +57 / -29
app/Support/WorkspaceNavigation.php: +2 / -2
lang/es.json: +12 / -4
resources/js/i18n/catalog.json: +556 / -0
resources/js/pages/banking/index.tsx: +12 / -3608
routes/web.php: +1 / -0
tests/Feature/FinanceTest.php: +3 / -3
tests/Feature/WorkspaceNavigationAuthorizationTest.php: +1 / -1
```

Comparación por contenido anterior a Finanzas: nuevo servicio/controlador, tres vistas y CSS, migración aditiva, pruebas; adaptaciones de navegación, bancos, previsión decimal, traducciones y ruta de edición. La gran reducción del índice de bancos corresponde a mover pantalla original a legacy-index, no eliminar funcionalidad.

## 30. Limitaciones reales

Saldo disponible confirmado, históricos fiables sin fecha inicial, aislamiento empresarial, conversión multidivisa, reparto de una conciliación entre varios documentos, disponibilidad por cuenta de obligaciones y clasificación bancaria completa no disponibles. Contratos false/null y explicación visible, sin acciones aparentes. Para ámbito de una cuenta la previsión consolidada se marca no disponible en lugar de asignar obligaciones arbitrariamente.

## 31. Deuda técnica

Roadmap unificado con dependencias, estados y aceptación: completar decimales en motores heredados, conservación del ciclo de reversión, pruebas concurrentes PostgreSQL, datos de apertura/históricos, multientidad, asignación de cuenta, filtros/eventos proyectados completos, búsqueda de contraparte, créditos/anticipos, categorías/repartos, selección a gran volumen y adaptación visual de herramientas avanzadas. No se anticipan diseños futuros.

Commit: NO realizado
Push: NO realizado
Merge: NO realizado
