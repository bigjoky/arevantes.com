# Plan de entrega fiscal: 303, 390 y 347

Fecha de revisión: 05/10/2026. Anexo ejecutable del [roadmap unificado](roadmap.md),
no un roadmap paralelo. Estado: **303 con proyección preliminar en develop;
390/347 planificados. Ningún modelo está listo para presentación oficial**.
Responsable técnico: desarrollo de Arevantes. La aplicabilidad, los casos fiscales
de referencia y la aprobación de declaraciones requieren validación de la empresa
y su asesoría. Ninguna publicación habilita envíos o pagos reales automáticamente.

## Objetivo y secuencia

Periodo de deducción de gastos (05/10), proyección v4: referencia documental,
fecha de registro y periodo explícitos desde `/tax/expenses/{expense}/classification`.
Base documental completa y cuota realmente deducible, conservando deducción
parcial. Mismas reglas de compras para cronología y protección del origen,
registro y asignación anterior/nueva. Revisiones sin periodo requieren nueva
confirmación; no se rellenan registros históricos. Documentos diferidos se
incluyen una sola vez en destino, sin modificar factura, pagos ni asientos.
Evidencia obsoleta permanece pendiente también en destino. Alcance 2026, 2T–4T;
sin preparación ni presentación oficial. Próxima prioridad: reconciliar esta
proyección con libros y liquidaciones compatibles, mostrando diferencias y
bloqueando preparación mientras existan discrepancias no justificadas.

Periodo de deducción de compras (05/10): fecha de registro y trimestre explícitos,
auditados, sin cambiar la fecha de factura ni el libro compatible. Se incluyen
compras revisadas por su periodo de deducción, aunque su fecha de factura quede
fuera del trimestre; origen muestra asignación a otro periodo sin duplicar cuotas.
Revisiones anteriores sin esos datos requieren confirmación nueva. Evidencia
obsoleta queda visible como pendiente también en el destino. Registro posterior
o igual a factura y no futuro; trimestre de deducción no anterior al registro.
Primer alcance: mismo ejercicio 2026, diseños 2T–4T; revisión interior soportada
sin portes/diferencias y con deducción íntegra. Origen, mes registral, destino
anterior y nuevo protegidos contra cierres/declaraciones preparadas o presentadas.
No reubica facturas de periodos contables ya protegidos. Gastos completados en v4;
ejercicios posteriores siguen pendientes; también reconciliación de esta proyección con
libros y liquidaciones compatibles antes de habilitar presentación oficial.

Revisión de compras (05/10), evolución del diagnóstico: clasificación persistida
y auditada desde `/tax/purchases/{purchaseDocument}/classification`. Confirma
documento cotejado, moneda EUR, régimen interior general sin caja, clase única,
IVA íntegramente deducible y mismo trimestre. Corriente/inversión/pendiente;
autor, motivo, referencia y snapshot conservados, con control de versión del
formulario. Casillas candidatas 28/29 y 30/31 solo con revisión vigente y desglose
exacto 4/10/21, sin portes ni diferencias; líneas libres admitidas. Cambios
fiscales invalidan revisión, no pagos/recepciones. Periodos y liquidaciones
protegidos bloquean nuevas revisiones. Alcance pendiente: deducción parcial,
deducción posterior con periodo registral propio, portes documentados,
rectificativas y regímenes especiales. No modifica libros ni habilita envíos.
Referencia: [AEAT, IVA deducible 2026](https://sede.agenciatributaria.gob.es/Sede/iva/pre-303/2026/iva-deducible.html).

Diagnóstico de compras (05/10): evidencia recibida del 303 explica los bloqueos
documentales de cada factura y enlaza su ficha. Comprueba snapshot de proveedor,
número de factura, registro, país, portes, tipos y coherencia exacta de importes
de líneas/cabecera, incluidas líneas libres sin inventario. No recalcula IVA ni
convierte en evidencia fiscal el 21 % estimado de portes del libro compatible.
Incluso sin incidencias de desglose, la compra permanece pendiente de revisión
de clasificación, deducción y periodo registral. No hay asignación automática,
persistencia de revisión ni nuevas casillas oficiales en este incremento.
Próximo paso: revisión auditada de compras; después portes/diferencias con
asignación documental, deducción parcial y periodos de deducción posteriores.

Perfil censal inicial (05/10): `/company-fiscal/census`, anual, sin valores
afirmativos por defecto. Guarda borradores y versiones revisadas con referencia,
autor, motivo y snapshot de identidad. Revisión humana, no consulta ni alta AEAT.
El 303 separa perfil vigente de alcance técnico admitido. La identidad fiscal
modificada o un borrador más reciente dejan el perfil sin revisión vigente.
Guardar no modifica libros, liquidaciones, pagos ni declaraciones. No se deduce
automáticamente obligación/exoneración del 390 o del 347. Cambios dentro del año,
situaciones adicionales de identificación oficial y regímenes fuera del alcance
requieren ampliar el perfil por periodos antes de habilitar presentación.

Segundo incremento 05/10: clasificación fiscal persistida de gastos en
`/tax/expenses/{expense}/classification`, enlazada desde documentos del 303.
Solo administradores; corriente / inversión / pendiente, autor, motivo y
confirmación explícita del territorio IVA, régimen sin caja, clase única y
deducción en el mismo periodo de la factura. Casillas candidatas 28/29 y 30/31
desde evidencia documental agrupada válida: base completa y cuota deducible.
La factura modificada deja la clasificación obsoleta. Se puede volver a pendiente
sin borrar el historial. No cambia importes, pagos, cuentas ni liquidaciones.
Periodo cerrado o liquidación preparada/presentada impiden nuevas revisiones.
Quedan fuera las compras, facturas mixtas, cuotas deducibles cero, regímenes
especiales, rectificaciones recibidas y deducciones en periodos posteriores.
No equivale a perfil censal aprobado ni a modelo presentable.

Referencia adicional: [Pre303 de AEAT, datos de liquidación 2026](https://sede.agenciatributaria.gob.es/Sede/iva/pre-303/servicios-especificos-sii/2026-notas/datos-liquidacion.html).
Reglas de cálculo por casilla: [IVA deducible Pre303 2026](https://sede.agenciatributaria.gob.es/Sede/iva/pre-303/2026/iva-deducible.html).

Incremento 05/10: `/tax/models/303`, accesible a administradores desde IVA,
documento de trabajo de solo lectura basado en instrucciones AEAT 2T–4T/2026.
Casillas candidatas 01–09 y 14/15 con evidencia documental y céntimos originales.
Solo candidatos con snapshot de país ES y tipo 4/10/21; territorio, régimen y
devengo siguen sujetos a validación. No equivalen a clasificación fiscal aprobada.
Operaciones a tipo cero, exentas, extranjeras, sin identidad y tipos desconocidos
quedan pendientes. Compras/gastos no se asignan indiscriminadamente a 28/29:
requieren clase de operación, corriente/inversión y periodo de deducción.
Las casillas pendientes conservan valor desconocido, nunca cero ficticio.
No hay resultado 71, compensación, exportación AEAT ni cambios en asientos/pagos.
Próximo corte: clasificación fiscal persistida y perfil censal; después bases y
cuotas deducibles por clase, compensaciones, totales oficiales y casos de asesoría.
Otros ejercicios y 1T/2026 no reutilizan automáticamente este diseño.

Referencia: [instrucciones oficiales AEAT 2T–4T/2026](https://sede.agenciatributaria.gob.es/Sede/todas-gestiones/impuestos-tasas/iva/modelo-303-iva-autoliquidacion_/instrucciones-2026/instrucciones-02-12-2t-4t-2026.html).
Preparar, revisar, conciliar y conservar los tres modelos cuando correspondan al
perfil censal. Mantener el objetivo operativo del 01/01/2027 y la puesta en marcha
controlada del 18/12/2026. No confundir estas fechas internas con vencimientos AEAT.

| Corte existente | Objetivo interno | Entrega | Dependencia / condición de salida |
| --- | --- | --- | --- |
| FIN-02.A | 09/10/2026 | Motor decimal, bases documentales e IVA agrupado; identidad fiscal y libros normalizados | Integridad de documentos, perfil censal y casos de redondeo aprobados |
| FIN-03.303 | 30/10/2026 | Borrador 303 por casillas, conciliación, compensaciones y expediente | FIN-02.A; comparación con casos de referencia y reglas del período |
| FIN-05.390 | 13/11/2026 | Resumen anual 390 y comprobación de exoneración | Libros anuales completos y conciliación con 303; contenido anual adicional |
| FIN-05.347 | 27/11/2026 | Operaciones por tercero, claves, exclusiones y revisión de discrepancias | Identidad fiscal unificada, fechas registrales y cobertura de todo el ejercicio |
| FIN-06 | 11/12/2026 | Ensayo del ejercicio 2026, rectificaciones y salidas oficiales validadas | Datos de referencia completos, diseños aplicables y aprobación de asesoría |
| FIN-07 | 18/12/2026 | Publicación controlada del alcance aprobado y manual operativo | Ninguna diferencia inexplicada ni operación fiscal no soportada silenciosamente |
| Reserva | 19–31/12/2026 | Estabilización, revalidación de diseños y calendario | Sin ampliar alcance no esencial |

Son objetivos de planificación sujetos a capacidad y dependencias. FIN-04
(VERI*FACTU) conserva su hito y sus pruebas propias; no es un requisito técnico
para calcular estos tres modelos, ni los sustituye. Se mantienen 111/115,
190/180, 349 y 200/202 según obligaciones: no se eliminan del plan general.

El 303 de 3T/2026 vence antes del objetivo de este módulo: debe seguir preparándose
y presentándose por el circuito verificado de la asesoría, sin esperar a esta
entrega. Importar después sus datos y justificante como histórico, sin recrear
pagos, asientos o presentaciones. Confirmar vencimiento y domiciliación concretos
en el calendario oficial. El cierre 2026 se prepara para su campaña de 2027;
no se presupone que ya estén disponibles todos los diseños o calendarios de 2027.

## 1. Diagnóstico del punto de partida

Existen libros e informe de IVA (`VatReport`), liquidación (`VatSettlement`),
expedientes `TaxReturn`, justificantes y pagos tributarios. No constituyen por sí
solos un modelo 303 oficial, ni hay implementación específica de 390/347.

Brechas observadas que se convierten en tareas FIN-02:

- `VatReport::received` presupone portes al 21 % y puede inferir un tipo efectivo
  sobre residuos: persistir el tratamiento real y marcar históricos ambiguos
  para revisión; no inventar una clasificación fiscal para cuadrar diferencias.
- La comprobación contable buscaba códigos exactos 472/477. Incremento FIN-02.B
  implementado en develop: jerarquía y mapeos explícitos de IVA, naturaleza y
  signos, incluidas rectificaciones/reversiones, sin coincidencias de prefijo.
  La liquidación usa las cuentas reales y conserva su desglose en el snapshot.
  Pendiente publicación; no equivale a completar los libros ni el modelo 303.
- Cuota soportada, deducible y no deducible separadas en develop (29/09): libro,
  resumen y exportación recibida; pendiente publicación. Los históricos sin
  desglose y las compras conservan el tratamiento previo, pendiente revisión
  censal; no se da por validada su deducibilidad ni el modelo oficial.
- Se filtra por fecha de factura/gasto: incorporar recepción, registro y período
  de deducción/devengo, sin desplazar fechas originales para encajar trimestres.
- `VatSettlement::prepare` sincroniza contabilidad y genera asiento: separar
  vista previa sin escrituras, revisión, aprobación y contabilización explícita.
  Incremento 04/10 en develop: vista previa de asiento sin escrituras y retirada
  de sincronización implícita al preparar; exige conciliación previa. Continúan
  pendientes aprobación separada, concurrencia y snapshot de fuentes/versiones.
  Segundo incremento 04/10 en develop: borrador, aprobación motivada y
  contabilización independientes; fuentes de libros y cuentas conservadas con
  huella, revisión invalidada por cambios y protección contra repetir el asiento.
  Pendientes ensayos de concurrencia independientes y fuentes/versiones fiscales
  completas; no representa todavía un modelo 303 oficial.
  Controles previos visibles añadidos: ejercicio y periodo abiertos, cuentas
  activas/contabilizables, equilibrio, movimientos y coincidencia de evidencia.
  Se validan otra vez en servidor antes de aprobar y contabilizar.
  Comparación documental añadida: altas/bajas/modificaciones, cambios de
  identidad, tipos y cuotas; actualización con resumen y huella previa en el
  historial. No sustituye la trazabilidad normativa por casillas del 303.
- La ausencia de movimientos actualmente impide preparar una liquidación:
  soportar el escenario sin actividad cuando corresponda, sin equipararlo
  automáticamente a todas las declaraciones de resultado cero.
- Ampliar snapshots: documentos y versiones utilizados, reglas, casillas,
  ajustes, huella de entradas, autor/revisor y vínculos a justificantes previos.

## 2. Base común obligatoria

### Perfil fiscal versionado

Confirmar entidad y territorio, periodicidad, actividades, regímenes, SII/REDEME,
criterio de caja, prorrata/sectores diferenciados, bienes de inversión y otras
obligaciones. Registrar vigencia y evidencia de validación censal. Un régimen
no confirmado o no soportado bloquea la aprobación/exportación, no se interpreta
como régimen general. No asumir que el 390 o el 347 son obligatorios para Arevantes.

### Identidad y libros

- Unificar tercero por país/NIF normalizado, con validación y revisión de duplicados;
  múltiples nombres comerciales o direcciones no deben fragmentar el declarante.
- Conservar identidad documental histórica y mapeo de consolidación auditable.
  Una fusión del directorio no reescribe declaraciones presentadas.
- Un solo documento fiscal para una factura recibida aunque aparezca en compras
  y gastos. Detectar duplicados por identidad, número/serie, fecha e importes.
- Integrar el criterio acordado de precisión decimal, base documental y cuota
  por grupo. Mostrar diferencias y no generar céntimos artificiales para conciliar.
- Tipificar operaciones, rectificativas, anticipos, portes, inversión del sujeto
  pasivo, operaciones internacionales y cuotas no deducibles. Casos no cubiertos
  deben quedar en la bandeja de revisión, nunca fuera del cálculo sin aviso.
- Importar el ejercicio completo y declaraciones anteriores cuando proceda;
  no inferir que meses anteriores al uso de Arevantes carecen de operaciones.

## 3. Modelo 303 — autoliquidación periódica

**Entregables de producto**

1. Selector de entidad, ejercicio/período y versión normativa. Periodicidad según
   perfil confirmado, sin limitar el motor a trimestres de forma irreversible.
2. Casillas calculadas con acceso a los documentos que las alimentan. Diferenciar
   IVA devengado, soportado/deducible, rectificaciones, bienes de inversión y
   tratamientos especiales aplicables. Versionar el mapa de casillas, no fijarlo
   para todos los ejercicios.
3. Cuenta de compensaciones: origen, aplicación por período, saldo y cambios
   autorizados; importar el arrastre inicial con soporte documental.
4. Propuesta de resultado y opciones permitidas: ingreso, compensación, devolución
   o resultado cero. No convertir todo resultado negativo en devolución.
5. Conciliación documento → libro → subcuentas → casillas → liquidación. Explicar
   diferencias de período y ajustes; prohibir asientos automáticos para ocultarlas.
6. Flujo de autoliquidación rectificativa cuando corresponda, con referencia a
   la anterior, motivo y comparación de casillas; contemplar excepciones y reglas
   del período, sin editar el expediente presentado [S1, S7].
7. Salida legible para asesoría y, cuando esté validado el diseño aplicable,
   fichero importable en AEAT. Registrar justificante/CSV, fecha y resultado de
   presentación; enlazar pago/NRC o devolución sin duplicar tesorería.

**Aceptación:** casos con varios tipos, cuota documental, deducción parcial,
compensaciones anteriores, rectificativas, cero/sin actividad y períodos cerrados.
Cada casilla debe ser reproducible desde el snapshot. Vista previa sin cambios
contables; contabilización idempotente y presentación nunca implícita.

## 4. Modelo 390 — resumen anual

1. Evaluar obligación/exoneración con el perfil del ejercicio, conservando
   justificación. Si existe exoneración, preparar la información anual que
   corresponda en el último 303, en vez de generar un 390 innecesario [S1, S2].
2. Construir el resumen desde los libros anuales y sus ajustes; **no sumar sin más
   los resultados de los 303**. Preparar actividades, volumen de operaciones,
   desgloses y regularizaciones aplicables, con entradas manuales justificadas
   cuando la plataforma no disponga del dato.
3. Comparar con los 303 válidos de cada período, sus rectificativas y el cierre
   contable. No contar dos veces una declaración sustituida/rectificada.
4. Emitir matriz de diferencias, borrador por casillas y expediente anual firmado
   por el revisor. Exportador condicionado al diseño del ejercicio.

**Aceptación:** año completo (incluido histórico externo), conciliación anual y
periódica explicada, escenario exonerado y no exonerado, modificaciones posteriores
mediante nueva versión. El diseño 2025 consultado es referencia, no autorización
para etiquetar como oficial un fichero 2026/2027 [S2].

## 5. Modelo 347 — operaciones con terceros

1. Evaluar obligación y exclusiones de la entidad, incluidas las relacionadas
   con SII. No se obtiene simplemente de las cuentas 400/430 [S3, S4].
2. Consolidar terceros por identidad fiscal y clave de operación; tratar compras
   y ventas separadamente, sin compensarlas entre sí. Calcular el importe fiscal
   conforme a las reglas del modelo, no desde pagos bancarios ni el neto pagado.
3. Aplicar el umbral general de importe anual superior a 3.005,06 euros, IVA
   incluido, y reglas específicas de signos, operaciones y exclusiones [S4–S6].
4. Desglosar trimestres cuando corresponda, usando el criterio registral aplicable.
   Contemplar los tratamientos anuales especiales y no confundir fecha de emisión
   con período de registro de facturas recibidas [S3].
5. Motor explícito de inclusión/exclusión: operaciones ya informadas por otros
   modelos cuando proceda, comercio exterior y casos particulares. Guardar la
   regla aplicada y permitir revisar exclusiones, no esconderlas [S6].
6. Revisar anticipos sin doble cómputo, abonos, importes negativos, arrendamientos
   y cobros en metálico con sus campos/umbrales específicos. No confundir cobros
   en metálico con pagos propios en efectivo [S3, S5, S9].
7. Panel por tercero con acumulado, trimestres, documentos, NIF pendiente y
   discrepancias. Generar cartas de contraste como borradores; enviarlas solo
   con autorización y sin modificar automáticamente lo declarado por diferencias
   comunicadas por el tercero.
8. Generar fichero conforme al diseño de registro aplicable, validando cabeceras,
   registros, longitudes, codificación y totales; conservar resumen y justificante
   de presentación [S8].

**Aceptación:** pruebas de 3.005,05 / 3.005,06 / 3.005,07, compras y ventas del mismo
NIF, varios nombres comerciales, devoluciones que cambian el umbral, importes
negativos, factura tardía, duplicado compra/gasto, exclusión y registro incompleto.
No exigir que el total 347 coincida con 303/390: justificar las diferencias de
alcance y valoración mediante una tabla de conciliación.

## 6. Interfaz, permisos y expediente común

Ubicación prevista: **Contabilidad → Fiscalidad → Modelos → 303 / 390 / 347**.
Cada modelo tendrá vista propia, con selección de ejercicio/período, estado,
validaciones, detalle de casillas/terceros, diferencias e historial.

Estados previstos: borrador → calculado → pendiente de revisión → aprobado →
exportado → presentado. Rectificación mediante expediente vinculado, no sobreescritura.
Separar preparación, aprobación, exportación, marcado de presentación y gestión
del pago. Un fichero exportado no prueba presentación; un borrador no genera
asiento ni pago. Usar control de versiones para impedir aprobar datos obsoletos.

Revisión de rol administrador/finanzas/asesoría, ES/EN, móvil, accesibilidad y
rendimiento dentro de cada entrega. Toda entrada manual exige origen/motivo;
ajustes críticos requieren aprobación. Conservar documento fuente, regla y
justificante como evidencia consultable.

## 7. Puertas de salida y operación

- Casos de referencia contrastados con asesoría y herramientas oficiales.
- Pruebas unitarias, integración, PostgreSQL, permisos, concurrencia, doble envío,
  redondeos y conservación de documentos ya contabilizados/presentados.
- Ensayo de un ejercicio completo y prueba visual autenticada de cada formulario.
- Exportación solo tras validar el formato vigente y la importación oficial donde
  sea posible sin presentar. PDF/CSV de revisión claramente marcado como borrador.
- Calendario por ejercicio, incluyendo festivos y plazos de domiciliación;
  alertas de preparación no equivalen a autorización para presentar [S1, S9].
- 303, 390 y 347 pueden activarse por separado cuando superen aceptación. Regímenes
  pendientes permanecen bloqueados y visibles como no soportados.
- No hay presentación desatendida en el alcance inicial. Certificados, representación
  y remisión real requieren un hito de seguridad y autorización específico.

## Avance de implementación — 28/09/2026

Primer incremento de FIN-02.A: motor aislado `SupplierInvoiceCalculator`, versión
`supplier-vat-v1`, con aritmética decimal exacta mediante dependencia directa
`brick/math`. Acepta bases y cuotas documentales, registra diferencias y motivos
en su resultado, agrupa IVA y reparte céntimos de forma estable. Incluye
deducibilidad parcial y abonos por inversión de magnitudes. Las 28 pruebas
específicas realizan 2.105 comprobaciones.

**No está activado en producción ni conectado a los modelos fiscales.** El
29/09 se añade adaptador y persistencia opt-in de gastos, deshabilitados por
defecto: migración nullable, snapshot y evidencia de correcciones, sin backfill.
Formularios y vista previa de gastos implementados en develop, con revisión
cifrada del cálculo antes de guardar. Pendientes validación visual, activación
controlada y compras; no modifica facturas anteriores.
EUR únicamente; no admite líneas con signos mixtos, recargos, retenciones,
inversión del sujeto pasivo ni conversiones de moneda. La tolerancia técnica
inicial de cuota es 0,02 EUR por grupo, no una regla legal. Los adaptadores deberán
validar además los límites monetarios del almacenamiento y guardar evidencia del
cálculo. Quedan pendientes integración, formularios, regímenes adicionales y
conciliación con libros/asientos antes de habilitar su uso real.

## Fuentes oficiales y revalidación

Consultadas el 28/09/2026. Revalidar al implementar cada modelo y antes de cada
campaña. Los plazos, exoneraciones y diseños dependen del ejercicio y perfil.

- [S1 — AEAT, instrucciones 303 de 2026](https://sede.agenciatributaria.gob.es/Sede/todas-gestiones/impuestos-tasas/iva/modelo-303-iva-autoliquidacion_/instrucciones-2026/instrucciones-02-12-2t-4t-2026.html).
- [S2 — AEAT, modelo 390 y documentación por ejercicio](https://sede.agenciatributaria.gob.es/Sede/procedimientoini/G412.shtml).
- [S3 — AEAT, preguntas frecuentes 347](https://sede.agenciatributaria.gob.es/Sede/todas-gestiones/impuestos-tasas/declaraciones-informativas/modelo-347-decla_____racion-anual-operaciones-personas_/preguntas-frecuentes.html).
- [S4 — AEAT, declaración anual de operaciones con terceros](https://sede.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-practicos/folleto-actividades-economicas/8-declaraciones-informativas/8_2-declaracion-anual-operaciones-terceros-347.html).
- [S5 — AEAT, importe de las operaciones 347](https://sede.agenciatributaria.gob.es/Sede/todas-gestiones/impuestos-tasas/declaraciones-informativas/modelo-347-decla_____racion-anual-operaciones-personas_/importe-operaciones.html).
- [S6 — AEAT, operaciones excluidas 347](https://www3.agenciatributaria.gob.es/Sede/todas-gestiones/impuestos-tasas/declaraciones-informativas/modelo-347-decla_____racion-anual-operaciones-personas_/operaciones-excluidas-modelo-347.html).
- [S7 — AEAT, errores y autoliquidaciones rectificativas 303](https://sede.agenciatributaria.gob.es/Sede/iva/presentar-declaracion-iva-modelo-303/errores-declaracion.html).
- [S8 — AEAT, diseño lógico 347, 2025 y siguientes sujeto a revisión](https://sede.agenciatributaria.gob.es/static_files/Sede/Disenyo_registro/DR_300_399/archivos/347.pdf).
- [S9 — AEAT, imputación temporal 347](https://sede.agenciatributaria.gob.es/Sede/todas-gestiones/impuestos-tasas/declaraciones-informativas/modelo-347-decla_____racion-anual-operaciones-personas_/imputacion-temporal.html).
