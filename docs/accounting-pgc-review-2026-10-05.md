# Revisión contable PGC — 05/10/2026

Referencia: [publicación BOE aportada](https://www.boe.es/biblioteca_juridica/abrir_pdf.php?id=PUB-PB-2024-227)
y [RD 1514/2007 consolidado](https://www.boe.es/buscar/act.php?id=BOE-A-2007-19884).
La publicación contiene 534 páginas y su actualización indicada es 21/12/2024.
El catálogo local declara 898 cuentas PGC. Catálogo, automatización y cumplimiento
de las normas de registro/valoración son capas diferentes: no se certifica
cumplimiento integral por importar cuentas ni por cuadrar Debe/Haber.

## Hallazgo que explica la falta de proveedores

Diagnóstico de localhost previo al cambio: 1 proveedor, su cuenta asignada, 6
cuentas con prefijo 400 (incluidas generales), 17 gastos y los 17 sin proveedor,
39 asientos. No son cifras verificadas de producción. El observador sí generaba
cuentas al guardar un proveedor, pero introducir un emisor fiscal en gastos no
creaba la ficha. Se utilizaba la 410 genérica y los saldos por tercero omitían los
gastos sin vínculo. La ficha fiscal y el catálogo de proveedores estaban desconectados.

PGC: 400 incluye aprovisionamientos y servicios del proceso productivo;
410 corresponde a servicios que no tienen esa condición. No se decide únicamente
por el nombre de la empresa ni por el hecho de llamarla «proveedor» en la UI.

## Correcciones implementadas en develop

- Nuevas facturas EUR con identidad fiscal completa: reutilizar o registrar
  proveedor por país y NIF normalizado; identidad existente no sobrescrita con
  el texto de otra factura. Duplicidades requieren selección explícita.
- Dos subcuentas por proveedor: 4000 y 4100, prefijo monetario EUR. Códigos de
  12 cifras terminados en los tres últimos dígitos numéricos del NIF. Secuencia
  intermedia evita colisiones; sin NIF, identificador interno como fallback.
  Las cuentas antiguas no se renumeran al cambiar NIF o nombre; excepciones se
  revisan con trazabilidad antes de una eventual reclasificación.
- Facturas nuevas desde gastos: categoría 60 → 400; otras categorías → 410
  como alcance provisional del circuito de gastos, no como regla universal PGC.
  Cuenta elegida guardada en el documento y utilizada por factura y pagos.
  Categorías especiales, compras de inmovilizado, operaciones vinculadas y
  servicios productivos que no estén mapeados a 60 necesitan revisión específica.
- Gastos históricos sin campo nuevo mantienen la elección anterior. No se
  vinculan retrospectivamente ni se transfieren saldos genéricos silenciosamente.
- Recuperación de maestros desde emisores de facturas: simulación por defecto,
  aplicación explícita, omite identidades con razones sociales contradictorias.
  No cambia gastos, impuestos, pagos, asientos ni declaraciones.
- Motor automático utiliza JournalPoster: ejercicio abierto existente, cuentas
  activas/imputables, importes válidos, equilibrio y eventos sin duplicar.
  Revisión mensual repetida dentro del bloqueo del ejercicio.
- Contador de sincronización reparado: acumuladores en closures por valor
  devolvían cero aunque se crearan asientos. Iteración por lotes y retorno real.
- JournalPoster normaliza cada línea a céntimos y rechaza negativos/no finitos.
- No borrar cuentas vinculadas a proveedores/clientes/gastos. No cambiar
  naturaleza o jerarquía de cuentas con movimientos, que alteraría informes
  históricos sin un asiento de reclasificación.
- Saldos de terceros muestran 400 y 410 en filas separadas; enlace del mayor
  sigue apuntando a una cuenta real, no a una concatenación inválida.
- Migración sin backfill financiero; PostgreSQL con FK e índices. SQLite evita
  reconstrucciones que destruyen los índices funcionales preexistentes. Rollback
  bloqueado cuando ya existen asignaciones persistidas.

## Matriz de revisión y trabajo pendiente

| Circuito revisado | Evidencia / limitación | Próxima puerta |
| --- | --- | --- |
| Plan de cuentas y mantenimiento | Catálogo/hierarquía/versionado presentes; controles reforzados | Matriz oficial de estados financieros y modificaciones trazables |
| Proveedores/acreedores/clientes | Proveedores nuevos corregidos; clientes siguen numeración histórica | Recuperación y conciliación por tercero, cuentas antiguas y sufijos de clientes |
| Compras y abonos | Coste por producto; línea sin producto cae en 600, diferencia fiscal puede caer en 21% | Naturaleza por línea: 60/62/2; cuota documental y portes, sin inferir IVA residual |
| Gastos, IVA y retenciones | Cuota documental/deducción parcial y 4751 presentes | Clasificación por naturaleza, supuestos especiales y evidencia deducible |
| Ventas y abonos | Producto mapea ingreso; sin producto cae en 700 | Diferenciar mercancías/servicios, devengo y reconocimiento NRV 14 |
| Pagos y cobros | Pagos proveedor tienen origen específico; cobro cliente utiliza 570/572 genéricas | Cuenta bancaria/tarjeta concreta también en cobros y anticipos |
| Personal/reembolsos | 460/465 generadas; 465 usada para gastos pagados por empleado | Separar remuneraciones, anticipos y reembolsos con política validada |
| Diario y reversión | Motor unificado y controles de periodo/idempotencia | Pruebas concurrentes y restricciones de inmutabilidad en todos los canales |
| Periodificación | Plantillas, devengos y reversión implementados | Casos 480/485, resultado por periodo y revisión de fallos |
| Cierre/reapertura/apertura | Servicios presentes, meses cerrados y asientos compensatorios | Validar cierre completo, secuencias e idempotencia de apertura/reapertura |
| Existencias | Stock físico no basta como valoración contable | NRV 10, método de coste, variación 61/71, deterioros y conciliación con grupo 3 |
| Inmovilizado | No se ha demostrado circuito contable integral desde compras | NRV 2/3, altas, amortización 68/28, bajas y deterioros |
| Divisas | Contabilización nominal sin acreditar conversión completa | NRV 11, moneda funcional, 668/768 y cuentas monetarias adecuadas |
| Fiscal 303/390/347/200 | Proyecciones y liquidaciones compatibles no son presentación oficial | Continuar anexo fiscal; compensaciones, clasificación y ficheros validados |

## Operación segura

```sh
php artisan accounting:health
php artisan accounting:supplier-masters
php artisan accounting:supplier-masters --apply
```

El último comando crea/recupera maestros y subcuentas, no reclasifica históricos.
`accounting:sync` genera asientos faltantes: **no usarlo como herramienta de
diagnóstico** ni ejecutarlo indiscriminadamente en producción. Tras recuperar
maestros, conciliar saldos genéricos 400/410 por factura y pagos, elegir cuenta
correcta y corregir por asientos auditados solo en periodos permitidos. Los periodos
cerrados y declaraciones preparadas/presentadas requieren su circuito de revisión.

Producción sin cambios en esta entrega. Pendiente verificación/recuperación allí
y revisión con asesoría de las políticas contables y casos de referencia.

## Verificación y resultado local

Suite SQLite completa: 738 pruebas, 733 superadas y 5 omitidas; 10.863 aserciones.
Selección contable PostgreSQL: 61 pruebas y 540 aserciones, todas superadas.
El primer intento del fichero mixto AccountingTest tuvo un fallo ajeno al circuito
contable: búsqueda de factura en Arevia/CRM con diferencias de comparación de
texto PostgreSQL/SQLite. Se mantiene como deuda técnica, no se presenta ese
fichero mixto completo como verde en PostgreSQL. La selección contable excluye
las tres pruebas Arevia y añade gastos, pagos y libro de IVA.
TypeScript, lint, ES/EN, formato y build correctos; avisos existentes de bundles.
Migración probada en ambos sentidos en base PostgreSQL aislada y aplicada en
localhost; base temporal eliminada. Reversión posterior a asignaciones se bloquea
para no destruir las relaciones contables.

Recuperación local: 5 identidades elegibles, ninguna ambigua; 6 proveedores finales,
todos con cuenta de proveedores y de servicios. Comparación hash antes/después
confirma que gastos, compras, asientos, líneas y pagos no cambiaron. Diagnóstico:
898 cuentas PGC, ningún padre ausente, 39 asientos, ninguno descuadrado ni sin
ejercicio. Los 17 gastos históricos siguen sin vínculo y sin cuenta congelada:
**esto está pendiente**, no se oculta ni se cuenta como saneamiento terminado.
