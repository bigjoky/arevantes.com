# Facturación — informe de implantación local

11/10/2026. Diseño aplicado de forma incremental; capacidades incompletas declaradas, sin datos de demostración ni afirmación de cumplimiento fiscal.

## 1. Rama actual

`ai/sidebar-navigation-redesign`. Trabajo exclusivamente local en el worktree ab03. Se mantiene la rama y el trabajo previo; no se publica ni se afirma que esté integrado en develop.

## 2. Archivos modificados

La fase cambia los archivos enumerados al final, comparados con la captura tomada antes de Facturación. Incluye proyección/controlador, cuatro páginas, componentes/estilos acotados, migración aditiva, tests, catálogo y roadmap. No se sustituyó el shell compartido.

## 3. Modelos reales encontrados

SalesDocument/Line/Payment, PurchaseDocument/Line, SupplierPayment, Expense/TaxLine, Supplier, Person, Installation, Quote, QuoteBillingPlan, AcceptancePayment, FiscalYear, series, CompanyProfile, DocumentDelivery, PaymentBatch y modelos contables/financieros existentes. No se crea otro libro de facturas, pagos o stock.

## 4. Arquitectura implementada

BillingWorkspace proyecta consultas SQL; BillingWorkspaceController prepara DTOs, permisos y filtros. Nueva UI Inertia reutiliza Card, Button, Input, Sheet y layout existente. Los comandos de negocio siguen los servicios/controladores originales. Contratos aditivos de capacidades pendientes, con false/null separados de [] o cero consultado.

## 5. Dashboard

Antes no existía este dashboard conjunto. Nuevo /billing: seis indicadores reales, evolución mensual, vencimientos no solapados, estados, últimas facturas y movimientos. Emisión por fecha documental; pagos por fecha efectiva; abiertos de todos los ejercicios. No se inventan comparativas ni valores de la maqueta. Necesidades contractuales explícitamente pendientes.

## 6. Facturas de clientes

SalesDocument original reutilizado. /sales lista facturas/rectificativas con búsqueda, fechas, estados, orden y páginas backend; conserva rutas de pedidos/albaranes y gestión antigua. Ficha completa, líneas, fuentes, cobros y entregas. Crear/editar/emitir usa autorización original; herramientas auxiliares históricas aún requieren adaptación visual.

## 7. Facturas de proveedores

Unión real PurchaseDocument supplier_invoice/credit_note y Expense invoice/simplified_invoice EUR, sin exigir pedido. Excluye pedidos y tickets de la lista de facturas; movimientos efectivos pueden incluir pagos de gastos/tickets. Ficha completa con impuesto/retención, original cuando disponible, fuente y pago autorizado; flujos de revisión/cotejo se conservan.

## 8. Cobros y pagos

Reutiliza SalesDocumentPayment y SupplierPayment; anulados de gastos excluidos del efectivo y visibles aparte. Lista movimientos, pendientes y vencimientos. Pago contextual con fuentes reales, procesamiento/errores, bloqueo de factura, límite exacto y UUID único opcional para compatibilidad; nueva UI siempre envía clave. Lotes y anulación general quedan parciales.

## 9. Series y numeración

Se conserva asignador fiscal existente, ejercicio, serie, transacción y restricciones. Borrador duplicado recibe identificador DRAFT, sin número fiscal/copias de cobros. Emisión repetida no renumera. No se cambió la política de series. Falta prueba simultánea PostgreSQL en base aislada.

## 10. Estados documentales

Se muestran separados de pago: borrador/emitida para ventas, recibida para proveedor, cancelada y rectificativa según origen. No se modifica automáticamente el estado documental porque una deuda venza.

## 11. Estados de cobro/pago

Derivados de pagos activos y créditos emitidos: pendiente, parcial, pagada/cobrada y vencida si due_date<hoy. Hoy no es vencida; saldo no negativo, exceso visible como saldo a favor pendiente de aplicar. No se crea una compensación ficticia.

## 12. Impuestos

Neto, IVA y retención proceden del documento y cálculos existentes. Agregación NUMERIC en PostgreSQL; nueva validación monetaria y saldo con BigDecimal a dos decimales, sin imponer IVA21 universal. El motor fiscal completo conserva cálculos legacy que aún usan float; su migración está pendiente.

## 13. Rectificativas

Existía conversión a credit_note vinculada. Se añade protección de repetición bajo lock: reutilizar documento y limitar total de créditos emitidos al original; importes negativos decimales. Se aplica crédito al saldo real. Rectificación parcial, causas fiscales y devolución/compensación múltiple no se declaran terminadas.

## 14. Anticipos

Se preserva flujo de AcceptancePayment asociado a presupuesto/aceptación y su conversión. No se crea anticipo fiscal a partir de cualquier cobro. Aplicación genérica entre facturas, devolución y tratamiento fiscal contractual pendientes.

## 15. Facturación parcial

QuoteBillingPlan y SalesDocumentConversion ya soportan porcentajes/secuencias; no se reemplazan. Ficha conserva fuente y presupuesto. Contratos/hitos/certificaciones con asignación única y regla de generación siguen pendientes; terminar instalación no factura automáticamente.

## 16. Documentos PDF

Plantilla corporativa DocumentPdf existente, sin otro generador. Nuevas emisiones archivan bytes en disco privado y SHA256; descargar/enviar reutiliza archivo y verifica integridad. Archivo ausente o alterado falla. PDF no es formato electrónico estructurado ni prueba de SIF.

## 17. Conservación histórica

Snapshots de emisor/cliente originales reutilizados. Nueva prueba confirma PDF idéntico tras cambio de CompanyProfile. Facturas ya emitidas sin archivo histórico siguen flujo legacy: falta auditoría y migración documental sin fabricar originales. También falta reconciliar archivo huérfano eventual tras fallo de commit de DB.

## 18. Envío por email

Se reutilizan endpoint, DocumentDelivery y correo existentes; modal autorizado y registro real en actividad. No se enviaron correos durante validación. Pendiente verificar proveedor de correo simulado y escenarios de archivo/fallo adicionales; abrir modal no cuenta como entrega.

## 19. Integración con presupuestos

Links y origen SalesDocument.quote/plan existentes; duplicar factura no duplica presupuesto ni sus cobros. Conversión original preservada. Hitos contractuales y sus asignaciones son capacidad pendiente.

## 20. Integración con proyectos

Referencia disponible por instalación/origen real. No se fabrican relaciones si faltan. Relación contractual normalizada de proyectos y necesidades de certificación pendiente según UI-BILL-10/11/18.

## 21. Integración con instalaciones

Links reales y albaranes/documentos relacionados; no se altera ejecución, planificación, materiales o postventa. No se emite factura por cambio de estado de instalación.

## 22. Integración con compras

Factura y pedido siguen tipos distintos. Se conserva cotejo, revisión fiscal, documentos originales y SupplierPaymentSourceResolver. Compra no crea stock desde este rediseño; enlace a gestión histórica cuando necesario.

## 23. Integración con contabilidad

AccountingSynchronizer y servicios de contabilización originales reutilizados en emisión/pago. No se crea segundo motor. Anulación general requiere contraasiento, periodo y conciliación; no se habilita borrado en UI como sustituto.

## 24. Integración con finanzas

Pagos efectivos, fuentes, cuentas, tarjetas y fondos de empleado reales. Separación de saldo y efectivo. Conciliación y PaymentBatch siguen sus herramientas originales; integración visual más amplia pendiente.

## 25. Integración con fiscal

Se conservan revisiones y datos fiscales existentes; no se sustituyen libros, liquidación o criterio de deducibilidad. Nuevo dashboard es gestión y no declaración tributaria. Normas aplicables por entidad/régimen requieren auditoría específica.

## 26. Normativa española

Consulta oficial 11/10/2026: RD1619/2012 de facturación y RRSIF RD1007/2023. Se distinguen requisitos generales, SIF y B2B electrónico; no se afirma cumplimiento. Debe concretarse régimen, territorio, SII y supuestos. Fuentes oficiales al final; la previsión de cambio de calendario anunciada el 05/10/2026 no se trata como norma aprobada.

## 27. Estado de VERI*FACTU/SIF

No se encontró motor de registros fiscales encadenados, QR, incidencias/remisión y pruebas de conformidad. capabilities.sif=false. Hash de PDF no implementa cadena SIF. UI-BILL-19 recoge auditoría y dependencias; no se envió información a AEAT.

## 28. Estado de facturación electrónica

No existe integración estructurada identificada. capabilities.structuredInvoice=false. PDF/email no equivalen a SPFE ni B2B estructurado. RD238/2026 y OrdenHAC1028/2026 deben analizarse por aplicabilidad y calendario, formatos, transporte, recepción y conservación antes de habilitar capacidad.

## 29. Permisos

Gates reales conservados; acciones fiscal/tesorería extra verificadas backend. No se infiere acceso sólo por rol. Prueba de ausencia de view-finance redacciona métricas y vistas sensibles. Gestión de gasto y original requieren permisos reales; fuentes según capacidad. Granularidad adicional pendiente.

## 30. Performance

SUM/GROUP BY mensual en SQL, unión y búsqueda paginadas backend; índices de vencimiento/créditos y UUID único. No se descargan todas las facturas para agrupar en navegador. Directorios de selector y relaciones de fichas todavía requieren autocomplete/paginación a escala y medición con volumen representativo.

## 31. Responsive

Comprobación visual real: escritorio1440×1000, tablet820×1180 y móvil390×844. Cards en móvil, detalle apilado, tablas acotadas y Sheet accesible de filtros. Filtro móvil consultó seis pendientes reales. Sin overflow de documento en tamaños comprobados. Capturas locales /tmp/billing-dashboard-desktop.png y /tmp/billing-mobile.png; no fixtures de negocio en PostgreSQL real.

## 32. Tests

68 pruebas pasan, 1.061 assertions: BillingRedesign(13), SalesDocument, SalesWorkflow, InvoiceActionPermissions, Expense, ExpensePaymentCancellation, ExpenseIssuerCorrection, ExpenseVatRounding, SupplierInvoiceDocument, Purchase, AcceptancePaymentWorkflow y SupplyRedesign. SQLite aislado. Pasan lint global, types, i18n(4.673 cadenas/5.212 entradas) y build. Build avisa chunks generales >500kB. Pruebas de carrera PostgreSQL, correo real y conformidad fiscal no ejecutadas. Migración aplicada al PostgreSQL local; lecturas visuales reales.

## 33. Git status

El checkout ya estaba sucio antes: 152 entradas de status, 84 archivos tracked modificados, 10.335 adiciones/7.227 eliminaciones. No se descarta ni mezcla como nueva autoría. Estado acumulado guardado en billing-redesign-git-status.txt, que incluye archivos propios de esta fase. Sin commit/push/merge/rebase/stash/reset/clean/checkout/restore.

## 34. Resumen git diff

La comparación propia se realiza contra snapshot al inicio, no contra HEAD acumulado. La fase contiene 23 archivos, incluyendo informe y captura de estado; la comparación antes del último test añadió 4.964 líneas y eliminó219. La última corrección tipa explícitamente la retención nula para la unión PostgreSQL de pendientes y añade una prueba de tesorería. El diff acumulado de Git incluye módulos previos y no es atribuible íntegramente a Facturación.

## 35. Limitaciones

No publicación en develop/producción. EUR solamente en la proyección de facturas de gastos; no aislamiento multiempresa universal, cuotas múltiples, necesidades contractuales, SIF ni factura estructurada. Históricos sin archivo, algunos editores legacy, cálculos float existentes y concurrencia simultánea pendientes. Estados vacíos consultados no se confunden con capacidad pendiente.

## 36. Deuda técnica

Todos los pendientes y aceptación en UI-BILL-01…25 de docs/roadmap.md: archivo histórico, decimal uniforme, parcial contractual/rectificación, anulación trazable, lotes/cuotas, multientidad/divisa, performance, editores auxiliares, pruebas concurrentes y motores fiscales. No se anticipan diseños de secciones todavía no proporcionados.

## Archivos propios de esta fase

- `app/Http/Controllers/BillingWorkspaceController.php`
- `app/Http/Controllers/ExpenseController.php`
- `app/Http/Controllers/SalesDocumentController.php`
- `app/Http/Controllers/SupplyWorkspaceController.php`
- `app/Models/SalesDocument.php`
- `app/Models/SalesDocumentPayment.php`
- `app/Models/SupplierPayment.php`
- `app/Services/Billing/BillingWorkspace.php`
- `app/Services/Documents/DocumentPdf.php`
- `app/Support/WorkspaceNavigation.php`
- `database/migrations/2026_10_11_110000_prepare_billing_integrity.php`
- `docs/roadmap.md`
- `resources/css/billing.css`
- `resources/js/components/billing/workspace.tsx`
- `resources/js/i18n/catalog.json`
- `resources/js/pages/billing/dashboard.tsx`
- `resources/js/pages/billing/invoice.tsx`
- `resources/js/pages/billing/invoices.tsx`
- `resources/js/pages/billing/payments.tsx`
- `routes/web.php`
- `tests/Feature/BillingRedesignTest.php`
- `docs/billing-redesign-review.md`
- `docs/billing-redesign-git-status.txt`

## Fuentes normativas oficiales consultadas

- [RD1619/2012, texto consolidado BOE](https://www.boe.es/buscar/act.php?id=BOE-A-2012-14696).
- [RD1007/2023, texto consolidado BOE](https://www.boe.es/buscar/act.php?id=BOE-A-2023-24840).
- [AEAT, SIF/VERI*FACTU](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu.html) y [nota sobre ampliación de plazo](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/nota-informativa-ampliacion-plazo-adaptacion-facturacion.html).
- [Hacienda, previsión informativa de modificación del calendario del05/10/2026](https://www.hacienda.gob.es/sgt/gabsehacienda/nota-informativa-verifactu.pdf): previsión, no sustituto de norma publicada.
- [AEAT, factura electrónica/SPFE y referencias RD238/2026/OrdenHAC1028/2026](https://sede.agenciatributaria.gob.es/Sede/iva/factura-electronica.html).

Commit: NO realizado
Push: NO realizado
Merge: NO realizado
