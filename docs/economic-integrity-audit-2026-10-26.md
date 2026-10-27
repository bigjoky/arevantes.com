# Auditoría económica y cierre técnico temporal — 26/10/2026

## Alcance y conclusión

Trabajo exclusivamente local sobre el ERP existente, rama `fix/work-order-layout`, base Git `e4484989e160c548eac0ae50120d08a8ef640962`, con cambios anteriores y concurrentes. No se ha hecho commit, push, merge, auditoría, migración ni publicación en producción. No se ha desarrollado Nexo ni cambiado la identidad corporativa. Las nuevas pruebas utilizan datos sintéticos y bases desechables; no borran datos de la instancia de demostración.

**Temporal: APTO PARA PREPARAR DESPLIEGUE**, condicionado al procedimiento y puertas del incremento TIME-02. **Económico: NO APTO PARA DESPLIEGUE** mientras permanezcan sin resolver los riesgos de documentos históricos y doble fuente de facturas de proveedor, las condiciones de migración y la revisión del corte integrado. Esta valoración no significa que se hayan encontrado duplicados reales en producción: producción no se ha inspeccionado.

## A. Cierre temporal

El desarrollo local de TIME-02 queda concluido. Las 62 huellas del manifiesto `time-stabilization-02-verification.json` coinciden con el código al cerrar esta auditoría. No se ha ampliado el alcance temporal. Se conservan las pruebas y resultados originales: 963 aprobadas/15 omitidas, 50 frontend, PostgreSQL de módulos afectados 151 aprobadas, concurrencia 4 aprobadas y repetición final temporal/migraciones 20 aprobadas (subconjunto, no suma de casos únicos).

Siguen pendientes autorización de auditoría de producción, corte revisado, staging con volumen, respaldo restaurable, auditoría strict sin incidencias, diez triggers activos y despliegue coordinado de todos los escritores. No pueden convivir escritores antiguos y nuevos. La recuperación exige código compatible con UTC y conservación de columnas/evidencia. El P0 temporal no está publicado ni cerrado en producción.

## B. Mapa real y arquitectura

| Capacidad | Implementación real | Verificación / límites |
| --- | --- | --- |
| Presupuesto aceptado y anticipos | `AcceptancePaymentManager`, `QuoteBillingPlan`, borradores con `collect_before_invoice`; relación con presupuesto y proyecto existentes | Pruebas de aceptación, cobro previo y planes. Aceptar o cobrar no emite automáticamente una factura fiscal |
| Creación documental | `SalesDocumentController`, `SalesDocumentConversion`: directa, desde presupuesto, pedido, albarán y duplicación | Pruebas de reintento, capacidad parcial, aceptación vigente y concurrencia. UUID de intención añadido a las pantallas existentes |
| Emisión y series | Emisión expresa, `DocumentNumber`, ejercicio/serie, bloqueo del contador, número único; archivo PDF y huella | Emisiones simultáneas, repetición de emisión, rollback y formulario de serie obsoleto comprobados |
| Rectificativas y correcciones | Preparación de abono desde factura y emisión expresa. `IssuedInvoiceCorrection` exige administrador, motivo, revisión, ejercicio abierto y protecciones fiscales; conserva originales y asientos de corrección | Pruebas de correcciones existentes y preparación concurrente de abono. No se amplía a un nuevo procedimiento fiscal |
| Cobros de clientes | Parciales/totales, clave opcional de idempotencia, origen bancario, saldo neto de abonos; `OutstandingBalanceCalculator` | Pruebas HTTP, permisos, acumulación y dos conexiones PG. `paid_amount` conserva solo cobro real; un abono puede liquidar saldo sin crear recibo |
| Pagos de proveedores/gastos | Pagos parciales, cuenta de financiación, idempotencia; facturas pendientes de revisión bloqueadas | Pruebas de pago/reintento y carrera con contabilización. Cancelación administrativa de gasto existente con motivo, registro anulado y reversión |
| Cancelación de cobros | Borrado de recibo no contabilizado ni conciliado, con permiso de tesorería | Protegido bajo bloqueo. No existe procedimiento equivalente de anulación trazable de cobros contabilizados de clientes |
| Impagos y vencimientos | Estado vencido y consultas de saldo; tarea diaria sobre facturas emitidas | Código inspeccionado y regresiones de facturación. El estado parcial y la dimensión vencimiento no se sustituyen por una nueva máquina de estados |
| Bancos y conciliación | Importación CSV/N43, huella/ocurrencia, restricciones de conciliación, cobro/pago desde movimiento y reglas contables con reversión | Pruebas bancarias y migración de conciliación. No se afirma integración bancaria en vivo ni entrega externa exactamente una vez |
| Contabilidad existente | `AccountingSynchronizer`, `JournalPoster`, origen/evento, partidas equilibradas, ejercicio/periodo abierto, plantillas recurrentes | Regresiones y carreras. Se reutilizan estas garantías; no se añade un segundo núcleo contable |
| Relaciones | Cliente, presupuesto, documento padre/líneas de origen y `installation_id`; proyecto/órdenes siguen sus modelos existentes | La orden operativa no es una factura; aceptar, cobrar, emitir y completar una instalación son hechos diferentes |
| Exportaciones e informes | CSV económico en `FinanceController`, diario/exportaciones y consultas fiscales existentes | Inventario por código y pruebas existentes; no se certifica cada formato de exportación ni presentación fiscal externa |
| Automatismos | Sincronización contable y recurrencias cada cinco minutos; vencimientos diarios; entregas con estado pendiente/enviado/fallido | Revisados comandos, servicios y programación. Correo externo sin outbox transaccional: un fallo de respuesta no prueba no entrega |

Flujo conservado: presupuesto aceptado → borrador/hito → registro del cobro cuando corresponda → emisión expresa con número definitivo y archivo → asiento existente. Banco puede conciliar un recibo ya registrado o crear el efecto vinculado al movimiento. Un abono emitido reduce la deuda; no equivale a entrada de caja.

## C. Incidencias reproducidas y correcciones

| ID / severidad | Defecto y reproducción | Corrección y evidencia |
| --- | --- | --- |
| ECO-01 P0 | Repetir una creación/duplicación o hito podía crear otro documento. Prueba previa crea dos borradores para una intención | `SalesDocumentCreation`: UUID, huella de operación/actor/datos canónicos, bloqueo advisory transaccional PG y unicidad. Repetición devuelve el mismo documento; otro contenido con la misma clave se rechaza. Una intención nueva puede tener el mismo importe |
| ECO-02 P0 | Facturar directamente un pedido y simultáneamente su albarán podía consumir dos veces la misma capacidad; reproducido con dos conexiones | Bloqueo de la raíz común antes del documento origen. Corrección de factura comparte ese orden. Prueba fuerza ambas lecturas previas y verifica una única factura/cantidad |
| ECO-03 P0 | Clave de cobro/pago reutilizada simultáneamente en documentos distintos podía terminar en excepción 500; contabilización y borrado podían dejar asiento sin recibo | Unicidad sigue en BD; conflicto tras rollback se convierte en validación. Bloqueos padre→recibo→ejercicio compartidos por registro, eliminación y contabilización. Carreras verifican un efecto y ausencia de asiento huérfano |
| ECO-04 P1 | Usuario de operaciones podía borrar recibo no contabilizado sin tesorería | Permiso explícito; comprobación de contabilización/conciliación dentro de la transacción. Reproducción previa 302, ahora 403 |
| ECO-05 P1 | Emitir un abono dejaba la factura original como pendiente aunque su saldo ya fuera cero | Recálculo común de cobro real y saldo neto; emisión y conciliación reutilizan el calculador. Abono total deja cero recibos y `paid_amount=0`, con saldo liquidado |
| ECO-06 P1 | Fallo tras guardar el PDF dejaba un archivo huérfano aunque BD/contador retrocedieran; archivo preexistente alterado podía reutilizarse | Limpieza del archivo nuevo al fallar, sin borrar archivos referenciados; comprobación SHA256 antes de reutilizar evidencia existente. Pruebas de fallo de render, posterior a archivo y archivo alterado |
| ECO-07 P1 | Validadores de identidad de factura de proveedor/gasto no impedían duplicados desde escrituras concurrentes o fuera del controlador | Dos índices únicos parciales que reproducen las reglas actuales. Migración atómica; duplicados históricos la bloquean, sin limpieza ni cambios de documentos |
| ECO-08 P1 | Aceptación leída antes del bloqueo podía quedar obsoleta al crear documento | Revalidación dentro del bloqueo del presupuesto; prueba retira aceptación entre ambas lecturas |
| ECO-09 P1 | Importe IVA incluido positivo y descuento del 100% podía producir descuento negativo | Rechazo de esa combinación contradictoria. Se conservan precios de tres decimales y redondeo por línea; casos de cero y descuento ordinario verificados |
| ECO-10 P1 | Guardar un formulario antiguo de serie tras emitir podía retroceder el contador o cambiar el prefijo ya usado | Actualización bloquea ejercicio→serie y revalida estado/contador/prefijo sobre datos actuales. Prueba emite entre lectura y guardado del formulario |

No se modifican porcentajes fiscales, numeración histórica, documentos emitidos ni saldos históricos mediante backfill. Las correcciones de estados se aplican al ejecutar las operaciones correspondientes; esta intervención no recalcula masivamente producción.

### Garantías y migraciones

- `2026_10_26_090000_preserve_sales_creation_idempotency`: UUID nullable único y huella en `sales_documents`; filas anteriores quedan sin clave. La reversión se niega si eliminaría evidencia de idempotencia; bloqueo de tabla PG antes de comprobarlo.
- `2026_10_26_091000_enforce_existing_supplier_invoice_identity`: unicidad proveedor/tipo/número externo en compras y país/NIF normalizado/número en gastos. No añade año ni cambia la regla de validación existente. Si hay duplicados, falla toda la migración sin índice parcial ni modificación de filas.
- Se mantienen número único de documento, claves únicas de recibos, origen bancario, unicidad de conciliación y deduplicación de asientos. Los orígenes polimórficos contables requieren también los bloqueos de aplicación corregidos.
- Orden de bloqueos: clave de creación → presupuesto/raíz de capacidad → documento → recibo → ejercicio/serie según operación. No se añade bloqueo fiscal redundante: `JournalPoster` ya comprueba el periodo dentro de su garantía transaccional.
- Ambas migraciones nuevas aplicadas únicamente a la base local de demostración y a bases desechables de prueba. Ninguna aplicada a producción.

## D. Verificación

| Control final | Resultado exacto |
| --- | --- |
| Suite completa combinada, SQLite aislado | **1.008 casos: 982 aprobados, 26 omitidos, 0 fallos; 14.745 aserciones** |
| PostgreSQL económico: núcleo | **87 casos: 86 aprobados, 1 fallo preexistente; 1.136 aserciones** |
| PostgreSQL económico: módulos relacionados | **66 aprobados, 0 fallos; 1.209 aserciones** |
| PostgreSQL cálculo decimal | **3 aprobados, 0 fallos; 7 aserciones** |
| PostgreSQL concurrencia, dos procesos | **11 aprobados, 0 fallos; 69 aserciones** |
| Frontend | **56 aprobados, 0 omitidos, 0 fallos** |
| TypeScript | Aprobado después de generar las rutas auxiliares del frontend |
| ESLint global | Aprobado después de generar las rutas auxiliares; sin modificaciones automáticas de archivos ajenos |
| Build | Aprobado; advertencia de tamaño de fragmentos, sin error |
| Formato PHP de los 20 archivos afectados y diff | Aprobados |
| Traducciones globales | **NO aprobado: 211 cadenas detectadas** en el árbol integrado actual. No se reutiliza el anterior recuento temporal de 79 |
| Integridad de TIME-02 | **62/62 huellas coincidentes** |

Los tres grupos económicos PostgreSQL suman **156 casos: 155 aprobados y 1 fallo**, con 2.352 aserciones; los 11 de concurrencia se informan por separado. No sumar estos resultados a los de SQLite como casos funcionales únicos. Los 26 omitidos de la suite completa incluyen pruebas que exigen bases PostgreSQL específicas; no se declaran aprobados por su omisión. Las 11 carreras económicas sí se ejecutaron expresamente en PostgreSQL. No se reejecutaron todas las carreras temporales/otros dominios en esta fase: conservan su evidencia anterior.

Fallo PostgreSQL abierto: `AccountingTest::test_arevia_finds_specific_crm_records_and_hides_finance_from_technicians`, búsqueda de `FAC-AREVIA-77`. Confirmado sobre la base anterior a las correcciones; no se deshabilita ni cambia la expectativa para ocultarlo.

Cobertura nueva: diez casos de integridad HTTP/archivos/permisos, tres de identidad/migración, tres de cálculo y once carreras. Las carreras incluyen número simultáneo, doble emisión, cobros que excederían el saldo, claves repetidas en igual/distinto documento, duplicación documental, preparación concurrente de abono, capacidad compartida pedido/albarán y contabilización frente a borrado de recibos de clientes/proveedores. Las dos pruebas de proveedor fallaron inicialmente por una fixture sin proveedor obligatorio; corregida la fixture, las once pasan. Se conserva la distinción entre fallo de preparación de prueba y defecto del producto.

Resultados y huellas reproducibles del código afectado: `economic-integrity-verification-2026-10-26.json`.

Metodología: copia congelada del código integrado, sin `.env` ni bases de negocio, dependencias propias PHP y datos sintéticos. PostgreSQL local 18.6 en bases desechables `arevantes_economic_integrity_test` y `arevantes_economic_concurrency_test`. Las pruebas concurrentes comprueban nombre de base y motor antes de recrear su propia base. Dos procesos/conexiones reales, barreras y observación de esperas de bloqueo; no simulaciones SQLite para las carreras.

Se conservaron las reproducciones fallidas anteriores a las correcciones. Dos comparaciones estrictas de snapshots en `SalesDocumentTest` ordenan recursivamente claves de objetos para no confundir el orden JSONB de PostgreSQL con una modificación de valores o tipos.

Se repitió la suite completa después de construir los recursos: una ejecución intermedia coincidió con la sustitución de archivos de build y produjo cuatro fallos de fuente CSS ausente. Se registra como ejecución no válida de infraestructura; no se atribuye a un defecto corregido del ERP. El primer intento de repetición también falló al faltar archivos de la copia temporal; se restauró desde el código, sin tocar la aplicación ni ocultar esos intentos.

La comprobación es automatizada. No se presenta como prueba física móvil/PWA ni como validación de producción. No se ha rediseñado la interfaz.

## E. Riesgos y decisiones pendientes

| Riesgo | Estado / decisión necesaria |
| --- | --- |
| Histórico emitido sin PDF archivado o sin identidad emisora congelada | `DocumentPdf` devuelve el archivo íntegro cuando existe ruta; si no la hay, conserva una ruta histórica de reconstrucción con datos/identidad disponibles y marca actual. Riesgo observado por código, volumen real desconocido. Requiere inventario autorizado y decidir recuperación de originales o bloqueo/identificación explícita de reconstrucciones. No se ha regenerado ni alterado el histórico |
| Misma factura en compras y gastos, o proveedores duplicados por NIF | Las restricciones nuevas protegen cada entidad según sus reglas actuales. No establecen identidad fiscal global entre ambas fuentes. Ambas pueden originar asientos distintos. Requiere decidir fuente canónica y relación de espejo/exclusión, más revisión autorizada; no fusionar ni borrar automáticamente |
| Anulación de cobro contabilizado de cliente | El borrado está bloqueado. Falta procedimiento equivalente al existente para pagos de gastos. Una reversión manual aislada no actualiza por sí sola el recibo/saldo. Definir procedimiento con actor, motivo, reversión y conciliación antes de ampliarlo |
| Clientes API antiguos | Las claves nuevas son opcionales por compatibilidad. Sin clave no se puede distinguir un reintento de una segunda intención legítima. Pantallas de creación actualizadas; antes de publicar hay que inventariar y coordinar otros escritores |
| Duplicados preexistentes e índices | Pueden bloquear la migración. Hacer preflight de solo lectura tras autorización; revisión individual y decisión documental, sin limpieza automática |
| Entregas externas | Correo/servicios externos no comparten transacción con PostgreSQL. No se garantiza exactamente una entrega; nuevo outbox fuera de este incremento |
| Búsqueda Arevia en PostgreSQL | Fallo preexistente reproducido también sobre la base anterior: no encuentra `FAC-AREVIA-77`. Afecta recuperación de contexto, no escritura económica. P2 separado, no se declara aprobado |
| Traducciones e integración concurrente | Deuda global reflejada en resultados. No se ha cambiado identidad ni descartado trabajo de móvil/PWA/web. El corte final necesita revisión específica; una suite de copia congelada no certifica cambios posteriores |

## F–H. Seguimiento, siguiente incremento y publicación

`docs/roadmap.md` registra TIME-02 concluido localmente, ECO-01…10 corregidos/verificados localmente y los riesgos abiertos con criterio de aceptación. El siguiente incremento recomendado es **identidad documental y custodia de originales**: decidir compras frente a gastos, asegurar exclusión de doble registro, definir actuación sobre originales históricos ausentes y preparar el preflight sin modificaciones. Después, anulación trazable de cobros de clientes. Nexo, contabilidad futura, nuevos módulos y rediseño permanecen fuera del alcance.

No ejecutar el despliegue económico todavía. Para prepararlo: resolver decisiones anteriores, revisar escritores y duplicados, cerrar el fallo PG/deuda aceptada que corresponda, fijar un corte de cambios autorizado, repetir verificaciones sobre ese corte y ensayar migración/recuperación en staging. Un rollback no debe eliminar claves ya usadas, PDFs ni evidencia contable; conservar esquema aditivo y usar código compatible. La auditoría o publicación de producción exige autorización posterior expresa.
