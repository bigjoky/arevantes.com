# Revisión financiera y preparación fiscal 2027

Actualización de planificación, 28/09/2026: el [plan de modelos 303, 390 y 347](fiscal-models-303-390-347.md)
desarrolla FIN-02/03/05 del roadmap, con diagnóstico adicional de libros,
dependencias, validaciones y calendario interno. Este documento conserva la
revisión inicial y sus evidencias; no implica que los modelos estén implementados.

Fecha: 12/09/2026. Anexo de diagnóstico del [roadmap unificado](roadmap.md).
Objetivo solicitado: funcionamiento el 01/01/2027; puesta en marcha interna
prevista antes del 18/12/2026 para reservar margen de estabilización.

## Alcance y límites de esta revisión

Revisión inicial del mapa de servicios, roadmap, rutas/permisos, modelos,
emisión documental, libros de IVA, liquidaciones y cálculo de Sociedades.
Ejecutadas las pruebas existentes de impuestos, contabilidad, configuración
fiscal, facturas de venta, gastos, banca y finanzas: **86 pruebas, 1.570
aserciones, correctas**. No equivalen a una certificación fiscal ni validan las
brechas que todavía no cuentan con casos de prueba.

Comprobación adicional de regresión: suite completa correcta, **531 pruebas y
7.075 aserciones**. Cambios limitados a documentación; no se ha realizado un
despliegue ni se han activado servicios fiscales.

No se ha auditado cada pantalla de las veinte áreas ni se han contrastado los
saldos reales de producción, declaraciones presentadas, certificados o datos
censales. No se ha enviado información a AEAT. Este corte modifica documentación,
no comportamiento financiero. Los riesgos de concurrencia requieren reproducción
específica antes de afirmar un incidente real.

## Resultado de la revisión técnica

### Saldo de pagos/devoluciones de IVA — 13/09/2026

TaxController::pay comprobaba el saldo únicamente sobre el modelo recibido
antes de la transacción. Ahora recarga la liquidación con lockForUpdate y
revalida estado, posibilidad de movimiento y saldo antes de guardar el pago.
Pago, asiento y acumulado siguen en la misma transacción. Regresión con modelos
obsoletos para resultados positivos y negativos: un importe superior al nuevo
saldo produce error de validación sin cambiar liquidación, pagos ni asientos.
No se ha ejecutado todavía una prueba simultánea con conexiones PostgreSQL
independientes. Tampoco se resuelve aquí la idempotencia de un reintento parcial
que siga cabiendo en el saldo; requiere identificador de operación persistido.

Validación del incremento: 542 pruebas / 7.272 aserciones correctas, Pint,
sintaxis PHP y git diff --check correctos. Sin cambios de esquema, movimientos
reales ni despliegue; modificación local sobre develop.

### Navegación fiscal y capacidades — 13/09/2026

Libros de emitidas y recibidas con URL independiente, manteniendo filtros de
ejercicio/trimestre, exportaciones y permisos de consulta. El circuito de
liquidación queda en /tax. Las acciones visibles consultan manage-tax igual que
el servidor: un administrador con denegación no ve acciones y un gestor con
concesión puede utilizarlas. No cambia el cálculo ni los asientos. FIN-01 sigue
abierto; no se han presentado declaraciones ni activado VERI*FACTU.

Validación: 541 pruebas / 7.264 aserciones, TypeScript, lint, catálogo bilingüe
y build correctos. La suite se ejecutó después del build para evitar leer
assets mientras se regeneraban. Revisión visual pendiente: localhost:8000 no
estaba escuchando al comprobarlo. Cambios locales, sin despliegue.

### Tercer incremento FIN-01 — permisos manuales de facturación (13/09/2026)

Se añaden `issue-invoices` (Emitir facturas) y `prepare-credit-notes` (Preparar
rectificativas), categorizados como permisos financieros sensibles. Las rutas
de emisión y preparación de rectificativas ya consultan cada permiso específico,
en lugar de `manage-installations`. La ficha recibe del servidor las capacidades
de la persona y oculta las acciones no autorizadas; la emisión recurrente sigue
exigiendo adicionalmente `manage-treasury`, incluso para rectificativas derivadas.

La migración copia la pertenencia a perfiles y las excepciones individuales del
permiso anterior, incluidas denegaciones, concedente y fechas. Así conserva el
acceso efectivo inicial. Los usuarios sin perfil conservan su comportamiento
legado. Reejecutar la migración no restablece concesiones modificadas después.
Aplicada y comprobada en PostgreSQL local, sin cambiar datos de facturación.

Pruebas específicas: editor de documentos sin derecho de emisión, concesión de
emisión sin rectificación, concesión independiente de rectificación, capacidades
expuestas a la ficha y copia de perfiles/excepciones. Este corte no modifica
el circuito automático de anticipos tras aceptar una propuesta, ni separa aún
consulta, cobros o edición de borradores del permiso de instalaciones. Esos
accesos y la concurrencia real con conexiones independientes siguen pendientes.

Validación del tercer incremento: **537 pruebas / 7.148 aserciones**, TypeScript,
lint, catálogo bilingüe y build correctos. Migración ejecutada correctamente en
PostgreSQL local; cambios todavía sin publicar a producción.

### Segundo incremento FIN-01 — edición y borrado protegidos

La actualización de documentos directos recarga y bloquea el registro dentro de
la transacción y vuelve a comprobar estado, bloqueo, origen y tipo antes de
reemplazar datos o líneas. El borrado también bloquea y revalida estado y vínculos;
el borrado y la actualización del estado del documento origen son atómicos.
Se rechaza además un documento marcado como borrador si conserva `locked_at`.

Regresiones añadidas: edición obsoleta tras emisión sin alterar cabecera/líneas,
borrado obsoleto de documentos bloqueados sin asiento asociado y denegación de
las cinco mutaciones principales a un perfil de consulta financiera. No se han
ampliado permisos existentes. Sigue pendiente separar `manage-installations`
de permisos propios de facturación en servidor e interfaz, con migración de
perfiles explícita y revisión del circuito automático de anticipos.

Estas pruebas reproducen estados obsoletos de peticiones, no ejecución simultánea
con conexiones PostgreSQL independientes. Ese ensayo y la publicación siguen
siendo condiciones de salida de FIN-01.

Validación de este segundo incremento: suite completa correcta, **535 pruebas y
7.107 aserciones**; Pint, `git diff --check` y Composer estricto correctos.
Disponible localmente sobre `develop`, sin nueva migración en este incremento
y sin publicación en producción.

### Primer incremento FIN-01 — implementado localmente el 12/09/2026

- La emisión recarga y bloquea la factura antes de revalidar estado, bloqueo,
  permiso de tesorería en recurrencia y vencimiento. Una petición con un modelo
  obsoleto ya no puede emitir de nuevo ni renumerar una factura emitida.
- Las nuevas emisiones directas/rectificativas y anticipos guardan los datos
  del emisor en `issuer_snapshot`; la impresión consume esa identidad. Incluye
  identidad fiscal, dirección, contacto, datos de pago y pie documental. El logo
  sigue siendo un recurso gráfico actual: no se afirma conservación byte a byte
  del PDF ni archivo documental inmutable, pendientes de un corte posterior.
- Los libros de compras usan la identidad del proveedor guardada en el documento,
  incluidos transporte y diferencias sin asignar. Un NIF histórico vacío no se
  sustituye por uno actual. Solo documentos sin snapshot mantienen el fallback
  legado; no se reescribe su historia.
- Migración aditiva y nullable, sin backfill y sin índices JSON innecesarios:
  el snapshot se recupera por la clave de la factura. Se oculta de las cargas
  serializadas generales de los documentos.

Se reprodujeron antes de corregir tanto la doble emisión desde un modelo
obsoleto como la sustitución de identidad en el libro. Las pruebas usan SQLite;
no sustituyen el ensayo pendiente de concurrencia con conexiones PostgreSQL
independientes. Quedan abiertos edición/borrado frente a emisión, permisos y
publicación. La tabla siguiente conserva el diagnóstico inicial para trazabilidad.

Validación del incremento: 532 pruebas y 7.092 aserciones correctas, comprobación
TypeScript, build y validación estricta de Composer correctos. La guía PostgreSQL
orientó la revalidación bajo bloqueo y la columna JSONB nullable sin backfill.

| Capacidad                | Evidencia del repositorio                                                                                            | Evaluación y trabajo pendiente                                                                                                                                                                                   |
| ------------------------ | -------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Finanzas/tesorería       | `Services/Finance`, `Services/Banking`, pruebas de conciliación CSV/Norma 43, tarjetas y cuentas                     | Base reutilizable. Completar trazabilidad de pagos tributarios y separar conciliación de presentación fiscal.                                                                                                    |
| Contabilidad             | `JournalPoster`, `AccountingSynchronizer`, cierres y plantillas                                                      | Partida doble, cierres y reversos existentes. Validar integridad fiscal, periodos, saldos iniciales y concurrentes antes del cierre 2026.                                                                        |
| Facturación              | `SalesDocumentController::issue`, `DocumentNumber`, `SalesDocumentConversion`                                        | Borrador, numeración al emitir, bloqueo y rectificativas existentes. La comprobación de borrador precede a la transacción y no se recarga el documento bloqueado: riesgo de doble emisión pendiente de prueba.   |
| Emisor histórico         | `DocumentPdf::render` carga `CompanyProfile` actual                                                                  | La reimpresión puede incorporar cambios posteriores del emisor. Necesita snapshot fiscal y estrategia explícita para histórico sin inventar datos.                                                               |
| Proveedor histórico      | `PurchaseDocument` conserva `supplier_snapshot`; `VatReport::received` consulta `supplier` actual                    | Inconsistencia entre documento histórico y libro fiscal. Usar identidad congelada, con fallback legado documentado.                                                                                              |
| IVA                      | `VatReport`, `VatSettlement`, `TaxController`                                                                        | Libros por tipos, comparación con 472/477, liquidación interna trimestral, justificante y CSV. No hay generador oficial de casillas/diseño 303.                                                                  |
| Desglose de IVA recibido | `VatReport::received` pone la cuota deducible del gasto en `vat`, pero el total incluye cuota soportada              | Separar soportada, deducible y no deducible en contrato/exportación; no inferir cuota soportada a partir de deducibilidad. Revisar transporte fijado al 21 %, fecha de deducción y clasificación de operaciones. |
| Liquidaciones negativas  | `VatSettlement::prepare` solo añade líneas de 477/472 si las cuotas son positivas y rechaza periodos sin movimientos | Añadir casos con rectificaciones netas negativas, arrastres entre periodos y declaraciones sin actividad cuando procedan. No confundir ausencia de movimientos con ausencia de obligación.                       |
| Pago tributario          | `TaxController::pay` utiliza 570/572 genéricas y calcula saldo antes de la transacción                               | Seleccionar origen real, conciliación, bloqueo y revalidación del saldo dentro de transacción. Cubrir doble clic/pagos simultáneos.                                                                              |
| Sociedades               | `CorporateTaxCalculator`, `CorporateTaxController`                                                                   | Estimación con ajustes y tipos transitorios; justificante archivado. No equivale a modelos 200/202 oficiales. Validar elegibilidad, límites, compensaciones y fechas con calendario vigente.                     |
| Retenciones/informativas | Gastos con retención y contabilización en 4751                                                                       | No se localizaron generadores de 111/115/180/190/347/349/390. Faltan claves, perceptores, agregaciones, exclusiones y reglas versionadas.                                                                        |
| Permisos financieros     | `routes/web.php` mezcla `manage-tax`, `manage-accounting`, `manage-staff` y `manage-installations`                   | Revisar atribución de emitir, rectificar, cerrar, preparar, revisar, presentar y pagar sin ampliar acceso de otros roles.                                                                                        |
| VERI\*FACTU              | Sin integración localizada en `app`, `config`, `database`, `resources` o `tests`                                     | `DocumentFingerprint` es una huella interna JSON, no implementación del algoritmo/registro AEAT. Faltan cadena, XML, remisión, respuestas y QR fiscal.                                                           |

Las referencias de código son relativas a `app/` salvo indicación contraria.
Los nombres de clases y rutas delimitan puntos de inspección; no afirman cobertura
completa de todos los caminos de escritura.

## Alineación con las veinte áreas

| Áreas              | Contrato con el núcleo financiero/fiscal                                                                                           |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------- |
| PTY, CRM, SAL      | Identidad fiscal y territorial, propuesta no fiscal, anticipos, facturas y rectificativas; un único punto de emisión.              |
| ENG, PRJ, PLN, OPS | Coste y margen por proyecto, certificaciones/hitos y entrega; una actuación operativa no emite automáticamente una factura fiscal. |
| PUR, INV, AST      | Factura recibida sin duplicarla como gasto, recepción/valoración, activos y amortización con criterios revisados.                  |
| MNT, CS, CTR       | Facturación recurrente, garantías, abonos y anticipos por el mismo circuito fiscal que la venta directa.                           |
| FIN, ACC           | Submayores, cobros/pagos, conciliación, libros, modelos, cierres; casilla fiscal enlazada con documentos y asientos.               |
| HRM                | Nóminas/perceptores y retenciones, pagos por empleados y reintegros; no derivar 111/190 solo de gastos.                            |
| DOC, BI, QMS       | Evidencias privadas, informes coherentes con cierres, incidencias y revisiones fiscales sin modificar registros históricos.        |
| SYS                | Permisos, auditoría, certificados, colas, alertas y recuperación; secretos fuera de logs/repositorio.                              |

Cada corte probará origen → documento → asiento → libro → modelo, además de
PDF, permisos, ES/EN y uso móvil web. La app móvil independiente consume el
contrato central y no mantiene un segundo contador/cadena fiscal.

## Perfil fiscal y modelos objetivo

Confirmar con la empresa/gestoría: forma jurídica y NIF, territorio común o
foral, SII/REDEME, periodicidad, regímenes de IVA, prorrata, operaciones
intracomunitarias/importaciones, retenciones de alquileres/profesionales/nóminas,
criterio de caja y obligaciones censales. No pedir certificados o claves por chat.
No asumir que las cinco líneas de negocio tributan siempre al mismo tipo.

| Prioridad de producto   | Modelos candidatos | Dependencias de aceptación                                                                                                    |
| ----------------------- | ------------------ | ----------------------------------------------------------------------------------------------------------------------------- |
| Primera                 | 303                | Operaciones clasificadas, periodos, deducibilidad, rectificaciones y compensaciones; trazabilidad por casilla y conciliación. |
| Primera si aplican      | 111 / 115          | Naturaleza de retención y devengo/exigibilidad revisados, perceptores y pagos; integrar fuente de nóminas cuando corresponda. |
| Resúmenes si aplican    | 190 / 180 / 390    | Coherencia con periódicos, datos anuales, claves, exclusiones y exoneraciones.                                                |
| Informativas si aplican | 347 / 349          | Agregación por tercero/periodo/operación, umbrales y exclusiones versionados; no generar desde totales sin clasificación.     |
| Sociedades si aplica    | 200 / 202          | Resultado contable, ajustes, límites, régimen, pagos fraccionados y saldos previos revisados.                                 |

El catálogo no afirma que todos sean obligatorios para Arevantes. Otros modelos
detectados en el censo se añadirán al mismo roadmap. La elección provisional no
autoriza autoliquidaciones ni pagos.

Estados propuestos: borrador → validado → revisado → preparado para presentar →
presentado (con justificante) → conciliado. Errores y rectificaciones generan
nueva versión enlazada, nunca sobrescriben la presentada. Separar explícitamente:

- cálculo interno orientativo;
- borrador con reglas/casillas versionadas y fuentes reproducibles;
- archivo oficial validado para modelo y ejercicio, si existe ese mecanismo;
- presentación confirmada por justificante, no por descargar el archivo;
- pago/devolución/compensación, con su evidencia y conciliación independientes.

No etiquetar una exportación CSV de libros como «modelo AEAT». Si el diseño
oficial de un ejercicio todavía no está publicado, mostrar preparación de datos
y exportador pendiente; no reutilizar silenciosamente el del año anterior.

## Arquitectura propuesta de VERI\*FACTU

Decisión de diseño: modalidad con remisión a AEAT, no desarrollar simultáneamente
un SIF no VERI\*FACTU. Adaptador desacoplado del dominio para permitir evaluar
conexión directa o proveedor sin cambiar facturas, contabilidad ni libros.
La elección técnica se cerrará con un prototipo y revisión de operación, coste,
portabilidad y custodia, sin contratar servicios automáticamente.

1. Servicio único de emisión con transacción, bloqueo del documento/contador y
   control idempotente. Congela identidad, importes, tipos, claves y fechas.
2. Registro fiscal de alta o anulación conservado sin sobrescritura, con datos
   de sistema/productor/versión, cadena serializada según especificación AEAT y
   marca temporal. No equiparar anular un registro a emitir una rectificativa.
3. Huella oficial separada de `DocumentFingerprint`, vectores oficiales y XML
   validado contra esquemas versionados. QR y leyendas según modalidad y reglas
   vigentes; nunca simular aceptación de AEAT.
4. Outbox persistida en la misma transacción que la emisión y registro. Envío
   inmediato mediante trabajador, sin mantener bloqueos durante la llamada
   externa. Certificado en almacén seguro y separación estricta pruebas/real.
5. Respuestas por registro: aceptado, aceptado con errores, rechazado y resultado
   desconocido ante timeout. Reintentos conforme al protocolo, conservación de
   peticiones/respuestas y subsanaciones enlazadas sin recalcular la cadena.
6. Panel de pendientes/rechazos, alertas accionables, vencimiento del certificado,
   seguimiento de latencia y recuperación documentada. No activar remitentes en
   una copia restaurada o entorno de pruebas.

Verificar las restricciones exactas de cadena, tiempos, lotes, corrección y
reenvío contra los documentos técnicos al implementar. El despliegue del código
no habilita la remisión real: requiere configuración validada, aplicabilidad,
certificado/representación y decisión de activación documentada. Una vez activa,
una reversión de aplicación no puede descartar registros, reiniciar cadenas ni
desactivar obligaciones de remisión. No se remiten facturas históricas como
altas nuevas por hacer un backfill.

## Pruebas y puertas de salida

### Ensayo PostgreSQL independiente — 05/10/2026

`tests/Feature/FiscalConcurrencyTest.php` ejecuta trabajadores independientes
de `tests/Support/fiscal-concurrency-worker.php`. Cuatro escenarios comprobados:
borrador IVA concurrente, contabilización IVA concurrente, numeración concurrente
y emisión directa concurrente de una misma factura (34 aserciones).

La prueba solo admite PostgreSQL y una base cuyo nombre termine en
`_concurrency_test`; reinicia esa base con datos de prueba. Nunca apuntar a una
base de trabajo o producción. Ejecución tras crear una base temporal vacía:

```sh
DB_CONNECTION=pgsql DB_DATABASE=arevantes_fiscal_concurrency_test DB_URL='' vendor/bin/phpunit tests/Feature/FiscalConcurrencyTest.php
```

Las credenciales locales siguen en `.env`; no se incluyen en el comando ni en
el repositorio. Ambas conexiones deben estar esperando bloqueos en
`pg_stat_activity` antes de liberar el ejercicio. El ensayo verifica estos
cuatro casos, no cierra FIN-01: faltan conversiones, rectificaciones, pagos,
cierres y cobertura completa de accesos.

- FIN-01: peticiones simultáneas y reintentos no renumeran ni duplican; cambio de
  empresa/proveedor no altera documentos/libros históricos; roles sin permiso
  no emiten/rectifican/cambian cierres.
- FIN-02/03: casos de IVA ordinario, varios tipos, no deducible/parcial, abonos
  netos negativos, anticipos, duplicados compra/gasto, periodos sin actividad,
  compensación anterior y regímenes no soportados explícitamente bloqueados.
  Casilla → libro → asiento conciliados a céntimos y reglas reproducibles.
- FIN-04: vectores de huella, XSD, QR, alta, anulación y subsanación; timeout,
  duplicado, reinicio de trabajador, caídas de red, rechazo parcial y certificado
  caducado. Evidencias del portal externo, no solo mocks locales.
- FIN-05: conciliación periódicos/anuales y casos contrastados con gestoría.
- FIN-06: ejercicio 2027/series, cierre 2026, restauración y reanudación sin doble
  envío; carga con volumen representativo, revisión manual de interfaz/PDF,
  auditoría de permisos y declaración responsable de la versión distribuida.
- FIN-07: sin bloqueos críticos, soporte operativo y alertas probadas. Si falla
  la aceptación, no anunciar conformidad: decidir contingencia con un sistema
  adaptado y exportación/conciliación controlada, sin doble numeración/emisión.

## Referencias oficiales y vigencia

Consultadas el 12/09/2026; revalidar en cada corte normativo y antes de activar.

- [AEAT — Preguntas generales y plazos](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes.html):
  adaptación obligatoria para contribuyentes del Impuesto sobre Sociedades
  incluidos desde el 01/01/2027; resto de obligados incluidos desde 01/07/2027.
  Confirmar ámbito/exclusiones; el objetivo solicitado sigue siendo enero.
- [AEAT — Información técnica](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/informacion-tecnica.html):
  punto de entrada oficial a pruebas, diseños, WSDL, esquemas, validaciones,
  algoritmo de huella, QR y ejemplos de declaraciones responsables. Guardar
  versión y fecha de cada especificación al implementar el adaptador.
- [AEAT — Huella](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/huella-hash.html):
  la huella interna existente no sustituye el procedimiento oficial.
- [AEAT — Declaraciones por modelo](https://sede.agenciatributaria.gob.es/Sede/presentar-consultar-declaraciones-modelo.html):
  referencia para seleccionar instrucciones, diseños y presentación de cada
  modelo/ejercicio. Este plan no reproduce un calendario de vencimientos.
