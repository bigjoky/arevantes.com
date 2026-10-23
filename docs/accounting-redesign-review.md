# Contabilidad — revisión del incremento local

Inicio 15/10/2026; continuación y verificación 23/10/2026. Referencia: diseño de cinco pantallas y petición de 100 apartados. No es una publicación ni una certificación de cumplimiento del PGC.

## Inventario y criterio

Laravel 13 / PHP 8.4, Inertia 3, React 19, TypeScript, Recharts, shadcn, Brick Math y PostgreSQL existentes. Se conservan AppShell, sidebar, logo, permisos y los circuitos de facturación, tesorería y fiscalidad. Sin dependencias, migraciones ni tablas nuevas. Las escrituras de las pruebas se realizan en SQLite aislado; la revisión visual usa PostgreSQL local sin crear operaciones de negocio para la revisión.

Modelos reales: LedgerAccount, JournalEntry, JournalLine, AccountingTemplate, AccountingTemplateLine, AccountingRecurringRun, AccountingPeriod, FiscalYear, DocumentSeries, AuditLog, CompanyProfile, Person, Supplier, SalesDocument, SalesDocumentPayment, PurchaseDocument, SupplierPayment y Expense. Servicios reutilizados: JournalPoster, AccountingSynchronizer, AccountingPeriodManager, FiscalYearClosing, DocumentNumber y CorporateTaxCalculator. No se crea otro motor ni código Nexo.

Referencia normativa revisada: [PGC, RD 1514/2007 consolidado en BOE](https://www.boe.es/buscar/act.php?id=BOE-A-2007-19884). El catálogo PGC y el equilibrio Debe/Haber no acreditan por sí solos clasificación, valoración ni formulación correcta de cuentas anuales. Se mantiene la revisión previa `docs/accounting-pgc-review-2026-10-05.md`; no se trasladan sus afirmaciones de publicación a este incremento.

## Entrega de los 37 puntos solicitados

| Punto | Qué existía | Qué se reutiliza | Qué se implementa | Qué no se implementa | Validación contable pendiente |
| --- | --- | --- | --- | --- | --- |
| 1. Rama | Árbol con trabajo acumulado | ai/sidebar-navigation-redesign | Incremento sobre la rama actual | Cambio de rama, commit, push, merge y PR | Ninguna |
| 2. Archivos | Fases previas con cambios sin guardar en Git | Sus modificaciones preservadas | Inventario al final de este documento y estado Git completo | Atribuir todo el diff acumulado a Contabilidad | Revisión del incremento |
| 3. Modelos reales | Entidades enumeradas arriba | Mismas tablas y relaciones | Proyecciones y contratos tipados | Catálogos duplicados de cuentas, terceros o ejercicios | Correspondencia entre naturaleza configurada y operación |
| 4. Arquitectura | Motor de contabilización y cierres | JournalPoster/DocumentNumber y servicios existentes | AccountingWorkspace de lectura; controladores de workspace y borradores | Nuevo motor contable/fiscal/financiero | Alcance de automatizaciones legacy |
| 5. Navegación | Cinco hijos canónicos | Sidebar/chevron y rutas inglesas existentes | Accesos directos; Informes abre Balance; fichas completas; ejercicio conservado | Rutas españolas duplicadas o nuevo shell | Ninguna |
| 6. Vista general | Diario y resumen financiero existentes | Apuntes y cuentas reales | Cuatro indicadores, dos gráficos, grupos y últimos asientos | Importes del mockup o estado definitivo | Calidad de contabilización y clasificación |
| 7. Indicadores | Tipos asset/liability/equity/income/expense | Naturaleza real de cada cuenta | Saldos contabilizados, resultado separado y ecuación con resultado no regularizado | Certificar estados oficiales o inferir cuentas desde bancos | Reclasificaciones y partidas oficiales PGC |
| 8. Gráficos | Fechas contables | Agregación SQL por mes | Ingresos/gastos y resultado mensual; selector 3/6/12 meses con datos disponibles | Comparativa porcentual sin datos; gráfico bancario como resultado | Periodificación y partidas de explotación/financieras |
| 9. Asientos | Registro y reversión | JournalEntry y rutas existentes | Filtros/búsqueda/orden/paginación backend, borradores y ficha completa | Importador validado; nuevo estado de revisión | Revisión humana previa a contabilizar |
| 10. Apuntes | Cuenta, descripción, Debe y Haber | JournalLine | Detalle completo, Diario con líneas y formulario 2–50 apuntes | Centros de coste, notas o terceros nuevos ficticios | Dimensiones adicionales y referencias por línea |
| 11. Validaciones | Periodos/ejercicios/cuentas abiertas | Servicios y validaciones reales | Normalización decimal HALF_UP; negativos, infinitos, doble lado, descuadre y exceso rechazados | Redondeos específicos no definidos por operación | Política de redondeo y casos límite del dominio |
| 12. Numeración | DocumentSeries y unicidad de número | Contador bajo bloqueo del ejercicio | Borrador D-ULID; número final al contabilizar; misma entidad; reintento rechazado | Numeración multiempresa/diario sin soporte | Series/diarios y ensayo concurrente PostgreSQL |
| 13. Correcciones | Reversión con vínculo | JournalPoster y reverses_entry_id | Solo contabilizados; motivo en ficha; repetición rechazada; protección ORM de cabeceras y líneas | Triggers contra escrituras SQL directas; edición silenciosa | Circuitos de corrección especiales y cierres concurrentes |
| 14. Plan contable | Catálogo, jerarquía y procedencia PGC | LedgerAccount | Tabla/filtros/paginación y ficha de cuenta | Nueva taxonomía oficial inventada | Clasificación de auxiliares/contra-cuentas |
| 15. Cuentas | Formulario y controles existentes | CRUD de ChartOfAccountsController | Acceso a gestión y auditoría de cuentas | Renumeración de históricos o borrado destructivo con movimientos | Naturaleza y uso real de cuentas nuevas |
| 16. Subcuentas | parent_code, level, is_postable | Gestión existente | Acceso desde ficha y Mayor | Nueva gestión paralela; agrupación jerárquica materializada | Compatibilidad de padre/naturaleza y usos especiales |
| 17. Saldos | Sumas de líneas | SQL y decimales exactos | Solo posted; naturaleza; inicial/periodo/final y saldo acumulado | Saldos independientes o agregación de padres sin regla | Integridad, aperturas y continuidad de ejercicios |
| 18. Informes | Cálculos agregados legacy | Mismos apuntes | Pestañas y consultas provisionales coherentes con el Mayor | ECPN, EFE, matriz oficial PGC, comparativas y niveles avanzados | Modelo normal/abreviado/PYMES aplicable |
| 19. Diario | Resumen de cabeceras limitado a 250 | Asientos/apuntes | Consulta cronológica paginada, líneas reales, ficha y CSV sin truncamiento | Libro oficial versionado/legalizado | Orden/numeración/alcance del libro final |
| 20. Mayor | Consulta de una cuenta | Cuenta y apuntes | Saldo inicial del ejercicio; ventana SQL estable por fecha/asiento/línea antes de paginar | Centros de coste inexistentes | Aperturas y naturaleza de cuenta |
| 21. Sumas y saldos | Sumas simples | Fuente común de líneas | Inicial neto, Debe/Haber del periodo, final neto | Formato oficial con partidas no mapeadas | Conciliación de cierres/aperturas |
| 22. Balance | Tipos de cuentas y saldos | Cuentas reales | Agrupación por naturaleza, ecuación y resultado no regularizado separados | Clasificación corriente/no corriente y balance estatutario completo | Matriz por partidas y contra-cuentas; no se certifica definitivo |
| 23. Pérdidas y ganancias | Ingresos/gastos reales | Naturaleza configurada | Resumen provisional; excluye cierre y reversión de cierre | Resultado de explotación/financiero/impuestos sin matriz | Clasificación y ajustes contables |
| 24. Libros oficiales | Libros de IVA y consultas contables | Diario/Balance y rutas fiscales | Cinco tarjetas, pestañas, consultas reales y capacidades pendientes explícitas | Generación oficial, versiones, actas/socios, legalización o evidencia ficticia | Formatos, composición, obligaciones y justificantes reales |
| 25. Exportaciones | CSV local previo limitado | Infraestructura de respuesta Laravel | CSV de apuntes posted, periodo/cuenta/búsqueda/tipo y protección frente a fórmulas | PDF/XLSX de estados o fichero de legalización | Formatos de presentación y consistencia con informe oficial |
| 26. Facturación | Facturas/abonos/cobros/pagos con source/event | AccountingSynchronizer sin reescribirlo | Trazabilidad de origen; nueva lectura no dispara sincronización | Nuevo sistema, nuevas facturas o nuevas reglas de cuentas por defecto | Fallbacks 700/600, divisas, anticipos, devengo y retenciones según revisión previa |
| 27. Finanzas | Conciliación, bancos y tesorería | Enlaces y servicios existentes | Compatibilidad del enlace antiguo por financial_category; borradores fuera de saldos legacy | Movimiento bancario convertido automáticamente en asiento | Política de cuentas bancarias y conciliación |
| 28. Fiscal | Sociedades y libro de IVA | Proyección/cálculos existentes | Exclusión explícita de draft en resultado contable de Sociedades | Reglas tributarias nuevas o presentación | Limitaciones fiscales documentadas en fase Fiscal |
| 29. Permisos | view-finance, manage-accounting, manage-staff | Gates backend | Lecturas, borradores, contabilización y mantenimiento protegidos; rechazo 409 si hay múltiples CompanyProfile | Aislamiento completo por company_id; permisos granulares nuevos | Segregación de funciones y sociedad activa |
| 30. Auditoría | AuditableObserver y AuditLog | Mismo registro | Cuentas y apuntes auditados; cambios/borrados de líneas de draft registrados; historial de asiento | Historial jurídico de libros o cierre SQL inmutable | Retención, acceso y evidencia de procedimientos |
| 31. Responsive | Shell existente | Componentes comunes | Cinco vistas a 1440/820/390 px; cards móviles de asientos y tablas con scroll interno | Otro shell; modificar preferencias de otros módulos | Revisión visual con usuario |
| 32. Tests | Pruebas contables/integración existentes | Flujos de factura, pago, cierre y apertura | Nuevas pruebas de decimales, drafts, reversión, Mayor paginado, filtros/exportación y permisos | Ensayo multiproceso PostgreSQL completado | Concurrencia real de cierres/aperturas/idempotencia legacy |
| 33. Build | Vite/TypeScript/ESLint/i18n | Comandos del repositorio | Validación anotada abajo | Cambios de dependencias para silenciar avisos | Ninguna |
| 34. Git status | Árbol previamente modificado | Cambios acumulados intactos | Inventario final completo | Declararlo limpio | Ninguna |
| 35. Git diff | Cambios de fases anteriores | Integraciones mínimas | Diff final y listado de archivos | Atribuir todos los cambios acumulados a esta fase | Revisión humana |
| 36. Limitaciones | Deuda PGC/fiscal previa | Roadmap unificado | Carencias con estado/dependencias/aceptación | Capacidad completa por tener interfaz | Ver apartado de límites |
| 37. Deuda técnica | Floats legacy, cierres/contabilidad automática | Servicios sin reconstrucción masiva | Decimales en escritura JournalPoster y nuevas consultas; barreras para drafts | Migrar todos los cálculos legacy a decimales o crear motor multiempresa | Campaña de casos y reglas con asesoría |

## Límites que no deben confundirse con capacidades implantadas

- Los saldos derivan de apuntes contabilizados del intervalo y del ejercicio. El balance agrupa por naturaleza configurada; no determina activo/pasivo corriente/no corriente ni partidas legales del PGC. Una cuenta correctora mal clasificada requiere revisión. Sin apertura/contabilidad completa no se certifica situación patrimonial.
- Se muestra el patrimonio registrado y el resultado pendiente de regularizar por separado. La ecuación comprobada es activo − pasivo − patrimonio registrado − resultado no regularizado. El resultado de PyG excluye cierre y su reversión para evitar perder el resultado al regularizar; los servicios legacy requieren campaña específica de casos.
- Los borradores usan la misma entidad y no contaminan nuevos informes ni agregaciones legacy revisadas. Los cierres se bloquean por borradores pendientes. La protección de contabilizados se aplica a actualizaciones/borrados ORM y endpoints; no se presenta como inmutabilidad impuesta por el motor SQL.
- Los bloqueos y restricciones se reutilizan para contador, asiento, periodo y source/event. Las pruebas nuevas verifican reintentos secuenciales; no demuestran concurrencia multiproceso ni resuelven todas las carreras de los cierres legacy. Ediciones de draft se serializan, pero falta detección optimista de edición obsoleta.
- Las rutas contables rechazan más de una CompanyProfile. Esto impide usar estas pantallas en contexto ambiguo; no constituye aislamiento multiempresa integral de todo el ERP.
- No existe un registro completo de libros oficiales con versiones/artefactos/justificantes demostrado. Consultar el Diario o descargar apuntes CSV no genera ni legaliza un libro. No se crean actas, socios ni comunicaciones con el Registro Mercantil.
- Plantillas, recurrencias, cierres, reapertura y mapeos se conservan en sus herramientas existentes. La nueva creación usa borrador; la escritura manual legacy sigue requiriendo endurecimiento de idempotencia por petición.

## Archivos del incremento

Nuevos: `app/Services/Accounting/AccountingWorkspace.php`; `app/Http/Controllers/AccountingWorkspaceController.php`; `app/Http/Controllers/AccountingDraftController.php`; `app/Http/Middleware/AccountingCompanyContext.php`; `resources/js/pages/accounting/workspace.tsx`; `resources/js/pages/accounting/entry.tsx`; `resources/js/pages/accounting/account.tsx`; `resources/css/accounting.css`; `tests/Feature/AccountingRedesignTest.php`; este informe y `docs/accounting-redesign-git-status.txt`.

Integraciones: `AccountingController.php`, `ChartOfAccountsController.php`, `WorkspaceModuleController.php`, `JournalPoster.php`, `JournalEntry.php`, `JournalLine.php`, `AccountingPeriodManager.php`, `FiscalYearClosing.php`, `CorporateTaxCalculator.php` (solo filtro posted), `AppServiceProvider.php`, `WorkspaceNavigation.php`, `routes/web.php`, `resources/js/pages/accounting/accounts.tsx`, `resources/js/lib/workspace-navigation.ts`, `resources/js/i18n/catalog.json`, `lang/es.json`, `lang/en.json`, `tests/Feature/AccountingTest.php` (sus asserts legacy pasan por area=management), `tests/Feature/WorkspaceNavigationAuthorizationTest.php`, `tests/frontend/workspace-navigation.mjs`, `docs/roadmap.md`.

## Verificación

- Línea base antes de modificar: 44 pruebas contables, integraciones y navegación correctas (948 aserciones).
- Verificación final relacionada: **120 pruebas correctas, 2374 aserciones**, incluyendo Contabilidad, Facturación, Finanzas, Fiscal, traducciones y navegación. Once pruebas específicas nuevas de Contabilidad.
- Suite general: **848 pruebas, 839 correctas, 3 fallos, 1 error y 5 omitidas**, 13.405 aserciones. Los cuatro casos coinciden con las incidencias documentadas en Fiscal: CatalogTest espera catalog/index frente a supply/products; ExecutiveAnalyticsTest busca profitability.planned_margin; SupplierIdentityTest busca supplierIdentity.legal_name; PlanningScenarioTest recibe null en lugar de colección. No se declara suite global verde.
- TypeScript y ESLint global correctos. Catálogo ES/EN correcto: 5081 textos visibles y 5660 entradas bilingües. Compilación final correcta. Aviso conocido de chunks >500 kB conservado.
- 25 comprobaciones frontend de navegación correctas; git diff --check correcto. Las pruebas de cierre/reapertura/apertura, facturas, cobros y pagos existentes permanecen correctas en la selección relacionada. No se ha ejecutado una nueva prueba multiproceso PostgreSQL.
- La revisión PostgreSQL de las cinco vistas detectó un alias SQL reservado y la revisión SQLite un límite de fecha que excluía el último día. Ambos se corrigieron; cierre de 31/12 y resultado tras regularización comprobados.
- Las pruebas legacy conservan sus asserts de dominio usando area=management; las nuevas verifican el nuevo componente. El enlace financial_category conserva su contexto previo.

Capturas autenticadas locales en `/tmp/accounting-{overview,entries,accounts,reports,books}-{desktop,tablet,mobile}.png`. Comprobaciones DOM: ancho de documento igual al viewport en las 15 combinaciones. Sin operaciones de negocio creadas durante la revisión visual. Se recuperaron el servidor local y el contenedor PostgreSQL existente, sin recrear volúmenes ni cambiar credenciales. La preferencia del shell se conserva; los contrastes internos de Contabilidad siguen la referencia clara.

Commit: NO realizado
Push: NO realizado
Merge: NO realizado
