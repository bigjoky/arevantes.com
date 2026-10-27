# ECO-02 — mapa previo y decisiones de arquitectura

Estado: mapa previo conservado; implementación local verificada según `eco-02-identity-custody-2026-10-26.md`. Sin acciones en producción. Este mapa se escribe antes de modificar la estructura.

## Dependencias encontradas

- `PurchaseDocument` representa pedidos, albaranes y también facturas/abonos recibidos. Las facturas tienen líneas, proveedor congelado en `supplier_snapshot`, origen, proyecto (`installation_id`), orden, pagos y clasificación fiscal. Un pedido no es otra obligación de pago por su importe.
- `Expense` representa otra obligación económica con identidad fiscal propia, categoría y líneas fiscales, adjuntos, proyecto y pagos. No es actualmente un simple desglose de una factura de compras.
- `SupplierPayment` apunta a compra o gasto; conciliación enlaza ese pago con movimiento de banco. Conserva origen bancario y clave de reintento. Cambiar el origen retrospectivamente afectaría a conciliación y cuentas.
- `AccountingSynchronizer` publica documentos de compra y gastos por separado; `JournalPoster` deduplica por origen/evento, no por identidad fiscal entre módulos. El mismo papel puede producir dos orígenes diferentes.
- `CompanyProfile` es la empresa emisora/receptora configurada; el ERP no ofrece actualmente un selector multiempresa autorizado. No debe aceptarse otro receptor desde un formulario sin comprobar contexto.
- Las restricciones ECO-01 protegen proveedor/tipo/número dentro de compras y país/NIF/número dentro de gastos. No comparten espacio de identidad. NIF duplicado entre proveedores evade la primera.
- `SalesDocument` archiva PDF, SHA-256, identidad emisora y destinatario. Emisión y corrección administrativa usan transacción y compensación de archivos. `DocumentPdf` verifica el archivo si tiene ruta, pero reconstruye si no hay ruta: ese es el defecto de custodia.
- `AuditLog` y `AuditableObserver` son la auditoría existente. No se creará otra bitácora.

## Selección

Conservar el propietario económico existente: una factura vive en compras **o** gastos. Las relaciones operativas de ese propietario permiten varios procesos/líneas/pagos sin otra obligación. No crear un gasto espejo con importe independiente para clasificar una compra. Un intento duplicado debe señalar el propietario existente.

Se justifica un registro compartido **de identidad**, sin importes, vencimientos, pagos ni asientos: una restricción única ordinaria no puede abarcar dos tablas. Convertir automáticamente todos los gastos en `PurchaseDocument` exigiría migrar históricos, cambiar referencias polimórficas contables y mover pagos/conciliaciones, expresamente fuera del alcance. El registro compartido debe reservar la clave fiscal para un único propietario y congelar su receptor. No es un segundo sistema de facturación.

Clave seleccionada: receptor canónico `CompanyProfile` (contexto operativo único) + país/NIF normalizado del proveedor + clase factura/abono + número externo original (normalización conservadora de espacios extremos; no quitar puntuación significativa ni añadir ejercicio artificial). Conservar valores originales. Un proveedor duplicado por NIF no crea otro emisor fiscal. Sin datos suficientes no afirmar identidad verificada; impedir efectos económicos nuevos hasta revisión según las garantías implantadas. No inventar NIF ni inferir receptor histórico si existe ambigüedad.

El histórico se diagnostica en solo lectura, sin rellenar, fusionar ni vincular automáticamente. Una resolución real exige actor, motivo, evidencia y decisión autorizada; pagos/asientos/originales permanecen conservados.

## Custodia

La recuperación ordinaria de una factura emitida solo puede devolver bytes archivados con huella válida. Ruta/archivo ausentes o hash alterado deben bloquearse y mostrarse como revisión pendiente. La generación definitiva pertenece a emisión/corrección autorizada y no puede ser activada mediante parámetro HTTP de descarga. Una reconstrucción informativa, si se habilita, será explícita y marcada; nunca un original ni un archivo sustitutivo. SHA-256 verifica integridad, no autenticidad fiscal ni firma electrónica.

El directorio existente `FiscalEntity` conserva su función de tercero fiscal; no representa una factura y no puede sustituir la exclusión documental entre compras/gastos. Se mantiene la misma normalización de NIF de `FiscalEntity::taxKey`. El registro nuevo conserva claves documentales, no otro directorio ni importes.
