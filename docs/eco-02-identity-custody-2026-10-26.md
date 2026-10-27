# ECO-02 — identidad compartida y custodia documental

Fecha: 26/10/2026. Estado: desarrollo y verificaciones locales; sin publicación. Rama de trabajo `fix/work-order-layout`, base `e4484989e160c548eac0ae50120d08a8ef640962`, con trabajo previo/concurrente conservado. No se ha hecho commit, push, merge, reset ni auditoría/migración de producción. No se han fusionado, eliminado, recalculado ni regenerado registros históricos.

## A. Arquitectura encontrada

El mapa previo a modificar estructura está en [eco-02-architecture.md](eco-02-architecture.md).

`PurchaseDocument` representa varios hechos: pedido, recepción documental, factura recibida y abono. Solo los documentos económicos correspondientes originan obligación/asiento. Conserva proveedor congelado, líneas, origen, proyecto, orden y pagos. `Expense` es otra obligación, no un desglose neutro de una factura de compra. Tiene categoría, líneas fiscales, adjuntos y pagos. `SupplierPayment` pertenece a uno de esos orígenes; `BankReconciliation` enlaza el pago con el movimiento. `AccountingSynchronizer` y `JournalPoster` ya realizan contabilización y deduplicación por origen/evento.

Existe además `FiscalEntity`: directorio de terceros por país/NIF, usado por `ExpenseCreditorRegistry`. No representa una factura ni una obligación. Se conserva; la normalización del nuevo índice reproduce `FiscalEntity::taxKey` y los validadores existentes (mayúsculas y retirada de espacios, guiones y puntos del NIF). No se crea otro directorio de proveedores ni otro núcleo contable.

El defecto era que deduplicar por origen/evento no detecta que una compra y un gasto distintos representan la misma factura. La consulta comercial de una compra no convierte automáticamente su importe en un gasto adicional.

## B. Solución y límites de la identidad canónica

Se conserva **un único propietario económico existente**, compra o gasto. Se añade `received_invoice_identities`, un registro de claves sin importes, vencimientos, caja, pagos ni asientos propios. Una restricción única ordinaria no puede abarcar dos tablas; convertir todos los gastos en compras habría obligado a mover históricos, pagos, conciliaciones y referencias polimórficas. El registro resuelve la exclusión entre tablas sin realizar esas migraciones.

Clave única: **empresa receptora (`CompanyProfile` canónico) + país del emisor + NIF normalizado + clase factura/abono + número externo**. El número conserva mayúsculas/minúsculas y puntuación significativa; solo se recortan extremos. No se añade ejercicio: la regla anterior ya era de número por emisor sin año. Los datos originales permanecen en el documento; la clave normalizada no los sustituye. Se reconoce el legado `Spain`/`España` como `ES`; el país canónico de registro debe estar expresado de forma compatible con el código de país. No se deduce identidad por nombre, importe, fecha o concepto. No se eliminan prefijos fiscales de país ni se adivinan identidades incompletas.

Los dos orígenes comparten una vista de candidatos. Al crear un documento se comprueba también el histórico no registrado en la nueva tabla. Un índice único global y triggers PostgreSQL garantizan exclusión bajo concurrencia. Dos proveedores con el mismo NIF/país comparten clave. Dos números distintos con el mismo importe siguen siendo facturas distintas.

El ERP sigue ofreciendo **un único contexto de empresa operativa**, no un nuevo módulo multiempresa. La clave permite distinguir receptores distintos y las pruebas lo verifican; registrar efectos de otra empresa desde la sesión operativa se rechaza. El cliente HTTP no puede seleccionar arbitrariamente `receiver_company_id` mediante los formularios actuales. Un receptor histórico no identificado con varias empresas configuradas requiere revisión; no se reasigna automáticamente. Los perfiles no deben duplicarse para representar la misma empresa ni reutilizarse para cambiar de titular: esa gestión no se ha convertido en una nueva funcionalidad multiempresa.

Una factura mantiene sus líneas, proyecto/orden y varios pagos/conciliaciones en su propietario. Para usarla en otro proceso se conserva esa referencia: no se crea un gasto espejo con otra obligación. Un segundo registro fiscal coincidente se rechaza con validación, incluso si llega desde otro módulo.

## C–D. Incidencias, cambios y garantías

| Incidencia | Severidad | Corrección local |
| --- | --- | --- |
| Compra y gasto de la misma factura podían tener dos obligaciones/asientos | P0 | Registro compartido, exclusión única, triggers y comprobación del histórico; carreras con dos conexiones verificadas |
| Proveedores duplicados por NIF evadían la unicidad por `supplier_id` | P0 | La identidad usa NIF/país del documento, no el identificador comercial del proveedor |
| Reintento o escritura concurrente podía crear otro propietario | P0 | Un único commit; rollback del segundo documento y sus efectos. Se conservan las claves de reintento de ECO-01 |
| Emitido sin ruta de archivo se reconstruía como una descarga ordinaria | P0 | Recuperación exige archivo con SHA-256 válido, también si se pasa `final=true`; ausencia o alteración bloquea la descarga |
| Corrección podía liberar una identidad anterior para otro registro | P1 | Alias de identidad conservados. Corrección de gasto sigue exigiendo administrador, motivo, revisión y periodos abiertos; reserva identidad anterior/nueva sin borrar evidencia |
| Receptor ajeno o histórico contradictorio podía recibir efectos económicos | P1 | Guardas en servicios y PostgreSQL para pagos, asientos y conciliación; otra empresa no es operable desde el contexto actual |
| Borrado de gasto podía tocar adjuntos antes de fallar por referencias | P1 | Comprobación bajo bloqueo, rechazo de identidad reservada y eliminación de archivos solo tras aceptación de BD; prueba conserva el original ante rechazo |
| Falta de diagnóstico unificado | P1 | Comando de solo lectura: coincidencias, obligaciones, pagos, conciliaciones, asientos, proveedores y archivos; sin reparación automática |

Migraciones nuevas, aditivas y sin backfill de documentos:

1. `2026_10_26_100000_share_received_invoice_identity`: receptor opcional en ambos orígenes, registro compartido, claves foráneas, índice fiscal global y propietario exclusivo en PG. El instante nuevo de reserva usa `timestamptz`; no cambia la zona global ni convierte históricos. Columnas añadidas mediante ALTER nativo para conservar los índices de expresión de SQLite.
2. `2026_10_26_101000_guard_received_invoice_identity`: vista de candidatos y triggers de reserva/protección. Facturas nuevas ordinarias sin NIF/número/receptor suficientes se rechazan. Los históricos no se reclaman por instalar la migración.
3. `2026_10_26_102000_scope_existing_invoice_identity_to_receiver`: conserva las restricciones anteriores y añade ámbito receptor. No relaja la exclusión entre módulos ni cambia la regla de año. No se eliminan filas si una restricción detecta contradicciones.
4. `2026_10_26_103000_guard_received_invoice_economic_effects`: guardas nativas PG sobre pagos, asientos y conciliaciones; impide borrar/modificar reservas de identidad. SQLite comprueba los servicios y el registro, pero las garantías de procesos concurrentes/economía directa se certifican en PostgreSQL.

Una factura puede conservar varias claves históricas tras una corrección autorizada; cada clave sigue perteneciendo a un solo origen. Los índices de propietario son de búsqueda, no una prohibición de conservar alias. Los campos económicos no se duplican en el registro.

Orden de bloqueo: propietario/raíz operativa → claves fiscales normalizadas en orden estable → ejercicio/serie y efectos. Creación y corrección toman las claves antes de numeración/periodos; contabilización y conciliación comparten el bloqueo del propietario. Se mantienen los bloqueos de ECO-01. La comprobación externa de archivos no se presenta como una transacción distribuida.

La auditoría existente incluye los identificadores de reserva en `AuditLog`; la corrección conserva su actor, motivo y snapshots. No se añaden bitácoras paralelas ni NIF sin necesidad a logs técnicos. Los errores de restricción se convierten en validaciones; no se exponen consultas SQL al usuario.

## E. Custodia del documento emitido

- **Original archivado**: descarga de bytes conservados, SHA-256 comprobado; se vuelve a comprobar el contenido exacto que se devuelve. Cambiar datos o marca actuales no altera esos bytes.
- **Histórico sin original localizado**: ruta ausente o archivo no localizado; descarga ordinaria bloqueada con 409 y aviso de revisión. No se crea un original sustitutivo.
- **Integridad fallida**: archivo presente pero huella inválida/distinta; descarga bloqueada. No se sobrescribe ni se ofrece reconstrucción para ocultar esa incidencia.
- **Reconstrucción informativa**: acción separada, exclusivamente administrativa y expresamente identificada. Aviso visible y marca en el PDF, nombre `RECONSTRUCCION-…`, sin persistir archivo ni cambiar el registro. Solo usa snapshots/líneas disponibles; si falta identidad histórica se declara desconocida. No toma la marca actual para rellenar un pasado desconocido. Cada descarga se registra en `AuditLog` y no convierte el documento en original.

`DocumentPdf::renderForArchiving` queda separado de la descarga: exige snapshot y transacción de emisión/corrección. Se reutiliza el archivo definitivo, huella, identidad emisora/destinatario, líneas y compensación de fallos ya existentes. La corrección administrativa de una factura emitida exige recuperar/verificar el original antes de modificarla y conserva el anterior. No se ha cambiado su contrato de permisos ni sustituido por edición libre.

El hash acredita **integridad de archivo**, no autenticidad fiscal, firma electrónica ni corrección de los datos introducidos. No se afirman esas garantías adicionales.

Los fallos controlados entre archivo y BD retroceden número/asiento/documento y retiran solo el archivo nuevo no referenciado. Un apagado o pérdida del almacenamiento no puede compensarse con una transacción PostgreSQL: el diagnóstico detecta archivo perdido y archivos no referenciados para revisión. Conserva referencias de PDFs anteriores en auditorías de corrección; no los considera automáticamente basura ni los elimina. La recuperación exige original/verificación/restauración autorizados, nunca regeneración silenciosa.

## F. Diagnóstico histórico y resolución autorizada

Comando: `php artisan received-invoices:audit`. Fuera de una transacción previa. En PG ejecuta una transacción `REPEATABLE READ, READ ONLY`; en SQLite activa `query_only` y restaura su estado. Producción se bloquea por defecto: `--allow-production` está reservado a una autorización humana previa, no concede autorización por sí mismo. No se ha utilizado en producción.

El informe no ejecuta escrituras ni reservas. Clasifica:

- Colisión de identidad confirmada cuando los datos de identidad/receptor están explícitos; **no confirma por sí sola que sea el mismo original físico**.
- Coincidencias que requieren revisión si la identidad/receptor proceden de inferencias del legado.
- Identidades incompletas y proveedores potencialmente duplicados.
- Cada origen con obligación, asientos, pagos y conciliaciones distintos.
- Referencias contables huérfanas y pagos con propietario contradictorio.
- Originales disponibles, no localizados, alterados y archivos sin referencia actual/histórica localizada.

No se acusa duplicidad por igualdad de importe o fecha. La salida técnica usa identificadores y huellas de clave; no imprime credenciales ni NIF completos como claves de diagnóstico.

Procedimiento preparado para cada caso real, **sin ejecutarlo**:

1. Con autorización, conservar un expediente de revisión con referencias a los registros originales y evidencias; evitar nuevos efectos sobre la identidad en conflicto.
2. El responsable autorizado verifica receptor, emisor, número, naturaleza del documento, original y referencias. Decide si hay una colisión real, un dato erróneo o documentos legítimamente distintos.
3. Antes de actuar, inventariar todos los pagos activos/anulados, conciliaciones, asientos, periodos y originales. Elegir el propietario que seguirá operativo; nunca mover ni borrar automáticamente sus efectos.
4. Someter la actuación contable/documental concreta al procedimiento autorizado que corresponda. Conservar originales, asientos, pagos, conciliaciones y referencias anteriores; cualquier compensación debe quedar trazada y revisada. No utilizar una edición directa de base de datos como sustituto del procedimiento.
5. Registrar en el `AuditLog` existente actor, fecha, motivo, decisión, evidencia y referencias afectadas. Comprobar que solo queda una obligación operativa, que el saldo/caja concuerdan y que el diagnóstico explica la resolución.
6. Un falso positivo se documenta con su justificación y datos verificados; no se desactiva la restricción global. Un original recuperado se verifica contra evidencia y se custodia mediante un procedimiento aprobado, nunca a partir de una reconstrucción.

No se ha añadido un botón de fusión ni un resolutor automático de históricos. La anulación trazable de cobros de clientes contabilizados sigue fuera de ECO-02.

## G. Verificación y evidencias

| Control | Resultado final |
| --- | --- |
| Suite general combinada, copia aislada | **1.032 casos: 1.002 aprobados, 30 omitidos, 0 fallos; 14.805 aserciones** |
| PostgreSQL económico ampliado | **277 casos: 273 aprobados, 4 fallos previos, 0 omitidos; 3.774 aserciones** |
| Concurrencia nueva ECO-02 | **4 aprobadas, 25 aserciones**, dos procesos reales |
| Concurrencia anterior ECO-01 | **11 aprobadas, 69 aserciones**, dos procesos reales |
| Frontend | **56 aprobadas, cero fallos/omitidas** |
| TypeScript y ESLint global | Aprobados |
| Build | Aprobado; advertencia de tamaño de fragmentos, sin error |
| Traducciones globales | **211 cadenas pendientes; no aprobado**. Se tradujeron los siete avisos/claves nuevos; permanecen las 211 incidencias anteriores |
| Identidad/migraciones PG, repetición final | **20 aprobadas / 62 aserciones**; subconjunto repetido. Instante de reserva verificado como `timestamp with time zone` |
| Formato PHP y diff | Aprobados para archivos afectados |
| Diagnóstico CLI PostgreSQL | Ejecutado en base aislada: `read_only=true`, `history_modified=false`; transacción de solo lectura, sin reparación |
| Instalación de esquema local SQLite | Cuatro migraciones aplicadas solo al workspace; **13 tablas existentes comparadas por huella, cero cambios de datos**, cero reservas históricas creadas |
| TIME-02 | **62/62 huellas originales intactas** |

Los 30 omitidos incluyen las cuatro carreras nuevas y otros casos que exigen su base PostgreSQL específica. Las 15 carreras económicas se ejecutaron expresamente en sus bases aisladas; no se declaran aprobadas por omisión. No sumar ejecuciones repetidas entre motores como casos funcionales únicos. Los **20 casos nuevos** de identidad/custodia pasan dentro de las suites general y PostgreSQL.

Cuatro fallos PostgreSQL permanecen **abiertos**, fuera de las escrituras/custodia de ECO-02:

1. `AccountingTest::test_arevia_finds_specific_crm_records_and_hides_finance_from_technicians`: búsqueda de `FAC-AREVIA-77`, ya reproducida antes de ECO-02.
2. `PurchaseOrderDateCorrectionTest::test_admin_can_correct_a_saved_locked_order_with_an_audited_reason`: comparación estricta entre importe JSON `121` y decimal `121.00`. No se relaja la prueba ni se cambia el contrato del snapshot.
3. `SecurityAdministrationTest::test_deye_health_uses_the_credentials_required_by_the_client`: el comando de diagnóstico devuelve 1 en vez de 0.
4. `SecurityAdministrationTest::test_postgres_restore_refuses_the_production_database_as_target`: el registro de restauración recibe un `rpo_seconds` fraccionario incompatible con bigint antes de alcanzar la comprobación esperada. Es una puerta de recuperación que debe resolverse antes de un despliegue.

Los tres últimos se reprodujeron también en copia de los servicios/controladores anteriores a ECO-02, contra otra base PG desechable: **4 controles, 1 aprobado y 3 fallidos**. El control aprobado era la analítica con IDs iniciales; su fallo al combinar pruebas se debía a asumir IDs fijos. Se sustituyeron por los IDs reales de las fixtures y el conjunto exacto de enlaces, manteniendo importes y número de registros exigidos. No se atribuyen los cuatro fallos abiertos a una supuesta aprobación global de PG.

La interfaz local vuelve a responder en `http://localhost:8000`; se reutiliza el script oficial, con 5173 solo como auxiliar. La pestaña estaba sin conexión porque el servidor se había detenido; después del arranque muestra el acceso. No se han cambiado credenciales ni creado usuarios para la comprobación. Las pantallas económicas y permisos se verifican mediante pruebas HTTP/Inertia; no se afirma una revisión visual autenticada ni prueba física móvil.

Las pruebas de identidad/custodia cubren registro por compras/gastos, proveedores con NIF coincidente, receptores distintos, importes iguales con números distintos, alias de corrección, falta de datos, propiedad única, varios conceptos/proyecto/pagos/conciliaciones, recuperación/alteración/ausencia de archivo, cambio posterior de empresa, permisos de reconstrucción, rollback del duplicado, protección de reservas, migración con legado duplicado y diagnóstico sin escrituras.

La regresión anterior conserva los casos de creación/reintento, emisión, cambios administrativos autorizados, rechazo de cambios no autorizados, fallos de PDF/transacción y archivo alterado. Las carreras nuevas usan dos procesos PG reales y barrera; verifican una obligación y un asiento, no solo respuestas HTTP. Las once carreras de ECO-01 se repiten expresamente.

Se completaron fixtures sintéticas que antes representaban facturas nuevas sin NIF/número. Las pruebas de históricos incompletos fabrican expresamente un estado anterior a las guardas, solo en bases desechables verificadas. No se han añadido excepciones de pruebas al código productivo ni relajado las restricciones para que pasen. El caso de analítica que suponía IDs 1/2 se corrige para comprobar los IDs reales y el conjunto exacto de enlaces: PostgreSQL no retrocede secuencias al hacer rollback y no existe orden contractual para fechas empatadas.

Los primeros intentos de regresión detectaron incompatibilidades de preparación (índice de expresión al reconstruir SQLite, conexión temprana sin fichero en copia aislada), fixtures incompletas y un error de clave ausente al reconstruir sin snapshot. Se corrigieron y repitieron los controles; no se contabilizan como aprobados esos intentos fallidos.

## H–K. Riesgos, roadmap, valoración y siguiente paso

- Producción no inspeccionada: volumen de coincidencias, datos incompletos y originales faltantes desconocido. La implantación no resuelve históricamente esas incidencias.
- Confirmar códigos de país e identidades proporcionadas por los documentos. No se adivinan equivalencias de NIF/prefijos, ni se garantiza que un dato escrito por una persona coincida con el papel sin revisar la evidencia.
- No se ha implantado multiempresa operativo ni un selector para cambiar titular. Reutilizar un perfil para otra empresa o duplicarlo indebidamente requiere revisión; las pruebas de ámbitos no son una nueva funcionalidad multiempresa publicada.
- PostgreSQL es el motor de certificación de las restricciones concurrentes y guardas de efectos. SQLite no se presenta como equivalente para escrituras SQL económicas directas fuera de los servicios.
- Almacenamiento y BD no comparten commit; permanecen necesarios respaldo restaurable y revisión de archivos. No se promete exactly-once externo.
- Fallos previos y deuda de traducciones se mantienen visibles en resultados; no se declara toda la plataforma aprobada en PG si no lo está.

TIME-02 y ECO-01 siguen implementados/verificados **localmente**, con despliegue pendiente. ECO-02 tendrá su estado exacto, dependencias y verificaciones en `docs/roadmap.md`. No se amplía Nexo, CRM, inventario, modelos fiscales ni diseño corporativo.

**ECO-02 queda implementado y verificado técnicamente en local para los flujos cubiertos. La entrega integrada es NO APTA PARA DESPLIEGUE**, por los fallos PG abiertos —especialmente la puerta de restauración—, el diagnóstico/resolución histórica aún no autorizado en producción y la preparación de staging pendiente. Esto no impide revisar y preparar un corte local; no equivale a publicar ni a certificar ausencia de duplicados reales.

Antes de publicar: corte revisado de los cambios concurrentes, staging con copia autorizada, diagnóstico de solo lectura autorizado, resolución individual de contradicciones que bloqueen operaciones, verificación del respaldo/recuperación y revisión de todos los escritores. Instalar esquema/guardas y código coordinadamente; no dejar aplicaciones antiguas actuando sobre las nuevas reglas sin comprobarlas. Reversión: no retirar tablas, alias, claves ni guardas con evidencia; conservar esquema aditivo y desplegar código compatible. Ningún down se debe forzar para borrar evidencia. Los downs PG comprueban evidencia bajo bloqueo exclusivo transaccional; reversión/reaplicación vacía y rechazo con evidencia están probados.

Siguiente incremento recomendado únicamente tras cerrar técnicamente ECO-02: anulación trazable de cobros de clientes contabilizados, respetando sus relaciones bancarias y contables. No se inicia en este trabajo.
