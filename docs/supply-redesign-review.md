# Almacén y compras — revisión de implantación local

Fecha: 8 de octubre de 2026. Referencia: especificación y diseño aportados por el usuario. Trabajo **local sin publicar**, sobre un checkout con cambios anteriores conservados. Los números, categorías e imágenes del mockup no se han convertido en registros de negocio. Véase el roadmap unificado, sección UI-SUP-01–20.

## 1. Rama actual

`ai/sidebar-navigation-redesign`. No se ha cambiado de rama ni realizado operaciones de integración.

## 2. Archivos modificados

El inventario de esta fase se incluye al final; distingue modificaciones respecto al estado inicial de este turno de archivos nuevos. El diff acumulado de Git incluye los rediseños anteriores y no representa únicamente Almacén y compras.

## 3. Modelos reales encontrados

Product, Unit, TaxRate, Warehouse, Supplier/SupplierContact/SupplierProduct, PurchaseDocument/PurchaseDocumentLine/PurchaseDocumentEvent, GoodsReceipt, StockMovement/StockMovementLine, StockBalance, StockReservation, InstallationMaterialRequirement, SerialNumber/SerialNumberEvent, Installation, WorkOrder, ReplenishmentPolicy y AuditLog. Se reutilizan sus identidades y relaciones; no se crean modelos paralelos Product, Inventory, Delivery o Project para imitar la imagen.

## 4. Arquitectura implementada

Antes: controladores, servicios y formularios de catálogo/inventario/compras existentes. Ahora: SupplyWorkspaceController ofrece proyecciones de lectura y páginas completas; MaterialRequirementController añade necesidades manuales sobre la entidad existente; MaterialTransportController/MaterialTransport registran entregas y retornos a través de StockLedger. StockPositionService continúa usando el libro de movimientos como fuente y StockBalance como posición de bloqueo. El shell y sus componentes compartidos se conservan. CSS nuevo limitado a supply-workspace. Contratos tipados de lectura, permisos explícitos e importes nulos sin permiso financiero. Pendiente: granularidad de preparación, ubicaciones y taxonomía según roadmap.

## 5. Dashboard

Antes: contadores y planificación dispersos. Ahora: padre `/supply` con seis indicadores reales, estado del stock, movimientos del periodo, tipos existentes, necesidades y últimos movimientos. Reutiliza ledger, políticas, pedidos y necesidades. Cero significa consulta realizada sin resultados; falta de autorización se presenta como tal. Pendiente: importes de consumo con valoración histórica, categorías y porcentajes comparativos donde no hay definición respaldada. Los gráficos de movimientos cuentan registros y no suman unidades heterogéneas.

## 6. Productos

Antes: catálogo y ProductDialog. Ahora: listado paginado de 15, búsqueda, tipo real, proveedor y estado; cantidades físicas, reservadas y disponibles; ficha completa con resumen, stock, proveedores y movimientos. Crear/editar reutiliza ProductDialog. Filtro y pestaña conservados al volver/paginar. Pendiente: imágenes, categorías de paneles/inversores/baterías, fabricante/EAN y variantes no modeladas. No se infiere categoría del nombre.

## 7. Inventario

Antes: posiciones y herramienta de gestión. Ahora: pestañas de existencias, necesidades, reservas, movimientos, recepciones, entregas, consumos y devoluciones con filtros y paginación. Se consulta sólo el conjunto elegido. Reutiliza posiciones y ledger. Pendiente: preparar físicamente y trasladar todos los formularios auxiliares históricos al nuevo diseño; enlazar una herramienta no equivale a rediseñarla.

## 8. Proveedores

Antes: Supplier, contactos, catálogo asociado e inteligencia/calidad existentes. Ahora: listado y ficha completa, contactos reales, productos, pedidos y facturas recientes con redacción financiera. Crear/editar reutiliza SupplierDialog. Pendiente: contactos y relaciones paginadas, migración visual de inteligencia/calidad y documentación general. No se fabrican contactos.

## 9. Compras

Antes: solicitudes, pedidos, aprobaciones, recepciones, devoluciones, cotejo, PDF y gestión fiscal. Ahora: listado de pedidos paginado y ficha completa con líneas, recepción, documentos y actividad. Se mantienen herramientas y enlaces antiguos bajo permisos. Cantidades pendientes se contabilizan como líneas de material, evitando sumar unidades diferentes. Pendiente: adaptar visualmente RFQ, facturas y procesos auxiliares; estados y fechas ampliados sólo si su backend existe.

## 10. Necesidades de material

Antes: InstallationMaterialRequirement de presupuestos/manuales y ProjectMaterialPlanner. Ahora: alta manual autorizada, validación de material/unidades y rechazo de duplicado; agrupación por instalación/producto sin multiplicar compromisos; requerido, reservado, consumido, disponible global y déficit. Reutiliza entidad y cálculos de stock reales. Pendiente: prioridad/fecha propia, origen visible, asignación fina a OT y cobertura por pedido. El disponible global se comparte; el déficit no descuenta pedidos pendientes ni constituye una reserva automática.

## 11. Reservas

Antes: StockReservation y asignadores comerciales/de proyectos/OT. Ahora: consultas paginadas, liberación bajo bloqueo, protección de disponibilidad y reubicación del compromiso de instalación al entregar. Se conserva el contexto de venta, planificación, OT y caducidad. No se consumen al reservar. Pendiente: preparación física independiente, permisos más granulares y política explícita para vencimientos. Formularios de alta existentes reutilizados.

## 12. Recepciones

Antes: GoodsReceipt y receive en PurchaseController. Ahora: recepción parcial desde ficha, cantidad pendiente por línea, series, almacén y albarán; clave estable y fingerprint de petición. La tabla técnica purchase_receipt_requests registra también intentos sólo con incidencias, evitando duplicar incidentes al repetir. Claves reutilizadas con payload diferente producen conflicto. Stock, recibido y documento se actualizan atómicamente. Se conserva backend de calidad/aprobación. Pendiente: fecha operativa editable, adjuntos propios y comprobación de concurrencia PostgreSQL simultánea aislada.

## 13. Entregas

Antes: transferencias genéricas. Ahora: movimiento delivery entre dos almacenes reales, instalación y receptor User opcional; dos líneas físicas compensadas; reservas propias trasladadas; series verificadas y receptor mostrado en historial. La entrega conserva stock y no registra instalación/consumo implícito. Pendiente: receptor obligatorio por regla acordada, equipos/vehículos/ubicaciones y albarán interno. No se crean almacenes de obra ficticios.

## 14. Consumos

Antes: WorkOrderMaterialConsumer sobre reserva de OT. Ahora: operación idempotente en formulario existente; repetir cantidad/series no consume dos veces, payload incompatible genera conflicto. Cantidad restante protegida; serie instalada mantiene instalación y origen de compra. Se reutiliza valoración existente con coste capturado al consumir; cambiar después el precio del catálogo no altera el movimiento ya registrado. Pendiente: política uniforme de valoración y formulario adaptado en la ficha nueva de OT (la herramienta histórica permanece disponible).

## 15. Devoluciones

Antes: devolución a proveedor y series contra compra. Ahora: internal_return exige entrega de origen, instalación/material/almacén coherentes, límite de lo entregado menos devoluciones y series de esa entrega. Daño entra en almacén real no utilizable, sin reserva utilizable. Retorno usable traslada el compromiso propio; puede liberarse explícitamente si ya no se necesita. Pendiente: decisión de calidad/liberación de cuarentena por material y prueba física/documental.

## 16. Movimientos

Antes: ledger con receipt/issue/transfer/adjustment/purchase_return. Ahora: delivery/internal_return aditivos, UUID/fingerprint, condición, destinatario y origen; historial con enlaces a compra/OT/instalación y unidades. Reutiliza StockLedger y serial events; movimientos no se editan para rehacer historia. Pendiente: ficha documental específica de cada movimiento, motivos normalizados y corrección/reversión guiada.

## 17. Almacenes y ubicaciones

Antes: Warehouse real con direcciones y stock. Ahora: is_usable por defecto verdadero, almacenes no utilizables para cuarentena y validación en asignadores/reservas/consumos/entregas. No puede cambiarse usabilidad si el almacén tiene movimientos o reservas. Pendiente: ubicaciones internas, vehículos/equipos y traslado autorizado de material tras revisión. No se infiere un almacén a partir de una instalación.

## 18. Números de serie

Antes: SerialNumber, SerialNumberEvent, compra origen, instalación y trazabilidad consultable. Ahora: enlace desde producto y validación de serie en entrega, consumo y devolución; una serie ajena a entrega original se rechaza. Reutiliza eventos existentes. Pendiente: integrar historial paginado directamente en ficha nueva y presentación de garantías/documentación, sin inventar fechas o coberturas.

## 19. Valoración de stock

Antes: unit_cost histórico en líneas y política de coste de consumo existente. Ahora: precios protegidos por view-finance y separación entre catálogo y costes históricos; no se inventa el importe de consumo del mockup. Pendiente: PMP/FIFO, coste de transporte, coste desconocido y valoración por fecha/almacén. No se declara implantado un sistema contable de valoración completo.

## 20. Integración con proyectos

Antes: ProjectMaterialPlanner/Allocator vinculados al proyecto operativo Installation. Ahora: necesidades, reservas, pedidos y movimientos utilizan esas relaciones; asignadores excluyen cuarentena. No se crea un segundo Project. Pendiente: cobertura/prioridad específica en nueva ficha de proyecto y preparación de kits con cantidad real.

## 21. Integración con instalaciones

Antes: requisitos, series instaladas, compras y consumos vinculados. Ahora: enlaces bidireccionales desde material/movimientos, filtro de instalación y trazas de entrega/retorno. Reutiliza Installation. Pendiente: panel documental/material ampliado en ficha de instalación y conversión de unidades cuando se defina.

## 22. Integración con órdenes de trabajo

Antes: StockReservation.work_order_id, WorkOrderKitAllocator y consumo operativo. Ahora: compromiso trasladado conserva OT, consumo idempotente y enlaces de historial. Reutiliza workflows/permisos de OT. Pendiente: preparación física y adaptación visual de auxiliares sin reabrir el rediseño de Operaciones.

## 23. Integración con facturación

Antes: PurchaseInvoiceMatcher, facturas derivadas y documentos fiscales. Ahora: documentos relacionados accesibles desde pedido con permisos, sin generar facturas al entregar/consumir. Reutiliza cotejo y reglas existentes. Pendiente: migración visual completa de facturas de proveedor y trazas documentales nuevas.

## 24. Integración con contabilidad

Antes: contabilización y cuentas existentes. Ahora: asignaciones del editor preservadas y campos financieros protegidos. No se añaden asientos automáticos por movimientos físicos. Pendiente: decisión sobre asientos de inventario y valoración, integración fiscal aprobada y conciliación del ledger con contabilidad.

## 25. Documentos y actividad

Antes: PDF de compra, PurchaseDocumentEvent, archivos y AuditLog. Ahora: pestaña de documentos reales relacionados y eventos de pedido; observadores auditables de producto, requisito, reserva y movimiento. Reutiliza PDF existente y no habilita envío de correos automáticos en las comprobaciones. Pendiente: adjuntos logísticos propios, actividad general de proveedor/producto y paginación de historiales extensos.

## 26. Permisos

Antes: gates de catálogo, compras, recepción y finanzas. Ahora: puertas existentes, importe/coste nulo sin permiso financiero en nuevas vistas; editor financiero no se serializa para técnicos. Herramientas financieras históricas requieren view-finance. Gestión de inventario oculta coste de producto/línea sin ese permiso. Reutiliza roles reales, sin conceder accesos nuevos. Pendiente: matriz granular de preparar/entregar/devolver y revisión completa de herramientas históricas ajenas al nuevo DTO.

## 27. Performance

Antes: vistas administrativas con catálogos/historiales amplios. Ahora: listados de 15, sólo pestaña activa, eager loading y agregaciones por lotes para necesidades; stock leído en backend. Pendiente: selectores remotos, paginación SQL de posiciones, agregación mensual SQL y relaciones/contactos paginados. No se afirma probado a gran escala.

## 28. Responsive

Antes: shell común responsive. Ahora: KPI/chart grids adaptativos; filtros apilados; filas como tarjetas en móvil; tabs desplazables y fichas completas. Comprobaciones reales en 390×844 y 820×1180, y escritorio con sidebar canónico. Móvil proveedores sin scroll horizontal (390/390); páginas de productos y ficha de proveedor inspeccionadas sin crear datos. Se corrige contraste local del contenido ante preferencia oscura. Pendiente: evidencia con un catálogo/pedidos reales poblados y datos largos representativos; no se introducen fixtures en la base de usuario.

## 29. Tests ejecutados

Backend: inventario, recepción parcial, reintentos incompatibles, stock reservado, entregas y devolución, cuarentena, series, consumo idempotente, coste histórico conservado, agrupación de necesidades, permisos; regresión compras, torre de suministro, analítica/planificación, OT, operaciones, ventas y recuentos. Frontend: cuatro suites existentes de navegación/contexto/formatos. Typecheck, lint, catálogo bilingüe, formato dirigido y build. Resultados finales y límites de concurrencia se recogen al cierre de este documento. Tests con SQLite en memoria; no se crean datos de negocio en PostgreSQL local para verificar pantallas.

## 30. Git status

Snapshot final: **152 entradas** (120 previas); archivo de estado adjunto con todas las rutas.

Estado inicial: 120 entradas, 64 archivos tracked modificados; se preservaron. No se hizo stash/reset/clean/checkout/restore. Snapshot final separado en `supply-redesign-git-status.txt`. `.env` es un enlace local ignorado a la configuración del checkout principal; no se incorpora ni se muestra su contenido.

## 31. Resumen Git diff

Diff tracked acumulado final: **84 archivos, 10.335 inserciones / 7.227 eliminaciones**. Incluye trabajo previo; no incluye archivos nuevos sin seguimiento.

Antes de esta fase: 9.616 inserciones/7.168 eliminaciones en 64 archivos tracked; no imputables a esta petición. Inventario incremental respecto a hashes iniciales al final. CSS global, sidebar compartido y páginas de Inicio, Comercial, CRM, Clientes, Presupuestos y Libro Diario mantienen los hashes iniciales. Diff final acumulado se identifica separadamente.

## 32. Limitaciones reales

No se declara finalizado el ciclo físico de preparación ni la valoración contable. Faltan taxonomía/imágenes, ubicaciones/vehículos/equipos, multientidad universal, documentos internos propios y rendimiento a gran escala. La recepción/entrega/consumo/retorno implantados sí tienen backend; las herramientas existentes enlazadas conservan su diseño anterior. Los valores de la referencia no se simulan. La prueba simultánea de dos conexiones PostgreSQL permanece pendiente, aunque el código usa transacciones, bloqueos ordenados e índices únicos.

## 33. Deuda técnica

Roadmap UI-SUP-01–20 con estado, dependencias y aceptación. Prioridad: pruebas PostgreSQL de concurrencia aisladas; preparación por cantidades; valoración acordada; selectores/paginación a gran escala; migración visual de auxiliares y documentación logística; taxonomía y ubicaciones. Continuidad fiscal conservada; la ausencia de company_id universal impide prometer aislamiento multiempresa completo.

## Cierre de verificación

- Backend: **69 tests, 1.644 aserciones, todos pasan** (13 archivos de pruebas).
- Frontend: cuatro suites, 63 comprobaciones de navegación/contexto/formato, todas pasan.
- TypeScript, ESLint y catálogo bilingüe pasan: 4.562 cadenas visibles / 5.096 entradas.
- Build de producción correcto (17,32 s); aviso de chunks >500 KB y aviso de tiempo de plugins, sin fallo. Gran escala pendiente en UI-SUP-17.
- PostgreSQL local: migración aditiva aplicada; la comprobación simultánea con dos conexiones no se ha ejecutado.
- Preservación: 12 archivos canónicos auditados contra hashes iniciales; CSS global y componentes de shell/sidebar y pantallas anteriores conservados.

### Inventario incremental de archivos

Modificados respecto al comienzo de esta fase (29):

- `app/Http/Controllers/InventoryController.php`
- `app/Http/Controllers/PurchaseController.php`
- `app/Http/Controllers/WorkOrderMaterialsController.php`
- `app/Models/GoodsReceipt.php`
- `app/Models/StockMovement.php`
- `app/Models/Warehouse.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/Inventory/ProjectMaterialAllocator.php`
- `app/Services/Inventory/ProjectMaterialPlanner.php`
- `app/Services/Inventory/SalesStockAllocator.php`
- `app/Services/Inventory/StockLedger.php`
- `app/Services/Inventory/StockPositionService.php`
- `app/Services/Inventory/WorkOrderKitAllocator.php`
- `app/Services/Inventory/WorkOrderMaterialConsumer.php`
- `app/Services/Supply/CriticalMaterialRisk.php`
- `app/Services/Supply/ReplenishmentPlanner.php`
- `app/Support/WorkspaceNavigation.php`
- `docs/roadmap.md`
- `lang/en.json`
- `lang/es.json`
- `resources/js/i18n/catalog.json`
- `resources/js/pages/catalog/index.tsx`
- `resources/js/pages/inventory/index.tsx`
- `resources/js/pages/purchases/index.tsx`
- `resources/js/pages/purchases/show.tsx`
- `resources/js/pages/work-orders/index.tsx`
- `routes/web.php`
- `tests/Feature/InventoryTest.php`
- `tests/Feature/WorkspaceNavigationAuthorizationTest.php`

Nuevos en esta fase (21):

- `app/Http/Controllers/MaterialRequirementController.php`
- `app/Http/Controllers/MaterialTransportController.php`
- `app/Http/Controllers/SupplyWorkspaceController.php`
- `app/Services/Inventory/MaterialTransport.php`
- `app/Services/Inventory/SupplyOperationFingerprint.php`
- `database/migrations/2026_10_08_230000_add_supply_transport_and_receipt_idempotency.php`
- `docs/supply-redesign-review.md`
- `docs/supply-redesign-git-status.txt`
- `resources/css/supply.css`
- `resources/js/components/supply/requirement-dialog.tsx`
- `resources/js/components/supply/workspace.tsx`
- `resources/js/pages/supply/dashboard.tsx`
- `resources/js/pages/supply/inventory.tsx`
- `resources/js/pages/supply/order.tsx`
- `resources/js/pages/supply/product.tsx`
- `resources/js/pages/supply/products.tsx`
- `resources/js/pages/supply/purchases.tsx`
- `resources/js/pages/supply/supplier.tsx`
- `resources/js/pages/supply/suppliers.tsx`
- `resources/js/pages/supply/transport.tsx`
- `tests/Feature/SupplyRedesignTest.php`

Estimación incremental por comparación de contenidos: 4853 líneas añadidas / 64 eliminadas, incluyendo documentación de esta fase (antes de este propio cierre). No equivale al diff acumulado contra HEAD.

Commit: NO realizado
Push: NO realizado
Merge: NO realizado
