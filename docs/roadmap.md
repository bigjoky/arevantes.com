# Roadmap unificado de Arevantes

## Estado vigente y orden de ejecución — 05/10/2026

**Prioridad ACC-PGC.01 (05/10):** revisión solicitada frente al PGC del BOE.
Primera corrección estructural: emisores de facturas EUR conectados a maestros
de proveedores, cuentas 4000/4100 individualizadas, cuenta del gasto conservada
para sus pagos, recuperación no destructiva de maestros y diagnóstico contable.
Asientos automáticos unificados con motor validado, contador de sincronización
corregido y protección de cuentas con movimientos. Develop, no producción.
No se reescriben históricos. Auditoría y secuencia en
[revisión contable PGC](accounting-pgc-review-2026-10-05.md).
ACC-PGC.02: conciliar saldos genéricos y terceros históricos, sin reclasificación
silenciosa. ACC-PGC.03: naturaleza por línea de compra/venta y cuentas concretas
en cobros. ACC-PGC.04: inmovilizado, existencias, personal, divisas y cierre completo.
Estos requisitos alimentan el roadmap fiscal 303/390/347; no lo sustituyen ni
permiten declarar cumplimiento PGC completo antes de cerrar las puertas pendientes.
Verificación: 733 pruebas superadas, 5 omitidas, 10.863 aserciones; 61 contables
en PostgreSQL, 540 aserciones. Migración reversible comprobada y aplicada en local.
Recuperadas 5 identidades: 6 proveedores locales con ambas cuentas. Hash confirma
documentos/asientos/pagos intactos; 17 gastos históricos aún pendientes de conciliar.
Build, tipos, lint y ES/EN correctos. Limitación CRM/Arevia de la suite PostgreSQL
documentada en el informe; no forma parte del bloque contable declarado verificado.

**Incremento FIN-03.303, comparación de liquidaciones guardadas (05/10):**
en develop, pantalla del 303 con liquidación contable del mismo ejercicio/trimestre.
Cuotas guardadas frente a candidatas y saldo provisional en céntimos; este saldo
no es la casilla 71 oficial, porque no incorpora compensaciones ni otros ajustes.
Evidencia de libros guardada frente a libros actuales: documentos añadidos,
retirados y modificados, incluso identidad cambiada con importes iguales.
Sin liquidación o sin snapshot compatible no se declara equivalencia. Estados
preparado/presentado claramente protegidos; ninguna actualización automática de
snapshot, factura, asiento o liquidación. Una consulta limitada al periodo,
sin N+1 ni migraciones. `ready=false`; producción sin cambios.
Siguiente puerta: tratamiento auditado de diferencias y revisión de operaciones
emitidas; después controles de resultado/compensación y expediente fiscal nuevo.
No habilitar presentación por la mera coincidencia de totales. Continúa el orden
303 → 390 → 347, sin abandonar el resto del roadmap operativo.
Verificación final: 724 pruebas superadas, 5 omitidas, 10.820 aserciones; 36 pruebas
específicas y 435 aserciones también en PostgreSQL aislado, base temporal eliminada.
TypeScript, lint, ES/EN y formato comprobados. Referencia oficial 2026 contrastada
en el anexo fiscal. Sin cambios de datos fiscales reales.
Build correcto; mantiene los avisos de tamaño de bundles existentes. Guardas
adicionales rechazan snapshots con resúmenes incompletos o cuotas no numéricas.

**Incremento FIN-03.303, diagnóstico de conciliación (05/10):** nueva sección
«Conciliación del 303» en `/tax/models/303`, develop. Compara cuotas candidatas
con los libros por fecha de factura y saldos contables de IVA, conservando los
libros originales antes de incorporar documentos de otro periodo. Diferencias
por documento: pendiente de revisión, asignado a otro periodo, incorporado desde
otro periodo o cuota documental distinta. Acumulación por céntimos y documento;
las diferencias que se compensan entre sí siguen visibles individualmente.
Detecta discrepancias entre resumen y filas como diferencia sin explicar.
Evidencias pendientes o totales desconocidos impiden mostrar coincidencia.
Diagnóstico de solo lectura, sin consultas adicionales ni migraciones: no cambia
facturas, asientos ni liquidaciones. Coincidencia numérica no habilita presentar.
Las liquidaciones compatibles aún utilizan libros originales; no se sustituye
su cálculo por candidatos incompletos. Primera fase de la puerta de conciliación
completada; siguiente: comparación con borradores guardados y tratamiento auditado
de diferencias antes de habilitar preparación fiscal nueva. Producción sin cambios.
Suite completa: 718 superadas, 5 omitidas, 10.769 aserciones.
30 pruebas específicas, 387 aserciones también en PostgreSQL aislado; base temporal
eliminada. TypeScript, lint, ES/EN y formato comprobados. Build correcto,
con avisos existentes de tamaño de bundles; sin nuevas dependencias de UI.

**Incremento FIN-03.303, periodos explícitos también en gastos (05/10):**
en develop, revisión con referencia documental, fecha de registro, ejercicio y
trimestre de deducción. Comparte validación y protección de periodos con compras,
sin cambiar fechas, importes, pagos ni asientos de la factura. Conserva la base
documental completa y la cuota realmente deducible, incluso con deducción parcial.
Proyección preliminar v4: candidato una sola vez en el destino; asignación a otro
periodo visible en origen; evidencia obsoleta pendiente en destino. Revisiones
anteriores sin periodo explícito necesitan nueva confirmación, sin backfill.
Alcance: mismo ejercicio 2026, 2T–4T, operaciones interiores soportadas. No
presentable ni desplegado en producción. Siguiente puerta fiscal: conciliar la
proyección con libros y liquidaciones compatibles antes de preparar declaraciones;
después ampliar portes, deducción parcial de compras y ejercicios/regímenes.
Mantiene el orden integrado del 303, 390 y 347 y el resto del roadmap operativo.
Verificación: 714 pruebas superadas, 5 omitidas, 10.751 aserciones; 32 pruebas
específicas y 411 aserciones también en PostgreSQL aislado. Migración reversible
comprobada y aplicada en localhost; base temporal eliminada. TypeScript, lint,
catálogo bilingüe y formato comprobados. Build correcto; conserva los avisos
de tamaño de bundles existentes. No se modifican datos fiscales reales.

**Publicación selectiva del idioma (05/10):** español nativo desplegado en
producción por PR131, versión efectiva `v2026.10.05.2`, commit
`f5890858f81fbabc0429aac95b1f8eecd4e2f843`. Laravel Cloud confirmó
`depl-a2e82a80-ddc1-460a-81fc-b038e2266f30` con éxito; `/up` y `/login` HTTP 200,
login sin preferencias con `lang="es"`. Inglés explícito conservado. Publicación
solo del idioma: no lleva funciones/migraciones fiscales pendientes de develop.
Requisito de reconciliación main/develop completado (05/10): conflictos históricos
resueltos por bloque tras comprobar que las correcciones publicadas ya estaban
conservadas. Ninguna clave/valor de traducción publicado se pierde; código funcional
idéntico al develop probado. 710 pruebas superadas, 5 omitidas, 10.688 aserciones.
Evidencia en [registro de reconciliación](branch-reconciliation-2026-10-05.md).
La publicación fiscal sigue necesitando sus puertas funcionales y de preparación;
reconciliar Git no equivale a validar un modelo oficial ni a desplegarlo.

**Incremento FIN-03.303, periodo de deducción de compras (05/10):** revisión
auditada con fecha de registro, ejercicio y trimestre de deducción explícitos.
Sin rellenar retrospectivamente revisiones guardadas: las antiguas sin periodo
quedan pendientes de nueva confirmación. Modelo preliminar v3 distingue fecha
de factura y periodo asignado; una compra revisada puede deducirse en un trimestre
posterior compatible de 2026 (2T–4T), sin modificar sus fechas ni libros existentes.
La factura aparece una sola vez como candidata en el trimestre seleccionado y
en el de origen se muestra como asignada a otro periodo. Evidencia modificada
queda pendiente también en el destino, no desaparece silenciosamente.
Autor, motivo, referencia y cambios de periodo conservados en auditoría.
No se admite registro anterior a factura/futuro ni trimestre anterior al registro.
Protegidos origen, registro, periodo de deducción anterior y nuevo; un origen
contable cerrado o ya preparado/presentado sigue bloqueado, sin bypass para
históricos. Solo compras interiores ya admitidas; la extensión a gastos se completa
en el incremento v4 descrito arriba. Sigue no presentable; los libros y liquidación compatibles
no se recalculan con esta proyección. Pendiente
conciliar diferencias con libros/liquidaciones, asignar portes documentales,
deducción parcial de compras y otros ejercicios/regímenes. Develop, no producción.
Verificación: 710 pruebas superadas, 5 omitidas y 10.688 aserciones; 28 pruebas
específicas y 348 aserciones también en PostgreSQL aislado. Migración reversible
y aplicada en localhost; base temporal eliminada. TypeScript, lint y ES/EN correctos.

**Incremento FIN-03.303, revisión fiscal de compras y español nativo (05/10):**
la plataforma arranca en español tanto pública como autenticada, incluso con
APP_LOCALE heredado en inglés; elección explícita de inglés conservada en sesión.
Revisión desde documentos del 303 en `/tax/purchases/{purchaseDocument}/classification`:
solo administradores, clase corriente/inversión/pendiente, autor, fecha, motivo,
referencia documental y snapshot fiscal, con historial auditable. Confirmación
humana de cotejo con factura, EUR, IVA interior general sin caja, clase única,
deducción íntegra y mismo trimestre de factura. Primer alcance: facturas
registradas, proveedor ES con identidad conservada, tipos 4/10/21 y desglose
exacto, incluidas líneas libres. Portes, diferencias, deducción parcial, casos
mixtos, rectificativas y otros regímenes siguen pendientes. Revisión vigente
alimenta candidatos 28/29 o 30/31 sin recalcular céntimos ni modificar libros,
pagos o asientos. Cambios fiscales invalidan revisión; pagos y recepción de stock
no. Formularios obsoletos rechazados; cierre de ejercicio/mes o liquidación
preparada/presentada protege la revisión. Migración reversible aplicada en
localhost tras ensayos en PostgreSQL aislado. Develop; no presentación oficial
ni despliegue a producción en este corte. Próximo: periodo registral/deducción
explícito, asignación documental de portes/diferencias y deducción parcial.
Verificado: 705 pruebas superadas, 5 omitidas y 10.597 aserciones; 25 pruebas
específicas y 249 aserciones también en PostgreSQL aislado. Migración reversible;
base temporal eliminada. ES/EN, TypeScript y lint correctos. No se han clasificado
compras reales ni modificado importes o pagos automáticamente.

**Incremento FIN-03.303, diagnóstico documental de compras (05/10):** el 303
muestra en la evidencia recibida los motivos concretos de bloqueo de cada compra,
con enlace al documento: registro, identidad fiscal conservada, país, portes,
desglose y diferencias entre líneas y cabecera. Las líneas personalizadas sin
producto de inventario también se comprueban. Se usan los céntimos almacenados,
sin recalcular cuotas ni atribuir automáticamente IVA a portes o diferencias.
La estimación histórica del 21 % de portes permanece solo en el libro compatible;
no habilita deducción en las casillas oficiales. Consulta de solo lectura, sin
migraciones, cambios de facturas, pagos ni asientos. Compras siguen pendientes:
el siguiente corte es revisión fiscal persistida/auditada, clase de operación,
deducibilidad y periodo registral, antes de alimentar 28/29 o 30/31. No sustituye
la revisión humana ni acredita por sí solo el tratamiento territorial del IVA.
Disponible en develop; pendiente producción. Verificado: 697 pruebas superadas,
5 omitidas y 10.492 aserciones; 14 pruebas específicas/94 aserciones también en
PostgreSQL aislado, cuya base se eliminó al terminar. TypeScript, lint y ES/EN
correctos. La base real de localhost no necesitó migraciones ni modificaciones.

**Incremento FIN-02.C/FIN-03.303, perfil censal (05/10):** configuración anual
en `/company-fiscal/census`, accesible desde Configuración fiscal y Modelo 303.
Obligación 303, régimen, periodicidad, administración, criterio de caja propio
y recibido, prorrata, REDEME y SII con «pendiente» explícito. Versiones nuevas
en cada guardado, autor, motivo, referencia censal y snapshot de identidad;
sin sobrescribir historial ni autoasignar obligaciones. Revisar exige datos
completos, referencia y confirmación para todo el año. No consulta el censo AEAT.
Cambio de identidad o un borrador posterior invalidan el estado revisado.
Ejercicio cerrado o IVA preparado/presentado impiden nuevas versiones. El 303
muestra vigencia y alcance (general trimestral, territorio común, sin caja,
prorrata, REDEME/SII) pero sigue no presentable. Regímenes adicionales y cambios
censales dentro del ejercicio requieren tratamiento por periodos, no anual.
Verificado: 690 pruebas generales superadas, 5 omitidas y 10.458 aserciones;
22 pruebas específicas en PostgreSQL con 226 aserciones. Migración reversible
y aplicada en localhost; base aislada eliminada. TypeScript, lint y ES/EN
correctos. Pendiente producción; no se ha configurado ni revisado ningún perfil
real automáticamente. Próximo corte: compras, fechas registrales/deducción y
clasificación de operaciones adicionales del 303.

**Incremento FIN-03.303, clasificación de gastos (05/10):** formulario específico
desde los documentos del Modelo 303 en develop. Selección interior corriente /
bienes de inversión / pendiente, con confirmación explícita de territorio IVA,
ausencia de criterio de caja, clase única, deducción documental y mismo periodo
de la factura. Autor, motivo, evidencia y cambios conservados en auditoría.
Casillas candidatas 28/29 y 30/31 solo para gastos con evidencia documental
vigente y revisión válida; la base no se prorratea. Cambiar la factura invalida
la revisión; pagos por sí solos no la invalidan. No cambia facturas ni asientos.
Bloqueo por ejercicio/mes cerrado y liquidación preparada o presentada. Casos
mixtos, deducción cero, regímenes especiales, compras y periodos de deducción
posteriores siguen pendientes; no asignación automática de históricos.
Migración local aplicada tras prueba en PostgreSQL aislado. Pendiente producción.
Verificación: suite general con 682 pruebas superadas, 5 omitidas y 10.371
aserciones. Pruebas específicas de revisión y proyección también en PostgreSQL.
PostgreSQL: 15 pruebas y 158 aserciones superadas; migración reversible y base
aislada eliminada tras los ensayos. TypeScript, ES/EN y lint verificados.

**Incremento FIN-03.303 en develop (05/10):** primera pantalla propia de Modelo
303 desde IVA, de solo lectura y alineada con las casillas AEAT 2T–4T/2026.
Evidencia documental para candidatos 01–09 y rectificaciones 14/15, preservando
importes redondeados de origen. Operaciones ambiguas y todas las deducciones
pendientes de clasificación explícita; compensaciones y resultado oficial no
calculados. No habilita exportación ni presentación. Continúa con perfil censal,
clasificación persistida y periodo de deducción antes de completar totales.
Suite general: 673 pruebas superadas, 5 omitidas (incluidas las que requieren
conexiones PostgreSQL independientes); 10.268 aserciones. Sin cambios en datos
de producción ni asientos por consultar esta pantalla.

Esta sección es el punto de entrada y prevalece sobre los estados de las notas
históricas inferiores. No se eliminan tareas anteriores ni se crean planes
paralelos. Cada entrega actualiza esta cola, su evidencia y el siguiente paso.

**Producción:** `v2026.10.04.1` (PR #130, deployment
`depl-a2e76b9e-c4bd-4532-88af-287228dc6b58`): revisión de gastos históricos
activa para administradores, con filtros por pagos, comparación y adopción de
evidencia cuando importes y líneas fiscales coinciden exactamente. El cálculo
documental agrupado conserva vista previa y evidencia versionada.
También publicados: edición unificada de gastos, anulación
trazable de pagos, líneas libres de compras, adjuntos, logo y banca.
Comprobación visual autenticada de Safari aún pendiente: las pruebas automatizadas
y comprobaciones remotas no sustituyen esa validación.

| Orden | Bloque | Estado vigente | Siguiente entrega / puerta de salida |
| --- | --- | --- | --- |
| 1 | FIN-01, integridad | Abierto; concurrencia PostgreSQL verificada para numeración, emisión directa y borrador/contabilización de IVA en develop | Extender ensayos a conversiones, rectificaciones, pagos y cierres; revisar todos los accesos de emisión antes de cerrar el bloque |
| 2 | FIN-02.B, libros y conciliación | Implementado en develop; incluye borrador, aprobación y contabilización separados; pendiente publicación | Subcuentas, libros y liquidación revisable; completar pruebas de concurrencia independientes y puertas de publicación |
| 3 | FIN-02.B, cuotas de los libros | Implementado en develop; pendiente publicación | IVA soportado/deducible/no deducible separado en libro, resumen y CSV; verificar compatibilidad de liquidación |
| 4 | FIN-02.A/B, cálculo y normalización | Cálculo documental y revisión histórica publicados y activos para administradores | Validación visual autenticada; compras, regímenes adicionales, portes, fechas registrales y preparación sin asientos implícitos |
| 5 | FIN-02.C, identidad y perfil | Directorio parcial y perfil censal anual versionado en develop | Completar direcciones/snapshot e integración, duplicados, cambios censales por periodo y validación con asesoría |
| 6 | FIN-03.303 | Proyección preliminar y clasificación auditada de gastos en develop; no presentable | Clasificar compras y casos adicionales, perfil censal, fechas registrales/deducción, compensaciones y expediente trazable; objetivo interno 30/10 |
| 7 | FIN-04 y FIN-05.390 | Planificados | VERI*FACTU en pruebas y resumen anual/exoneración 390; objetivos internos 13/11 |
| 8 | FIN-05.347 y otros modelos aplicables | Planificados | Terceros, exclusiones y trimestres; objetivo interno 27/11; conservar 111/115, 190/180, 349 y 200/202 |
| 9 | FIN-06/07 | Planificados | Ensayo integral 11/12, activación aprobada 18/12 y estabilización hasta 31/12; objetivo operativo 01/01/2027 |

La numeración indica prioridad, no autorización para saltar dependencias. El
incremento de subcuentas adelanta un defecto concreto de conciliación; no cierra
FIN-01 ni sustituye la integración del motor decimal. Los objetivos temporales
siguen siendo internos, no plazos tributarios ni garantías de conformidad.

**Backlog transversal conservado:** arquitectura de 20 áreas y secciones propias,
formularios coherentes, ES/EN, móvil, rendimiento p75, permisos por rol, agilidad
comercial, energía/CUPS, Ingeniería y horizontes 1–4. Se ejecutan dentro del corte
afectado o después de estabilizar sus dependencias fiscales. No reintroducir
«visitados recientemente» ni favoritos en el sidebar. La app móvil mantiene su
proyecto independiente y contratos comunes con la web.

Referencias de ejecución: [modelos 303/390/347](fiscal-models-303-390-347.md),
[preparación fiscal](fiscal-readiness-2027.md) y [energía/CUPS](energy-supply-roadmap.md).
No hay envío AEAT ni pagos automáticos habilitados por este roadmap.

## Implantación del nuevo diseño por secciones — 05/10/2026

Decisión del usuario: aplicar los diseños sección por sección. En **cada sección**,
los datos y acciones del diseño que todavía no estén disponibles se incorporan a
este roadmap, con preparación de interfaz y contrato para su futura conexión.
Esta regla se aplica al libro diario y a todos los siguientes cortes. No se
presupone el contenido de diseños que aún no se hayan proporcionado.

Por cada corte se debe:

1. Contrastar cada campo, filtro, pestaña y acción del diseño con el modelo,
   payload, rutas y permisos reales; reutilizar capacidades existentes.
2. Preparar componentes y tipos aditivos compatibles con el payload actual.
   Distinguir datos no suministrados de datos consultados y vacíos; no inventar
   documentos, nombres, importes, auditoría ni enlaces para completar el diseño.
3. Registrar aquí las carencias con identificador, estado, dependencias y criterio
   de aceptación. Una interfaz preparada no equivale a backend implantado.
4. Conectar las acciones solo cuando existan rutas y autorización en servidor.
   Conservar las funciones vigentes, documentar las ausentes y preparar su flujo.
5. Verificar con datos reales estados poblados, vacíos, no disponibles y errores,
   permisos, ES/EN, teclado, móvil y escritorio. Registrar las comprobaciones
   pendientes antes de considerar una sección terminada o publicada.

### UI-SIDEBAR — Componente común de navegación, 06/10/2026

Prioridad indicada por el usuario: completar primero el sidebar del diseño,
después continuar con el resto de la página. El ajuste anterior solo cambiaba
la paleta del diario; este corte modifica el componente compartido.

- Preparado: ancho de 238 px, fondo verde, marca de agua vectorial, palabra
  AREVANTES y línea verde, iconos finos, filas de 35 px, selección activa y grupos
  separados. El menú personal pasa a la cabecera y el pie muestra la empresa real
  y el ejercicio del contexto, con acceso autorizado a configuración fiscal.
- En el contexto financiero se presentan enlaces directos en Contabilidad,
  Gestión y Configuración. Se derivan de la navegación autorizada del servidor;
  las áreas restantes siguen accesibles desde «Otras áreas». Fuera de ese contexto
  se conserva la estructura de áreas con el nuevo componente visual.
- Balances, resultados, mayor, terceros y mapeos utilizan URLs con `view` para
  abrir las capacidades existentes. No se presentan como módulos nuevos ni se
  duplican informes. Continúa pendiente separarlos en endpoints propios y cargar
  únicamente los datos de cada vista, según UI-JOURNAL-08 y el plan transversal.

| ID | Pendiente del diseño | Preparación y criterio de aceptación |
| --- | --- | --- |
| UI-SIDEBAR-01 | Destino independiente «Asientos» | No existe separado del diario. Definir diferencia funcional, ruta y permiso; preparar su enlace cuando exista, sin duplicar el mismo destino con otra etiqueta |
| UI-SIDEBAR-02 | «Series y diarios» y «Periodos contables» como destinos propios | Series, ejercicio y cierre están distribuidos entre configuración y opciones contables. Separar pantallas reutilizando esas funciones; conservar bloqueo y permisos; incorporar enlaces autorizados |
| UI-SIDEBAR-03 | «Libros oficiales» e «Informes» con índice propio | Libros emitidos/recibidos e informes actuales se conservan con su nombre real; definir índice y alcance de libros oficiales antes de usar esa etiqueta; no confundir libros de IVA con libros legalizados |
| UI-SIDEBAR-04 | Selector directo de empresa y ejercicio del pie | Empresa actual real y acceso a configuración preparados. No existe cambio de empresa activo; solo permitir selección cuando haya catálogo autorizado y alcance definido. Selector de ejercicio debe conservar filtros y corresponder al ejercicio seleccionado de cada módulo |
| UI-SIDEBAR-05 | Aceptación visual y responsive | Comparar con la referencia en Safari con sesión local, verificar selección, navegación por teclado, colapsado y móvil; registrar validación e integración en develop |

Validación local del corte: Vite, TypeScript global, catálogo ES/EN, ESLint y
formato de los archivos modificados, Pint y Composer correctos. Suite Laravel:
733 pruebas correctas, 5 omitidas y 10.863 aserciones. Comprobado visualmente en
Safari con sesión real del entorno local: logo, dimensiones, grupos, selección del
diario, usuario en cabecera y empresa/ejercicio al pie. Preparación compatible con
colapsado y móvil; su aceptación visual e interacción siguen pendientes porque
el Mac se bloqueó durante la revisión. Aplicado y recompilado en develop local,
conservando los cambios contables ajenos que ya estaban en ese checkout.

### UI-JOURNAL — Libro diario, asientos y apuntes

Referencia: diseño adjunto del usuario «Libro diario, asientos y apuntes.png».
Estado: **interfaz implementada localmente y contrato preparado; backend adicional,
validación visual autenticada e integración/publicación pendientes**.

Disponible actualmente: fecha, número, concepto, tipo técnico, estado, líneas,
cuentas, debe/haber, documento de origen cuando exista, alta manual, reversión,
sincronización, cierres e informes. Lista/detalle, búsqueda y filtros locales,
exportación CSV de la selección y paleta del diseño implementados. Las opciones
contables se conservan en «Más opciones».

Contrato aditivo preparado en `resources/js/types/accounting-journal.ts`, consumido
por `resources/js/pages/accounting/index.tsx`: `journal`, `notes`, `documents` e
`history`. Campos ausentes (`undefined`) significan capacidad no suministrada;
`null` o arrays vacíos significan consulta realizada sin contenido. El controlador
actual todavía no emite estos campos. No inferir un diario «General» del tipo,
ni observaciones del concepto, ni adjuntos a partir del enlace al origen.

| ID | Capacidad pendiente / preparación actual | Dependencias y siguiente implantación | Criterio de aceptación |
| --- | --- | --- | --- |
| UI-JOURNAL-01 | Diario contable: columna, campo de detalle y selector preparados; selector deshabilitado sin catálogo | Definir asociación con series/diarios existentes y reglas de asignación; suministrar catálogo autorizado completo, no solo diarios de los asientos cargados; enlazar con configuración | Nombres reales en lista y detalle; filtro global; históricos sin asociación siguen visibles; no asignar diarios ficticios |
| UI-JOURNAL-02 | Observaciones: sección y campo opcional preparados | Confirmar si existe información reutilizable en origen o si requiere persistencia propia; definir autor, revisión y permisos de modificación | Observaciones reales separadas del concepto; ausencia y vacío distinguibles; cambios auditados sin alterar contabilidad |
| UI-JOURNAL-03 | Adjuntos: lista, contador, nombre, tamaño, fecha, consulta y descarga preparados | Reutilizar adjuntos de gastos/compras/ventas que correspondan al origen; definir adjuntos directos de asiento si se necesitan; emitir URLs autorizadas y metadatos del contrato | Lista completa sin duplicados; contador real; consulta/descarga con comprobación de acceso; no exponer rutas privadas ni mostrar PDF ficticio |
| UI-JOURNAL-04 | Historial: pestaña y cronología preparadas | Reutilizar auditoría existente; entregar eventos del asiento y relaciones de reversión, con actor/fecha; definir alcance de eventos del origen | Eventos reales ordenados y trazables; acceso autorizado; ninguna actividad inventada; reversión identificable |
| UI-JOURNAL-05 | Editar: flujo planificado; acción no habilitada | No existe ruta de edición de asiento en las rutas contables revisadas; definir borradores editables frente a correcciones por reversión/nuevo asiento, con permisos y controles de periodo/documento de origen | No modificar silenciosamente asientos contabilizados; periodo cerrado protegido; validación de líneas, motivo y auditoría; acción solo cuando el servidor la autorice |
| UI-JOURNAL-06 | Duplicar: flujo planificado; acción no habilitada | No existe ruta de duplicado revisada; reutilizar formulario manual con datos precargados, nueva fecha/número y confirmación antes de contabilizar; no copiar vínculos del origen automáticamente | Abrir propuesta editable; no contabilizar al pulsar duplicar; nuevo número asignado en servidor; balance y permisos verificados |
| UI-JOURNAL-07 | Filtros avanzados, paginación y exportación completa | El controlador limita a 250 asientos; búsqueda, filtros y CSV actuales solo operan sobre los cargados. Preparar consultas en servidor y exportación con idénticos filtros, incluidos diario, estado e importes | Filtrar todo el ejercicio sin truncamiento silencioso; totales y recuentos coherentes; exportación etiquetada y autorizada; filtros persistentes al cambiar selección |
| UI-JOURNAL-08 | Navegación contable y cabecera común del diseño | Continuar la separación de destinos financieros ya planificada; reutilizar navegación por permisos, búsqueda global, ejercicio, notificaciones y usuario; mantener acciones de cabecera existentes | Diario e informes con URL propia; selección de ejercicio coherente; cabecera/sidebar fieles al diseño sin perder accesos reales; no introducir favoritos en sidebar |
| UI-JOURNAL-09 | Tipos y estados en lenguaje de usuario | Preparar catálogo ES/EN para tipos, estados, fechas y nuevas etiquetas; evitar exponer códigos técnicos | Español e inglés completos; catálogo i18n validado; estado contable y reversión diferenciados. Nuevas etiquetas ES/EN ya incorporadas y comprobadas en este corte |
| UI-JOURNAL-10 | Verificación e integración | Entorno Laravel y rutas Wayfinder preparados; compilación y tipos globales comprobados; queda comprobar con autenticación y datos representativos | Compilación/tipos globales, lint, formato y pruebas proporcionales; revisión visual con referencia en escritorio/móvil; publicar solo tras validación |

Orden dentro del corte: 01–04 (datos de lectura) → 07 y 09 (consulta completa e
idioma) → 05–06 (operaciones, tras definir integridad) → 08 y 10 (integración y
aceptación). Se preservan las prioridades y dependencias FIN-01/FIN-02 y el resto
del roadmap; sin fechas nuevas ni declaración de capacidades como publicadas.
La preparación actual de 05–08 es especificación de flujo/dependencias; no existen
endpoints nuevos ni una implementación operativa de esas capacidades.

Validación de integración del 05/10: compilación Vite, TypeScript global, ESLint y
formato de los archivos modificados, catálogo ES/EN y `composer validate --strict`
correctos. Suite Laravel: 733 pruebas correctas, 5 omitidas y 10.863 aserciones.
PR de integración: [#132](https://github.com/bigjoky/arevantes.com/pull/132).
Revisión visual autenticada pendiente; no considerar cerrada UI-JOURNAL-10.

## Registro de entregas y detalle conservado

### 05/10/2026 — Concurrencia fiscal con conexiones independientes (FIN-01)

- Ensayo reproducible con dos procesos PHP y conexiones PostgreSQL separadas.
  La prueba exige que ambas conexiones estén esperando bloqueos reales antes
  de liberarlos; no se limita a repetir peticiones secuenciales o usar mocks.
- Crear el mismo borrador simultáneamente produce un único expediente sin
  asiento; contabilizar el mismo aprobado produce un único asiento cuadrado.
- Numerar dos documentos produce números distintos y avanza dos posiciones.
  Emitir la misma factura simultáneamente consume una sola numeración y genera
  un solo asiento, conservando el documento emitido y bloqueado.
- Cuatro pruebas, 34 aserciones en base PostgreSQL temporal aislada. Arnés
  protegido por driver y nombre terminado en `_concurrency_test`; se omite en
  SQLite y bases normales. Tiempos de espera acotados y procesos limpiados.
- No se alteró código de emisión ni datos reales: estos casos verifican los
  bloqueos existentes. FIN-01 sigue abierto para conversiones, rectificaciones,
  pagos, cierres y auditoría completa de permisos.
- Regresión focalizada de IVA/emisión: 20 correctas, 4 omitidas en SQLite,
  352 aserciones; formato y sintaxis correctos. Base temporal eliminada tras
  finalizar. Sin cambios de interfaz ni despliegue requerido para este ensayo.

### 04/10/2026 — Comparación documental del borrador de IVA (FIN-02.B)

- En develop: comparar la evidencia del borrador con los libros actuales antes
  de actualizarlo. Recuenta documentos añadidos, retirados y modificados, sin
  compensar cambios entre documentos ni confundir el orden de filas con cambios.
- Muestra identidad, fecha, bases, cuotas y desglose por tipo; compara IVA
  devengado, soportado, deducible/no deducible y resultado. Cambios contables se
  señalan aparte. Vista acotada a 50 documentos, con recuentos completos.
- Actualizar guarda la huella anterior y el resumen de diferencias en el
  historial; la auditoría del expediente conserva el snapshot anterior. La
  aprobación previa sigue invalidada y debe revisarse otra vez.
- Alcance contable, sin presentación AEAT ni cambios automáticos de documentos.
  Regresión: 667 correctas, 1 omitida y 10.214 aserciones; 8 específicas de
  comparación/flujo. Persistencia e historial comprobados en PostgreSQL aislado
  (5 pruebas, 59 aserciones); la base temporal se eliminó al terminar.
  Tipos, lint, catálogo ES/EN y compilación correctos. Pendiente publicación
  con el flujo revisable de liquidación.

### 04/10/2026 — Controles previos visibles de liquidación (FIN-02.B)

- En develop: lista de comprobaciones actuales para borrador y vista previa:
  conciliación, movimientos, equilibrio, ejercicio, periodo y cuentas activas
  contabilizables. Los borradores también contrastan su huella con los libros.
- La pantalla señala cada bloqueo, incluidas las cuentas afectadas, y deshabilita
  aprobación/contabilización cuando procede. El servidor vuelve a comprobarlo:
  un periodo cerrado o una cuenta desactivada no llega hasta JournalPoster.
- Los controles son contables; no certifican conformidad censal o del 303.
  Se conservan los siguientes pasos: concurrencia independiente de FIN-01,
  perfil censal, fuentes fiscales completas, sin actividad y casillas 303.
- PostgreSQL aislado: 26 pruebas y 292 aserciones; base temporal eliminada.
  Regresión: 664 correctas, 1 omitida y 10.200 aserciones. Tipos, lint, catálogo
  ES/EN, formato y compilación correctos. Pendiente publicación con el flujo
  de borradores.

### 04/10/2026 — Revisión y contabilización separadas de IVA (FIN-02.B)

- En develop: crear borrador → revisar/aprobar con motivo → contabilizar.
  Crear y aprobar no generan asientos. Solo un expediente aprobado puede
  contabilizarse; la presentación continúa reservada a los ya contabilizados.
- Evidencia versionada de documentos de los libros, resumen, tipos, cuentas y
  propuesta de asiento, con huella SHA-256. Un cambio de entradas obliga a
  actualizar el borrador y aprobarlo otra vez; se conserva el historial de
  revisión con autor, motivo y fecha. Los expedientes previos siguen operativos.
- Transiciones bajo bloqueo del expediente; contabilización y comprobación
  toman también el bloqueo de ejercicio utilizado por JournalPoster. Repeticiones
  no duplican asientos; los errores de contabilización revierten la operación.
- Alcance contable, no modelo 303 oficial ni presentación AEAT. Siguen pendientes
  pruebas de concurrencia con conexiones independientes para todo FIN-01,
  perfil fiscal, casos sin actividad y cobertura normativa/regímenes adicionales.
- PostgreSQL aislado: 24 pruebas y 269 aserciones; base temporal eliminada.
  Regresión completa: 662 correctas, 1 omitida y 10.177 aserciones. Tipos,
  lint, traducciones y compilación verificados. Sin cambios en documentos reales
  ni publicación.

### 04/10/2026 — Vista previa contable de liquidación de IVA (FIN-02.B)

- En develop: el resumen trimestral muestra una vista previa de cuentas, debe,
  haber y totales del asiento de liquidación. Consulta sin escrituras y acción
  identificada expresamente como preparar y contabilizar.
- Preparar ya no sincroniza todos los documentos de forma implícita: requiere
  conciliación previa y rechaza diferencias sin generar asientos ni expediente.
  La propuesta y la contabilización comparten la composición del asiento.
- Se conserva el circuito existente de liquidación; no se etiqueta como modelo
  303 oficial. Pendientes revisión/aprobación separadas, control de concurrencia,
  periodos sin actividad, perfil censal y snapshot completo antes de cerrar FIN-02.
- Verificación: 21 pruebas específicas y 230 aserciones; regresión completa
  con 659 correctas, 1 omitida y 10.138 aserciones. Tipos, lint y catálogo ES/EN
  y compilación correctos. Pendiente publicación con los incrementos de
  libros/subcuentas.

### 04/10/2026 — Revisión asistida de gastos históricos (FIN-02.A/B)

- Publicado mediante PR #130, commit `f32461f40f92ff43fb31bd682e789ab10a6f8875`,
  versión `v2026.10.04.1`; despliegue correcto el 04/10/2026 a las 23:12 Madrid.
  Rutas, activación, migración e índice verificados remotamente; `/up` y `/login`
  responden 200. No se modificó ningún gasto real durante la comprobación.
- Regresión de publicación: 638 correctas, 1 omitida, 9.983 aserciones.
  GitHub confirmó calidad y pruebas en PHP 8.4 y 8.5. La validación visual
  autenticada en Safari sigue pendiente y no se da por realizada.
- Nueva cola administrativa paginada desde Gastos para documentos aún no
  versionados. Clasifica cada gasto sin modificarlo y explica bloqueos por pagos,
  moneda, régimen, retención, desglose, ejercicio, periodo o declaración protegida.
- Los compatibles abren la edición con el cálculo documental seleccionado y un
  motivo inicial. Siguen siendo obligatorios la vista previa reciente, el motivo
  de corrección, el control de revisión y todas las comprobaciones contables.
- No existe migración masiva ni escritura al visitar la cola. Los gastos pagados,
  con pagos parciales, periodos cerrados o declaraciones preparadas/presentadas no
  ofrecen edición; solo evidencia sin impacto cuando la coincidencia es exacta.
  Acceso exclusivo de administrador con permisos financieros.
- Consulta acotada a 50 registros y apoyada por índice parcial de documentos sin
  evidencia, evitando penalizar los ya revisados. Sin recalcular históricos.
- Filtros en servidor para todos, con pagos y sin pagos. Los documentos
  compatibles muestran en solo lectura base, IVA matemático, IVA documental,
  ajuste, total, pagos y saldo. La comparación de un gasto protegido nunca
  habilita edición ni escritura.
- Cierre del ciclo histórico: un administrador puede adjuntar evidencia a un
  gasto protegido solo si subtotal, IVA, total y cada línea fiscal coinciden
  exactamente con el cálculo reproducible. La operación bloquea el registro,
  comprueba revisión optimista, conserva motivo y autor en auditoría y no cambia
  pagos, importes, líneas fiscales ni asientos. Diferencias e incompatibilidades
  continúan bloqueadas para corrección contable expresa.
- Bloque documental/correcciones verificado en PostgreSQL aislado: 21 pruebas y
  170 aserciones; la base temporal se eliminó al terminar. Regresión completa:
  658 correctas, 1 omitida y 10.128 aserciones; compilación, tipos, lint, formato
  y catálogo ES/EN correctos.


### 29/09/2026 — Vista previa documental de gastos (FIN-02.A/B)

Activación local y posterior publicación en producción solicitadas por el
usuario. Producción `v2026.09.29.1`, PR #129, commit `ea6f8f9c`, deployment
`depl-a2dcbed9-7d75-4753-bc80-ab0eb78890a2` correcto y activo. Configuración,
columnas, migración y ruta comprobadas remotamente; `/up` y `/login` responden
200. No se han recalculado históricos. Pendiente comprobación visual autenticada.
- Panel común ES/EN en alta y edición: base, IVA calculado, cuota documental,
  diferencia de redondeo, total e IVA deducible. Motivo del ajuste conservado.
- Vista previa calculada en servidor, sin crear gastos ni asientos. Guardado
  sujeto a una revisión cifrada ligada al usuario y al cálculo, válida 15 minutos;
  cambios fiscales o caducidad obligan a revisar nuevamente. Peticiones limitadas.
- Identificadores estables de líneas; cambios del formulario invalidan la vista
  y cancelan solicitudes pendientes. Un documento versionado no permite volver
  silenciosamente al cálculo anterior.
- Activación sigue deshabilitada por defecto. Sin migrar datos reales, sin
  publicación en producción y sin envío AEAT. Pendiente prueba visual autenticada
  antes de activar; se mantienen los límites de EUR y regímenes soportados.
- Verificación específica: 10 pruebas de cálculo/persistencia/vista previa;
  36 pruebas fiscales y de gastos sobre PostgreSQL aislado (366 aserciones).
  Suite completa: 654 correctas, 1 omitida y 10.074 aserciones; compilación,
  tipos, lint y catálogo bilingüe comprobados.


Las notas siguientes conservan decisiones, alcance y pruebas de cada corte.
«Pendiente de publicar» en una nota antigua describe aquel momento; para el estado
actual consultar la tabla anterior y la última publicación verificada.

## FIN-02.A — persistencia del cálculo documental de gastos — 29/09/2026

- Adaptador `grouped_v1` para filas fiscales de gastos: base documental, cuota
  agrupada por tipo y reparto de deducibilidad. Identificadores de línea estables,
  cadenas decimales exactas y límites del almacenamiento `numeric(12,2)`.
- Alta y corrección pueden guardar `tax_calculation_version` y snapshot con
  entradas, cuotas originales, resultado, motivos y huella SHA-256 reproducible.
  La auditoría de corrección conserva también la evidencia anterior. Esta huella
  no es un registro VERI*FACTU ni una certificación fiscal.
- Opción explícita de servidor `FISCAL_GROUPED_EXPENSE_CALCULATION`, **false por
  defecto**, y rol administrador. No activada en localhost ni producción, ni
  ofrecida aún en formularios. No habilitar hasta completar vista previa y UI.
- Una edición versionada nunca cae al cálculo legado: se recalcula con su
  versión compatible o se bloquea si está deshabilitada/desconocida. Datos de
  snapshot enviados por el cliente no se aceptan como evidencia calculada.
- Alcance inicial EUR y regímenes general/exento/no sujeto, sin recargos ni
  retenciones; casos incompatibles se rechazan. Las filas fiscales no sustituyen
  todavía a componentes/precios de productos en compras. Cálculos contables
  existentes conservan sus adaptadores: no se declara completada la migración decimal.
- Migración aditiva nullable, sin backfill. El rollback se bloquea si existe
  evidencia; nunca borrar snapshots para forzarlo. No se ha aplicado a datos reales.
- Siguiente entrega: vista previa del servidor y formularios con cuotas por grupo,
  motivo de ajuste y conservación de identificadores; después adaptador de compras.
- Verificado: 652 pruebas generales superadas, una omitida y 10.060 comprobaciones;
  bloque PostgreSQL: 34 pruebas y 352 comprobaciones. Compilación, formato y
  catálogo bilingüe correctos. Corregida además una prueba fiscal antigua que
  confundía el contador de asientos creados con su identificador real.

## FIN-02.B — desglose de cuotas en libros — 29/09/2026

- La columna `vat` del informe representa siempre la cuota documental completa.
  El libro recibido añade `deductible_vat` y `non_deductible_vat`; resumen y tipos
  separan también las tres magnitudes. La liquidación y la comparación contable
  siguen usando exclusivamente la parte deducible, sin cambiar cuotas persistidas.
- La pantalla muestra columnas propias y distingue «Base + IVA» del neto pagadero.
  El CSV recibido pasa a `supported_vat`, `base_plus_vat`, `deductible_vat` y
  `non_deductible_vat`; quienes procesen ese CSV deben adaptar las cabeceras.
  El CSV emitido mantiene su estructura anterior.
- Recargo de equivalencia no se suma como IVA ni duplica la base en este libro.
  Compras y gastos históricos sin desglose conservan el tratamiento previo de
  deducción íntegra por compatibilidad: no constituye una validación fiscal nueva.
  Queda pendiente revisar esos históricos y los regímenes con el perfil censal.
- No se recalculan documentos, pagos ni declaraciones existentes. Próximo tramo:
  retomar adaptadores/persistencia del motor decimal y clasificar portes y fechas
  registrales; seguir separando preparación, aprobación y presentación.
- Verificado: 644 pruebas generales superadas, una omitida y 10.016 comprobaciones;
  20 pruebas fiscales PostgreSQL y 219 comprobaciones. Compilación, TypeScript,
  ESLint, formato y catálogo bilingüe correctos. Pendiente validación visual
  autenticada y publicación aislada; no desplegado todavía en producción.

## FIN-02.B — conciliación y liquidación por subcuenta — 28/09/2026

- Nuevo lector contable de solo lectura: 472/477, descendientes por `parent_code`
  y cuentas mapeadas explícitamente por tipos de IVA. No usa prefijos arbitrarios;
  valida naturaleza de activo/pasivo y evita duplicados y bucles de jerarquía.
- Conserva movimientos de cuentas/tipos inactivos; excluye asientos no
  contabilizados, apertura/cierre y sus reversiones, y liquidaciones tributarias.
  Incluye correcciones operativas con su signo real.
- Informe con detalle de cuentas y totales. Liquidación compensatoria contra
  esas mismas cuentas, con signo inverso cuando corresponda, y desglose guardado
  en snapshot. No modifica liquidaciones ni documentos anteriores.
- Sin migraciones. El perfil fiscal, los portes, las fechas registrales y la
  separación de vista previa/preparación siguen pendientes; no es un 303 oficial.
- Verificación: 638 pruebas generales superadas, una omitida, 9.968 comprobaciones;
  fiscalidad en PostgreSQL aislado: 14 pruebas y 171 comprobaciones. Sin tocar
  documentos reales ni activar cambios en producción.
- Próximo corte funcional: separar cuota soportada/deducible en libros y exportación;
  después retomar adaptadores y persistencia del motor decimal. Concurrencia FIN-01
  continúa abierta y es puerta de aceptación global, no un bloque completado.

## Producción verificada: edición unificada de gastos — 28/09/2026

- Publicada `v2026.09.28.3`, PR #128, commit de producción
  `4f7464322410f7687c4a3d05a5a3014980603b19`.
- Cloud `depl-a2db5330-0419-49f5-b22a-0a44ef2965e1`: succeeded, 1m57s;
  `currentDeploymentId` confirmado, sin otros despliegues en curso.
- Edición administrativa de gastos pendientes activa y corrección separada del
  proveedor retirada. Se conservan el historial y las protecciones contables.
- Rama de publicación aislada: 596 pruebas superadas, una omitida y 7.766
  comprobaciones; PostgreSQL: 21 pruebas y 211 comprobaciones. CI PHP 8.4/8.5,
  calidad, TypeScript, traducciones y compilación aprobados.
- Comprobación remota de solo lectura: `/up` y `/login` HTTP 200; rutas
  `expenses.edit` y `expenses.update` activas, `expenses.issuer.update` ausente,
  servicio de corrección disponible y `fiscal_entities` todavía ausente.
- Sin migraciones ni cambios de documentos/pagos reales. El directorio fiscal y
  el motor decimal aislado permanecen en desarrollo; no se altera su planificación.
  Pendiente comprobación visual autenticada en Safari. Tras registrar ajustes,
  aplicar correcciones hacia delante: no volver a código que ignore su historial.


## Simplificación de la ficha de gastos — 28/09/2026

Retirados el formulario y la ruta independientes de corrección de nombres del
proveedor. La acción única es **Editar gasto**, con sus permisos y protecciones.
Se conserva el historial anterior de correcciones del emisor, sin borrar datos.
Los gastos pagados ya no tienen una vía alternativa para modificar el emisor.


## Corrección administrativa de gastos pendientes — 28/09/2026

Implementada en `develop` la acción **Editar gasto** en la ficha, exclusiva para
administradores con permisos de gestión. Permite corregir categoría, proveedor,
instalación, documento, emisor, fechas, moneda, desglose fiscal, retención y notas.
Los adjuntos siguen gestionándose desde la ficha. El número interno, autor y
pagos no se sobrescriben desde el formulario.

- Motivo obligatorio, copia anterior/posterior y autor en auditoría, con historial
  visible y detección de formularios obsoletos.
- Bloqueo de gastos pagados y de ejercicios/períodos cerrados o declaraciones
  preparadas/presentadas. Un cambio de fecha comprueba origen y destino.
- Conserva asientos originales; compensa valores anteriores y contabiliza los
  nuevos dentro de la misma transacción. Una discrepancia previa documento/libro
  bloquea la corrección para revisión, sin ocultarla.
- Pagos parciales conservados; el nuevo total no puede ser inferior a lo pagado.
  Cambiar proveedor o moneda exige cancelar antes los pagos existentes.
- Reutiliza las validaciones de alta; no activa aún el motor decimal aislado ni
  recalcula históricos. Sin migraciones ni cambios de datos reales.
- PostgreSQL aislado: 25 pruebas, 248 comprobaciones superadas. Regresión general:
  636 pruebas superadas, una omitida y 9.990 comprobaciones. Compilación,
  TypeScript, traducciones ES/EN y formato revisados. Pendiente prueba visual
  autenticada y despliegue; no habilitado todavía en producción.


## FIN-02.A — primer incremento del motor decimal — 28/09/2026

Implementado `SupplierInvoiceCalculator` como servicio puro y versionado, todavía
sin conectar con formularios, persistencia, asientos ni informes de producción.
No se recalculan documentos históricos. FIN-02.A sigue abierto.

- Importes decimales exactos, bases documentales con motivo de ajuste e IVA
  redondeado por grupo; reparto determinista de céntimos por identificador estable.
- Cuota documental con diferencia máxima de 0,02 EUR por grupo en esta primera
  política técnica, sin multiplicarla por líneas. No es una tolerancia legal.
- Deducibilidad parcial y abonos con inversión exacta de los importes.
- Conservado el caso de referencia: base 3,46 + cuota documental 0,34 = 3,80 EUR.
- Alcance inicial: EUR, magnitudes positivas, factura o abono completo, IVA
  ordinario, exención y no sujeción. Regímenes y campos no soportados se rechazan.
- Pruebas específicas: 28 casos, 2.105 comprobaciones, incluyendo 500 líneas,
  reordenación, límites de reparto y matriz de tipos/deducibilidad.
- Regresión general: 629 pruebas superadas, una omitida y 9.931 comprobaciones;
  formato PHP y validación de dependencias correctos.

Siguiente entrega: adaptadores de gastos/compras, conservación del cálculo y su
versión, límites de almacenamiento, vista previa y validación de formularios.
Antes de activar: resolver recargos, retenciones, líneas de signo mixto y demás
regímenes aplicables; probar integración contable sin cambiar históricos.


## Prioridad de modelos 303, 390 y 347 — 28/09/2026

Preparado el [plan detallado de los tres modelos](fiscal-models-303-390-347.md),
anexo de esta misma secuencia y basado en documentación AEAT revisada. No implica
que los modelos estén implementados, certificados ni habilitados para presentar.

- FIN-02.A (09/10): motor decimal, bases documentales, IVA agrupado, perfil fiscal,
  identidad unificada, fechas registrales y libros conciliados. Corregir antes
  los portes asumidos al 21 %, la consulta de subcuentas 472/477 y la mezcla de
  vista previa con generación de asientos detectadas en los servicios actuales.
- FIN-03.303 (30/10): borrador por casillas, compensaciones, validación contable,
  expediente y rectificación según reglas aplicables. El 3T/2026 debe seguir el
  circuito verificado de asesoría; no esperar al desarrollo para su presentación.
- FIN-05.390 (13/11): resumen anual desde libros, contraste con los 303 y control
  de obligación/exoneración; información anual del último 303 cuando corresponda.
- FIN-05.347 (27/11): terceros por NIF/país y clave, umbral, IVA incluido, períodos,
  exclusiones, anticipos/abonos y contraste de discrepancias sin envíos automáticos.
- FIN-06/07: ensayo integral hasta 11/12 y activación controlada hasta 18/12;
  reserva de estabilización y objetivo operativo 01/01/2027 conservados.
- Formatos y calendario por ejercicio sujetos a validación oficial; perfiles no
  soportados bloqueados. Preparar/exportar nunca equivale a presentar o pagar.
  Confirmar perfil censal y completar histórico 2026 con la asesoría.

Se conservan el resto de modelos aplicables, VERI*FACTU y las mejoras ya acordadas.
Este corte modifica planificación, no cálculos ni datos fiscales de producción.

## Criterio de cálculo de facturas de proveedor — 28/09/2026

Se incorpora la propuesta del usuario al siguiente bloque fiscal de importes/IVA.
**Diseño acordado para implementar; no cambia todavía el cálculo en producción.**

- Diagnóstico del código: compras redondea base a dos decimales por línea y luego
  calcula/redondea su IVA; la conversión a factura también limita el precio unitario
  a dos decimales. Gastos suma las cuotas calculadas o introducidas de cada fila
  fiscal. Ninguno de estos flujos normaliza aún todo el IVA por grupo impositivo.
- Conservar cálculo decimal de alta precisión para cantidades, precios y descuentos,
  evitando `float` como representación monetaria. Definir escalas, límites y regla
  de redondeo comunes en servidor y vista previa, sin reducir antes el precio unitario.
- Separar base calculada y **base documental de línea**: permitir introducir el
  importe neto que aparece en la factura del proveedor, sin falsear precio/cantidad.
  Guardar ambas bases, diferencia, origen y motivo de ajuste cuando corresponda.
- Agrupar bases documentales por tipo de IVA y tratamiento fiscal compatible;
  calcular y redondear una vez la cuota de cada grupo. Mantener separados recargo
  de equivalencia, exenciones/no sujeción e inversión del sujeto pasivo. Deducibilidad
  y retenciones no deben alterar la base documental ni duplicarla.
- Permitir registrar la **cuota documental del proveedor por grupo**, comparándola
  con la calculada y mostrando el ajuste. No sustituir automáticamente una cuota
  real por otra calculada, ni multiplicar tolerancias por el número de líneas.
  Las diferencias fuera de la política aprobada requieren revisión explícita.
- Si se necesitan cuotas por línea para abonos o informes, repartir los céntimos
  del grupo de forma determinista, garantizando que su suma coincida exactamente
  con el grupo. Incluir portes con el grupo fiscal que corresponda.
- Alinear registro de gastos, facturas derivadas de compras, abonos, PDF, asientos,
  libros fiscales y conciliación. Versionar el criterio: documentos contabilizados
  no se recalculan al visualizarlos; las correcciones usan el circuito auditable.
- Casos de aceptación: precios unitarios con más de dos decimales, descuentos,
  varios tipos, portes, deducibilidad parcial y abonos parciales; dos bases de 0,03
  al 21 % producen una cuota agrupada de 0,01 (no 0,02 por suma de redondeos).
  Conservar el caso documental confirmado de base 3,46, cuota 0,34 y total 3,80,
  registrando la diferencia frente al cálculo automático, sin inventar un céntimo.
- Secuencia: motor decimal y pruebas de grupos; entradas documentales y vista previa;
  persistencia/versionado y correcciones; integración contable/fiscal y despliegue
  aislado tras verificar PostgreSQL. Se mantienen las demás fases del roadmap.

## Corrección trazable de pagos de gastos — 28/09/2026

- Publicada **v2026.09.28.2**, PR #127, commit
  `94ba61aa4def2bf9af3ff651c4d4adb09ee9a098`. Despliegue activo
  `depl-a2da77fb-d72d-4ed0-9699-c2e3d0f0cfc3` completado en 1 min 50 s.
  Tras confirmar ausencia de despliegues activos/en cola y retraso del evento
  automático, se inició uno manual. Verificados `/up` y `/login` HTTP 200,
  ruta de anulación y cuatro columnas de historial; directorio fiscal excluido.
- Implementada anulación administrativa con motivo obligatorio. Conserva importe,
  fecha, origen y referencia del pago original; añade autor, momento, motivo,
  enlace al asiento de reversión y auditoría antes/después.
- La reversión usa la fecha original, únicamente en ejercicio y mes abiertos.
  Es una corrección del registro: no ordena devoluciones ni cancela transferencias.
  Después puede registrarse el pago correcto desde el formulario habitual.
- Los pagos anulados quedan visibles en el historial, pero excluidos de saldos,
  candidaturas de conciliación y sumas operativas. Los asientos conservan acceso
  a su pago de origen. No se permite borrar un gasto con pagos históricos.
- Bloqueados pagos conciliados o generados por banca: deben deshacerse desde banca.
  Protección conservadora de períodos con declaraciones preparadas o presentadas,
  tanto de la fecha del pago como del gasto. No reabre ni modifica declaraciones.
- Exclusivo de administrador con permiso de pagos; validación del gasto propietario,
  bloqueo ante doble envío y frente a conciliación concurrente, reversión y auditoría
  en una sola transacción. Si falla cualquier paso, saldo y registro se conservan.
- Verificado en desarrollo: 601 pruebas generales / 7814 aserciones, una exclusiva
  de PostgreSQL omitida; 42 pruebas PostgreSQL / 625 aserciones tras ampliar controles
  de permisos e historial. Build, TypeScript, ESLint y traducciones correctos.
  Rama de publicación: 593 pruebas correctas / 7744 aserciones y una omitida;
  42 pruebas PostgreSQL / 625 aserciones. CI PHP 8.4/8.5 y calidad aprobados.
  Migración aplicada en localhost y producción. Pendiente comprobación visual
  autenticada; no se han usado pagos reales para una prueba de anulación.
- Operación: después de usar anulaciones, no volver a una versión de código anterior
  que desconozca `voided_at`, porque contaría pagos anulados. Corregir hacia delante.
  El rollback de esquema se bloquea si existen anulaciones para preservar historial.
- Próximos bloques, sin sustituir el roadmap fiscal: corrección de importes e IVA de
  gastos existentes; fechas de recepción/registro/deducción y protección de períodos;
  completar directorio fiscal, modelos y preparación de VeriFactu para 01/01/2027.
  No se han anulado pagos reales ni realizado transferencias durante las pruebas.

## Compras libres y verificación de conciliación — 28/09/2026

- Publicada **v2026.09.28.1**, PR #126, commit
  `16f046ef94578a7c48a6cdaace95f4d4fa338835`. Despliegue activo
  `depl-a2da0684-ac34-4079-bb53-68247fee3b95` completado sin errores.
  Verificados `/up` y `/login` HTTP 200, producto nullable en líneas de compra,
  tipo de asiento de 64 caracteres y directorio fiscal incompleto excluido.
- Implementadas líneas personalizadas sin producto en solicitudes y pedidos:
  descripción, cantidad, coste, descuento e IVA; selección explícita «Línea
  personalizada (sin inventario)» junto al catálogo, en español e inglés.
- Conservadas en conversión a pedido y factura, PDF y aprobaciones por importe.
  No crean artículos, tarifas de proveedor ni entradas de almacén. Los pedidos
  mixtos reciben únicamente sus productos con control de existencias.
- Los costes de líneas libres facturadas se incluyen en rentabilidad de proyectos.
  Migración incremental nullable con rollback bloqueado si existen líneas libres.
- Validación: develop 593 pruebas correctas / 7746 aserciones y una omitida
  exclusiva de PostgreSQL. Rama aislada de release PR #126: 585 pruebas correctas /
  7664 aserciones y una omitida; PostgreSQL aislado: 41 pruebas / 661 aserciones.
  Build, tipos, ESLint y catálogo bilingüe correctos. Migración aplicada a localhost.
  Sin comprobación visual autenticada en Safari en esta entrega.
- Conciliación: ampliadas las pruebas de pagos de gastos con subcuenta bancaria,
  anulación y reintento, y conciliación automática con reclasificación contable.
  La corrección previa de `entry_kind` ya está aplicada en producción (64 caracteres).
  Los registros locales encontrados corresponden al límite anterior de 20;
  no se ha reproducido un nuevo error con la versión actual. Pendiente que el
  usuario confirme entorno y aporte un nuevo error si persiste. No se han
  conciliado movimientos reales para probar.
- Se mantienen las siguientes fases fiscales: corrección administrativa trazable
  de pagos e importes, directorio fiscal y asignación a períodos sin alterar
  declaraciones presentadas. Esta entrega no activa el directorio incompleto.


## Publicación operativa y avance fiscal — 27/09/2026 — v2026.09.27.2

- Publicada PR #125 desde una rama aislada de producción, sin el directorio
  fiscal incompleto. Commit `ffe024c5b6415ac78cd58bb6971c25c5ca12fdc6`;
  despliegue activo `depl-a2d920b5-4f85-44e2-aef5-d00540af7d9a` completado.
- Disponible: adjuntos posteriores en gastos, sustitución/eliminación de logo,
  Cuaderno 43 con oficinas de origen distintas y UTF-8, errores JSON para Safari
  y cuotas de IVA editables al registrar gastos nuevos con tolerancia de ±0,02.
- Se preservan los espacios de las referencias AEB usados en identificadores
  históricos para no duplicar movimientos importados anteriormente.
- La prueba PostgreSQL detectó y corrigió otro error 500: `bank_reclassification`
  (21 caracteres) no cabía en `journal_entries.entry_kind` (20). Migración a 64
  conservando el valor por defecto; rollback bloqueado si truncaría información.
- Verificación de release: 581 pruebas generales / 7610 aserciones, una prueba
  exclusiva de PostgreSQL omitida en SQLite; 46 pruebas PostgreSQL / 614 aserciones
  en base aislada, build, tipos, traducciones, ESLint y CI PHP 8.4/8.5 correctos.
- Comprobación posterior: `/up` y `/login` HTTP 200; petición JSON no autenticada
  devuelve 401 JSON; ruta de adjuntos y migración activas, columna de 64 caracteres,
  sin tabla de directorio fiscal. No se modificaron documentos o pagos reales,
  ni se importó el extracto del usuario. Pendiente comprobación visual autenticada
  en la aplicación instalada de Safari.
- Siguiente prioridad: corrección administrativa de pagos ya contabilizados con
  reversión y trazabilidad; después importes de gastos existentes, líneas libres
  de compra y asignación a períodos fiscales sin alterar declaraciones presentadas.
## Correcciones operativas de finanzas y compras — 27/09/2026

Se añaden al roadmap existente, sin sustituir las fases fiscales ni el directorio
de entidades. Orden de ejecución y criterios de aceptación:

1. **Evidencias y logo**: permitir adjuntos después de crear o pagar un gasto,
   con permisos, validación y limpieza de archivos si falla la escritura;
   sustituir/eliminar el logo sin perder el anterior ante errores de validación
   o persistencia, y renovar la imagen mostrada. Publicado en v2026.09.27.2.
2. **BBVA Cuaderno 43**: reproducir con el fichero del usuario (solicitado), sin
   importar movimientos reales. Corregida la conversión de referencias y registros
   Windows-1252 a UTF-8 después de separar sus campos de longitud fija; mensajes
   comprensibles cuando la vista previa recibe HTML o una sesión caducada.
   Prueba sintética con Ñ, tildes y puntos suspensivos. El usuario confirma
   aplicación web instalada desde Safari y aporta el extracto real. Reproducido
   un rechazo adicional: el registro 22 contiene oficina de origen, que puede
   diferir de la de la cuenta; posiciones 3–6 libres. Eliminada la comparación
   incorrecta, manteniendo cabecera, IBAN, pie, bloques y control de totales.
   Vista previa local del fichero real: 90 movimientos, 01/01–31/08/2026,
   JSON válido y controles cuadrados, sin importar ni copiar el extracto al repo.
   Causa del mensaje genérico en Safari: la política global de excepciones
   ignoraba `Accept: application/json` y redirigía los errores de validación.
   Restaurado `expectsJson()` para responder 422 con errores estructurados.
   Referencia: Cuaderno 43, apartado 1.2 (publicado por Bankinter):
   https://docs.bankinter.com/file_source/empresas/estaticos/stf/plataformas/empresas/gestion/ficheros/formatos_fichero/norma_43_castellano.pdf
   Publicado en v2026.09.27.2; pendiente verificación visual autenticada en Safari.
   Validación tras corregir el caso real: 581 pruebas correctas / 7652 aserciones,
   una comprobación exclusiva de PostgreSQL omitida en SQLite. No se han escrito
   movimientos del fichero aportado en ninguna cuenta local ni de producción.
3. **Pagos erróneos**: acción administrativa de anular/corregir con motivo,
   historial antes/después y asiento de reversión, seguida de nuevo pago con
   fecha correcta. Comprobar conciliaciones, ejercicios/períodos cerrados,
   saldos y doble envío. No borrar silenciosamente pagos contabilizados.
4. **Importes e IVA del proveedor**: cuotas editables por línea y ajuste explícito
   de redondeo documentado; recalcular saldo, deducibilidad y retenciones.
   Primera entrega publicada: cuota editable al registrar un gasto nuevo,
   suma de cuotas redondeadas por línea y límite existente de ±0,02 por línea
   comprobado en céntimos enteros. Cambiar base o tipo restablece el cálculo.
   Probados ambos límites, asiento equilibrado, deducibilidad y rechazo de
   importes negativos, exceso de tolerancia o más de dos decimales.
   Correcciones de gastos existentes con auditoría, reversión/nuevo asiento y
   protección de declaraciones presentadas. No modificar importes históricos
   sin motivo ni utilizar una tolerancia ilimitada.
5. **Compras con líneas libres**: descripción, cantidad, precio, descuento e IVA
   sin producto. Conservarlas en pedido, PDF, conversión, factura y abono; jamás
   generar stock, reservas ni catálogo de proveedor a partir de ellas. Revisar
   aprobaciones, rentabilidad, recepción mixta y nulos antes de permitir guardarlas.
6. **Orden documental y período fiscal**: separar fecha de factura, recepción,
   contabilización y período de deducción. La lista de gastos ya se ordena por
   fecha; añadir filtros/agrupación por trimestre y período de presentación.
   Congelar declaraciones presentadas y proponer períodos posteriores elegibles,
   con confirmación y control de duplicados, no mover la fecha original ni aplicar
   la misma regla a IVA soportado, repercutido, rectificaciones y criterio de caja.
   Validar plazos, situaciones especiales y trazabilidad con el asesor fiscal.

Referencia de diseño fiscal: AEAT, requisitos para deducir IVA:
https://sede.agenciatributaria.gob.es/Sede/iva/que-iva-soportado-puedo-deducir/que-requisitos-debo-cumplir-poder-iva.html
La deducción requiere justificante y registro y está sujeta, en general, al plazo
de cuatro años; no equivale a trasladar indiscriminadamente facturas de trimestre.

Validación del primer bloque: 580 pruebas correctas / 7642 aserciones (una
comprobación exclusiva de PostgreSQL omitida en SQLite), compilación y TypeScript
correctos, catálogo bilingüe completo. Los nuevos adjuntos conservan los importes
y estado del gasto y limpian archivos si falla la persistencia. Las pruebas del
logo cubren sustitución, eliminación y conservación ante un reemplazo inválido.
Este bloque está en producción como v2026.09.27.2; no publicar todo develop
mientras el directorio fiscal siga pendiente. El punto 4 tiene su primera entrega
para gastos nuevos publicada; las correcciones históricas y puntos 3, 5 y 6 siguen abiertos.

## Directorio fiscal — ficha canónica y direcciones — 27/09/2026

- Implementación local, aún no publicada: catálogo propio bajo Personas y
  Empresas → Directorio fiscal, reservado a administradores. Una ficha por
  país + NIF normalizado, varios nombres comerciales y hasta 30 direcciones
  etiquetadas: domicilio fiscal (uno actual), social y establecimientos.
- Identidad protegida contra reasignación, motivo de edición obligatorio,
  versión del formulario para evitar sobreescrituras simultáneas y auditoría
  antes/después en la misma transacción. Desactivar no borra la ficha ni facturas.
- Integración de nombres con gastos: las fichas activas del directorio tienen
  prioridad sobre los nombres históricos, ofreciendo selección cuando existen
  varias marcas. País seleccionable y guardado en nuevos gastos; búsqueda y
  duplicados separan países. Los gastos antiguos sin país no se reescriben y
  siguen como candidatos heredados en la búsqueda ES, pendientes de revisión.
- No se han importado ni fusionado automáticamente identidades anteriores;
  no se modifican proveedores, documentos emitidos, pagos ni asientos.
- Pendiente antes de completar la fase: selección y copia fija de la dirección
  en nuevas facturas, enlace explícito con clientes/proveedores y conciliación
  de identidades históricas. Unificar los selectores geográficos con el
  componente común y realizar validación visual autenticada antes de publicar.
- Verificación: 574 pruebas PHP / 7598 aserciones, build, TypeScript, ESLint y
  catálogo bilingüe correctos; migración aplicada en PostgreSQL local. Cubiertos
  conflictos de identidad, país, formularios obsoletos, permisos, auditoría y
  rollback, conservación de facturas/proveedores e inactivación del autocompletado.
  Sin publicación de esta fase: el directorio fiscal permanece en develop hasta
  cerrar copia fija de direcciones, integración geográfica y validación visual.

## Corrección urgente del historial de gastos — 27/09/2026

- Causa del error 500 al corregir la razón social: PostgreSQL limita
  `audit_logs.event` a 20 caracteres y `expense_issuer_corrected` tiene 24.
- Migración incremental a 255 caracteres, sin reescribir documentos ni
  movimientos contables. La reversión se bloquea si truncaría eventos existentes.
- Verificado el flujo completo en PostgreSQL aislado: 7 pruebas y 68
  aserciones, incluidos permisos, concurrencia y reversión sin pérdida del historial.
- Compilación correcta y suite general SQLite correcta; la capacidad real del
  campo se comprueba en PostgreSQL porque SQLite no limita los varchar.
- Publicación aislada desde producción, sin incluir el directorio fiscal
  pendiente de desarrollo. Publicado el 27/09/2026 mediante PR #124:
  commit `a10754adfd9521314aed5caa2427e070ae2ee1d2`, despliegue
  `depl-a2d89059-4072-4bd9-bc77-de5e2a16b5aa` completado y activo.
- Verificación posterior: columna de 255 caracteres y migración aplicada en
  producción; `/up` y `/login` HTTP 200. EXP-2026-00002 conserva sus datos y
  fecha de modificación; no se corrigió ninguna factura en nombre del usuario.
- Suite aislada: 568 pruebas SQLite correctas / 7523 aserciones (la prueba
  específica de capacidad se ejecuta en PostgreSQL); CI PHP 8.4/8.5 y calidad
  correctos. Integrado también en develop y validado con el directorio fiscal.

## Directorio fiscal y memoria de emisores — 26/09/2026

- Fase 1 implementada en local: búsqueda exacta por NIF/CIF normalizado desde
  gastos históricos y proveedores activos. Autocompleta campos vacíos cuando
  solo existe una combinación de razón social/nombre comercial; ofrece elegir
  si existen variantes. No sobrescribe datos escritos ni enlaza proveedores a
  ciegas. Al cambiar el NIF se descartan los nombres autocompletados anteriores.
- Búsqueda limitada y protegida por permisos de gastos y finanzas, con índices
  de normalización para PostgreSQL/SQLite y sin devolver importes, IBAN ni
  contactos. Respuestas tardías canceladas y entrada manual si falla la búsqueda.
  La misma normalización protege la detección de facturas duplicadas: cambiar
  puntos, guiones o espacios del NIF no evita el bloqueo de duplicados.
- Fase 2 pendiente: ficha fiscal canónica por país + identificación normalizada,
  vinculada a proveedores/clientes sin duplicar sus funciones. Varias marcas y
  establecimientos por entidad; domicilios fiscal, social y operativos con etiqueta,
  vigencia y dirección estructurada. No tratar automáticamente una marca como
  otra empresa ni mezclar sujetos de países distintos con identificaciones similares.
- Fase 3 pendiente: importar identidades históricas con revisión de conflictos,
  deduplicación y fusión auditada; congelar la dirección y razón social de cada
  factura para que cambios del directorio nunca modifiquen documentación anterior.
  La fase 1 no es una validación censal ante AEAT ni un directorio completo.
- Caso identificado EXP-2026-00002 (/expenses/3): lectura de producción confirma
  gasto pagado, razón social «E. S. LA PEÑITA, S. A.» y nombre comercial
  «E. S. Universidad». Se preservan ambos conceptos y no se tocan pagos/asientos.
  La herramienta administrativa permite corregir solo los nombres con histórico.
- Verificado: 566 pruebas PHP y 7516 aserciones; build, TypeScript, ESLint y
  catálogo bilingüe. Índices y consulta validados en PostgreSQL local; suite en
  SQLite. No se ha hecho validación visual autenticada.
- Publicado: PR #123, commit 970f198065a4f2ad8c82bda4445bbd6a8d9e3c23,
  despliegue depl-a2d73df5-77ab-40c2-b989-9f5821b3b420 completado el 26/09/2026
  a las 20:12:52 UTC. Producción confirma ambos índices, ruta de corrección y
  búsqueda del emisor de EXP-2026-00002; gasto sin cambios, estado pagado.
  Salud y acceso responden HTTP 200. Las fases 2 y 3 siguen pendientes.

## Recuperación administrativa: CRM, agenda y compras — 26/09/2026

- OPP-2026-00005: confirmado el archivado en producción. La ficha normal
  excluía registros archivados y devolvía 404. Nueva pantalla de recuperación
  en el enlace original; restauración atómica e idempotente, con actor e
  histórico conservados. Las rutas de edición siguen rechazando archivados.
- Agenda: corregida la política que permitía gestionar actividades al
  comercial pero no al administrador. Mantiene restricciones de técnicos y
  actividades ligadas a órdenes de trabajo. Probadas las vistas día, semana,
  mes y tablero, reprogramación, finalización y eliminación administrativa.
  El 404 concreto de la cita sigue pendiente de identificar con su enlace;
  el defecto confirmado de permisos produce 403, no 404.
- PC-2026-00001: diagnóstico de producción de solo lectura: pedido abierto,
  sin recepciones, devoluciones, facturas ni pagos; ejercicio 2026 abierto.
  Prueba del cambio 15/09/2026 a 29/05/2026 conservando entrega 03/06/2026,
  número e histórico. Formulario administrativo visible sin desplegar panel.
  No se relajan protecciones fiscales ni se ha alterado el registro real.
- Publicado en producción: PR #122, commit 0c524c3966e13b313d32447a5d7b24ca348a007a,
  despliegue depl-a2d65b1f-92c2-4148-ae7b-237b68c1727b finalizado correctamente
  el 26/09/2026 a las 09:38 UTC. Verificados ruta de archivados y permiso admin
  de agenda en el servidor; no se ha modificado el estado de OPP-2026-00005 ni
  la fecha real de PC-2026-00001. Sin migraciones en esa publicación.
  Se mantienen el roadmap fiscal y los demás frentes previamente acordados.

## Gastos — herramientas de corrección administrativa — 26/09/2026

- Primer incremento: corregir razón social y nombre comercial registrados en un
  gasto, con motivo obligatorio, historial antes/después y protección contra
  formularios obsoletos. Solo administradores; operación y auditoría atómicas.
- No cambia NIF, proveedor vinculado, importes, impuestos, pagos, asientos,
  adjuntos ni declaraciones archivadas. No modifica otras facturas ni la ficha
  maestra del proveedor. El listado y la ficha priorizan el nombre documental.
- Caso solicitado: «E.S. LA PEÑITA, S.A.», EXP-2026-00002 (/expenses/3).
  La razón social ya corresponde a esa entidad; el nombre comercial es distinto
  y se conserva, sin intervenciones silenciosas sobre el registro de producción.
- Pendiente: correcciones de NIF/número/fechas; correcciones económicas con
  contrapartidas contables, revisión de pagos y periodos cerrados. Esta entrega
  no habilita edición universal ni implementa todavía esas contrapartidas.
- Verificado localmente: 559 pruebas PHP, 7444 aserciones, build, TypeScript,
  ESLint y catálogo bilingüe. Incluye gasto pagado, preservación de asientos,
  rechazo de formulario obsoleto y rollback si falla la auditoría. Publicado
  junto a la memoria de emisores en la versión 2026.09.26.2. Mantiene los demás
  frentes del roadmap.

## Compras — corregir fechas de pedidos guardados — 20/09/2026

- Corrección del hueco posterior a la publicación 2026.09.20.1: la conversión
  admitía fecha, pero la ficha del pedido guardado no permitía rectificarla.
- Implementación local: sección administrativa «Corregir fechas del pedido de
  compra», fecha documental, entrega prevista y motivo obligatorio. Registro
  transaccional del actor, momento real, fechas y números anterior/nuevo.
- Mantiene importes, líneas, creación técnica y bloqueo operativo. En cambio de
  ejercicio asigna número de la serie destino y conserva referencia anterior.
- Requiere ejercicios abiertos; no corrige pedidos cerrados ni con recepciones,
  devoluciones, facturas o pagos. Un pedido enviado no se renumera entre ejercicios.
  No modifica facturas de proveedor ni asientos contables asociados.
- Validación local: 554 pruebas PHP (7385 aserciones), build, TypeScript, ESLint
  y catálogo bilingüe aprobados. Publicación preparada, pendiente de CI y Cloud;
  los demás frentes del roadmap fiscal, CRM y arquitectura siguen vigentes.

## Publicación 2026.09.20.1 — preparada

- Alcance: niveles de interés y seguimiento rápido CRM, selector de puestos RRHH
  y primera entrega de fechas históricas para administradores descrita abajo.
- Sin migraciones nuevas ni modificación de datos existentes. Se conservan los
  cierres fiscales, permisos, auditoría y bloqueo de documentos emitidos.
- Validación previa: 549 pruebas PHP y 5 pruebas de helpers CRM; TypeScript,
  ESLint, catálogo bilingüe y compilación verificados. La publicación requiere
  CI, integración develop → main, éxito de Cloud y comprobación HTTP posterior.
- No amplía el alcance a todas las fechas: las limitaciones y siguientes tareas
  de documentación histórica siguen registradas en el apartado correspondiente.

## RRHH y documentación histórica — 20/09/2026

- Implementado localmente: puesto de RRHH seleccionable desde catálogo central
  `config/staff.php`, validado en servidor. No concede permisos de acceso. Se
  permite conservar el puesto antiguo del propio empleado, pero no crear texto
  arbitrario. Pendiente: gestión del catálogo desde Administración de Plataforma.
- Implementado localmente: administrador puede elegir fecha en conversiones de
  solicitud/oferta de proveedor a pedido, propuesta a pedido/albarán/factura y
  pedido/albarán a documento derivado. Numeración según ejercicio de la fecha;
  facturas derivadas siguen en borrador. Emitir una factura propone su fecha
  documental existente en vez de sustituirla por hoy.
- Seguimientos de oportunidades: administrador puede introducir una fecha
  vencida; comerciales mantienen la restricción futura en la siguiente acción.
- Las altas directas de propuestas, pedidos, facturas y facturas de proveedor
  ya aceptan fecha documental anterior: no se elimina la coherencia entre fecha
  de emisión y vencimiento ni la validación de ejercicio/serie abiertos.
- No se reescriben `created_at`, auditorías, documentos emitidos ni cierres.
  **Pendiente, no cubierto aún:** fecha comercial de origen de oportunidad
  independiente de `created_at`; corrección auditada de fechas de documentos
  ya registrados; inventario completo del resto de flujos (abonos, proyectos,
  operaciones, recurrencias). No considerar esta entrega habilitación universal.
- Verificación: pruebas de catálogo/legado, rol administrador frente a comercial,
  ejercicio histórico y cierre, numeración, vencimiento relativo y auditoría.
  Mantiene prioridad fiscal y todas las tareas CRM/arquitectura anteriores.

## CRM — agilidad del equipo comercial — 20/09/2026

Objetivo: cerrar cada gestión con resultado y próximo paso, sin volver a editar
toda la oportunidad. Complementa los niveles de interés y no desplaza fiscalidad.

- Primer incremento local: resultados frecuentes editables al completar una
  actividad, que se añaden sin borrar notas; accesos de seguimiento mañana/en
  una semana a las 09:00 locales, manteniendo fecha y hora manuales. No envían
  mensajes, cambian etapas ni guardan hasta confirmar. Los días son naturales.
- Próximo: vista «Mi jornada» con vencidas, hoy y oportunidades sin próxima
  acción; filtros por responsable y permisos existentes, sin exponer carteras.
- Después: contacto desde la ficha, captura móvil breve de notas de visita,
  seguimiento de propuestas pendientes y alertas de oportunidades estancadas.
- Criterios transversales: no duplicar actividades por reintentos, conservar
  historial y permisos, comprobar móvil y medir pasos/tiempo de registro antes
  de afirmar una mejora de rendimiento. No sustituir el trabajo por automatismos
  que contacten clientes o cambien valoraciones sin confirmación.

## CRM — valoración comercial por niveles — 20/09/2026

Formulario de oportunidades: sustituido el porcentaje manual por Sin valorar
(10), Poco interesado (25), Interesado (40), Muy interesado (60) y Listo para
decidir (80). Son pesos internos orientativos, no probabilidades calibradas.
Ficha y tarjetas muestran el nivel; los informes mantienen el contrato numérico.
Cambiar entre etapas abiertas conserva la valoración; Ganada/Perdida mantienen
100/0 y no permiten modificar el nivel. Valores históricos fuera de la escala
se muestran como valoración anterior y no se redondean al guardar otros cambios.
Sin migración ni recalculo del histórico. Siguiente mejora: criterios observables
de cualificación y calibración de pesos con cierres reales, sin confundir interés
con aceptación de propuesta ni alterar el programa fiscal prioritario.

## Preparación de publicación acumulada — 19/09/2026

Integrados los cambios locales de navegación, favoritos, separación de secciones,
libros de IVA, protección de emisión/edición/pagos e identidad fiscal histórica.
La rama de publicación incorpora también las correcciones de main hasta ae096d9,
incluida PUR-500 (PR #115): eliminación de la ordenación heredada en DISTINCT.
La nota de diagnóstico anterior se conserva como historial, no como estado actual.

Producción comprobada: PostgreSQL, main, Push to Deploy activo. Las dos migraciones
nuevas son aditivas: issuer_snapshot nullable y permisos de emisión/rectificación
copiados de los accesos anteriores. No se modifican facturas históricas ni se
introducen cambios de credenciales. Archivos privados de conexión quedan excluidos.

ARCH-01 y ENG-ENERGY se publican como planificación; no como funciones terminadas.
FIN-01 conserva pendientes el ensayo de concurrencia con conexiones independientes
y la idempotencia de pagos parciales. La publicación solo se dará por completada
tras CI, despliegue y comprobaciones del entorno de producción.

## Arquitectura maquetada y diagnóstico de Compras — 19/09/2026

### ARCH-01 — implementar la nueva arquitectura maquetada

Solicitud incorporada, pendiente de identificar el archivo/enlace y versión
de la maqueta. No se presupone que sea idéntica al mapa anterior de veinte áreas.
Hasta contrastarla, ese mapa sigue siendo la referencia funcional vigente.

1. Inventariar pantallas y recorridos de la maqueta y mapearlos a rutas, permisos,
   entidades y funcionalidades actuales; registrar diferencias y dependencias.
2. Definir componentes comunes de sección, listado, ficha y formulario, con
   variantes móvil, estados vacíos/error/carga y traducciones ES/EN.
3. Migrar por recorridos completos: Compras y documentos; Finanzas, Contabilidad
   y Fiscalidad; después el resto de áreas. Conservar URLs compatibles, filtros,
   historial, permisos y datos; no realizar una sustitución global de golpe.
4. Validar cada corte con pruebas de autorización y negocio, comprobación visual
   escritorio/móvil, accesibilidad y medición de carga antes/después.
5. Publicar por incrementos verificados y con reversión definida. Mantener el
   programa fiscal 2027 y ENG-ENERGY, sin reiniciar ni sustituir sus pendientes.

Decisiones invariables: sin «Visitados recientemente», sin rótulo «Espacio de
trabajo» y favoritos únicamente en la barra superior.

### PUR-500 — conversión de solicitud/propuesta a pedido

Diagnóstico: PurchaseOrderApprovalWorkflow::requirements obtiene tipos de
producto con DISTINCT desde la relación lines(), que añade ORDER BY position.
PostgreSQL rechaza esa consulta porque position no está en la selección.
Reproducido el 19/09/2026 en PostgreSQL local mediante una transacción de solo
lectura y un ID inexistente, sin leer documentos de clientes ni crear pedidos.
La misma consulta sin esa ordenación se ejecuta correctamente.

Corrección propuesta: eliminar la ordenación heredada únicamente en la consulta
de tipos (reorder()), conservando el orden de las líneas del documento y las
reglas de aprobación. Añadir regresión PostgreSQL para conversión, reglas de
producto/servicio, umbral de aprobación y ausencia de efectos parciales.
Estado: fallo técnico reproducido, corrección todavía no implementada en este
diagnóstico. Para vincularlo al incidente concreto faltan entorno y número de
propuesta o traza. No consta revisión de logs de producción ni despliegue.

## Protección del saldo en liquidaciones de IVA — 13/09/2026

Continuación fiscal: el registro de pago/devolución recarga y bloquea la
liquidación antes de comprobar su estado y saldo pendiente. Se rechaza una
petición obsoleta que exceda el saldo sin crear movimiento ni asiento.
La guía PostgreSQL orienta una transacción corta, sin llamadas externas,
que mantiene pago, asiento y actualización del saldo como una sola operación.
Prueba de regresión para IVA a pagar y devolución. No equivale a un ensayo
simultáneo con conexiones PostgreSQL independientes ni añade idempotencia
de reintentos parciales; ambos controles siguen pendientes en FIN-01.
Se conserva la prioridad fiscal 2027 y el diseño ENG-ENERGY ya incorporado.

## Incremento de interfaz y fiscalidad — 13/09/2026

- Favoritos trasladados a una barra superior horizontal con alta/baja y accesos;
  eliminados los favoritos y el rótulo «Espacio de trabajo» del sidebar.
  No se recupera «Visitados recientemente» ni su registro.
- IVA: liquidación, libro de emitidas y libro de recibidas tienen destinos
  independientes en el sidebar. Cambiar ejercicio/trimestre conserva el libro
  actual. Preparar, registrar presentación y pagar se muestran según manage-tax,
  el mismo permiso que protege las rutas; no según el rol nominal administrador.
  No se ha añadido presentación telemática ni certificación fiscal.
- Próximo tramo fiscal: continuar FIN-01 (concurrencia PostgreSQL y separación
  restante de permisos), después controles de liquidación/modelos y VERI*FACTU.
  Contabilidad y Gastos conservan pendiente su separación de vistas.
- Incorporado [ENG-ENERGY: suministros CUPS y estudio energético](energy-supply-roadmap.md).
  Secuencia: ficha y evidencias → curvas y calidad → escenarios técnicos →
  conector autorizado → móvil. Diseño, no integración disponible todavía.
  Se intercala tras estabilizar FIN-01 sin desplazar el objetivo fiscal 2027.

## Corrección transversal de navegación — 13/09/2026

- Decisión permanente del usuario: eliminar «Visitados recientemente» y dejar de registrar visitas. No reintroducir esta función sin una nueva petición explícita. Se conservan los favoritos y se limpia el historial antiguo al cargar los accesos.
- Inventario: Stock, Reservas, Movimientos y Almacenes disponen de URL y acceso propio en el sidebar, sin pestañas internas que los mezclen. Los enlaces anteriores de planificación siguen admitidos.
- Catálogo: Productos y servicios, Proveedores y Configuración pasan a destinos independientes; proveedores permanece en Personas y Empresas y la configuración del catálogo en Inventario. Se reutilizan los formularios existentes y se conserva la autorización en servidor.
- Auditoría de navegación: quedan por separar las vistas de Contabilidad (diario, balances, mayor, terceros y mapeos), Fiscalidad (resumen y libros) y Gastos (gastos y pendientes de pago). El portal tiene navegación propia de cliente (resumen, documentos, soporte, actividad y preferencias); no debe trasladarse al sidebar interno ni ampliar permisos.
- Siguiente incremento: separar esos destinos financieros preservando filtros, cierres y acciones; después reducir los datos cargados por pantalla y verificar móvil con datos reales. Esta corrección complementa el programa fiscal 2027; no sustituye ni cierra el roadmap anterior.

Este documento ordena la evolución del ERP web, sus APIs y los consumidores
móviles. `develop` es la línea común de integración del desarrollo web. La app
móvil mantiene su repositorio y ciclo de compilación independientes, pero debe
respetar los mismos contratos API, permisos y estados de negocio.

El roadmap se organiza por horizontes de capacidad, no por módulos aislados ni
por fechas artificiales. Ningún área debe avanzar al horizonte siguiente si las
dependencias que comparte con las demás siguen incompletas.

Excepción de calendario acordada el 12/09/2026: finanzas, contabilidad,
facturación, modelos fiscales aplicables y VERI*FACTU tienen como objetivo
operativo el **01/01/2027**. El programa prioritario siguiente ordena sus
dependencias sin eliminar los horizontes ni los trabajos pendientes anteriores.

## Alcance de negocio común

La plataforma trabaja con un catálogo canónico de cinco líneas operativas:

1. instalaciones fotovoltaicas;
2. recarga de vehículo eléctrico;
3. climatización;
4. instalaciones eléctricas;
5. alarmas y sistemas de seguridad.

Mantenimiento, reparación, puesta en marcha y posventa son capacidades
transversales aplicables a esas líneas, no ramas comerciales desconectadas.

## Principios de evolución

- Un dato se define una vez y se reutiliza en CRM, oferta, proyecto, operación,
  suministro, finanzas, portal, analítica, Arevia y API.
- Los permisos del servidor determinan también la navegación, las acciones y
  los datos visibles en web y móvil.
- Cada proyecto conserva trazabilidad desde el origen del lead hasta la
  instalación, activos, intervención, documento, cobro y resultado económico.
- Las automatizaciones proponen o preparan acciones; los cambios sensibles
  mantienen aprobación humana y auditoría.
- Los datos históricos siguen siendo válidos durante cualquier ampliación de
  catálogo o migración.
- La configuración específica de una línea no contamina las fichas técnicas de
  las demás.

### Gobierno continuo del roadmap

Este documento es acumulativo y constituye la única secuencia de evolución de
la plataforma. Una incidencia, corrección urgente o nueva idea no sustituye los
horizontes ya acordados ni crea un roadmap paralelo: se incorpora al tramo que
corresponda por dependencia, riesgo y valor, y el desarrollo continúa después
desde el siguiente punto pendiente.

Cada corte debe mantener simultáneamente:

1. el objetivo funcional de los horizontes previamente planificados;
2. las mejoras transversales de rendimiento, traducción, responsive, permisos y
   consistencia de interfaz que afecten a ese objetivo;
3. las nuevas tareas aceptadas, con prioridad, estado y criterio de salida;
4. una actualización de este documento que indique qué se ejecutó, qué cambió y
   cuál es el siguiente corte lineal.

Las tareas pueden adelantarse cuando resuelvan un bloqueo o una regresión, pero
después se retorna a la secuencia pendiente. Ninguna tarea se considera
completada solo por existir código: requiere validación proporcional, publicación
y trazabilidad en el roadmap. Las propuestas futuras permanecen visibles aunque
un corte posterior cambie su orden o alcance.

## Prioridad vigente — Preparación fiscal al 01/01/2027

Actualización de planificación al 28/09/2026: FIN-02 se concreta con motor decimal
y libros normalizados; FIN-03 prioriza el 303 (30/10), y FIN-05 adelanta el 390
al 13/11 y el 347 al 27/11. Véase el [anexo de ejecución 303/390/347](fiscal-models-303-390-347.md)
para alcance, dependencias, fuentes y aceptación. La revisión inicial que sigue
es histórica; las entregas publicadas se registran al comienzo de este documento.

Estado a 12/09/2026: revisión inicial realizada y primer incremento FIN-01
implementado localmente; pendiente de integración/publicación y del resto de
criterios del corte. No hay remisión AEAT habilitada.
El diagnóstico y los criterios verificables están en
[la revisión fiscal](fiscal-readiness-2027.md), anexo de este roadmap, no una
secuencia independiente. No se considera que un cálculo interno o un CSV de
libros sea un modelo oficial ni que una huella documental sea VERI*FACTU.

| Corte | Fecha objetivo interna | Entrega y criterio de salida | Estado |
| --- | --- | --- | --- |
| FIN-01 | 25/09/2026 | Integridad de emisión, identidad histórica de emisor/proveedor, concurrencia y permisos. Regresiones que prueben documentos estables y ausencia de doble emisión. | Tres incrementos locales: integridad, datos históricos y permisos manuales de emisión/rectificación; pendientes concurrencia PostgreSQL, resto de accesos y publicación |
| FIN-02 | 09/10/2026 | Perfil fiscal versionado y libros conciliados: cuotas soportadas/deducibles separadas, operaciones tipificadas, retenciones, compensaciones y pagos tributarios contra cuenta concreta. Casos incompatibles bloqueados, sin supuestos fiscales silenciosos. | Pendiente; depende de FIN-01 y confirmación censal |
| FIN-03 | 30/10/2026 | Expediente de modelos: borradores trazables por casilla, revisión, versiones, validación y salida oficial cuando exista diseño publicado. Prioridad 303; 111/115 según obligaciones. Contraste con gestoría y casos de referencia. | Pendiente; depende de FIN-02 |
| FIN-04 | 13/11/2026 | VERI*FACTU en pruebas: registro inmutable de alta/anulación, encadenamiento y huella oficial, XML/XSD, QR, cola transaccional, autenticación y respuestas AEAT. Reintentos sin duplicar registros; ninguna remisión real. | Pendiente; diseño tras FIN-01, aceptación tras FIN-02 |
| FIN-05 | 27/11/2026 | Modelos restantes aplicables: 190/180, 347/349/390 y 200/202; conciliaciones cruzadas y revisión fiscal. Distinguir preparación de datos de exportadores aún pendientes de diseños oficiales. | Pendiente; alcance condicionado al perfil censal |
| FIN-06 | 11/12/2026 | Ensayo integral web/API, cierre/apertura 2026–2027, carga, recuperación, certificados, permisos, ES/EN y móvil web. Declaración responsable por versión y manual operativo revisados. | Pendiente; depende de FIN-03/04/05 |
| FIN-07 | 18/12/2026 | Puesta en marcha controlada y decisión documentada de activación real; monitorización de colas, rechazos y caducidad del certificado. Contingencia ensayada sin borrar ni reiniciar cadenas. | Pendiente; solo si FIN-06 supera aceptación |
| Reserva | 19–31/12/2026 | Estabilización y correcciones; sin ampliaciones no esenciales. Disponibilidad operativa objetivo el 01/01/2027. | Planificado |

Las fechas son objetivos de ejecución, no garantía de conformidad ni calendario
tributario. El alcance depende de confirmar entidad, territorio, obligaciones,
SII/REDEME y regímenes especiales. Los diseños y calendarios oficiales de cada
ejercicio se revalidarán al publicarse. No se fabricarán exportaciones oficiales
con formatos todavía no publicados. La preparación/revisión de declaraciones
permanece separada de su presentación y de los pagos reales.

La modalidad objetivo es VERI*FACTU con remisión, sujeta a aplicabilidad y
validación técnica. La emisión desde cualquier origen comercial converge en un
único servicio; la app móvil no emitirá facturas fiscales offline por una vía
paralela. No se habilitan envíos reales por desplegar código ni se transforma el
histórico en emisiones nuevas.

Se conservan pendientes las reprogramaciones/cancelaciones desde Ingeniería,
la medición de rendimiento p75 y los horizontes 1–4. Los bloqueos operativos y
regresiones mantienen prioridad inmediata; traducción, responsive, rendimiento
y permisos se ejecutan dentro de cada corte fiscal. La ampliación funcional
no esencial retorna a la secuencia previa después del hito fiscal.

## Arquitectura funcional objetivo — 20 áreas

La navegación, los permisos, la búsqueda y los futuros límites de dominio se
alinearán sobre estas veinte áreas. Esta taxonomía no obliga a mostrar veinte
entradas abiertas a todos los usuarios: el sidebar será jerárquico, plegable y
derivado de permisos, sin accesos recientes y con favoritos en la barra superior. Las rutas y datos
existentes se migrarán de forma compatible, sin renombrados masivos ni módulos
vacíos.

|   # | Área                         | Código | Cobertura principal                                |
| --: | ---------------------------- | :----: | -------------------------------------------------- |
|   1 | Personas y Empresas          |  PTY   | Clientes, proveedores y contactos                  |
|   2 | CRM                          |  CRM   | Leads y oportunidades                              |
|   3 | Ventas                       |  SAL   | Propuestas, pedidos y contratos comerciales        |
|   4 | Ingeniería                   |  ENG   | Estudios, cálculos y diseños                       |
|   5 | Proyectos                    |  PRJ   | Gestión integral de instalaciones                  |
|   6 | Planificación                |  PLN   | Agenda, recursos, cargas y escenarios              |
|   7 | Operaciones / SAT            |  OPS   | Órdenes de trabajo e intervención                  |
|   8 | Compras                      |  PUR   | Aprovisionamiento                                  |
|   9 | Inventario                   |  INV   | Stock y almacenes                                  |
|  10 | Activos                      |  AST   | Vehículos, herramientas y equipos                  |
|  11 | Mantenimiento                |  MNT   | Preventivo y correctivo                            |
|  12 | Finanzas                     |  FIN   | Facturación, cobros, pagos y tesorería             |
|  13 | Contabilidad                 |  ACC   | PGC, impuestos y contabilidad                      |
|  14 | Recursos Humanos             |  HRM   | Personal, horarios y PRL                           |
|  15 | Documentación                |  DOC   | Expedientes y documentos                           |
|  16 | Dirección / BI               |   BI   | KPIs, planificación y rentabilidad                 |
|  17 | Calidad                      |  QMS   | No conformidades, inspecciones y mejora            |
|  18 | Atención al cliente          |   CS   | Tickets, incidencias y posventa                    |
|  19 | Contratos y Servicios        |  CTR   | Recurrencia, garantías y SLA                       |
|  20 | Administración de Plataforma |  SYS   | Configuración, permisos, auditoría e integraciones |

### Programa transversal de experiencia y rendimiento

Este programa se ejecuta en cortes verticales junto al roadmap funcional y tiene
prioridad sobre añadir pantallas que amplíen deuda de interfaz.

| Prioridad | Trabajo                           | Estado y criterio de salida                                                                                                                                                                                                                                                                                                                                                                                                      |
| --------- | --------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| P0        | Direcciones editables             | Primer corte completado. País, provincia, municipio y localidad guardados reconstruyen sus listas dependientes al crear y editar clientes e instalaciones; los catálogos estables se cachean durante 24 horas.                                                                                                                                                                                                                   |
| P0        | Rendimiento medible               | Instrumentación y primera optimización ejecutadas. `Server-Timing` mide aplicación y SQL; los excesos registran solo metadatos técnicos. El panel difiere datos y código del mapa. LCP, INP y CLS se muestrean ahora por pantalla y dispositivo, sin URL, usuario ni IP, con retención de 90 días e informe de percentiles. Pendiente recoger una línea base suficiente en producción y optimizar el siguiente punto demostrado. |
| P0        | Localización completa             | En curso. España ya conserva `Spain` como valor canónico y se presenta como `España` en formularios españoles. Ningún otro texto visible puede quedar mezclado y los demás catálogos seguirán el mismo patrón.                                                                                                                                                                                                                   |
| P0        | Adaptación móvil integral         | En curso continuo. Cada ficha, tabla, diálogo y acción principal debe funcionar desde 320 px sin desbordamiento, pérdida de información ni acciones inaccesibles.                                                                                                                                                                                                                                                                |
| P1        | Sistema único de formularios      | En curso avanzado. Cliente, instalación, propuesta y alta de gasto comparten secciones, jerarquía visual, resumen accesible de errores, estado de guardado y barra de acciones adaptable. El patrón se extenderá por cortes a los formularios operativos restantes.                                                                                                                                                              |
| P1        | Terceros según naturaleza         | Cuarto corte completado. Particular, empresa, comunidad y organismo público adaptan identidad, contactos, representación e información fiscal. Clientes y proveedores disponen de contactos por responsabilidad y firmantes autorizados; ventas y compras congelan la identidad histórica en sus documentos derivados.                                                                                                           |
| P1        | Sidebar y arquitectura de módulos | Cuarto corte completado. Ingeniería y Planificación completan el recorrido hasta la orden operativa, con revisión, disponibilidad e idempotencia. Pendiente extender el historial técnico a reprogramaciones y cancelaciones de la orden. |

La optimización se considera terminada únicamente con mediciones antes/después;
la traducción y el responsive se validan sobre las rutas realmente autorizadas a
cada rol, no mediante una revisión visual parcial del perfil administrador.

La primera línea base del build identifica tres objetivos claros: el núcleo de
aplicación ronda 597 kB, las gráficas 352 kB y el mapa —ya separado del núcleo—
1.042 kB antes de gzip. El mapa ha dejado de descargarse y de serializar todas las
instalaciones durante la entrada al panel; su código y payload se cargan al pedirlo.
El siguiente corte de rendimiento recogerá tiempos, consultas y tamaño de respuesta
en las rutas principales y decidirá con esos datos el próximo objetivo.

## Base consolidada

Esta es la línea de partida ya disponible y sobre la que se construyen los
próximos horizontes.

| Área              | Capacidad consolidada                                                                                                                                                   |
| ----------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| CRM y ventas      | Captación multicanal y móvil, contactos, oportunidades, cartera, agenda, rutas offline, consentimiento, SLA, scoring, ofertas, aprobaciones y atribución de conversión. |
| Operaciones       | Instalaciones multirramo, planificación, órdenes, agenda técnica, checklists, mantenimiento, activos, números de serie, RMA, incidencias y sincronización móvil.        |
| Suministro        | Catálogo y composiciones, clasificación por línea de negocio, compras, aprobaciones, reservas, reposición, calidad, criticidad, plazos y alternativas de proveedor.     |
| Finanzas          | Contabilidad, facturación, banca, conciliación, anticipos, riesgo de cobro, tesorería, alertas y bloqueo operativo por excepciones.                                     |
| Recursos humanos  | Expediente, contratos, ausencias, controles sensibles y capacidad técnica compartida con Operaciones.                                                                   |
| Portal de cliente | Acceso protegido a instalaciones, documentos, aceptaciones, certificados e incidencias.                                                                                 |
| Web pública       | Captación multirramo, formulario conectado al CRM, SEO técnico, metadatos sociales, accesibilidad y experiencia responsive.                                             |
| Analítica         | Indicadores autorizados, drilldowns acotados, alertas e informes programados.                                                                                           |
| Arevia            | Contexto autorizado, conocimiento gobernado, citas, propuestas de acciones y soporte a instalaciones de las cinco líneas.                                               |
| Plataforma        | Perfiles y permisos, navegación autorizada, auditoría, copias, observabilidad, geocodificación y despliegue continuo.                                                   |
| App móvil         | Trabajo técnico y área comercial offline, geolocalización, rutas, captación, sincronización e intereses de las cinco líneas.                                            |

## Roadmap de la web pública multirramo

La web presenta a Arevantes como un único equipo técnico con tres líneas
comerciales de primer nivel: energía y movilidad, instalaciones eléctricas, y
alarmas y sistemas de seguridad. El mantenimiento y la atención posventa son
capacidades transversales, no una cuarta línea aislada.

### Fase 1 — Posicionamiento y captación común

- Reorganizar la portada como entrada corporativa a las tres líneas y publicar
  una página indexable y compartible para cada servicio.
- Mantener un único formulario conectado al CRM, conservando la línea de
  interés desde cada acceso para reducir fricción y mejorar la atribución.
- Actualizar SEO, datos estructurados y tarjeta social para reflejar el negocio
  multirramo sin promesas, cifras ni acreditaciones no verificadas.
- Garantizar navegación por teclado, contraste, movimiento reducido y jerarquía
  semántica en móvil y escritorio.

### Fase 2 — Páginas de servicio y confianza

- Ampliar las páginas de energía, electricidad y seguridad con preguntas
  frecuentes, objeciones y contenidos especializados por tipo de proyecto.
- Incorporar procesos, tipologías de proyecto y entregables documentales
  reales de cada línea.
- Consolidar el portfolio público con listado, fichas indexables y una selección
  destacada en portada. El área privada permite crear borradores, vincularlos a
  una instalación, ordenar imágenes y controlar su publicación.
- Publicar casos de uso solo cuando dispongan de alcance, ubicación pública,
  permiso e información verificable; nunca inventar resultados comerciales ni
  exponer direcciones, personas o metadatos de localización de las fotografías.

Incremento completado el 25 de agosto de 2026:

- cada página de servicio responde dudas específicas con contenido visible y
  datos estructurados `FAQPage` equivalentes;
- los proyectos publicados se relacionan automáticamente con su servicio sin
  mostrar borradores ni casos pertenecientes a otra línea;
- el acceso desde la web pública fuerza una navegación completa hacia login o
  panel, y el área «Web y proyectos» verifica autorización administrativa en
  servidor;
- el siguiente corte de esta fase debe ampliar procesos y tipologías con casos
  verificados de electricidad y seguridad a medida que exista material apto
  para publicación.

### Fase 3 — Crecimiento medible

- Medir visitas a cada línea, clics de contacto, inicio y finalización del
  formulario, y oportunidades cualificadas por origen.
- Priorizar rendimiento y Core Web Vitals antes de añadir contenido pesado o
  animaciones adicionales.
- Evolucionar contenidos locales por área de servicio y necesidad real, sin
  generar páginas duplicadas ni orientadas exclusivamente a buscadores.

### Indicadores de seguimiento

- Tasa de finalización del formulario y errores por campo.
- Oportunidades creadas por línea de negocio y fuente.
- Clics en llamadas a la acción por sección.
- Entradas orgánicas a páginas de servicio.
- Visitas a fichas de proyecto y clics hacia contacto desde el portfolio.
- LCP, INP y CLS en móvil y escritorio.
- Consultas que llegan correctamente clasificadas al CRM.

## Horizonte 1 — Operar las nuevas líneas de extremo a extremo

Objetivo: que electricidad y seguridad puedan recorrer el ciclo completo con el
mismo nivel operativo que las líneas existentes.

| Área              | Incremento de este horizonte                                                                                                     | Criterio de salida                                                                                                |
| ----------------- | -------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| CRM y ventas      | Plantillas de descubrimiento, cualificación y oferta específicas para electricidad y seguridad; forecast segmentado por línea.   | Un lead puede convertirse en oferta y proyecto sin recurrir a texto libre para los datos esenciales.              |
| Operaciones       | Plantillas de visita, ejecución, puesta en marcha y entrega; ficha de equipos, mediciones, pruebas y documentación por línea.    | Cada orden nace con checklist, competencias, materiales y evidencias exigibles según su línea y tipo de trabajo.  |
| Suministro        | Familias de materiales, kits y servicios reutilizables para cuadros, protecciones, cableado, alarmas, CCTV y control de accesos. | Una oferta puede resolver su composición y detectar disponibilidad o carencias antes de aprobar la planificación. |
| Finanzas          | Márgenes, hitos de facturación, anticipos y exposición de compras desglosados por línea y proyecto.                              | El responsable puede conocer margen previsto, comprometido y real sin cálculos externos.                          |
| Recursos humanos  | Matriz de competencias, habilitaciones, disponibilidad y asignación compatible con cada intervención.                            | El sistema advierte asignaciones incompatibles y explica la capacidad disponible sin exponer datos sensibles.     |
| Portal de cliente | Hitos, documentación de entrega, manuales y certificados adaptados al tipo de instalación.                                       | El cliente ve únicamente sus entregables y el estado real del proyecto.                                           |
| Analítica         | Embudo, ingresos, margen, plazo, incidencias y carga operativa comparables por línea.                                            | Los cuadros utilizan el catálogo canónico y permiten comparar las cinco ramas con definiciones comunes.           |
| Arevia            | Briefings y propuestas basados en las fichas, checklists y procedimientos propios de electricidad y seguridad.                   | Las evaluaciones cubren todas las combinaciones de línea y estado sin inventar requisitos ni datos.               |
| Plataforma        | Contratos API versionados para catálogos, plantillas y campos técnicos; auditoría de cambios sensibles.                          | Web y móvil validan los mismos valores y la compatibilidad se prueba antes de publicar.                           |
| App móvil         | Visita técnica, evidencias, seriales, pruebas y cierre offline para electricidad y seguridad.                                    | Un técnico puede completar una intervención de ambas líneas sin depender de la interfaz web.                      |

## Horizonte 2 — Convertir instalaciones en servicio recurrente

Objetivo: que la relación no termine con la entrega y que mantenimiento y
posventa sean predecibles, trazables y comercializables.

| Área              | Incremento de este horizonte                                                                        | Criterio de salida                                                                           |
| ----------------- | --------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| CRM y ventas      | Renovaciones, ampliaciones, venta cruzada y campañas basadas en activos, antigüedad y vencimientos. | Cada oportunidad recurrente conserva su causa, instalación origen y resultado.               |
| Operaciones       | Planes preventivos versionados, frecuencias, SLA, garantías, revisiones y escalados por criticidad. | Las intervenciones futuras se generan sin duplicados y con la versión de plantilla correcta. |
| Suministro        | Stock preventivo y repuestos mínimos ligados a la base instalada y a los planes activos.            | La reposición distingue demanda prevista, reserva operativa y consumo real.                  |
| Finanzas          | Contratos recurrentes, facturación periódica, provisión de costes y rentabilidad del ciclo de vida. | Ingresos, costes y deuda se reconcilian por contrato e instalación.                          |
| Recursos humanos  | Turnos, guardias y coste planificado coordinados con SLA y desplazamientos.                         | La capacidad recurrente se reserva antes de aceptar compromisos incompatibles.               |
| Portal de cliente | Próximas revisiones, garantías, comunicaciones proactivas y seguimiento de incidencias.             | El cliente entiende qué está cubierto, qué está pendiente y cuándo se actuará.               |
| Analítica         | Retención, renovación, attach rate, recurrencia, coste de servicio y fiabilidad por línea.          | Cada métrica tiene definición, periodo, población y responsable visibles.                    |
| Arevia            | Detección explicable de renovaciones, riesgos y siguientes acciones sujetas a aprobación.           | Toda recomendación enlaza datos operativos y fuentes autorizadas.                            |
| Plataforma        | Automatizaciones idempotentes, colas observables y preferencias de comunicación gobernadas.         | Reintentos y fallos no duplican contratos, tareas, mensajes ni documentos.                   |
| App móvil         | Agenda recurrente, historial resumido, consumo de repuestos y cierre con conformidad del cliente.   | La intervención queda sincronizada con evidencias y costes aun tras trabajar offline.        |

## Horizonte 3 — Planificar con escenarios compartidos

Objetivo: tomar decisiones de venta, capacidad, compra y caja sobre una misma
previsión, sin comprometer operaciones antes de su aprobación.

| Área              | Incremento de este horizonte                                                                  | Criterio de salida                                                                              |
| ----------------- | --------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| CRM y ventas      | Forecast por cohortes, probabilidad calibrada y escenarios de cierre por línea.               | El forecast explica cambios y conserva la diferencia entre dato confirmado y supuesto.          |
| Operaciones       | Simulación de calendario, cuadrillas, rutas, materiales y alternativas de ejecución.          | Los escenarios muestran impactos en fecha, coste, SLA y carga antes de confirmar.               |
| Suministro        | Conversión gobernada de carencias en solicitudes de oferta y comparación de alternativas.     | Ningún escenario crea compras comprometidas sin aprobación y trazabilidad.                      |
| Finanzas          | Simulación integrada de caja, cobros, pagos, impuestos y financiación.                        | Tesorería puede comparar escenario base, riesgo y alternativa con las mismas fechas operativas. |
| Recursos humanos  | Capacidad y coste por competencia, turno, ausencia y subcontratación potencial.               | Operaciones recibe disponibilidad agregada y compatible con privacidad.                         |
| Portal de cliente | Fechas previstas y comunicaciones derivadas solo de planes confirmados.                       | El portal nunca presenta simulaciones internas como compromisos.                                |
| Analítica         | Objetivos versionados, desviaciones explicadas y comparación plan-real.                       | Cada desviación llega a su dato origen y a la versión del plan utilizada.                       |
| Arevia            | Comparación de escenarios y borradores de decisión con límites de riesgo.                     | Una persona aprueba cualquier efecto económico, contractual o externo.                          |
| Plataforma        | Motor común de escenarios, versiones y aprobaciones reutilizable por áreas.                   | Todas las áreas consumen el mismo identificador y estado de escenario.                          |
| App móvil         | Replanificación recibida de forma segura y resolución guiada de conflictos de sincronización. | El usuario distingue con claridad trabajo confirmado, cambiado o pendiente de resolución.       |

## Horizonte 4 — Optimización y automatización gobernada

Objetivo: mejorar rendimiento y escala después de disponer de datos fiables y
procesos estables.

- Optimización de rutas, capacidad, inventario y caja con restricciones reales.
- Mantenimiento basado en condición cuando existan fuentes de telemetría
  verificables para la línea correspondiente.
- Detección de anomalías de margen, plazos, consumo, fallos y calidad.
- Aprobaciones automáticas únicamente dentro de límites explícitos, reversibles
  y auditables.
- Evaluación continua de Arevia por precisión, cobertura, citas y utilidad.
- Modularización progresiva del backend por dominios cuando reduzca acoplamiento
  medible, sin separar procesos que todavía comparten transacciones críticas.

## Secuencia de dependencia del siguiente incremento

El trabajo del Horizonte 1 debe avanzar en este orden:

1. plantillas comerciales y técnicas por línea;
2. competencias, documentación y evidencias requeridas;
3. kits de catálogo y composición de oferta;
4. planificación de materiales y personal;
5. ejecución web y móvil offline;
6. entrega documental y portal de cliente;
7. margen, indicadores y evaluación de Arevia.

Las áreas se desarrollan en vertical sobre una misma historia de negocio. No se
considera terminado un formulario si todavía no existe validación de servidor,
uso operativo, visibilidad autorizada y prueba del flujo que lo consume.

## Métricas comunes de avance

Las métricas se segmentarán por línea de negocio y conservarán una definición
única:

- tiempo desde captación hasta primera respuesta, oferta, aprobación e inicio;
- conversión, valor ganado, margen previsto y margen real;
- desviación de fecha y coste por proyecto;
- utilización y sobrecarga por competencia;
- disponibilidad de materiales y carencias bloqueantes;
- cumplimiento de checklist, documentación y primera resolución;
- incidencias, recurrencia, renovación y coste de servicio;
- sincronizaciones móviles pendientes, fallidas o en conflicto;
- propuestas de Arevia aceptadas, descartadas o corregidas.

Los objetivos numéricos se fijarán cuando exista una línea base suficiente; el
roadmap no convierte estimaciones sin evidencia en compromisos.

## Definición de incremento alineado

Un incremento solo puede cerrarse cuando incluye, en proporción a su riesgo:

1. modelo y migración reversibles y compatibles con datos históricos;
2. autorización, validación y aislamiento por rol en el servidor;
3. interfaz accesible, estados vacíos, carga, error y recuperación;
4. trazabilidad de cambios sensibles y aprobaciones necesarias;
5. contrato API y comportamiento offline cuando interviene la app móvil;
6. pruebas del flujo principal, permisos y regresión cruzada;
7. tipos, formato, compilación y revisión de rendimiento;
8. documentación operativa y actualización de este roadmap;
9. integración en `develop`, revisión remota y despliegue monitorizado.

## Cortes operativos completados — 24 de agosto de 2026

El primer vertical del Horizonte 1 queda conectado de extremo a extremo:

- el presupuesto fija la línea de negocio y filtra el catálogo compatible;
- la aceptación resuelve automáticamente la plantilla de visita técnica;
- las órdenes conservan una instantánea de competencias, checklist, evidencias
  mínimas y documentos de entrega;
- electricidad y seguridad disponen de definiciones de sondeo, instalación y
  puesta en marcha;
- web y móvil clasifican la evidencia y aplican las mismas condiciones de cierre;
- el progreso permanece disponible en el contrato móvil y en modo offline.

El segundo vertical une planificación de materiales y personas:

- la viabilidad de material se anticipa desde el presupuesto;
- al crear una orden de instalación se reserva automáticamente el kit disponible;
- las reservas generales del proyecto se vinculan a la primera orden que las
  necesita, evitando duplicidades;
- una compra solo cubre la fecha planificada cuando su entrega esperada o
  confirmada llega a tiempo;
- el plan diferencia material prometido de stock físicamente disponible y no
  permite iniciar el trabajo hasta que este último exista;
- competencias, capacidad y zona producen una lista común de técnicos aptos;
- operaciones y la app móvil comparten el estado listo, en riesgo o bloqueado.

El tercer vertical protege la replanificación y el trabajo offline:

- los cambios de fecha, horario, técnico, prioridad y disponibilidad de material
  avanzan la versión de sincronización de la orden;
- checklist, evidencias y consumos independientes continúan enviándose aunque
  otra acción de la misma orden necesite revisión;
- inicio, cambio de estado y cierre conservan la versión sobre la que decidió el
  técnico y detectan una replanificación concurrente;
- la bandeja móvil explica qué cambió y muestra la planificación vigente;
- el usuario puede revisar la orden, reaplicar su acción sobre la última versión
  o aceptar el servidor y descartar únicamente esa acción local;
- una acción reaplicada mantiene su clave idempotente y rebasa las siguientes
  acciones de la misma orden para evitar duplicados y conflictos artificiales.

La continuidad comercial dispone además de un expediente de oportunidad:

- cada oportunidad tiene una ficha permanente que sustituye la edición como
  destino principal desde pipeline, búsqueda, cliente y oferta;
- creación, responsable inicial, última edición y cambios de etapa conservan
  autor, fecha y motivo;
- notas, actividades y cambios de datos forman una cronología comercial común;
- cada revisión de oferta exige una nota de modificación y conserva importe,
  líneas, margen, autor y versión;
- aceptación o rechazo de una oferta actualiza la oportunidad sin perder el
  origen, canal ni responsable del cambio;
- las acciones se cierran desde el propio expediente con autor, fecha y
  resultado; la siguiente acción planificada toma el relevo automáticamente y
  el mismo flujo permite añadir un seguimiento nuevo sin duplicar los ya
  pendientes;
- la auditoría técnica continúa separada del histórico de negocio visible para
  que el expediente sea útil sin exponer metadatos administrativos sensibles.

La superficie de trabajo comercial queda simplificada y gobernada:

- propuestas dispone de permiso propio, listado paginado, filtros operativos y
  aprobación cruzada; administración puede gestionar cualquier propuesta pero
  nunca aprobar una creada por el mismo usuario;
- pedidos, albaranes y facturas permanecen separados como documentos de venta;
- el pipeline concentra el trabajo diario y las alertas accionables, mientras
  forecast, conversión y SLA se cargan únicamente en análisis comercial;
- scoring e histórico se consultan solo en el expediente, evitando repetirlos
  dentro del formulario de edición y reduciendo el payload del tablero.

El cuarto vertical completa la entrega documental al cliente:

- solar, climatización, recarga eléctrica, electricidad y seguridad disponen de
  plantillas de instalación y puesta en marcha con un paquete de entrega
  explícito;
- los PDF nacen como internos o pendientes de validación y solo administración
  puede publicarlos o retirarlos del portal;
- cada entregable conserva versión, revisor, publicador, fechas y documento al
  que sustituye, sin permitir borrar una versión que ya formó parte del
  histórico;
- publicar una revisión sustituye automáticamente la versión anterior y evita
  que el cliente descargue documentación obsoleta;
- el portal agrupa la versión vigente por instalación y línea de negocio, junto
  con el estado y el hito de finalización confirmados;
- la migración conserva los documentos históricos y traduce la visibilidad
  anterior al nuevo flujo sin convertir archivos internos en públicos.

El quinto vertical cierra el Horizonte 1 con una lectura económica común:

- un único cálculo por cartera reconcilia oferta aceptada, coste planificado,
  compras aún comprometidas, consumos, horas, desplazamientos, servicios de
  proveedor, gastos, facturación y cobro;
- el expediente distingue margen previsto, comprometido y real, desglosa sus
  fuentes y avisa cuando faltan costes o documentos por validar;
- dirección y finanzas comparan las cinco líneas con idénticas definiciones,
  incluyendo los proyectos solares históricos clasificados como `solar_pv`;
- cada proyecto enlaza su expediente y los documentos origen, y el umbral de
  riesgo requiere una erosión mínima del 10 % o margen comprometido negativo;
- las cifras solo se calculan y entregan a usuarios con permiso financiero;
  técnicos y perfiles no autorizados no las reciben ni en la interfaz ni en el
  contexto de Arevia;
- Arevia puede explicar desviaciones y calidad del dato, pero mantiene sus
  acciones como borradores sujetos a confirmación humana.

## Corte ejecutado — Servicio recurrente y posventa

El primer vertical del Horizonte 2 ya convierte una instalación entregada en un
plan contractual versionado con cobertura, activos incluidos, frecuencia, SLA,
vigencia, aviso de renovación y próximas intervenciones. Cada revisión exige una
nota y conserva una instantánea inmutable; el ticket registra la versión que
originó la visita y la orden la entrega también a la app móvil para trabajar sin
conexión.

CRM prepara, solo por acción humana, una oportunidad de renovación enlazada con
el cliente, la instalación y la oportunidad ganada de origen. Finanzas mantiene
una previsión recurrente diferenciada de una factura; el portal publica solo los
planes activos y confirmados, con su alcance y equipos cubiertos. La generación
operativa sigue siendo idempotente y no duplica tickets abiertos ni órdenes.

## Corte ejecutado — Demanda preventiva y salud recurrente

El segundo vertical del Horizonte 2 enlaza cada plan versionado con repuestos
esperados por visita, probabilidad de uso y stock mínimo deseado. Suministro
compara esta demanda con existencias, reservas de proyecto y entradas
confirmadas, descuenta las visitas que ya tienen ticket abierto y conserva las
fuentes por plan y versión. El resultado es estrictamente una previsión: no crea
reservas, solicitudes de oferta, pedidos ni movimientos de almacén.

Analítica define attach rate, ARR, renovación, cumplimiento SLA, first-time fix
y coste real del servicio sobre una población y periodo visibles, con desglose
por línea. Los costes quedan restringidos a perfiles financieros. Posventa añade
la carga recurrente aún no comprometida a la capacidad semanal y alerta sobre
semanas sobrecargadas o planes críticos sin responsable, sin generar ni
planificar órdenes automáticamente.

Arevia recibe los planes autorizados y la demanda preventiva mediante fuentes
identificables, junto con una regla explícita: cualquier contacto de renovación,
reserva, compra, compromiso de agenda o comunicación al cliente debe permanecer
como borrador hasta que una persona autorizada lo revise y confirme.

## Corte ejecutado — Compromiso operativo y margen del ciclo

El tercer vertical del Horizonte 2 incorpora una aprobación humana e idempotente
antes de convertir la previsión recurrente en compromiso interno. Cada ciclo
congela versión contractual, responsable, semana, capacidad disponible,
duración, desplazamiento y costes previstos. El sistema rechaza planes sin
responsable, fuera del horizonte de seis semanas, con una versión obsoleta o en
una semana sobrecargada. Aprobar el ciclo no crea una orden de trabajo ni
publica una fecha al cliente.

Suministro puede convertir una carencia preventiva vigente en un objetivo de
reposición aprobado, conservando periodo, producto, cantidad y fuentes por plan
y versión. Este objetivo permanece expresamente separado de reservas,
movimientos, solicitudes de oferta y pedidos. Las previsiones financieras ya no
se sobrescriben al revisar el contrato: cada combinación de plan, versión y
vencimiento conserva su propio registro.

Posventa y Analítica comparan ingreso y margen previstos con ingreso liquidado,
horas, desplazamientos y materiales emitidos para los tickets de la misma
versión y periodo. Los importes solo se entregan a perfiles financieros; el
resto recibe estado operativo sin campos económicos. Arevia distingue fuentes
previstas y objetivos aprobados, pero mantiene compras, agenda y comunicaciones
externas bajo confirmación humana.

## Corte ejecutado — Facturación recurrente y comunicación gobernada

El cuarto vertical del Horizonte 2 enlaza cada compromiso contractual aprobado
con un único expediente de facturación. La preparación es idempotente, conserva
importe neto, impuesto, vencimiento, nota y autor, y genera exclusivamente una
factura en borrador. Su contenido contractual no puede alterarse desde la
edición general; la emisión fiscal continúa siendo una acción humana separada.

La factura, sus cobros y el abono corrector permanecen vinculados al ciclo y a
la versión exacta del plan. El estado recurrente acompaña borrador, emisión,
vencimiento, cobro parcial o completo y abono. Analítica utiliza el ingreso neto
en proporción a los cobros registrados, con las previsiones históricas
liquidadas únicamente como compatibilidad para ciclos anteriores.

Cada ciclo puede preparar un aviso de mantenimiento y otro de facturación sin
duplicados. El canal procede de la preferencia vigente del cliente; la
confirmación se bloquea cuando existe oposición al contacto, falta el dato de
destino o están desactivados los avisos de mantenimiento. Borrador,
confirmación y entrega conservan autores, fechas y evidencia operativa.

El portal recibe solo comunicaciones confirmadas o entregadas y nunca muestra
borradores, simulaciones ni importes internos. Los perfiles sin autorización
comercial no reciben el contenido de los avisos, y los perfiles sin permiso
financiero no reciben el expediente recurrente ni sus importes.

## Corte ejecutado — Materialización y planificación del servicio recurrente

El quinto vertical del Horizonte 2 convierte cada compromiso aprobado en una
única pareja de ticket y orden de trabajo. La materialización bloquea el ciclo,
utiliza la versión contractual exacta y una instantánea técnica de plantilla,
duración, habilidades, checklist, evidencias y documentación. Un segundo
intento devuelve la misma orden y el generador automático omite los ciclos
gobernados todavía pendientes, evitando trabajos paralelos para una misma
visita.

La capacidad se contrasta de nuevo con el técnico aprobado y los materiales
preventivos disponibles se reservan directamente para la orden. La fecha nace
como planificación interna: no aparece en el portal ni permite sincronizar la
orden móvil hasta una confirmación humana. La API móvil recibe entonces el
contrato operativo, activos, checklist, evidencias y reservas, sin instantáneas
de capacidad, previsiones ni importes económicos.

Cada cambio posterior crea una revisión de agenda, conserva la fecha anterior
como histórica, retira temporalmente la orden de la sincronización móvil y
prepara una nueva revisión del aviso confirmado. El portal distingue fechas y
comunicaciones vigentes de las históricas; nada se sobrescribe ni se publica de
nuevo hasta confirmar la última planificación.

## Corte ejecutado — Cierre y continuidad del servicio recurrente

El sexto vertical del Horizonte 2 convierte la finalización de la orden en el
único cierre válido del ciclo recurrente. La transacción bloquea plan,
compromiso, ticket y orden en un orden estable, exige una agenda confirmada y
captura una sola conciliación de checklist, evidencias, firma, materiales
consumidos, reservas liberadas, horas, desplazamiento y activos atendidos. Los
reintentos móviles devuelven el mismo resultado y no vuelven a avanzar fechas,
costes ni contadores.

El ticket se resuelve desde la orden y ya no puede cerrarse manualmente mientras
el ciclo gobernado siga abierto. La próxima visita avanza hasta el periodo
siguiente sin saltos, cada activo conserva su último servicio, contador y evento
trazable, y el consumo real reduce existencias para alimentar la previsión de
reposición sin crear compras automáticas. Analítica usa el coste congelado del
cierre en lugar de recalcular un pasado mutable.

El portal recibe un resumen operativo sin costes internos y mantiene separada
la publicación documental: completar el trabajo no publica certificados ni
adjuntos. Posventa y la API móvil exponen únicamente métricas operativas; las
instantáneas técnicas, económicas y de capacidad permanecen fuera de esos
contratos.

## Corte ejecutado — Desviaciones y calidad del servicio recurrente

Cada cierre recurrente compara el alcance contractual congelado con su ejecución
real y detecta, sin duplicados, desviaciones de duración, desplazamiento, consumo
de materiales, reaperturas, evidencias y documentación. Las tolerancias se
definen por línea de negocio y cada hallazgo abre una revisión interna con
gravedad, responsable, vencimiento, activos afectados e histórico inmutable de
asignación, confirmación, descarte, resolución o reapertura.

Posventa separa la operación diaria de un área específica de calidad. Solo las
desviaciones confirmadas reducen la puntuación de salud recurrente, señalan los
activos afectados y acompañan la lectura de rentabilidad contractual. Los
hallazgos abiertos o descartados nunca alteran indicadores históricos. El coste
de materiales permanece oculto para perfiles sin permiso financiero.

El análisis puede reconciliar ciclos históricos, pero no modifica planes, no
crea compras u objetivos de reposición, no contacta con clientes y no prepara
renovaciones o propuestas. Cada una de esas consecuencias continúa requiriendo
una decisión humana separada y trazable.

## Corrección transversal ejecutada — origen real de pagos y personal

El pago de gastos y facturas de proveedor mantiene la forma de pago y exige
además su origen real. Transferencias y adeudos seleccionan una cuenta bancaria
activa; las tarjetas se limitan a productos financieros configurados; caja y
otros medios usan un auxiliar compatible; y los importes adelantados por el
personal identifican a la persona acreedora. El asiento conserva el auxiliar
seleccionado y el historial muestra de dónde salió el dinero, evitando agrupar
toda la tesorería en las cuentas genéricas 570 o 572.

Cada integrante del personal dispone de auxiliares individuales 460 para
anticipos y 465 para cantidades pendientes. Los tres últimos dígitos coinciden
siempre con los de su identificación; una secuencia intermedia resuelve
colisiones sin romper esa regla. La generación se ejecuta al crear o actualizar
la ficha y se aplica retroactivamente a la plantilla existente. Cuando una
persona paga un gasto, el auxiliar 465 deja contabilizada la deuda que deberá
liquidarse en el circuito de reembolso, sin duplicar el pago del proveedor.

## Corte ejecutado — Acción correctiva y aprendizaje recurrente

Las desviaciones confirmadas de un mismo plan de mantenimiento pueden agruparse
en un expediente CAPA con causa raíz, responsable, vencimiento y línea base
congelada. Cada expediente separa acciones correctivas de preventivas, exige
evidencia para completarlas y conserva un histórico inmutable de activación,
ejecución, verificación, cierre o reapertura.

La verificación espera el número configurado de ciclos posteriores comparables.
Contrasta la tasa normalizada de desviaciones y el exceso medio sobre tolerancia
antes y después, mientras muestra por separado el cambio de carga planificada
por ciclo. Así, una reducción de actividad no se interpreta como mejora. La
decisión humana puede confirmar o contradecir la recomendación calculada, pero
debe dejar nota y queda vinculada a la instantánea empleada.

El plan puede preparar propuestas para plantilla de trabajo, stock preventivo,
alcance de mantenimiento o renovación. Estas propuestas son artefactos de
análisis: no modifican configuración, no reservan material, no alteran contratos
y no crean oportunidades por sí mismas.

## Corte ejecutado — Decisiones de mejora transversales

Las propuestas CAPA llegan a una bandeja común que filtra en servidor el ámbito
de cada persona: Operaciones gobierna plantillas y alcance recurrente, Suministro
revisa stock preventivo y CRM decide renovaciones. Un perfil no recibe títulos,
motivos, destinos ni históricos pertenecientes a otra área. La bandeja se integra
en la navegación autorizada y en el centro de acciones sin abrir el resto del
módulo de calidad a roles que no deben consultarlo.

Cada propuesta congela la referencia, versión, contenido y huella del destino en
el momento de prepararse. Antes de aprobar se vuelve a leer la configuración
vigente; cualquier cambio de plantilla, plan, materiales u oportunidad bloquea
la aprobación. El área puede devolverla, revisar el cambio propuesto y reenviarla
sobre una nueva instantánea, conservando contador, decisiones y eventos CAPA.

Aprobar, rechazar, devolver y reenviar exige una nota y registra persona y fecha.
La aprobación no modifica plantillas, existencias, contratos ni oportunidades:
solo declara que el cambio está autorizado para pasar a un circuito de aplicación
posterior y controlado.

## Corte ejecutado — Aplicación controlada de mejoras

Una propuesta aprobada requiere una segunda confirmación sobre una vista previa
del destino y de la versión vigente. El sistema vuelve a comprobar su huella bajo
bloqueo y rechaza la ejecución si plantilla, plan, materiales u oportunidad han
cambiado desde la aprobación. Cada intento conserva entrada, persona, resultado o
error; un reintento posterior a un éxito devuelve el mismo resultado sin repetir
el efecto.

Los adaptadores limitan expresamente cada aplicación: Operaciones puede añadir un
control obligatorio a la plantilla o incorporar texto al alcance mediante una
nueva versión del plan; Suministro puede aprobar un objetivo de reposición para
un producto preventivo con carencia vigente; CRM puede preparar la oportunidad de
renovación mediante el flujo existente. No se crean pedidos, reservas ni mensajes
al cliente, y la ficha CAPA conserva destino anterior, destino resultante y autor.

La bandeja distingue decisiones pendientes, propuestas listas para aplicar y
aplicaciones completadas. También alimenta el centro de acciones y mantiene el
aislamiento por área tanto al consultar como al ejecutar.

## Corte ejecutado — Seguimiento de implantación de mejoras

Cada mejora aplicada identifica automáticamente los primeros ciclos, órdenes,
objetivos de reposición u oportunidades que consumen su versión exacta. La
bandeja distingue una mejora todavía no utilizada, una implantación cuyo destino
ha desaparecido o perdido el cambio, una adopción aún bajo observación, un uso
sin eficacia suficiente y un resultado eficaz frente a la línea base CAPA.

Las tasas observadas solo utilizan ciclos comparables completados y desviaciones
confirmadas o resueltas de las mismas categorías que originaron el expediente.
Se muestran adopción, ciclos requeridos, consumidores y resultado sin exponer
costes internos. El histórico no se recalifica cuando aparece una versión
posterior: una segunda decisión autorizada enlaza el sucesor aplicado, conserva
los consumidores de la versión anterior y transfiere el seguimiento activo sin
revertir ni reescribir trabajos terminados.

## Corte ejecutado — Primer escenario compartido

El Horizonte 3 comienza con una fotografía inmutable de la cartera comercial
seleccionada. Administración puede congelar hasta cincuenta oportunidades abiertas
en un horizonte definido; cada versión conserva autor, huella, datos confirmados de
oportunidad y propuesta, composición conocida y las hipótesis explícitas de horas,
coste material cuando todavía no existe propuesta y plazo de cobro.

La comparación calcula valor confirmado y ponderado, demanda de capacidad,
materiales esperados y entradas y salidas mensuales de caja. Los cambios posteriores
en las oportunidades no reescriben escenarios ya congelados. El proceso registra un
evento de seguridad y no crea órdenes, reservas, objetivos de reposición, compras,
previsiones contables, pagos ni comunicaciones al cliente.

## Corte ejecutado — Comparación de escenarios y referencias operativas

Cada escenario se clasifica como base, riesgo o alternativa. Los dos últimos exigen
un escenario base congelado y conservan diferencias de cartera, ingreso ponderado,
coste material, demanda de capacidad, sobrecarga y faltas de stock. No se comparan
consultas vivas: los deltas quedan almacenados con la misma huella del escenario.

La fotografía operativa captura fecha, periodo y capacidad disponible, planificada y
remanente, junto con existencias, reservas y stock libre de cada producto implicado.
La interfaz muestra tanto la referencia como sus diferencias y mantiene las
hipótesis separadas. Las claves foráneas están indexadas y la captura se realiza
antes de una transacción de escritura mínima. Ningún escenario crea o altera
órdenes, reservas, movimientos, compras, pagos ni comunicaciones.

## Corte ejecutado — Aprobación gobernada de escenarios

Los escenarios nuevos fijan una vigencia de 24 horas para su fotografía de capacidad
y stock. La aprobación administrativa bloquea el registro durante la decisión,
compara la huella mostrada por la interfaz con la persistida y recalcula la huella de
todo el contenido congelado. Una referencia caducada, una pantalla obsoleta, una
alteración del contenido o una aprobación concurrente detienen el proceso.

La decisión conserva aprobador, fecha, nota y huella aprobada, y registra un evento
de seguridad. Los escenarios anteriores a este control deben volver a congelarse.
La aprobación no crea órdenes, reservas, movimientos, compras, pagos, previsiones ni
comunicaciones; solo identifica de forma auditable el escenario elegido.

## Corte ejecutado — Solicitudes preliminares por área

Un escenario aprobado y todavía vigente puede generar una sola vez cuatro
solicitudes independientes: operaciones, almacén, compras y finanzas. Cada una copia
únicamente su carga de trabajo, materiales, faltas o caja, conserva la huella
aprobada y añade una huella propia del contenido. Todas nacen como borrador y no
consultan después datos vivos para reescribir su fotografía.

La bandeja filtra las solicitudes según los permisos reales de cada usuario. La
confirmación exige una nota, compara la huella mostrada, recalcula la integridad,
bloquea decisiones concurrentes y registra responsable, fecha y evento de
seguridad. Ni la generación ni la confirmación crean agendas, órdenes, reservas,
compras, pagos o previsiones.

## Corte ejecutado — Previsualización y segunda autorización

Cada solicitud confirmada puede preparar y renovar una previsualización contra el
estado vivo: operaciones recalcula capacidad, almacén existencias y reservas,
compras proveedores activos y finanzas periodos contables. La fotografía conserva
conflictos bloqueantes, huella propia, autor, fecha y una clave UUID de idempotencia.

Antes de la segunda autorización se recalculan los datos. Cualquier cambio obliga a
renovar la previsualización; tampoco se admiten contenido alterado, pestañas
obsoletas, conflictos pendientes ni que el mismo usuario que confirmó conceda la
segunda decisión. La preparación y autorización registran eventos de seguridad y
siguen sin crear ningún recurso real.

## Corte ejecutado — Ejecución final idempotente por área

La ejecución consume atómicamente la clave autorizada y vuelve a comprobar huella,
escenario, solicitud, previsualización y datos vivos. Una solicitud solo puede
ejecutarse una vez. Operaciones crea tareas de agenda vinculadas a cada oportunidad;
almacén crea reservas únicamente con instalación, almacén y disponibilidad válidos.

Compras y finanzas conservan borradores trazables dentro del resultado de ejecución,
sin emitir pedidos ni crear previsiones contables nativas. Cada resultado guarda
responsable, fecha, huella y los identificadores creados, y emite un evento de
seguridad. Los escenarios nuevos conservan además la asignación de materiales por
oportunidad e instalación para que las reservas no pierdan su destino.

## Corte ejecutado — Promoción nativa y seguimiento del resultado

Los borradores ejecutados de compras y finanzas requieren una promoción explícita
posterior. La operación vuelve a comprobar la huella de ejecución y es idempotente:
compras agrupa las faltas por proveedor recomendado y crea solicitudes de oferta en
borrador; finanzas genera cobros y pagos materiales planificados por mes. Ninguna
promoción envía solicitudes, convierte pedidos, aprueba pagos ni liquida previsiones;
cada documento entra en el circuito nativo de su área para continuar la revisión.

Tareas, reservas, solicitudes de oferta y previsiones conservan una clave foránea
indexada hacia la solicitud de planificación. La bandeja consulta sus estados vivos y
muestra completadas, bloqueadas, vencidas o sin asignar, reservas activas, consumidas,
liberadas o caducadas, y el importe y estado actual de los documentos promovidos. Así,
la evolución operativa no reescribe el escenario aprobado y un reintento no duplica
recursos.

## Corte ejecutado — Navegación, excepciones y resultado frente al plan

Cada solicitud ejecutada enlaza ahora con una vista nativa filtrada por su propia
procedencia. Operaciones muestra exclusivamente las tareas generadas, almacén abre
la pestaña de reservas incluyendo su ciclo completo, compras aísla sus solicitudes
de oferta y finanzas desplaza la vista a sus previsiones. Los cuatro módulos indican
el filtro activo, lo conservan al refinar la vista cuando corresponde y permiten
volver al conjunto general.

La bandeja destaca tareas sin asignar o vencidas y reservas activas ya caducadas.
También compara el resultado vivo con el aprobado mediante magnitudes equivalentes:
tareas creadas y terminadas, unidades reservadas y consumidas, unidades e importe
solicitados, y cobros y pagos materiales previstos. Los cálculos se obtienen de los
recursos vinculados y no modifican la huella ni la fotografía del escenario.

## Corte ejecutado — Resolución gobernada de excepciones

Las solicitudes de operaciones permiten asignar en bloque únicamente sus tareas
abiertas y todavía sin responsable. La decisión exige responsable, fecha objetivo y
nota; excluye trabajos ajenos, completados o cancelados y queda registrada como
evento de seguridad. Los técnicos pueden consultar y trabajar sus tareas, pero no
gobernar la asignación global del escenario.

Las solicitudes de almacén permiten renovar o liberar en bloque exclusivamente sus
reservas activas ya caducadas. La renovación exige una nueva fecha futura y la
liberación queda limitada a la misma procedencia. Ambas operaciones bloquean solo la
solicitud y las filas afectadas durante una transacción corta, rechazan reintentos sin
excepciones pendientes y registran actor, nota e identificadores afectados.

## Corte ejecutado — Revisiones y cierre formal del escenario

Los escenarios aprobados disponen de hitos de revisión correlativos e inmutables.
Cada revisión conserva actor, fecha, explicación, resultado actual por área,
recursos vinculados, excepciones pendientes y una huella verificable, sin modificar
la fotografía aprobada ni el estado de los recursos nativos.

El cierre exige una revisión vigente, la ejecución completa de las solicitudes de
área y la promoción de los borradores de compras y finanzas. Una confirmación
explícita permite reconocer las excepciones que aún permanezcan abiertas. El cierre
conserva responsable, fecha, nota, fotografía final y huella propia, y bloquea nuevas
revisiones sin cancelar ni reescribir tareas, reservas, documentos o previsiones ya
creados. Las operaciones usan bloqueos breves, claves foráneas indexadas y
restricciones únicas para evitar secuencias duplicadas y carreras de cierre.

## Corte ejecutado — Aprendizaje desde escenarios cerrados

Cada escenario cerrado transforma su fotografía final inmutable en una lectura
plan-real por operaciones, inventario, compras y finanzas. Las magnitudes conservan
valor planificado, observado, desviación absoluta y porcentual, unidad y severidad;
el resumen distingue métricas alineadas, en observación y significativamente
desviadas.

Las áreas con desviaciones reciben una recomendación explicada para revisar
capacidad, finalización, materiales, precios, cobros o pagos en el siguiente
escenario. La lectura referencia la huella del cierre, no hace consultas vivas y
declara expresamente que nunca aplica cambios automáticos sobre supuestos o datos
maestros.

Este corte añade también la primera instrumentación transversal de rendimiento.
Las respuestas web publican tiempos de aplicación y SQL en `Server-Timing`; las que
superan los presupuestos configurables registran únicamente ruta, método, estado,
duración, tiempo de base de datos, tamaño y correlación. Así se podrá priorizar con
datos reales sin guardar payloads ni información del cliente.

## Corte ejecutado — Carga progresiva del panel

El panel inicial ya no consulta ni serializa el inventario completo de instalaciones
para construir el mapa, ni descarga automáticamente su motor cartográfico de cerca
de 1 MB. Las métricas y la agenda aparecen primero; una acción explícita obtiene los
datos mediante un endpoint autorizado y carga el módulo visual en ese momento. La
intención por teclado o puntero precarga únicamente el código para reducir la espera
sin anticipar datos sensibles.

El payload cartográfico se comparte durante 30 segundos para absorber aperturas y
refrescos próximos. El sondeo periódico del resumen continúa siendo ligero y el mapa
solo se actualiza después de que la persona lo haya abierto. La interfaz conserva
estados bilingües de carga, error y reintento, navegación por teclado y una altura
adaptada a móvil.

## Corte ejecutado — Calibración versionada entre escenarios

Los cierres inmutables ya pueden analizarse de forma agregada por línea de negocio,
área y métrica. El sistema exige al menos tres escenarios monorrama comparables,
una desviación mínima del 10 % y una dirección coincidente en el 75 % de la muestra;
los escenarios mixtos, las métricas sin base porcentual y los patrones insuficientes
no generan propuestas.

Cada patrón válido crea una propuesta persistente e idempotente con versión, tamaño
de muestra, consistencia, desviación media, totales plan-real y huellas de todos los
cierres de origen. Una nueva evidencia produce una nueva versión sin reescribir las
anteriores. Administración puede aceptarla o descartarla con una nota obligatoria;
la decisión, el responsable y la evidencia quedan auditados. Incluso aceptada, la
propuesta nunca modifica supuestos, recursos ni datos maestros automáticamente.

## Corte ejecutado — Referencias de calibración aplicadas por una persona

Aceptar una propuesta exige ahora fijar un valor de referencia válido para el
supuesto relacionado: horas por oportunidad, porcentaje de material sin propuesta
confirmada o días de cobro. La aceptación sigue sin cambiar ningún escenario. En el
formulario de uno nuevo, Administración ve el valor actual, la referencia y su
diferencia, y debe elegir expresamente «usar referencia».

La referencia solo puede utilizarse si toda la cartera seleccionada pertenece a su
misma línea de negocio. Cambiar las oportunidades o editar manualmente el supuesto
la deselecciona. El servidor vuelve a verificar estado aceptado, rama y coincidencia
exacta del valor; al congelar, incorpora a la fotografía la propuesta y versión, la
huella de evidencia, el supuesto, el valor anterior y el aplicado. Así el efecto se
podrá evaluar después sin alterar ni perder el origen de la decisión.

## Corte ejecutado — Evaluación del efecto de las calibraciones

Los escenarios cerrados que aplicaron una referencia pueden compararse ahora con el
patrón inmutable que la originó. El sistema exige que propuesta, huella, área,
métrica y unidad sigan coincidiendo; calcula desviación original, desviación
observada y mejora absoluta en puntos porcentuales, y conserva los valores plan-real
y la huella del cierre dentro de una evaluación propia.

Cada horizonte genera como máximo una evaluación idempotente y cada propuesta
mantiene su secuencia versionada. Una reducción clara recomienda conservar, un
empeoramiento claro retirar y la zona intermedia revisar. Administración decide
entre esas tres opciones con nota obligatoria. La recomendación y la decisión quedan
auditadas, pero ninguna modifica la propuesta aceptada, su valor ni el escenario ya
cerrado.

## Corte ejecutado — Core Web Vitals propios y respetuosos con la privacidad

La plataforma puede medir LCP, INP y CLS reales por pantalla Inertia y clase de
dispositivo. La captura se activa por muestreo configurable, respeta Do Not Track y
carga la librería de medición de forma diferida. Solo conserva el nombre técnico de
la pantalla, métrica, valor, variación, dispositivo, tipo de navegación, despliegue
y fecha; no almacena URL, parámetros, usuario ni dirección IP. El servidor calcula
la clasificación contra los umbrales estándar, valida rangos y limita la ingestión.

Las muestras caducan automáticamente a los 90 días. El comando
`performance:web-vitals-report` resume por pantalla, métrica y dispositivo los
percentiles p50, p75 y p95 y el porcentaje saludable, con ventana y muestra mínima
configurables. La siguiente optimización se elegirá cuando producción aporte una
muestra suficiente, evitando conclusiones basadas en visitas aisladas.

## Corte ejecutado — Gobierno agregado de versiones de calibración

Las calibraciones aceptadas agregan ahora sus evaluaciones humanas decididas en
revisiones inmutables y versionadas. Cada revisión exige al menos tres resultados,
conserva sus huellas y decisiones, calcula la distribución entre mantener, revisar
y retirar, y solo recomienda una dirección cuando alcanza un 75 % de consistencia;
los patrones mixtos se declaran como evidencia insuficiente.

Administración decide expresamente si mantiene activa la referencia, la sustituye
por una versión aceptada más reciente de la misma serie o la bloquea por evidencia
insuficiente. La sustitución marca las versiones anteriores como no utilizables en
escenarios nuevos y conserva el vínculo con la sucesora. Ninguna recomendación se
aplica sola ni modifica escenarios congelados o cerrados, cuyos valores, versiones
y huellas permanecen intactos.

## Corte ejecutado — Formularios base y naturaleza del cliente

Clientes e instalaciones comparten ahora un armazón de formulario reutilizable:
secciones con título y ayuda, resumen accesible de validación, espaciado coherente,
estado de procesamiento y acciones persistentes que ocupan todo el ancho en móvil.
La nueva base reduce diferencias de interacción sin acoplar la lógica propia de
cada módulo.

El formulario de cliente reacciona al elegir particular, empresa, comunidad u
organismo público. Las entidades jurídicas muestran y exigen razón social e
identificación fiscal junto a la persona de contacto principal; un particular no
arrastra una razón social anterior. El servidor aplica las mismas reglas que la
interfaz y mantiene compatibilidad con las actualizaciones parciales antiguas. Los
textos y ayudas incorporados en cliente e instalación tienen versión completa en
español e inglés.

## Corte ejecutado — Propuestas y gastos sobre el sistema común

La edición de propuestas separa ahora contexto, aceptación y pago, partidas,
resumen comercial y trazabilidad de la revisión. Conserva las líneas de catálogo,
los componentes personalizados de hasta cinco niveles, el cálculo de totales y el
borrador previo al envío, pero presenta los errores de todo el documento en un
resumen común y mantiene la acción principal accesible en móvil.

El alta de gasto organiza documento y proveedor, desglose fiscal, retención y
totales, justificantes y notas. La acción de registro permanece visible durante el
desplazamiento y los campos fiscales conservan sus errores individuales. Los dos
flujos, incluidos estados, ayudas y controles complejos de componentes, responden
ahora al selector temporal de español o inglés.

## Corte ejecutado — Identidad fiscal, representantes y contactos

La ficha del cliente distingue ahora identidad operativa e identidad de
facturación sin obligar a duplicar datos: los campos fiscales vacíos heredan los
datos generales. También registra plazo de pago, dirección fiscal independiente
y un contacto de facturación que queda congelado junto a cada nueva propuesta,
documento de venta o factura recurrente.

Los contactos pueden asumir varias responsabilidades —representación legal,
facturación, comercial, técnica o administración de fincas—, identificarse como
firmantes autorizados y editarse desde la propia ficha. El servidor exige correo
para facturación y documento más representación legal para un firmante. Siempre
se conserva exactamente un contacto principal mientras existan contactos, incluso
al eliminar el vigente, y las fusiones de duplicados preservan los nuevos datos.

## Corte ejecutado — Plazos de pago comerciales

El plazo definido en la ficha del cliente deja de ser meramente informativo. Una
propuesta nueva lo utiliza para el vencimiento de su anticipo; cambiar de cliente
actualiza la sugerencia y sigue permitiendo un ajuste explícito. Las facturas
directas calculan su vencimiento desde la fecha de emisión y recalculan la
sugerencia al cambiar cliente o fecha.

Las facturas creadas desde una propuesta aceptada toman el plazo de la instantánea
fiscal de la propuesta, no el valor actual del cliente. Así, un cambio contractual
posterior no altera las condiciones de una oferta ya emitida. El servidor aplica
los mismos valores aunque un cliente API omita el vencimiento y conserva 30 días
como respaldo para registros históricos sin plazo.

## Próximo corte recomendado

### Corrección transversal — pantallas por área y entorno local (12/09/2026)

Se incorpora la petición de separar las pantallas interiores, conservando el
sidebar actual y el programa fiscal. Primer corte local: Atención al cliente,
Mantenimiento, Activos y Contratos y servicios tienen componentes de página y
breadcrumbs propios. Calidad del servicio elimina la dependencia visual de
posventa. Se retira la barra transversal que agrupaba las cinco áreas.

Los formularios y tipos compartidos se reutilizan sin duplicar entidades:
mantenimiento prioriza visitas/materiales y contratos muestra vencimientos,
cuotas y renovaciones; los indicadores ya se solicitan por contexto. Pendiente
ampliar esta separación al resto de áreas, separar los formularios largos por
responsabilidad y validar los flujos con datos y a tamaños móviles estrechos.

PostgreSQL local responde desde Laravel y el contenedor, con volumen persistente.
Se corrige `APP_URL` al puerto local 8000. La documentación operativa está en
[PostgreSQL local](postgresql-local.md), sin contraseñas versionadas.
No hay publicación a producción en este corte ni cambios al calendario fiscal.

Validación: 535 pruebas / 7.107 aserciones, TypeScript, catálogo bilingüe y build
correctos. Revisión visual en la sesión local de Safari de las áreas separadas
con los datos disponibles (sin registros de planes/activos); no sustituye el
ensayo pendiente con registros reales y pantallas móviles. Las guías React y
PostgreSQL orientaron la reutilización de componentes y la comprobación del
entorno sin recrear ni borrar la base.

### Siguiente corte fiscal

**FIN-01 — Integridad de facturación**, conforme a la prioridad fiscal acordada
el 12/09/2026: reproducir y cubrir las emisiones concurrentes, congelar el emisor
fiscal al emitir y utilizar la identidad histórica del proveedor en los libros.
Tres incrementos implementados: revalidación bajo bloqueo al emitir, editar y
borrar; snapshot del emisor en emisión directa/rectificativa y anticipo; identidad
histórica del proveedor en los libros. Las regresiones cubren peticiones obsoletas
y la denegación de mutaciones al perfil de consulta financiera. Pendiente:
pruebas concurrentes con conexiones PostgreSQL independientes. Emitir y preparar
rectificativas ya tienen permisos propios con migración compatible de perfiles
y excepciones (13/09/2026); quedan consulta, cobros, borradores y revisión del
circuito automático de anticipos. No activar todavía remisiones a AEAT ni cambiar documentos
emitidos históricos.

Se conserva a continuación del programa fiscal el corte anterior: extender la
trazabilidad de órdenes de Ingeniería a reprogramaciones/cancelaciones y a la
sustitución explícita de órdenes canceladas. Mantener las mediciones en
producción con `Server-Timing` y percentiles p75, aplicadas ahora también a
facturación, libros y conciliación.

## Corte ejecutado — Favoritos y accesos recientes personales

El sidebar permite añadir y quitar la sección actual de favoritos y recuperar las
cinco últimas secciones visitadas. La selección se guarda por usuario en servidor
para conservarla entre sesiones y dispositivos, con presentación común en móvil
y escritorio y etiquetas en español e inglés.

Solo se aceptan destinos presentes en la navegación autorizada. Los accesos que
pierden autorización se eliminan al volver a cargar el área privada, y los datos
brutos no se exponen en el objeto de usuario. Las precargas no registran visitas;
las recargas de la misma sección no generan escrituras. La modificación de
favoritos es idempotente y las actualizaciones concurrentes se serializan.

La conexión revisable entre Ingeniería y Planificación se completa en el corte siguiente.

## Corte ejecutado — Solicitud técnica de planificación

Un expediente aprobado y vinculado a un proyecto puede solicitar planificación
con una nota obligatoria. Operaciones recibe su alcance, solución, riesgos y
normativa en la bandeja de solicitudes, y acepta o rechaza con justificación.
Ingeniería muestra la decisión, los responsables y sus fechas en el mismo expediente.

El servidor comprueba permisos, versión e integridad del contenido aprobado al
solicitar y revisar, bloquea solicitudes duplicadas y decisiones repetidas, y
registra cada acción en el historial. La aceptación no programa recursos ni
genera órdenes, compras o reservas. Las solicitudes rechazadas conservan su
decisión final; la reapertura con revisiones será un incremento explícito.

La interfaz bilingüe reutiliza un componente adaptable entre ambas áreas.
El siguiente corte incorpora fecha, responsable y horas sobre las solicitudes
aceptadas, manteniendo el seguimiento de rendimiento y el resto del roadmap.

## Corte ejecutado — Propuestas de programación técnica

Operaciones propone fecha, responsable y horas estimadas sobre una solicitud
técnica aceptada. La propuesta se somete a aprobación o rechazo con nota; cada
alternativa posterior a un rechazo crea un registro nuevo y conserva las
decisiones anteriores. Ingeniería puede consultar este historial en su expediente.

El servidor controla permisos, versión técnica, propuesta abierta e integridad
de los datos antes de aprobar; rechaza fechas pasadas y esfuerzos fuera del
intervalo de 0,25 a 24 horas en incrementos de 15 minutos. Las decisiones se
registran con autor y fecha y se reflejan en el historial del expediente.

La fecha aprobada representa una intención de programación: todavía no bloquea
agenda. El siguiente corte comprueba ausencias y solapes antes de crear una orden
revisable. La interfaz comparte formularios
adaptables y traducción completa entre Ingeniería y Planificación.

## Corte ejecutado — Disponibilidad por franja horaria

Una propuesta aprobada permite comprobar una hora de inicio usando sus horas
estimadas. Se contrastan disponibilidad del perfil de servicio, capacidad diaria,
órdenes existentes, ausencias y vigencia laboral cuando existe ficha de empleo.
La carga incluye los tramos de órdenes que comienzan la jornada anterior.
Los horarios incompletos y la falta de capacidad configurada bloquean el resultado.

Las ausencias aprobadas bloquean y las pendientes avisan, sin exponer sus motivos.
La consulta valida permisos, versión y huella de la propuesta, no reserva recursos
y presenta hora de comprobación y zona horaria. Cambiar la hora limpia el resultado.
La creación de la orden repite la comprobación dentro de su transacción;
un resultado favorable por sí solo no constituye una reserva ni autorización.

## Corte ejecutado — Orden operativa desde Ingeniería

Una propuesta aprobada con disponibilidad favorable permite crear explícitamente
una orden planificada. La transacción bloquea propuesta, responsable y referencias
laborales, revalida agenda y ausencias y aplica habilidades, zona y controles
financieros mediante el circuito nativo de órdenes. Las ausencias pendientes
deben resolverse antes de crear trabajo, aunque la consulta previa solo avise.

La orden conserva el alcance técnico, checklist y condiciones de su plantilla;
actualiza la planificación del proyecto, genera su actividad y aplica las reservas
y notificaciones habituales. La pantalla informa de estos efectos antes de crearla.
La propuesta guarda orden, actor, fecha y hora inicial, y el expediente registra
el evento. Los reintentos devuelven la misma orden y una hora distinta se rechaza.
Una cancelación posterior no permite recrearla implícitamente.

Se completa así el recorrido de aprobación técnica, solicitud, propuesta,
disponibilidad y creación operativa. El siguiente incremento enlazará también
reprogramaciones y cancelaciones con el expediente de origen.

## Corte ejecutado — Expediente técnico gobernado de Ingeniería

Ingeniería deja de depender de notas libres dentro de la instalación. Cada
estudio, cálculo, diseño o informe técnico dispone de código ENG, línea de
negocio, alcance, condiciones del emplazamiento, cálculos, solución propuesta,
riesgos, normativa y conclusiones. Puede vincularse a una oportunidad, a un
proyecto o a ambos, y el servidor impide asociar una oportunidad a un proyecto
distinto.

El ciclo separa borrador, revisión, aprobación y entrega a ejecución. Cada cambio
del borrador crea una versión inmutable con autor, motivo y huella SHA-256; la
aprobación y la entrega comprueban la versión y huella abiertas para evitar
decisiones sobre contenido obsoleto. La entrega exige un proyecto y solo registra
el hito gobernado: no cambia el estado de la instalación ni crea órdenes de
trabajo, compras o reservas.

La ficha muestra el contenido técnico, versiones y trazabilidad completa de
eventos y responsables. El formulario usa el sistema visual común, los diálogos
se adaptan a móvil y las fichas largas aplican renderizado diferido. Los perfiles
con consulta de instalaciones pueden leer ENG; solo quienes gestionan
instalaciones pueden editar o decidir. Los expedientes se incorporan también a
la búsqueda global y sus resultados enlazan directamente a la ficha resaltada.
Todo el flujo está cubierto en español e inglés.

## Corte ejecutado — Dominios de servicio y carga contextual

La antigua pantalla única de posventa se divide en cuatro accesos estables sin
duplicar registros ni reglas: Atención al cliente abre tickets y SLA;
Mantenimiento abre planes preventivos y correctivos con capacidad; Activos abre
el parque instalado y sus garantías; Contratos y Servicios abre cobertura,
renovaciones, facturación recurrente y economía del servicio. AST, MNT y CTR se
incorporan así al mismo nivel que el resto de áreas objetivo.

Cada URL solicita únicamente su contexto. La entrada de tickets ya no serializa
planes, garantías, previsión de capacidad ni economía; Activos tampoco carga el
histórico contractual, y los cálculos de capacidad y rentabilidad se ejecutan
solo en Mantenimiento y Contratos respectivamente. Los formularios y acciones
siguen utilizando el mismo ciclo de vida gobernado, por lo que no aparecen copias
divergentes de un contrato o plan.

Activos presenta además un inventario responsive de los equipos instalados con
estado, modelo, serie, intervenciones, último servicio y garantía, enlazado con la
ficha de instalación. Las garantías serializadas abandonan la tabla rígida y se
editan mediante fichas adaptables. Las nuevas rutas heredan el permiso operativo,
se prueban también por acceso directo y conservan traducción completa en español
e inglés. Ingeniería no se publica todavía: sus notas técnicas actuales no
constituyen el expediente versionado y auditable que exige esa área.

## Corte ejecutado — Primera migración del sidebar a las 20 áreas

La navegación deja de concentrar clientes, oportunidades, propuestas,
instalaciones, catálogo y compras en tres bloques genéricos. Cada flujo existente
se presenta ahora dentro de su área funcional y con su código estable: PTY, CRM,
SAL, PRJ, PLN, OPS, PUR, INV, FIN, ACC, HRM, DOC, BI, QMS, CS y SYS. Los grupos
vacíos se eliminan después de aplicar permisos, por lo que ningún perfil descubre
un área o una URL que no pueda visitar.

Proveedores dispone de un acceso semántico propio dentro de Personas y Empresas,
aunque conserva el formulario y controlador común del catálogo para evitar
duplicación. Productos y servicios permanece en Inventario. Este patrón permite
migrar progresivamente rutas antiguas sin romper marcadores ni enlaces internos.
Los códigos se transportan desde el servidor hasta el sidebar, se muestran en la
jerarquía expandida y también contextualizan los tooltips del modo compacto.

Las pruebas cubren tanto la composición exacta de las áreas según permisos como
la denegación por URL directa y las dos entradas semánticas del catálogo. La
traducción incorpora todos los títulos nuevos para mantener íntegramente español
e inglés durante la transición.

## Corte ejecutado — Productos y documentos de compra unificados

El maestro de productos y servicios separa identidad, precios y planificación,
ramas de negocio, composición reutilizable e inventario. Los errores de campos y
componentes aparecen en un resumen común, la acción de guardado permanece
accesible y el diálogo se adapta a la altura y anchura real del dispositivo.

El alta de solicitudes de oferta y pedidos comparte el mismo patrón con bloques
de cabecera, líneas y notas. Las líneas abandonan la tabla rígida de 60 rem y se
editan como fichas sin duplicar campos entre móvil y escritorio; muestran el total
calculado, permiten añadir o retirar partidas y mantienen visibles todos sus
errores. El total estimado y la cantidad de líneas acompañan a la acción final.

## Corte ejecutado — Formulario y dirección de proveedores

El alta y la edición del proveedor ya usan el mismo sistema visual que clientes,
instalaciones, propuestas y gastos: secciones de identidad, dirección y pago,
resumen accesible de errores y acción principal persistente. País, provincia,
municipio, localidad y código postal reutilizan el catálogo geográfico común y
reconstruyen correctamente sus listas al volver a editar.

El selector mantiene el código ISO que exige el maestro de proveedores sin
mostrarlo como una etiqueta técnica, y la localidad se incorpora tanto a la ficha
como a la instantánea histórica de compras. En móvil, el listado deja de depender
de una tabla ancha y presenta fichas completas con las mismas acciones autorizadas.

## Corte ejecutado — Contactos e identidad histórica de proveedores

La ficha de inteligencia de proveedor admite múltiples contactos con
responsabilidades explícitas de compras, facturación, logística, calidad,
soporte técnico o representación legal. Puede señalarse un contacto principal y
un firmante autorizado; el servidor valida sus datos y promociona otro contacto
si se elimina el principal.

Cada solicitud de oferta, pedido, factura o rectificativa de compra conserva una
instantánea de razón social, NIF, dirección, condiciones de pago, cuentas
bancarias y contactos relevantes. Los documentos derivados heredan la instantánea
de su origen, y tanto la ficha como el PDF utilizan esa identidad histórica aunque
la ficha maestra del proveedor cambie posteriormente.

## Corrección transversal — idioma temporal de la plataforma

La cabecera privada permite seleccionar Español o English sin modificar el
perfil permanente del usuario. La elección se valida en servidor, permanece
solo en la sesión activa y actualiza el idioma de Laravel, los mensajes
traducibles, los metadatos de accesibilidad y las propiedades compartidas con
la interfaz. Cuando falte una cadena se conserva el texto de respaldo, de modo
que el selector puede convivir con la traducción progresiva de cada sección sin
mostrar claves técnicas ni bloquear la navegación.

### Corte completado — circuito comercial bilingüe

Propuestas, documentos de venta y el flujo operativo de pedidos, albaranes,
facturas y faltas de stock disponen ya de terminología coherente en español e
inglés. La localización incluye títulos de página, campos y ayudas de formularios,
estados, acciones, mensajes vacíos y textos dinámicos con cantidades o referencias.
La interfaz utiliza **propuesta** para la oferta comercial, **pedido** para el
documento de venta previo al albarán y **factura rectificativa** para los abonos,
evitando traducciones literales incompatibles con el uso administrativo español.

### UI-HOME — Inicio financiero, 06/10/2026

Referencia: «ChatGPT Image 6 Oct 2026, 06_54_00.png». Inicio financiero por
permiso `view-finance`, sidebar contable común, cabecera con ejercicio, tarjetas,
gráfico mensual, estado, saldos, accesos, últimos asientos/documentos, conciliación
y actividad contable. El mapa y la agenda permanecen en «Resumen operativo»;
los perfiles sin acceso financiero mantienen su inicio operativo.

Contratos aditivos en `FinancialOverview` y resumen de solo lectura en
`FinancialDashboardSummary`. Ingresos/gastos/resultados proceden de líneas
contabilizadas, excluyendo cierre; comparación con el mismo periodo anterior,
sin porcentaje si no existe base. Meses futuros se muestran sin valores. Saldos
son movimientos contables del ejercicio, incluido asiento de apertura cuando
exista: 57, 430, 400/410 y 472/477/4700/4750; no sustituyen extractos bancarios.
La fecha es real, el porcentaje indica avance del calendario y no porcentaje de
trabajo terminado. Documentos y accesos respetan permisos de sus fuentes.

| ID | Capacidad y estado | Dependencias y criterio de aceptación |
| --- | --- | --- |
| UI-HOME-01 | IVA a ingresar: contrato nullable y tarjeta preparada; pendiente | Conectar cálculo fiscal autorizado del periodo/modelo 303 con periodo y estado declarativo explícitos. No equiparar el saldo contable de IVA con cuota a ingresar; contrastar rectificaciones y compensaciones |
| UI-HOME-02 | Cola de documentos por procesar: contador nullable; pendiente | Definir estados de recepción, extracción, validación y contabilización, permisos y fuentes. Contador real sin duplicados; distinguir indisponible de cero |
| UI-HOME-03 | Tareas y vencimientos: conciliación bancaria real implantada; resto pendiente | Agregar revisión de facturas, modelos tributarios, cierre mensual y provisiones desde fuentes autorizadas. Contrato de tarea con enlace, cantidad y fecha nullable preparado; vencimientos reales y acciones persistentes antes de permitir completar tareas |
| UI-HOME-04 | Actividad reciente: contabilización real implantada; agregado pendiente | Incorporar facturas, cobros/pagos e informes a partir de eventos auditados. Actor, fecha y destino con permisos de cada fuente; no exponer datos privados de auditoría |
| UI-HOME-05 | Documentos recientes: gastos, facturas de venta y compra autorizadas implantadas; otros pendientes | Incorporar nóminas, adjuntos/cola y estados de procesamiento definidos en UI-HOME-02. No llamar «Procesado» a un documento comercial emitido/borrador; ordenar fuentes con fechas e identidad estable |
| UI-HOME-06 | Búsqueda: buscador global existente conservado; alcance contable pendiente | Extender endpoint a cuentas, asientos y documentos con permisos y navegación a detalle. Mantener descripción de alcance actual hasta disponer de resultados financieros |
| UI-HOME-07 | Accesos rápidos: destinos existentes habilitados por permiso; libros oficiales/informes pendientes | Reutilizar índices pendientes UI-SIDEBAR-03. Nuevo asiento abre formulario sin contabilizar; entrada concreta enlaza al detalle. No duplicar etiquetas para destinos inexistentes |
| UI-HOME-08 | Ejercicio y empresa: ejercicio seleccionable; cambio de empresa pendiente | Cambiar año actualiza todo el resumen y conserva contexto; ejercicio no configurado señalado; multiempresa depende de UI-SIDEBAR-04. No mostrar roles inventados ni una empresa ficticia |
| UI-HOME-09 | Saldos: alcance contable explícito implantado; conciliación ampliada pendiente | Validar integridad de aperturas, cobertura de subcuentas/proveedores de inmovilizado y periodos cerrados; enlazar tesorería con extractos conciliados si se quiere saldo disponible. No considerar ausencia de apertura como saldo real de banco |
| UI-HOME-10 | Verificación e integración | Compilación, tipos, formato/lint y pruebas de cálculos, periodos, permisos, vacío e indisponible. Comparar referencia en escritorio/móvil autenticados y registrar publicación en develop separadamente de trabajo local |

Comprobación local: Safari autenticado muestra Inicio con el sidebar contable,
cuatro métricas y paneles completos en escritorio; se conserva el tema oscuro
seleccionado por el usuario. Comprobado responsive a 402 px; cabecera adaptada, tablas con desplazamiento interno. Pendiente comparación en tema claro.
Suite local: 743 pruebas, 738 correctas, 5 omitidas, 10.977 aserciones;
compilación, TypeScript, ESLint de archivos modificados, Pint y catálogo ES/EN.
Enlaces verificados en Safari: alta manual abre formulario y el enlace a un
asiento selecciona su detalle. No se ha enviado ningún formulario contable.
Los documentos de Gastos conservan su moneda original al mostrar importes;
no se presentan como euros ni se convierten con un cambio supuesto.

### UI-SIDEBAR-CANONICAL — Arquitectura definitiva, 06/10/2026

Sustituye la organización plana de UI-SIDEBAR: fuente canónica en
`WorkspaceNavigation::definition()`, filtrada con los gates existentes. Inicio
más nueve módulos y 30 secciones; escritorio, tablet y móvil consumen la misma
respuesta autorizada. Preferencias locales por usuario; la ruta activa abre su
módulo. Los informes, plantillas, herramientas auxiliares y favoritos conservan
sus rutas y pasan a navegación interna. No se han publicado estos cambios.

| Capacidad | Estado / dependencia / aceptación |
| --- | --- |
| Navegación canónica | Implantada: Comercial (CRM, Clientes, Presupuestos); Proyectos (Proyectos, Instalaciones, Postventa); Operaciones (Planificación, Órdenes de trabajo, Agenda); Almacén y compras (Productos, Inventario, Proveedores, Compras); Facturación (Facturas de clientes, Facturas de proveedores, Cobros y pagos); Contabilidad (Vista general, Asientos, Plan contable, Informes contables, Libros oficiales); Finanzas (Tesorería, Bancos y conciliación, Previsiones); Fiscal (Impuestos, Modelos, Periodos fiscales); Empresa (Personal, Documentos, Configuración) |
| Registro de proyectos | Preparado con acceso a expedientes técnicos; pendiente entidad de proyecto independiente, permisos y relación con instalaciones/expedientes; validar ciclo completo sin duplicación |
| Agenda | Preparada con enlace al resumen operativo existente; pendiente agenda independiente, calendario real y permisos de eventos |
| Asientos | Actualizado localmente el 23/10/2026: listado con filtros/búsqueda/orden/paginación backend y ficha completa; creación/edición de borradores y contabilización con decimales exactos. Plantillas/periódicos existentes conservados; importación pendiente. Véase UI-ACC-04/05/06/25. |
| Informes | Diario, Mayor, situación, resultados, sumas/saldos y terceros existentes; patrimonio neto y flujos de efectivo pendientes de fuentes contables, métodos de cálculo y contraste con ejercicios cerrados |
| Libros oficiales | Vista implantada localmente con cinco tarjetas y pestañas; consultas Diario/Balance y libros de IVA existentes. Generación oficial, versiones, descargas de artefactos y legalización pendientes; formatos e integridad requieren dominio propio. Libros de IVA no sustituyen libros mercantiles. Véase UI-ACC-16/17/18/19. |
| Facturación | Conserva documentos de ventas y gastos existentes; pendiente índice dedicado de facturas de clientes/proveedores y cobros/pagos con filtros, vínculos y estados reales |
| Periodos fiscales | Vista implantada localmente: ejercicios reales y periodos trimestrales únicamente con censo vigente que confirme 303 trimestral y ejercicio natural. Pendientes entidad/calendario por impuesto y cierre independiente; véase UI-FISC-15/16/17/18. |
| Documentos de empresa | Base de conocimiento conservada; pendiente índice documental corporativo, adjuntos, clasificación, búsqueda y permisos por documento |
| Empresa y ejercicio | Pie usa contexto real, arquitectura existente conservada; multiempresa y selector completo dependen de UI-SIDEBAR-04. No se ofrecen empresas ficticias |
| Validación | Rutas existentes preservadas; hubs rechazan secciones no autorizadas; comprobar navegación profunda, persistencia, escritorio y drawer tablet/móvil. Entrega local sin commit, push ni merge |

Validación local de este corte: 740 pruebas correctas, 5 omitidas, 11.367
aserciones; 11 aserciones frontend de navegación, TypeScript, ESLint dirigido,
Pint, catálogo ES/EN y build correctos. Safari autenticado verificó el árbol y
el despliegue de Contabilidad. Corrección de contraste en tema claro aplicada;
verificación visual final de esta corrección y del drawer móvil/tablet pendiente
por uso concurrente de Safari. Informe: `docs/sidebar-navigation-review.md`.

### UI-SHELL — Layout general aprobado, 06/10/2026

Consolidación del layout privado existente, sin alterar la arquitectura canónica
del sidebar. `AppShell`/`AppSidebarLayout` integran sidebar y área principal;
`AppSidebarHeader` es la topbar común. `PageShell` admite full, standard, focused
(y formularios) y master-detail; no existe max-width universal. `PageHeader`
recibe breadcrumbs, título, descripción, metadatos, acciones y navegación
secundaria. Las tabs internas consumen la navegación autorizada existente y
no duplican los módulos principales. Scroll de documento, sidebar fijo,
topbar sticky, tablas con viewport local y safe areas declaradas.

Migración: Inicio financiero y operativo; páginas contables (diario/asientos,
mayor, saldos, balances, resultados, mapeos); plan contable; plantillas/periódicos;
hubs de vista general, informes y libros oficiales; formulario de cliente en
modo focused. Las páginas privadas restantes heredan la topbar/shell y los
usuarios de PageShell/PageHeader reciben la API común. Portales, páginas públicas
y autenticación conservan sus layouts especiales.

| ID | Estado / dependencia / aceptación |
| --- | --- |
| UI-SHELL-01 | Implantado: topbar compacta, acciones de página, breadcrumbs discretos, tabs con overflow horizontal, modalidades de contenido y tokens limitados al shell. Mantener contrastes y probar acciones reales sin alterar sus permisos |
| UI-SHELL-02 | Búsqueda existente reutilizada: clientes, oportunidades, instalaciones/expedientes, órdenes, ventas, compras y proveedores por gates; ⌘/Ctrl+K, combobox, flechas, Enter y Escape, diálogo móvil. Pendiente añadir presupuestos, productos, asientos y documentos con fuentes/permisos reales; no crear índices ficticios. Una command palette de acciones/navegación es evolución pendiente, no backend implantado |
| UI-SHELL-03 | Selector de ejercicio trasladado al pie del sidebar, conservando ruta/filtros. Empresa real conservada; cambio de empresa depende del contexto multiempresa anterior. Idioma, favoritos y Arevia siguen disponibles en herramientas, sin barras permanentes duplicadas |
| UI-SHELL-04 | Asientos: acciones existentes y CSV filtrado integrados en PageHeader; nuevo asiento mantiene formulario/backend y requiere manageAccounting/ejercicio abierto; tabs de Todos/Borradores/Periódicos/Plantillas. Importación preparada como capacidad no disponible (UI-SIDEBAR-CANONICAL), sin botón funcional falso |
| UI-SHELL-05 | Drawer único por debajo de 1024 px; topbar móvil por debajo de 640 px; acción contable principal inferior y acciones secundarias en sheet. Tablas existentes conservan scroll horizontal; listas/cards móviles específicas se abordarán por módulo, sin reducir columnas a texto ilegible |
| UI-SHELL-06 | Verificado con sesión autenticada en navegador independiente: escritorio 1440×900, iPad 834×1112 y móvil 390×844; drawer, Escape, búsqueda, menús, acciones y ausencia de overflow global. Ejercicio conservado entre Asientos y Periódicos. Sin errores ni warnings de consola. Informe: docs/app-shell-review.md |

La validación visual pendiente del corte anterior UI-SIDEBAR queda resuelta en
UI-SHELL-06: contraste del sidebar y drawer comprobados en navegador independiente.

### UI-HOME — Inicio definitivo, 06/10/2026

Inicio reutiliza el shell aprobado y FinancialDashboardSummary existente. Se
implanta bienvenida dinámica sin breadcrumbs, cuatro KPI, gráfico mensual
Recharts con tabla alternativa, estado real del ejercicio, saldos, accesos a
acciones autorizadas y documentos/tareas/actividad en composición 2:1:1.
En móvil: KPI en una columna, tareas antes de documentos y lista documental
compacta. La vista operativa se conserva para perfiles sin view-finance.
No se cambian sidebar, routing, modelos contables ni dependencias.

| ID | Estado / dependencias / aceptación |
| --- | --- |
| UI-HOME-01 | Implantado: datos contables reales por ejercicio y corte temporal; comparación previa sin divisor válido devuelve null. Verificar cuentas de ingreso/gasto, exclusión del cierre y ausencia de cifras del mockup |
| UI-HOME-02 | Implantado: fechas y progreso diario a partir de FiscalYear, limitado a 0–100; sin ejercicio configurado, fechas/progreso no disponibles. Prueba de periodo personalizado, antes y después del mismo |
| UI-HOME-03 | Pendiente conexión del KPI IVA a ingresar: existen VatReport, VatSettlement y Model303Preview, pero no un importe fiscal validado/periodo elegido en el contrato de Inicio. Definir periodo, procedencia y distinción de estimación frente a declaración; reutilizar fuentes existentes sin cargar libros/documentos completos en cada apertura. Aceptar con trazabilidad y sin confundir saldo 472/477 con deuda a ingresar |
| UI-HOME-04 | Pendiente contador unificado de documentos por procesar: definir fuentes, estados y permisos de gastos, ventas y compras; evitar doble conteo. Mantener null (capacidad no integrada), diferenciándolo de cero documentos consultados |
| UI-HOME-05 | Parcial implantado: tareas reales de conciliación bancaria. Otras obligaciones, revisiones y vencimientos dependen de fuentes y permisos propios; aceptar con enlaces a acciones reales, sin task manager nuevo ni checkboxes que aparenten completar tareas |
| UI-HOME-06 | Parcial implantado: actividad real por posted_at de asientos. Extensión a cobros, pagos y documentos depende del audit log/eventos existentes, permiso por evento, orden temporal y deduplicación; no generar un segundo historial |
| UI-HOME-07 | Pendiente Nuevo proyecto: no hay creación de entidad de proyecto independiente. Depende de UI-SIDEBAR-CANONICAL. Se omite la acción; no se etiqueta como proyecto una instalación para rellenar el diseño |
| UI-HOME-08 | Deuda detectada: el P&G existente consulta el año completo sin filtro posted, mientras Inicio conserva posted hasta as_of. Reutiliza la misma convención de signo/tipo de cuenta y exclusión del cierre, pero pueden diferir por borradores/fechas futuras. Armonizar posteriormente el alcance en una fuente contable única y contrastar ambos informes con fixtures; no rehacer Contabilidad en esta fase |
| UI-HOME-09 | Contexto actual de una empresa: nombre real dinámico; los datos pertenecen al ERP actual, sin aislamiento multiempresa ficticio. Selección/filtrado multiempresa depende de UI-SIDEBAR-04 y contexto autorizado de empresa |
| UI-HOME-10 | Rendimiento: listas limitadas a cinco por fuente y relaciones eager; agregación contable existente por cuenta/fecha, doce valores mensuales en props. Revisar crecimiento de esa agregación con profiling antes de introducir cache o resumen mensual. Carga actual síncrona de Inertia: no skeleton artificial; errores aislados/reintento por widget dependen de una futura carga parcial real |
| UI-HOME-11 | Verificación técnica: 45 tests de Dashboard/FinancialDashboard correctos (457 aserciones), incluyendo privacidad en reload parcial, fuentes, comparativas y progreso. TypeScript, ESLint dirigido, i18n y build comprobados; visual responsive e informe en docs/home-dashboard-review.md |


### UI-COMMERCIAL — Dashboard comercial definitivo, 06/10/2026

Página independiente `/commercial`; conserva CRM, Clientes y Presupuestos y el shell canónico. Datos agregados con permisos por dominio, ámbito propio heredado de AnalyticsFilter y periodos reales. Informe: docs/commercial-dashboard-review.md.

| ID | Estado / dependencia / aceptación |
| --- | --- |
| UI-COMMERCIAL-01 | Implantado: cuatro KPI autorizados, gráfico Recharts con tabla alternativa, pipeline, presupuestos, clientes, eventos y tareas reales. 59 pruebas/1.444 aserciones; responsive 1440, 1024, 834 y 390 px. Sin cifras del mockup |
| UI-COMMERCIAL-02 | Pendiente catálogo configurable de etapas: hoy existen siete etapas en OpportunityCatalog, no lead_stages ni trece estados del diseño. Depende de decisión funcional y migración reversible del CRM; aceptar preservando historiales, transiciones, cierres y automatizaciones. No crear etapas desde este dashboard |
| UI-COMMERCIAL-03 | Parcial: objetivo de ingresos reutiliza FinancialBudget revenue, prorrateado por días, solo ámbito de equipo con view-finance. Objetivos comerciales individuales/mensuales propios y configuración dependen de entidad, permisos y asignación; aceptar con trazabilidad y sin presentar presupuesto financiero como cuota individual |
| UI-COMMERCIAL-04 | Pendiente seguimiento/inactividad >90 días: lifecycle_status no constituye última interacción comercial. Depende de definición de interacción y fuente consolidada; aceptar con fechas verificables y scope propio/equipo |
| UI-COMMERCIAL-05 | Pendiente comparaciones históricas de pipeline/presupuestos: se muestran estados actuales de registros creados/emitidos en el periodo. No existen snapshots equivalentes para delta histórico; aceptar con reconstrucción temporal verificable, sin comparar stocks actuales como históricos |
| UI-COMMERCIAL-06 | Pendiente normalización de importes: ventas netas sin IVA según Analytics; presupuestos amount según Quote (total). Definir base homogénea sin alterar fuentes silenciosamente; aceptar con descuentos, impuestos y abonos contrastados. Las series son métricas distintas |
| UI-COMMERCIAL-07 | Parcial actividad: OpportunityEvent y QuoteStatusHistory existentes, seis resultados por fuente y seis combinados. Unificación de cambios de clientes y otros eventos depende del audit log existente y permisos por evento; aceptar deduplicación y trazabilidad. No crear task manager ni historial paralelo |
| UI-COMMERCIAL-08 | Parcial tareas: acciones CRM pending/in_progress con oportunidad y vencimiento dentro del periodo. Tareas sin oportunidad/fecha o futuras fuera del periodo requieren contrato funcional; aceptar navegación real y permisos, sin checkboxes ficticios |
| UI-COMMERCIAL-09 | Ámbito propio heredado: oportunidades por owner_user_id, presupuestos por created_by y clientes relacionados con oportunidades propias. Reasignación comercial de presupuestos y propiedad autónoma de clientes dependen de reglas/modelos existentes; aceptar exclusión de datos ajenos sin ampliar accesos |
| UI-COMMERCIAL-10 | Rendimiento: agregaciones SQL, doce buckets, relaciones eager y límites de listas; consulta estable comprobada con veinte oportunidades. Cache/materialización e índices adicionales requieren profiling real y análisis de planes, sin migraciones especulativas |


### UI-CRM — CRM operativo definitivo, 06/10/2026

Se conserva `/opportunities` como CRM independiente de `/commercial`. Kanban
principal, tabla paginada y agenda mensual sobre CrmActivity existente. Shell,
sidebar, Inicio, dashboard Comercial, Clientes y Presupuestos no se rediseñan.
Detalle, alta, notas e historial reutilizan los flujos existentes.

| ID | Estado / dependencia / aceptación |
| --- | --- |
| UI-CRM-01 | Implantado: catálogo de siete etapas servido por OpportunityCatalog al índice y detalle; scroll horizontal independiente, cards reales, responsables, valor, antigüedad y estados vacíos. Las trece etapas conceptuales dependen de UI-COMMERCIAL-02; no se han añadido ni equiparado Accepted con Installed ni Rejected con Lost |
| UI-CRM-02 | Implantado: ocho cards iniciales por etapa, agregado SQL completo y navegación por lotes de ocho manteniendo primer lote visible. Window ROW_NUMBER en una consulta para selección, relaciones eager. Tabla de 25 filas, orden y filtros en URL; resumen inferior limitado a ocho cards cargadas, no listado completo |
| UI-CRM-03 | Implantado: movimiento HTML nativo y selector accesible; etapa visible permanece canónica hasta respuesta. PATCH mínimo con expected_stage y lockForUpdate, servicio de transiciones e historial existente. Fallos muestran feedback, sin optimistic stage ficticio. Lost utiliza formulario existente con motivo. Sin librería nueva ni realtime |
| UI-CRM-04 | Implantado: búsqueda remota con debounce 350 ms por referencia/título/cliente/email/teléfono/instalación; responsable y origen canónicos, estado global separado de etapa, fecha de creación y seguimiento. Filtros guardados amplían claves existentes; estados loading/error y filtros móviles en Sheet |
| UI-CRM-05 | Implantado: agenda mensual por due_at de CrmActivity pending/in_progress, filtrada por oportunidades, con 25 filas y paginación; no segundo modelo de calendario. No sustituye Agenda /operations ni mezcla permisos de operaciones. Pendiente cuadrícula mensual avanzada/reprogramación si aporta valor; aceptar reutilizando esas actividades y permisos |
| UI-CRM-06 | Implantado: detalle con deep link y pantalla completa existente; edición de card fuera del primer lote soportada. Notas son OpportunityEvent note_added según modelo actual, no lead_notes; timeline conserva autor/fecha/cambios y relaciones Quote. Drawer de detalle opcional pendiente: depende de navegación Inertia del detalle y misma autorización; aceptar refresh/back/forward y sin timeline duplicada |
| UI-CRM-07 | Permisos existentes conservados: manage-customers mediante PermissionResolver/perfiles/overrides en backend, no rol Manager/Captador inventado. CRM actual permite ámbito de equipo a quien tiene ese gate; owner=me es filtro, no frontera de seguridad. Ámbitos propios y separación Captador/Comercial/Manager requieren definición funcional y policy común para índice/detalle/alta/edición/notas/tareas/presupuestos; aceptar pruebas negativas directas por endpoint. No se ha confundido el scope analítico del dashboard Comercial con una policy CRM inexistente |
| UI-CRM-08 | Pendiente campos no presentes: potencia estimada, lat/long del lead y trece estados comerciales/postventa. No se inventan en cards ni se añaden columnas. Dependen de modelo/relaciones reales; aceptar trazabilidad de instalación y semántica de estados sin duplicar Lead/Opportunity |
| UI-CRM-09 | Rendimiento operativo verificado con 29 oportunidades y consulta estable; componentes no recorren pipeline completo. Deuda heredada: análisis comercial legacy y selectores completos de clientes/instalaciones/orígenes ganados del formulario. Evolución a lookup paginado/carga diferida y resumen analítico SQL depende de profiling; aceptar preservando edición de relaciones antiguas y métricas. Métricas legacy del payload operativo salvo totales monetarios se calculan sobre cards cargadas y no se presentan como KPI globales; análisis mantiene su fuente completa |
| UI-CRM-10 | Verificación: 53 pruebas, 1.267 aserciones (CRM, Opportunity, Comercial, navegación y seguridad de presupuestos), TypeScript, ESLint dirigido, i18n y build correctos. Kanban/Tabla/agenda/ficha/formulario revisados con datos reales; iPad 834/1024 y móvil 390 sin overflow global, desktop 1440. Informe docs/crm-redesign-review.md. Ningún dato ficticio insertado en el entorno conectado ni publicación Git |

### Corrección incremental de navegación Comercial (2026-10-06)

- Estado: implantado localmente, pendiente de revisión; sin publicación ni operaciones Git de entrega.
- Comercial es el padre navegable a `/commercial`; sus únicos hijos son CRM (`/opportunities`), Clientes (`/people`) y Presupuestos (`/quotes`). Sustituye la estructura anterior con hijo Comercial redundante.
- Capacidad compartida aditiva `navigable` para nodos con ruta e hijos, aplicada únicamente a Comercial. Enlace y botón del acordeón independientes; etiquetas accesibles, teclado y objetivo táctil de 44 px.
- Se conserva la clave de preferencias existente. El dashboard respeta el grupo contraído; entrar en una ruta hija muestra su contexto activo. Se conserva el cierre manual en la página actual.
- Dependencias: destinos existentes y sus permisos; no requiere nuevos datos, migraciones, dependencias ni trabajo funcional del CRM/dashboard.
- Aceptación: dashboard y CRM conservados, tres hijos correctos, coincidencia de rutas y estados activos, apertura/cierre sin navegación, persistencia y navegación responsive verificadas.
- Verificación: 24 tests PHP (766 aserciones), 16 aserciones frontend de navegación, TypeScript, ESLint y build; comprobación interactiva de escritorio, tablet y móvil.

### Comercial → Clientes: ficha 360º (2026-10-06)

La navegación canónica no cambia: Comercial abre `/commercial`; Clientes abre
`/people`; cada cliente conserva `/people/{id}`. Estado: **implantado localmente,
pendiente de revisión**, sin commit, push, merge ni publicación.

| Capacidad | Estado | Dependencias y criterio de aceptación |
| --- | --- | --- |
| Listado, búsqueda, filtros y ordenación | Implantado | `Person` con categoría customer; consulta remota con debounce, filtros reales de tipo/estado/provincia/origen/etiqueta/relaciones/archivo, orden y paginación en URL. Agregaciones sin N+1. |
| Ficha 360º y deep link | Implantado | Lista y detalle en escritorio; detalle separado en tablet/móvil. Refrescar, pestañas y volver conservan el contexto de búsqueda/página. |
| CRM y Presupuestos | Implantado | Oportunidades y presupuestos reales por person_id; enlaces al registro original; presupuesto nuevo preselecciona cliente. No duplica workflows. |
| Proyectos e Instalaciones | Implantado | Proyectos son expedientes técnicos relacionados por oportunidad/instalación. Instalaciones incluyen titular y relación compartida. Mantiene su distinción. |
| Facturación y volumen | Implantado | Gate real manage-installations. Volumen = suma de amount (IVA incluido) de invoice con estado issued/partial/paid/overdue, todos los periodos. Excluye draft/cancelled, no descuenta rectificativas: facturación bruta emitida, no ingreso neto. Sin permiso, no se consulta ni serializa el importe. |
| Actividad | Implantado con alcance definido | CrmActivity, OpportunityEvent, presupuestos, facturas y órdenes de trabajo, paginados y ordenados por fecha. La columna Última actividad CRM usa MAX(created_at) de actividades directas del cliente, sin instalación/orden. No utiliza Person.updated_at ni representa última interacción de todos los dominios. |
| Documentos | Integración disponible | Adjuntos kind=document de órdenes de trabajo vinculadas a instalaciones del cliente, con nombre, origen, tamaño, fecha y descarga autorizada. Técnicos: solo sus órdenes. No expone rutas privadas de almacenamiento. |
| Contactos y direcciones | Conservado e integrado | CustomerContact permite contactos múltiples y roles. Reutiliza formularios existentes de contacto/etiquetas/consentimiento. Dirección general y fiscal diferenciadas; ubicaciones de instalaciones independientes. |
| Comercial responsable del cliente | Pendiente | No existe asignación directa en Person. Definir propiedad y scopes, contrato aditivo y permisos; migración y backfill revisados. Aceptación: responsable real visible y filtro autorizado sin confundirlo con owner de oportunidades o commercial de instalaciones. |
| Repositorio documental central del cliente | Pendiente | Definir vínculo directo cliente-documento y sus permisos sobre sistema existente. Aceptación: documentos por origen, con búsqueda/paginación y autorización de descarga; sin inventar archivos ni duplicar gestor documental. |
| Última interacción consolidada | Pendiente | Unificar eventos comerciales, propuestas, facturación y actividades, distinguiendo interacción de edición de datos. Aceptación: fecha agregada trazable y filtros de actividad/inactividad con alcance por permisos. |
| Sectores, imagen y sedes múltiples | Pendiente de necesidad funcional | No existe catálogo de sectores ni imagen de cliente; usa tipos reales e iniciales. Direcciones general/fiscal y ubicaciones de instalación ya disponibles. Definir catálogo y relación de sedes si se requieren; no incorporar almacenamiento solo por el mockup. |
| Identificación fiscal y duplicados | Validación existente conservada | Validación básica del formulario y coincidencias limitadas por NIF/email/teléfono normalizado en la ficha. No es verificación fiscal completa ni deduplicación automática. Pendiente: definir política fiscal/duplicados si se necesita endurecerla. |

Verificación: suite Clientes/CRM/Portal/Comercial/Navegación, tests frontend de
URLs/formato/navegación, TypeScript, ESLint, catálogo bilingüe, Prettier, Pint y
build. Validación interactiva a 1536, 834 y 390 px, las siete pestañas de relaciones,
recarga, regreso con filtros, edición real y presupuesto preseleccionado.
El detalle de fuentes, permisos, límites y resultados está en
`docs/customers-redesign-review.md`.


### Corrección UX incremental de Clientes — 7 de octubre de 2026

Estado: implantado y verificado localmente, pendiente de entrega Git.
Sustituye el master/detail simultáneo de la fase anterior por listado → ficha
360º independiente, ambos a todo el ancho disponible del AppShell. Las rutas
reales `/people` y `/people/{person}` y los parámetros del listado se conservan.
Se reutiliza CustomerDetail, con breadcrumb de cliente, regreso visible,
resumen responsivo y pestañas completas. No cambia backend, queries, permisos,
modelos, relaciones ni los módulos conectados. Las carencias documentadas en la
fase anterior siguen pendientes con las mismas dependencias.

Aceptación verificada: ningún listado simultáneo en la ficha; regreso con
búsqueda, orden y tamaño de página; deep link, refresh y atrás/adelante; siete
pestañas; formularios existentes de edición y presupuesto; escritorio 1536 px,
tablet 834 px y móvil 390 px sin desbordamiento. Pasan 19 tests PHP (328
aserciones), 15 aserciones de navegación frontend, ESLint, TypeScript, i18n,
Prettier y build. Ver `docs/customers-full-page-correction.md`.

### Comercial → Presupuestos: rediseño funcional (7 octubre 2026)

Implementado sobre Quote y sus servicios existentes: listado/ficha independientes,
filtros/orden/paginación/KPI backend, vistas de consulta Kanban y agenda, versiones,
duplicación, precisión decimal por línea, permisos relacionados, control de versión,
archivo privado del PDF exacto de nuevos envíos y auditoría paginada. Se mantienen
los cinco estados reales, el PDF corporativo y los efectos de aceptación/rechazo.
No se incorporan los datos ficticios de la imagen. Informe: `quotes-redesign-review.md`.

| ID | Estado y alcance | Dependencias | Criterio de aceptación |
|---|---|---|---|
| UI-QUOTES-01 | Pendiente: estado Preparado independiente | Definición comercial, workflow y migración de estados aprobada; compatibilidad con CRM/portal | Diferenciar preparado de aprobación; transiciones, permisos, KPI y versión verificables; no mapear pending approval como Preparado |
| UI-QUOTES-02 | Pendiente: responsable comercial autónomo | Reglas de asignación, modelo/permisos y relación con responsable CRM | Autor y comercial separados, reasignación auditada y filtros coherentes; no renombrar created_by como comercial |
| UI-QUOTES-03 | Pendiente: potencia y tipología contractual | Fuente técnica por servicio y unidad, modelo existente de proyecto/instalación | Mostrar datos reales o ausencia explícita, sin extraer kW de textos ni introducir cifras del mock |
| UI-QUOTES-04 | Parcial: nuevos originales PDF archivados; históricos anteriores sin original | Archivo privado original antiguo, inventario de storage y política de conservación/backups | Recuperar solo binarios originales acreditados por entrega/hash; no regenerar un documento histórico y llamarlo original |
| UI-QUOTES-05 | Pendiente: librería de documentos técnicos adicionales | Modelo documental/almacenamiento existente, relaciones y permisos | Fichas técnicas/esquemas reales vinculados al presupuesto con trazabilidad y acceso autorizado |
| UI-QUOTES-06 | Parcial: Mail real reutilizado; entrega externa y resiliencia operacional pendientes | Transporte real configurado, pruebas con destinatario autorizado, diseño de outbox/idempotencia | Fallos no marcan Enviado; reintentos no duplican envíos; registrar aceptación del proveedor sin equipararla a recepción; evitar bloqueo prolongado durante transporte |
| UI-QUOTES-07 | Parcial: consulta Kanban y agenda de página; calendario mensual/DnD pendientes | Endpoint acotado por intervalo, transición válida y permisos backend | Fechas reales del intervalo, volumen controlado, cambios auditados; sin arrastre que eluda workflow/aprobación |
| UI-QUOTES-08 | Pendiente: scopes comerciales individuales | Policy común acordada para índice/detalle/alta/edición/PDF/envío | Acceso consistente en todos los endpoints y relaciones, sin convertir el filtro Autor en protección aparente |
| UI-QUOTES-09 | Parcial: control optimista en nueva UI; clientes antiguos opcionales | Auditoría de consumidores previos y estrategia de compatibilidad | Versiones esperadas obligatorias tras migrar consumidores, conflictos visibles y ningún overwrite silencioso |
| UI-QUOTES-10 | Pendiente: selectores remotos a gran escala | Endpoint de autocomplete con permisos/búsqueda/paginación | No descargar todos los clientes/autores al aumentar el volumen; filtros y contexto conservados |

Aceptación de esta fase: 39 tests PHP (601 aserciones), 16 aserciones frontend,
ESLint, TypeScript, i18n, Pint, Prettier y build; comprobación de PostgreSQL local
además de tests SQLite. Ningún correo ni conversión de negocio real ejecutados
para validar el diseño; fixtures solo en tests. No se requiere migración.

### Proyectos: dashboard, proyectos, instalaciones y Postventa — 7 octubre 2026

Estado: implantado y verificado localmente; sin entrega Git. El padre Proyectos
abre `/execution`; su chevron mantiene la expansión independiente y sus únicos
hijos son Proyectos, Instalaciones y Postventa. Se preservan Sidebar/AppShell y
las pantallas canónicas anteriores. El dominio operativo existente es
`Installation.project_reference`: se implementa su proyección como proyecto,
sin duplicar entidades ni utilizar los proyectos públicos `PortfolioProject`.

Dashboard con agregaciones SQL por cohorte de creación, estados actuales,
valor autorizado del último presupuesto aceptado o estimación, potencia FV en
kW, líneas de servicio y fechas de instalaciones/órdenes/planes. Listado y ficha
independientes, filtros/orden/paginación persistidos en URL, pestañas de consulta
con carga contextual y enlaces a la gestión existente. Postventa reutiliza
instalaciones finalizadas, planes e incidencias y sus scopes de asignación.
La conversión aceptada presupuesto → proyecto conserva su transacción y
protección contra duplicados. No se incorporan cifras del mock ni revisiones
anuales ficticias. Informe completo de 28 puntos: `projects-redesign-review.md`.

| ID | Estado y alcance | Dependencias | Criterio de aceptación |
|---|---|---|---|
| UI-PROJECTS-01 | Parcial: unidad operativa actual basada en Installation; proyecto autónomo y relación 1:N pendientes | Decisión de dominio, modelo/migración y políticas; preservar Quote/Opportunity/Installation y numeración | Un proyecto puede agrupar instalaciones físicas distintas sin copiar registros ni perder referencias; migración y conversión idempotentes |
| UI-PROJECTS-02 | Pendiente: taxonomía de instalación residencial/industrial/comunidad/comercial/pública | Campo y catálogo de uso del emplazamiento, validación, normalización de históricos | Clasificación real e independiente de service_type; agregación, filtros y permisos consistentes; sin inferirla del nombre del cliente |
| UI-PROJECTS-03 | Parcial: valor aceptado/estimado disponible; base homogénea y evolución histórica pendientes | Definir IVA/moneda de estimated_budget, instantáneas históricas y periodo operativo | Total comparable con base financiera explícita, registros incompletos visibles y variaciones calculadas desde periodos equivalentes; no sumar versiones del presupuesto |
| UI-PROJECTS-04 | Parcial: fechas e hitos existentes; fin planificado, legalización y cierre administrativo pendientes | Campos/eventos operativos con transición y responsables reales | Separar fecha prevista de finalización efectiva; hitos auditados, pendientes explícitos y fechas clave consultables sin usar CRM como ejecución |
| UI-PROJECTS-05 | Parcial: adjuntos privados de órdenes y expedientes técnicos; biblioteca documental unificada e informe de proyecto pendientes | Modelo documental y storage existentes, vínculos/ACL, generador de informes reales | Descargar documentos propios autorizados; integrar PDFs/dossiers/materiales sin endpoints ni reportes simulados |
| UI-PROJECTS-06 | Parcial: actividad auditada de instalación/órdenes; timeline consolidado pendiente | Eventos de presupuesto, ingeniería, materiales, mantenimiento y tickets; políticas de visibilidad | Historia consolidada paginada y ordenada, sin exponer snapshots financieros ni duplicar eventos |
| UI-PROJECTS-07 | Parcial: planificación, equipos y materiales existentes integrados; gestión heredada aún extensa | Formularios y endpoints actuales, paginación/autocomplete y payloads por sección | Mantener mutaciones operativas y trazabilidad sin descargar grafos completos al abrir una ficha ni crear inventario paralelo |
| UI-PROJECTS-08 | Parcial: Gate/scopes actuales respetados; segregación multiempresa e individual de proyectos pendiente | Contexto corporativo real y políticas comunes de instalaciones/proyectos; modelo actual sin company_id | Todas las lecturas/mutaciones/destinos aplican la misma segregación; filtros de cliente/técnico nunca sustituyen una policy |
| UI-PROJECTS-09 | Parcial: listados y relaciones paginados, agregados SQL y eager loading; selectores y widget isolation pendientes | Autocomplete autorizado/paginado, índices con EXPLAIN sobre volumen real, contratos de fallos por widget | Complejidad estable por página; no descargar todos los clientes/técnicos; fallo secundario visible sin fabricar métricas ni inutilizar widgets independientes |
| UI-PROJECTS-10 | Parcial: planes/tickets reales y fecha siguiente existentes; programa postventa y garantías avanzadas pendientes | Reglas de cobertura/frecuencia/garantía y servicios existentes, estados y pruebas | Revisiones según plan real, incidencias autorizadas y continuidad trazable; instalación finalizada sin plan conserva ausencia explícita |
| UI-PROJECTS-11 | Parcial: PRJ existente en conversión; numeración manual unificada pendiente | Auditoría de referencias históricas y edición manual, reglas de unicidad/normalización | Alta y edición no duplican referencias; conservar códigos existentes y la conversión bloqueada/idempotente |

Validación: 128 tests PHP (2317 aserciones), 63 aserciones frontend entre navegación
canónica/clientes/presupuestos/proyectos, ESLint completo, TypeScript, i18n,
Prettier de los archivos nuevos y build. Verificación visual en escritorio
1536 × 1024, tablet 834 × 1194 y móvil 390 × 844 con PostgreSQL local, incluidas
URL, filtros, regreso y tabs. Único aviso de build: chunks grandes ya existentes.
No hay migración ni librerías adicionales. No se han ejecutado correos,
conversiones ni cambios de datos de negocio real para probar el diseño.

### Operaciones: panel, planificación, OT y Agenda — 7 octubre 2026

Estado: implantación local, sin commit/push/merge. Operaciones es padre navegable
(`/operations`), con chevron independiente y exactamente tres hijos:
Planificación (`/operations/planning`), Órdenes de trabajo (`/work-orders`) y
Agenda (`/operations/agenda`). Se conserva la gestión de actividades en
`/operations/activities` y las herramientas documentales, firma, cronómetro y
consumos en `/work-orders/manage`. Enlaces antiguos con `work_order` abren la
ficha independiente; parámetros históricos de Operaciones conservan su destino.

El panel usa fechas de ejecución y completed_at, conteos SQL y proyección semanal
acotada. La matriz trabaja con técnicos reales, valida solapamientos, capacidad,
cualificación y zona mediante TechnicianSchedule; reprogramación transaccional,
registro de motivo y comprobación optimista de updated_at. OT: creación sobre
WorkOrderFactory, listado paginado y ficha a todo el ancho con pestañas paginadas
solo al solicitarlas. Agenda proyecta OT y actividades independientes sin copiar
entidades ni duplicar las actividades enlazadas a una OT. Zona Europe/Madrid
(configurable); entrada ISO normalizada en servidor. Informe: `operations-redesign-review.md`.

| ID | Estado y alcance | Dependencias | Criterio de aceptación |
|---|---|---|---|
| UI-OPS-01 | Implantado: cuatro áreas, padre navegable, lista y ficha independiente | Modelos y permisos existentes; shell canónico | Texto e icono abren panel; chevron no navega; tres hijos; deep links y regreso funcionales |
| UI-OPS-02 | Parcial: planificación por técnico; equipos/cuadrillas y vehículos pendientes | No existe relación operativa Team/Crew/Vehicle; decisión de modelo, disponibilidad y asignación | Recursos reales vinculados sin duplicar Personal; validación de disponibilidad y conflictos; técnicos conservan su scope |
| UI-OPS-03 | Parcial: carga expresada como trabajos reales; porcentaje semanal pendiente | Calendario efectivo de capacidad, ausencias, festivos y disponibilidad homogénea por recurso | Fórmula horas planificadas/capacidad disponible con periodos equivalentes y datos incompletos explícitos; ningún 78% ficticio |
| UI-OPS-04 | Implantado: asignación/reprogramación mediante formulario accesible; DnD diferido | Componente accesible, backend WorkOrderRescheduler y bloqueo existente | DnD opcional usa la misma API, valida versión, revierte errores y ofrece control equivalente por teclado |
| UI-OPS-05 | Parcial: checklist existente consultable/completable; CRUD de tareas individuales pendiente | Endpoints/políticas para añadir y editar WorkOrderChecklistItem | CRUD con posición, obligatoriedad y auditoría; ningún checklist genérico creado por la UI; plantillas existentes preservadas |
| UI-OPS-06 | Parcial: reservas, archivos, fotos, partes horarios y gestión existente integrados; migración de formularios auxiliares pendiente | Reutilizar WorkOrderMaterialsController, attachments/publication/signature y time entries | Formularios en las nuevas pestañas con permisos, errores inline y consultas paginadas; no pasar a gestión histórica para operar |
| UI-OPS-07 | Parcial: parte de horas y firma existentes; PDF/impresión corporativa de parte de trabajo pendiente | No hay generador de parte OT; definir plantilla corporativa y permiso documental | Generación descargable desde datos reales, marca AREVANTES, firma existente y sin inventar nuevos documentos |
| UI-OPS-08 | Parcial: Agenda por periodos en lista; cuadrícula gráfica mensual y fuentes adicionales pendientes | No hay biblioteca de calendario/scheduler; confirmar fechas autorizadas de instalación, mantenimiento e hitos | Proyección por rango sin crear CalendarEvent duplicados; cada evento tiene fuente/deep link; reprogramación usa API común |
| UI-OPS-09 | Parcial: versión esperada en nueva planificación/workflow; clientes históricos opcionales | Migrar clientes previos y rescheduling por lotes | Versión requerida tras migración, conflicto visible sin overwrite; transacción y bloqueo de técnicos conservados |
| UI-OPS-10 | Parcial: numeración WO segura y workflow real preservados; inicio resumido sin started_at propio | Inicio real en TimeEntry + AuditLog; decisión sobre campo específico y migración | Ningún inicio inferido de fecha prevista; actor/fecha trazables y varias OT por instalación verificadas; reglas de cierre de instalación explícitas |
| UI-OPS-11 | Parcial: selectores limitados/búsqueda y proyecciones acotadas; gran escala pendiente | Autocomplete remoto, paginación de técnicos y planificación densa | No cargar todo el directorio/histórico; truncamiento visible; selección de recurso/rango conserva permisos y contexto |

La base local visible no contiene OT; la comprobación visual usa esos vacíos
reales. Asignaciones, conflictos, progreso y cierre se verifican con fixtures
exclusivamente en la base aislada de tests. No se insertan datos del mock ni se
alteran trabajos reales para demostrar la interfaz. Validación y límites
concretos figuran en el informe; ninguna migración o dependencia nueva requerida.

Validación de la fase: 154 tests PHP / 3059 aserciones y 63 aserciones frontend;
ESLint, TypeScript, i18n, Pint, Prettier y build correctos. Comprobación visual
con datos vacíos reales en escritorio, tablet y móvil; prueba de filtros GET y
creación contextual sin guardar OT en la base visible. Snapshot Git y revisión
de 28 puntos conservados en los documentos del área.

### Almacén y compras — diseño por secciones (8 de octubre de 2026)

Referencia: diseño aportado «ChatGPT Image 8 Oct 2026, 22_17_46.png» y especificación de Almacén y compras. Implantación **local, sin publicación ni commit**. Auditoría detallada: `docs/supply-redesign-review.md`.

El sidebar mantiene exactamente Productos, Inventario, Proveedores y Compras. El padre abre `/supply`; su chevron despliega los hijos. No se añade Agenda ni un hijo Dashboard. Se reutilizan los productos, almacenes, proveedores, pedidos, reservas, movimientos, recepciones y números de serie existentes. Las cifras del diseño no son datos de la aplicación.

| ID | Estado y capacidad | Dependencias | Criterio de aceptación |
| --- | --- | --- | --- |
| UI-SUP-01 | Implantado local: resumen, cuatro listados y fichas completas | Shell canónico y gates existentes | Padre navegable; listados con búsqueda/filtros/paginación; fichas sin split; volver conserva filtros y pestaña por URL; no cambios al sidebar compartido |
| UI-SUP-02 | Implantado local: stock físico, reservas, disponible y pedidos en tránsito | StockMovementLine, StockBalance y StockPositionService | La compra no crea stock; la reserva no consume; disponibilidad excluye cuarentena; cantidades decimales y unidades reales |
| UI-SUP-03 | Implantado local: necesidades manuales y agrupación por instalación/material | InstallationMaterialRequirement, reservas y consumos existentes | Alta autorizada sin duplicar necesidad manual; agregación suma necesidades sin multiplicar las reservas; disponible global y déficit explican que el stock se comparte y no descuentan pedidos |
| UI-SUP-04 | Parcial: origen presupuesto/manual existente; priorización y cobertura específica por OT pendientes | ProjectMaterialPlanner, relaciones existentes y reglas de asignación por OT | Necesidad conserva origen, fecha, prioridad, OT y cantidades cubiertas por pedido; sin duplicar requerimientos ni repartir varias veces el mismo disponible |
| UI-SUP-05 | Implantado local: recepción parcial y protección de reintentos | GoodsReceipt, StockLedger, nueva purchase_receipt_requests y clave de operación | Una clave idéntica produce una sola recepción/incidencia; cambiar payload provoca conflicto; transacción indivisible; series validadas y stock sólo por cantidad aceptada |
| UI-SUP-06 | Implantado local: entregas y devoluciones internas físicas | Warehouses reales, reservas, StockLedger, destinatario User opcional y entrega de origen | Conserva stock global, desplaza compromiso de instalación, no crea consumo, limita retorno a lo entregado, muestra receptor y origen; serie retornada pertenece a entrega original |
| UI-SUP-07 | Parcial: consumo por OT real e idempotente; preparación física independiente pendiente | WorkOrderMaterialConsumer y stock/reservas existentes; definir preparación por cantidad y actor | Preparar no consume; entrega separada de preparación y consumo; cantidad preparada parcial con fecha, responsable y cancelación auditada |
| UI-SUP-08 | Implantado local: cuarentena por almacén no utilizable; liberación controlada pendiente | Campo Warehouse.is_usable aditivo, almacén real de cuarentena, evaluación de calidad | Daño mantiene existencia física con disponible cero; no reserva/entrega/consume; no cambiar flag de un almacén con movimientos/reservas; liberar daño requiere decisión auditada específica |
| UI-SUP-09 | Parcial: series existentes y trazas recepción/traslado/consumo/devolución | SerialNumber, SerialNumberEvent y origen compra existentes | Serial instalado enlaza instalación y compra/proveedor; devoluciones verifican entrega; ficha nueva integra historial paginado y garantía sin datos inferidos |
| UI-SUP-10 | Parcial: histórico unit_cost conservado; valoración uniforme pendiente | Decisión contable sobre PMP/FIFO, costes de transporte y material sin coste | Valoración reproducible por almacén/fecha; movimientos históricos inmutables; consumo no sustituye política de valoración por precio actual de catálogo |
| UI-SUP-11 | Pendiente: categorías del diseño, marca, fabricante, EAN e imágenes de catálogo | Modelo taxonómico y almacenamiento de imágenes autorizados | Filtros y pestañas sólo con categorías reales; relaciones normalizadas; imágenes propias; sin deducir marca/categoría del nombre |
| UI-SUP-12 | Parcial: unidades reales y almacenes existentes; conversiones y ubicaciones detalladas pendientes | Unit, Warehouse; definir conversiones, estantes, vehículos/equipos y asignación de receptor | Conversiones exactas por producto; almacén/ubicación física trazable; destinatario no se inventa a partir del creador; no crear obras como almacenes automáticamente |
| UI-SUP-13 | Parcial: documentos, PDF y eventos de compra existentes reutilizados | PurchaseDocumentEvent, PDF, archivos/adjuntos existentes y auditoría | Adjuntos de entrega/recepción y actividad general unificada con permisos, fecha real y paginación; albaranes internos requieren plantilla aprobada |
| UI-SUP-14 | Parcial: factura derivada y contabilidad existentes preservadas | PurchaseInvoiceMatcher, contabilización existente | Ningún movimiento genera asientos/facturas nuevos implícitos; migrar herramientas auxiliares a pestañas manteniendo aprobación, cotejo y reglas fiscales |
| UI-SUP-15 | Parcial: permisos reales y redacción de importes/costes en nuevas vistas | Gates manage-catalog/view-purchases/manage-purchases/receive-purchases/view-finance | Usuario técnico recibe sin costes ni editor financiero; gestión financiera histórica requiere view-finance; añadir permisos granulares de entrega/reserva/preparación tras acordar matriz |
| UI-SUP-16 | Parcial: continuidad entre ejercicios; aislamiento multientidad pendiente | No existe company_id universal en este dominio; contexto fiscal real | Cambiar ejercicio no reinicia stock; futura separación empresarial con migración y pruebas de no fuga entre entidades |
| UI-SUP-17 | Parcial: páginas de 15, relaciones batch y sólo pestaña consultada; gran escala pendiente | Selectores remotos, inventario agregado SQL paginado y series mensuales agrupadas SQL | No cargar catálogo/directorio completo en editores; paginar contactos/relaciones; medir consultas y tiempos con volumen representativo |
| UI-SUP-18 | Verificado local: escritorio y responsive; pruebas de concurrencia PostgreSQL simultánea pendientes | Base PostgreSQL de pruebas aislada y dos conexiones/procesos independientes | Dos reservas/recepciones simultáneas nunca sobreasignan/duplican; índices únicos y bloqueo de almacenes ordenado; no usar datos reales como fixtures |
| UI-SUP-19 | Parcial: gráficos con cantidades de registros reales; indicadores monetarios/taxonomía pendientes | Valoración histórica y categorías reales | No sumar unidades distintas ni valorar consumos con precios de catálogo; gráfico y KPIs indican unidades/periodo; ausencia de capacidad diferente de conjunto vacío |
| UI-SUP-20 | Parcial: reuso de formularios operativos históricos | Migración incremental de reservas, movimientos, almacenes, series y compras auxiliares | Formularios adaptados al diseño sin perder endpoints ni permisos; estados de envío/errores y confirmaciones accesibles; no considerar rediseñada una herramienta histórica sólo por enlazarla |

Verificación: pruebas backend de stock, recepción parcial/reintentos, seriales, cuarentena, consumo y permisos; regresión de operaciones, compras, asignaciones y torre de suministro; typecheck, lint, catálogo bilingüe y build. Las pruebas automatizadas emplean SQLite en memoria. La migración aditiva se aplicó también a PostgreSQL **local**, sin crear registros de negocio de prueba. La ejecución simultánea real sobre PostgreSQL queda pendiente y no se considera verificada por el bloqueo escrito en código.


## Facturación — diseño por secciones (11/10/2026)

Estado de trabajo **local**, rama `ai/sidebar-navigation-redesign`; no publicado. Se reutilizan los dominios reales y el shell. La ausencia de capacidad se representa como `false`/`null`, distinta de una consulta vacía. Informe: [billing-redesign-review.md](billing-redesign-review.md).

| ID | Estado y capacidad | Dependencias | Criterio de aceptación |
|---|---|---|---|
| UI-BILL-01 | Implantado local: dashboard y tres entradas canónicas | WorkspaceNavigation, shell compartido y gates existentes | Padre /billing navegable; exactamente clientes, proveedores y cobros/pagos; sin publicación ni cambio del shell. |
| UI-BILL-02 | Implantado local: seis indicadores y evolución real; comparativas parciales | BillingWorkspace, fechas fiscales y pagos efectivos | Emisión incluye rectificativas; cobros/pagos por fecha efectiva; saldos abiertos de todos los ejercicios. Comparativas y sparklines de saldos requieren snapshots históricos reproducibles. |
| UI-BILL-03 | Implantado local: listas y fichas completas | SalesDocument, PurchaseDocument y Expense | Filtros, orden y paginación backend; ficha a pantalla completa; facturas de proveedor independientes de pedidos; un pedido no se cuenta como factura. |
| UI-BILL-04 | Implantado local: estados documentales y financieros separados | Documentos originales, pagos activos, créditos vinculados y vencimiento | Saldo exacto no negativo; parcial distinto de emitida/recibida; vencida sólo si fecha anterior a hoy; saldo a favor visible sin asignación ficticia. |
| UI-BILL-05 | Parcial: cobros/pagos con bloqueo, decimales e idempotencia | Servicios contables y fuentes financieras existentes; UUID único aditivo | Reintento idéntico no duplica; payload cambiado rechazado; exceso rechazado; permiso y fuente real. Ampliar interfaz de lotes reutilizando PaymentBatch sin otro motor. |
| UI-BILL-06 | Parcial: numeración existente reutilizada | FiscalYear, series, locks y restricciones existentes | No numerar borradores ni duplicados; emisión repetida rechazada; validar carrera real PostgreSQL aislada con dos procesos antes de declarar concurrencia verificada. |
| UI-BILL-07 | Parcial: PDF archivado para nuevas emisiones | DocumentPdf corporativo, disco privado y hash SHA256 | PDF emitido idéntico tras cambio de empresa; archivo ausente/alterado falla. Auditar archivo histórico de facturas antiguas sin reconstruirlas como originales; reconciliar posibles archivos huérfanos tras rollback. |
| UI-BILL-08 | Parcial: rectificativa completa de cliente protegida | Relación source_document_id y serie de rectificativas existente | Reintentar reutiliza documento; agregado emitido no supera original. Rectificación parcial, causas fiscales, devolución/compensación y ciclo múltiple requieren reglas y pruebas específicas; compras conservan sus flujos existentes. |
| UI-BILL-09 | Parcial: anticipo asociado a aceptación existente | AcceptancePayment y conversión existente de presupuesto | No tratar cualquier cobro como anticipo fiscal. Anticipos genéricos, aplicación entre facturas, devolución y exceso requieren asignación trazable e integración contable/fiscal. |
| UI-BILL-10 | Parcial: facturación parcial de presupuesto existente | QuoteBillingPlan y SalesDocumentConversion | Mantener porcentajes/secuencia/origen existentes. Hitos contractuales únicos, certificaciones y asignaciones pendientes; instalación terminada no genera factura automática. |
| UI-BILL-11 | Pendiente: necesidades de facturación contractuales | Catálogo de hitos, condiciones contractuales y asignación de importes | Contrato capabilities.contractualNeeds=false y needs=null; mostrar no disponible. Tras implantar, necesidades sin doble facturación y navegación al origen real. |
| UI-BILL-12 | Parcial: cálculo e impuestos originales preservados | TaxLines, retención Expense, revisiones fiscales y cálculos existentes | Mostrar neto, impuesto y retención real; no fijar IVA21 para todo. Migrar cálculos legacy restantes de float a decimal exacto con regresión de redondeos y rectificaciones. |
| UI-BILL-13 | Parcial: histórico de pagos anulados de gastos | ExpensePaymentCancellation y asientos/periodos existentes | Pago anulado separado de saldo activo. Anulación general de cobros/pagos de compra requiere contraasiento, periodo abierto, conciliación y auditoría; no habilitar borrado como sustituto. |
| UI-BILL-14 | Pendiente: calendarios de vencimiento múltiples | Modelo de cuotas/asignación e integración con tesorería | Importes y fechas de cuotas reales suman total; saldo distribuido sin duplicación. Mientras tanto usar únicamente due_date existente, no inventar cuotas. |
| UI-BILL-15 | Parcial: formas y fuentes financieras existentes | SupplierPaymentSourceResolver, cuentas, tarjetas y empleados | Selector real con permiso y validación backend; fondos de empleado trazables. Automatismos bancarios y conciliación requieren servicios existentes y pruebas, sin reglas monetarias legales inventadas. |
| UI-BILL-16 | Pendiente: aislamiento multientidad y multimoneda | company_id transversal, scopes, migración y tipos de cambio históricos | No fuga entre empresas. Esta proyección EUR no suma divisas heterogéneas; ampliar tras especificar política de conversión, moneda y contexto empresarial. |
| UI-BILL-17 | Parcial: documentos y entrega por email existentes | DocumentDelivery, correo y PDF corporativo reutilizados | Registro de entrega real y errores existentes; no afirmar envío por abrir modal. Probar proveedor simulado, archivo histórico y fallos; no enviar correos reales durante validación. |
| UI-BILL-18 | Parcial: integración de dominios existente | Presupuestos, instalaciones, compras, AccountingSynchronizer y tesorería | Links al origen real y ausencia explícita; no nuevo motor ni stock por factura; integración contractual con proyectos requiere relación normalizada cuando falte. |
| UI-BILL-19 | Pendiente: SIF / VERI*FACTU | Auditoría normativa por entidad/régimen, registros, cadena, QR, incidencias y remisión | capabilities.sif=false; hash de PDF no es cadena fiscal. Evidenciar requisitos aplicables e implementar motor probado antes de declarar cumplimiento o habilitar remisión. |
| UI-BILL-20 | Pendiente: factura electrónica estructurada | Aplicabilidad B2B, RD238/2026, SPFE/OrdenHAC1028/2026, formatos y transporte | capabilities.structuredInvoice=false; PDF/email no equivalen a factura estructurada. Validar formato, autenticidad, recepción, estados y conservación con integración autorizada. |
| UI-BILL-21 | Parcial: permisos reales reutilizados | Gates view-finance, manage-installations, manage-treasury, issue-invoices, prepare-credit-notes, manage-purchase-payments y manage-catalog | Redactar importes no autorizados también en backend; no deducir permisos del nombre del rol. Matriz más granular requiere decisión explícita y tests. |
| UI-BILL-22 | Parcial: agregación SQL y páginas acotadas | Índices aditivos, agrupación mensual SQL y selectores existentes | No agrupar todas las filas en navegador. Pendiente medir gran volumen, autocomplete de terceros y paginación de líneas/historial de fichas. |
| UI-BILL-23 | Parcial: formularios históricos preservados | Enlaces de gestión view=manage y formularios originales | Nueva interfaz sirve acciones autorizadas principales. Editores auxiliares, revisión fiscal, lotes y configuración conservan flujo original; adaptar diseño sin considerar un enlace como rediseño completo. |
| UI-BILL-24 | Verificado local: escritorio, tablet y móvil | Shell existente, cards móviles y Sheet de filtros | 1440×1000, 820×1180 y 390×844; sin overflow de página; filtro móvil GET devuelve seis pendientes reales; no usar datos simulados en base local. |
| UI-BILL-25 | Parcial: pruebas y migración local | SQLite en memoria, PostgreSQL local para lectura y esquema | 68 pruebas/1061 assertions; build, lint y types. Carrera simultánea, aislamiento empresarial, normativa y correo real no verificados; no confundir locks escritos con ejecución concurrente probada. |

Verificación: 68 pruebas backend pasan con 1.061 comprobaciones; 13 pertenecen a esta fase. Base de pruebas SQLite aislada; migración aditiva aplicada a PostgreSQL local y lectura visual de sus datos reales. No se han creado registros de negocio para rellenar el diseño, enviado correos ni presentado una certificación fiscal. Build pasa con aviso de chunks generales superiores a 500 kB; lint global, tipos y catálogo bilingüe pasan. La revisión de concurrencia PostgreSQL simultánea y capacidades fiscales pendientes conserva su estado pendiente.

## Finanzas · rediseño por secciones (11/10/2026)

Referencia: mockup de cuatro pantallas aprobado y especificación de Finanzas. Implantación local en `ai/sidebar-navigation-redesign`; informe de revisión con 31 puntos en `docs/finance-redesign-review.md`. No publicación, Nexo ni datos de muestra. Se reutilizan bancos, extractos, conciliación, reglas, facturas, pagos y TreasuryForecast; no una segunda verdad financiera.

| ID | Estado y alcance | Dependencias | Criterio de aceptación |
| --- | --- | --- | --- |
| UI-FIN-01 | Implantado local: navegación y cuatro vistas | Shell canónico y gates existentes | Parent Finanzas navegable /finance con chevron independiente y exactamente Tesorería, Bancos y conciliación, Previsiones; rutas/deep links históricos conservados. |
| UI-FIN-02 | Implantado local: dashboard real | FinanceWorkspace, BillingWorkspace, BankAccount/Transaction | Seis KPIs SQL; no cifras ilustrativas, crecimiento inventado ni suma de páginas frontend. Saldo registrado y estimación etiquetados correctamente. |
| UI-FIN-03 | Implantado local: tesorería y deduplicación | Extractos y BankReconciliation | Flujo efectivo usa extractos EUR; pagos sin vínculo se listan por separado y no se suman a saldo. Transferencias propias activas excluidas del flujo consolidado; ambos movimientos conservados. |
| UI-FIN-04 | Implantado local: posición por cuenta | Saldo inicial, operación y moneda | Apertura más operaciones hasta hoy; sin reinicio por ejercicio. Monedas distintas no sumadas como EUR; pendientes no restados otra vez. |
| UI-FIN-05 | Pendiente: saldo disponible confirmado | Fecha de control, retenciones, pólizas y confirmación bancaria | availableBalance=false/available=null hasta fórmula de disponibilidad verificable por cuenta y fecha; no equiparar saldo registrado, contable y disponible. |
| UI-FIN-06 | Pendiente: histórico de saldos fiables | Fecha/importe de apertura y extractos completos | historicalBalances=false; gráficos actuales son flujos de extractos. Reconstrucción debe identificar periodos cubiertos, huecos y fecha de corte sin aparentar confirmación. |
| UI-FIN-07 | Implantado local: consulta/alta/edición/desactivación | Backend existente y endpoint PATCH con gate manage-treasury | IBAN oculto en consulta; editar nombre/entidad/estado conserva movimientos. No eliminación de cuenta con historial ni modificación arbitraria de moneda/apertura. |
| UI-FIN-08 | Reutilizado y reforzado: CSV/Norma43 | Importadores, hash por cuenta, preview, transaction y bloqueo de cuenta | Vista previa y validación antes de inserción; dos reintentos no crean duplicados; mantener origen/fecha/usuario/resultados. Prueba simultánea PostgreSQL pendiente en FIN-19. |
| UI-FIN-09 | Reutilizado e integrado: conciliación manual | Candidatos y relaciones únicas existentes | Revisar importe/fecha/referencia/candidato antes de confirmar; pago existente solo se vincula. Factura seleccionada crea pago únicamente mediante servicio autorizado. No autoacción al consultar. |
| UI-FIN-10 | Pendiente: conciliación agrupada | Modelo de asignación N:M con importes y comisiones | Un movimiento con varias facturas o varias asignaciones conserva importes exactos y trazabilidad; no forzar relación 1:1 para casos que el dominio necesita. |
| UI-FIN-11 | Reutilizado: reglas versionadas | BankReconciliationRuleEngine y gates de tesorería/contabilidad | Preview explicable, aplicación autorizada y reversión existentes. Ninguna regla IA ni conciliación por mera coincidencia de importe. |
| UI-FIN-12 | Parcial: reversión trazada | Contraasientos y AuditLog existentes | Nuevo UI pide motivo; evento guarda usuario/fecha/motivo/snapshot. Pendiente conservar entidades de anulación íntegramente y exigir motivo a clientes antiguos sin romper compatibilidad. |
| UI-FIN-13 | Implantado local: previsión 7/30/60/90 | TreasuryForecast existente y obligaciones con fecha | Saldo inicial + entradas - salidas; hipótesis visibles 90/70/100% y probabilidad manual. Rectificativas emitidas reducen cobro; datos sin fecha no se inventan. Sin cuenta EUR base, forecast.available=false y closing=null. |
| UI-FIN-14 | Implantado local: previsión manual idempotente | FinancialForecastItem.operation_key único, transacción, autorización y auditoría | Reenvío mismo token/datos conserva un registro, otros datos error; cancelación conserva fila. No crear pagos reales por recurrencia. Casos simultáneos ver FIN-19. |
| UI-FIN-15 | Parcial: vencimientos y resumen mensual | Facturación y eventos del motor existente | Listado paginado, link al origen, flujos y saldo de cierre separados por mes. Pendiente tabla paginada unificada de todos los eventos con impacto, filtros estado/origen/tipo y resumen semanal. |
| UI-FIN-16 | Parcial: clasificación de movimientos | Categoría financiera de contrapartida contable explícita | Solo asignación simple no revertida con categoría real; resto Sin clasificar. Pendiente categorías por conciliación, reparto de líneas y reglas completas; no inferir categoría por texto o importe. |
| UI-FIN-17 | Pendiente: multientidad y cuentas asignadas | Claves de sociedad, políticas y relaciones de obligación a cuenta | multiCompany=false. Segregación real en SQL/autorización y pruebas cruzadas antes de consolidar varias empresas. Forecast por cuenta no asigna obligaciones globales arbitrariamente. |
| UI-FIN-18 | Parcial: exactitud monetaria heredada | BigDecimal, control bancario y PurchaseCommitmentAnalysis | Nuevo DTO y sumas de proyección exactos; consumidor anterior compatible. Pendiente convertir cálculos float restantes de compromisos, control, créditos y multidivisa; currency conversion solo con tipo/fecha autorizados. |
| UI-FIN-19 | Pendiente: concurrencia PostgreSQL probada | Base aislada de prueba y dos conexiones/procesos reales | Verificar imports simultáneos, doble conciliación, reversión y reintento manual; una sola relación/pago/token y rollback coherente. Locks/índices escritos y pruebas SQLite no equivalen a carrera probada. |
| UI-FIN-20 | Parcial: performance | SQL, eager sums, páginas 10/25 y filtros backend | No cargar movimientos completos en React. Pendiente benchmark con gran volumen, paginar candidatos/historial/selectores y motor de obligaciones; ordenar/buscar tercero/proyecto en lista bancaria por campos normalizados. |
| UI-FIN-21 | Reutilizado: integración compras/proyectos/contabilidad | Facturas derivadas, compromiso residual, JournalPoster y AccountingSynchronizer | No presupuesto/oportunidad tratado como cobro garantizado; no orden confundida con factura ni doble obligación; no asientos ficticios. Relaciones de hitos/anticipos normalizadas pendientes. |
| UI-FIN-22 | Verificado local: responsive y permisos | Shell existente, tarjetas móviles, gates y datos locales | 1440×1000, 820×1180 y 390×844 sin overflow de página; importes reales, IBAN enmascarado, 403 al no autorizado; no nóminas individuales expuestas. |
| UI-FIN-23 | Parcial: gestión avanzada preservada | view=manage, formularios originales y deep links | Importación/reglas integradas; productos bancarios, lotes, automatismos, fiscal, presupuestos y edición avanzada conservan gestión anterior. Su acceso no equivale a adaptación visual completa. |
| UI-FIN-24 | Verificación: pruebas y build | SQLite aislada y PostgreSQL local de lectura | 90 pruebas relacionadas/1.880 assertions; tipos, lint, catálogo y build pasan. Suite global con incidencias adicionales documentadas en informe; no certificación de seguridad multientidad ni de concurrencia real. |

La migración aditiva de token de previsión se aplicó a PostgreSQL local; las pruebas usan SQLite aislada. No se han insertado datos de negocio para rellenar el diseño ni ejecutado movimientos financieros reales durante la verificación. Las capacidades incompletas permanecen false/null o en estado explícito y se planifican aquí.

## Fiscal — diseño por secciones, 11/10/2026 (solo local)

Referencia visual y especificación de Fiscal/Dashboard, Impuestos, Modelos y Periodos recibidas el 11/10. Incremento sobre la rama existente, sin publicar ni integrar. No altera el orden fiscal 303 → 390 → 347 ni sustituye las puertas FIN-03. Informe completo: [fiscal-redesign-review.md](fiscal-redesign-review.md).

| ID | Estado / capacidad | Dependencias | Criterio de aceptación |
| --- | --- | --- | --- |
| UI-FISC-01 | Implantado local: Fiscal padre navegable, exactamente tres hijos y detalle a pantalla completa | WorkspaceNavigation, AppShell y permisos vigentes | Padre abre /fiscal; chevron independiente; hijo activo expandido, deep links originales y ejercicio conservados; sin hijo Dashboard |
| UI-FISC-02 | Implantado local: cuatro vistas responsive y estados diferenciados | PageShell/PageHeader y componentes existentes | Desktop/tablet/móvil sin overflow global; consultas vacías no se confunden con capacidad ausente; no copiar cifras del mockup |
| UI-FISC-03 | Implantado local: IVA y retenciones documentales exactos con fuentes paginadas | Documentos existentes, DECIMAL, SQL SUM y BigDecimal | Excluir borradores/cancelados y gastos fuera de ámbito EUR/factura; rectificativas con signo; cada importe trazable; ningún agregado documental prepara o presenta por sí mismo |
| UI-FISC-04 | Pendiente: devengo fiscal completo de emitidas y anticipos | Clasificación de operaciones emitidas, snapshot/versión y normativa aplicable | Ordinarias/rectificativas/anticipos/exentas/no sujetas/ISP correctamente clasificados por fecha aplicable, sin duplicación; diagnóstico individual de fuentes excluidas |
| UI-FISC-05 | Parcial: revisión y deducibilidad originales conservadas; agregado confirmado pendiente | ExpenseFiscalReview, PurchaseFiscalReview, Model303Preview y conciliación FIN-03 | Reutilizar revisiones actuales con evidencia válida, periodo explícito y cuotas no deducibles/pendientes separadas; no asumir deducción total de legacy ni leer todos los libros en cada render |
| UI-FISC-06 | Pendiente: resultado 303 completo/confirmado | Puertas FIN-03.303, compensaciones, ajustes, normativa y censo | Resultado reproducible y versionado con fuentes/ajustes, revisión y ámbito; nullable hasta validación; no usar repercutido menos soportado como definitivo |
| UI-FISC-07 | Implantado local: catálogo real de tipos y alta aditiva de IVA | TaxRate, manage-tax + view-finance y auditoría | Crear tipo decimal no modifica porcentajes históricos/default; validaciones backend, evento auditado; retenciones no se activan como IVA en consumidores genéricos |
| UI-FISC-08 | Pendiente: vigencias, códigos y ciclo de activación/edición de tipos | TaxRate aditivo, consumidores de facturas/líneas/cuentas y periodos protegidos | Fechas explícitas sin solapamientos; porcentaje usado histórico inmutable; modificación mediante versión/nuevo tipo; no exponer borrado o activación sin backend autorizado |
| UI-FISC-09 | Parcial: exención de catálogo conservada; tratamientos especiales de operaciones pendientes | TaxRate.kind, evidencia fiscal por línea/documento y normativa | Porcentaje cero no sustituye clase fiscal; distinguir exención, no sujeción e ISP sin reescribir documentos; distribución por tratamiento solo sobre datos verificados |
| UI-FISC-10 | Implantado local: resumen de censo y acceso al editor versionado existente | CompanyProfile/CompanyCensusVersion, revisión de identidad, manage-tax y admin | Censo actual/revisado condiciona aplicabilidad/periodicidad; identidad modificada invalida el resumen; mantener motivo, referencia y protección de periodos preparados |
| UI-FISC-11 | Pendiente: clasificación/obligaciones de retenciones y catálogo integrado | ExpenseTaxLine/Expense, personal cuando exista, naturaleza profesional/trabajo/alquiler/otras | Mostrar categorías separadas y periodicidad/modelo aplicable revisados; sin agrupar todo en 111; alta/activación no se habilita hasta consumidores distingan kind fiscal |
| UI-FISC-12 | Parcial: capacidades 303/200 y declaraciones reales; catálogo de obligaciones completo pendiente | Censo, TaxReturn, CorporateTaxReturn y fuentes oficiales | No activar modelos por existencia del cálculo; 200 y restantes requieren aplicabilidad verificada; modelos informativos sin resultado económico ficticio |
| UI-FISC-13 | Implantado local: listado y ficha de declaración, resultado guardado y evidencia | TaxReturn/CorporateTaxReturn y servicios originales | Separar borrador/revisión/preparación/presentación; archivo privado y SHA-256 coincidente más fecha/referencia; falta/corrupción/inaccesibilidad => evidencia pendiente, sin mutar el registro |
| UI-FISC-14 | Parcial: registro manual de presentación original preservado | Endpoints y evidencias existentes, almacenamiento privado y permisos | No simular remisión AEAT; hash prueba integridad, no autenticidad; validar procedimiento/justificante real; endurecer transacciones/reintentos simultáneos de file y de Sociedades antes de ampliar automatismos |
| UI-FISC-15 | Pendiente: calendario aplicable y vencimientos internos | Obligaciones por sociedad, fuente/versionado AEAT, periodicidad, días inhábiles y domiciliación | Guardar fecha/procedencia/modalidad confirmadas y actualizar normativa; próximos vencimientos y filtros reales; no derivar fecha de filing_date o dar por válido deadline legacy 200 sin revisión |
| UI-FISC-16 | Parcial: consulta de ejercicios/series reales y periodos IVA condicionados por censo | FiscalYear, TaxReturn y CompanyCensus | Fechas YYYY-MM-DD sin desfase UTC; consulta no crea registros; trimestre solo con censo confirmado y ejercicio natural; ejercicios mensuales/no naturales necesitan dominio/verificación propia |
| UI-FISC-17 | Pendiente: gestión independiente de periodos por impuesto | Periodicidad revisada y entidades equivalentes auditadas antes de migrar | Límites coherentes, no solapamientos, protección concurrente, cierre/reapertura auditados; no confundir meses contables con periodos tributarios ni suponer retenciones trimestrales |
| UI-FISC-18 | Pendiente: cierre fiscal independiente y checklist automático completo | Obligaciones, evidencia de presentaciones, incidencias/revisiones y permisos específicos | Cerrar solo con criterios explícitos cumplidos; reapertura con motivo/auditoría y controles concurrentes; cierre contable no acredita cierre fiscal |
| UI-FISC-19 | Pendiente: segregación backend por sociedad | Modelo de empresa activa y company_id/contexto coherente en todas las fuentes/rutas/evidencias | Pruebas de lectura/escritura/descarga cruzadas negativas; nuevas vistas rechazan múltiples CompanyProfile con 409 y multiCompany=false; FiscalEntity no representa empresa activa; no afirmar aislamiento de rutas legacy |
| UI-FISC-20 | Parcial: documentos/exportaciones existentes preservados | Almacenamiento privado y exportación CSV original | Consultar justificantes autorizados; adaptar anexos/requerimientos/historial y exportaciones al diseño sin gestor duplicado; PDF/CSV de trabajo no se anuncian importables/presentados ante AEAT |
| UI-FISC-21 | Parcial: integraciones contable y financiera existentes preservadas | VatLedger/VatSettlement, TaxReturnPayment, TreasuryForecast | Una obligación, sin duplicar documento ni pago; diferenciar estimado/declarado/pendiente/pagado; no trasladar KPI documental a tesorería ni pagar automáticamente |
| UI-FISC-22 | Pendiente: migración de floats legacy y cobertura de cálculos Sociedades | VatReport/VatLedger/VatSettlement/CorporateTaxCalculator, redondeos/reglas oficiales y fixtures de regresión | Decimal exacto de extremo a extremo, histórico reproducible, umbrales/versiones comprobados; no reescritura masiva durante rediseño |
| UI-FISC-23 | Parcial: flujos avanzados originales accesibles | Páginas /tax, /tax/models/303, /company-fiscal/census, /company-fiscal y /corporate-tax | Acceso no equivale a rediseño completo de formularios de revisión/presentación/ejercicios; adaptar incrementalmente sin cambiar transiciones ni permisos |
| UI-FISC-24 | Parcial: validación local y suite fiscal; concurrencia específica pendiente | SQLite aislada, PostgreSQL local de lectura, entorno *_concurrency_test y EXPLAIN con volumen real | Pruebas funcionales/permisos/evidencia/fechas y regresión, responsive y build; concurrencia simultánea y límites de volumen no declarados verificados por tests omitidos |

Verificación final de esta fase: 142 pruebas relacionadas (138 correctas, 4 omitidas, 2019 aserciones); tipos, lint, ES/EN, 22 comprobaciones de navegación y compilación correctos. Revisión visual de las cuatro vistas a 1440, 820 y 390 px sin desbordamiento horizontal. La suite general conserva las cuatro incidencias de fases anteriores, detalladas en el informe. Sin commit, push, merge, migraciones ni datos de negocio creados para completar el diseño; producción sin cambios.

### Contabilidad — diseño por secciones, 23/10/2026 (solo local)

Implementación incremental de las cinco vistas. Informe de los 37 puntos, límites y pruebas: [accounting-redesign-review.md](accounting-redesign-review.md). Reutiliza el dominio existente; no se considera publicada y no añade facturación, fiscalidad ni código Nexo.

| ID | Estado y capacidad | Dependencias | Criterio de aceptación |
| --- | --- | --- | --- |
| UI-ACC-01 | Implantado local: cinco destinos y navegación de detalle | WorkspaceNavigation, gates y shell existentes | Jerarquía canónica conservada, Vista general explícita, Informes abre Balance, enlaces antiguos y ejercicio preservados |
| UI-ACC-02 | Implantado local: indicadores, grupos y gráficos desde posted | LedgerAccount/JournalLine/JournalEntry | Debe/Haber exactos; draft excluido; resultado separado de patrimonio; ausencia de movimientos no se rellena con mockup |
| UI-ACC-03 | Parcial: ecuación y balance provisional por naturaleza | Integridad del plan, aperturas y periodificación | Diferencia visible; no se presenta como definitivo ni balance estatutario; validar signo de correctoras y saldos previos |
| UI-ACC-04 | Implantado local: listado de asientos | Consultas SQL, permisos | Búsqueda/fecha/estado/tipo/cuenta/ejercicio, ordenación y paginación backend sin límite silencioso 250 |
| UI-ACC-05 | Implantado local: borradores y ficha completa | Misma entidad JournalEntry, líneas y audit log | Crear/editar en ejercicio/mes abierto, excluir de informes, validar al postear; mismo ID, número definitivo único; reintento sin duplicado |
| UI-ACC-06 | Implantado local: normalización monetaria JournalPoster | Brick Math y DECIMAL existentes | No floats al validar/normalizar escritura; HALF_UP por línea, suma exacta, negativos/no finitos/doble lado/descuadre y límite de columna rechazados |
| UI-ACC-07 | Parcial: inmutabilidad ORM y reversión | Modelos y poster existentes | No editar/borrar posted mediante endpoints/ORM; reversión trazada, original preservado, sin repetición. Pendiente protección ante SQL directo y todos los procesos externos |
| UI-ACC-08 | Implantado local: plan/ficha y acceso a gestión | LedgerAccount y ChartOfAccountsController | Cuentas reales, jerarquía/procedencia conservadas, filtros/paginación, desactivación y cuentas con movimientos protegidas; no borrar históricos |
| UI-ACC-09 | Parcial: saldos de cuentas y grupos | Sumas SQL de apuntes posted | Inicial, Debe/Haber y final coherentes por ejercicio; Mayor con orden estable y ventana antes de paginar. Pendiente agregación de padres por jerarquía, sin inferir saldo agregado de padre sin movimientos |
| UI-ACC-10 | Implantado local: Diario y Mayor de trabajo | Fuente común de apuntes | Diario incluye cada línea; Mayor exige cuenta; saldo acumulado entre páginas, fechas y filtros consistentes; detalles a página completa |
| UI-ACC-11 | Parcial: sumas y saldos de trabajo | Inicial del ejercicio y apuntes del periodo | Mostrar inicial neto, Debe/Haber y final neto exactos; incluir cuentas con inicial aunque sin movimiento del periodo; validar continuidad con aperturas |
| UI-ACC-12 | Pendiente: matriz oficial de estados PGC | Modelo normal/abreviado/PYMES aplicable y mapeo versionado de cuentas | Activo/pasivo corriente/no corriente, patrimonio y PyG explotación/financiero/impuesto; trazabilidad, sumas y casos oficiales revisados por asesoría |
| UI-ACC-13 | Pendiente: comparativas y detalle de informes | Periodos comparables con datos reales y matriz UI-ACC-12 | No fabricar ejercicio anterior ni porcentajes con denominador inválido; mismos filtros en pantalla/exportación |
| UI-ACC-14 | Pendiente: ECPN y EFE contables | Matriz y movimientos patrimoniales/clasificación de flujos | Reconciliar con balances; no sustituir EFE por gráfico bancario ni inventar movimientos |
| UI-ACC-15 | Parcial: CSV completo de apuntes contabilizados | Respuesta stream, permisos y filtros | Exportar sin límite 250, sin drafts, con periodo/cuenta/búsqueda/tipo y mitigación de fórmulas; CSV de trabajo, no libro legalizado; PDF/XLSX de estados pendientes |
| UI-ACC-16 | Parcial: libros oficiales, consulta y capacidades explícitas | Diario, Balance y libros de IVA existentes | Cinco tarjetas, pestañas mercantiles/fiscales/configuración; sin botones de generación/estado legalizado ficticios; IVA no sustituye libros mercantiles |
| UI-ACC-17 | Pendiente: generación/versiones de libros oficiales | Modelo de libro/artefacto/evidencia, empresa/ejercicio, formato aplicable, almacenamiento privado y permisos | Integridad previa, usuario/fecha/filtros/hash/versiones sin sobrescribir; inventarios/cuentas anuales completos, no un balance suelto |
| UI-ACC-18 | Pendiente: actas y socios vinculados | Documentos societarios reales y permisos | No inventar decisiones/participaciones; referencias documentales auditadas sin crear sistema societario paralelo |
| UI-ACC-19 | Pendiente: legalización verificable | Formatos, procedimiento y justificantes del Registro Mercantil | Diferenciar preparado/generado/pendiente/legalizado/incidencia; evidencia antes de legalizado; sin simular presentación |
| UI-ACC-20 | Parcial: periodos/cierre/apertura existentes preservados | AccountingPeriodManager/FiscalYearClosing/DocumentNumber | Draft bloquea cierres y se excluye de balances legacy; continuidad y apertura única requieren revisión completa de carreras y reglas; no afirmar cierre integral validado |
| UI-ACC-21 | Parcial: integraciones de facturación/tesorería/fiscal existentes | source/event, synchronizer, documentos, bancos y cálculos fiscales | Factura/abono/cobro/pago con trazabilidad sin duplicación; no sync al abrir vista. Revisar fallbacks 700/600, divisas/anticipos/naturaleza/retenciones y transferencias contables antes de ampliarlas |
| UI-ACC-22 | Pendiente: aislamiento multiempresa completo | Contexto empresa activa y company_id coherente en todo el ledger | Pruebas negativas lectura/escritura/exportación cruzada; mientras, nuevas/antiguas rutas accounting rechazan múltiples CompanyProfile con 409; no declarar segregación integral por este guard |
| UI-ACC-23 | Parcial: auditoría de cuentas/apuntes/asientos | AuditableObserver y AuditLog | Eventos de creación/cambios/reversión y draft, actor y fecha; borrado de líneas de draft auditado. Historial de libros/cierres y retención requieren circuito propio |
| UI-ACC-24 | Pendiente: ensayo multiproceso y edición obsoleta | PostgreSQL aislado, worker/barrier y contexto transaccional | Probar contador/posteo doble/reversión/close/open/reintentos source/event; detectar edición obsoleta; los tests SQLite secuenciales no acreditan concurrencia real |
| UI-ACC-25 | Pendiente: importador de asientos | Formato confirmado, preview, cuentas/ejercicio, reglas, duplicados y auditoría | No insertar sin validación y confirmación; rechazar descuadre; resultado trazado e idempotente; no simular importación |
| UI-ACC-26 | Pendiente: notas/diarios/terceros/centros y adjuntos adicionales | Contratos de detalle UI-JOURNAL, datos reales y permisos | undefined/capacidad ausente distinguida de null/[] consultado vacío; no inventar campos o documentos |
| UI-ACC-27 | Parcial: diseño, accesibilidad y validación | Componentes del shell y CSS scoped | Cinco vistas responsive, foco, etiquetas ES/EN, contraste; formularios avanzados existentes preservados; pruebas/tipos/lint/build y reporte real de fallos |
| UI-ACC-28 | Pendiente: precisión integral de servicios legacy | Campaña de casos, cálculos de cierre/plantillas/fiscal y equivalencias verificadas | Migrar floats fuera de JournalPoster sin alterar históricos; reconciliar resultado y cierres/reaperturas, no solo redondear al final |

Actualiza el alcance histórico de UI-JOURNAL-01/07/10 y de los hubs del shell: el nuevo listado tiene consulta backend y ficha completa; se conserva el acceso de gestión legacy para mapeos/cierres/terceros. La duplicación de asientos, diarios y adjuntos aún no se anuncia como disponible. Pruebas de concurrencia, dominio de libros y validación contable de estados continúan pendientes.

Verificación final: 120 pruebas relacionadas correctas (2374 aserciones), 25 comprobaciones frontend de navegación y cinco vistas verificadas a 1440/820/390 px sin desbordamiento de documento. Suite general: 848 pruebas, 839 correctas, cuatro incidencias preexistentes y cinco omitidas; no se declara toda la suite verde. Tipos/lint correctos; detalle de ES/EN/build en el informe. Sin commit/push/merge; producción sin cambios.

## UI-COMP — Empresa: diseño y administración corporativa, 23/10/2026

Referencia: imagen «ChatGPT Image 23 Oct 2026, 01_47_22» y especificación
«AREVANTES ERP — IMPLEMENTACIÓN DEFINITIVA DEL MÓDULO EMPRESA».
Implantación local en `ai/sidebar-navigation-redesign`; no publicada ni integrada
mediante commit/push/merge. Informe: [company-redesign-review.md](company-redesign-review.md).

Se reutilizan Person/Staff, el dominio laboral HR, permisos, auditoría,
CompanyProfile, datos fiscales, seguridad y herramientas administrativas. El
archivo KnowledgeDocument mantiene su función de conocimiento de IA. Al no
existir un archivo corporativo compatible, se añade un archivo mínimo separado,
privado y versionado, sin copiar documentos laborales, facturas o adjuntos previos.

| ID | Estado y alcance | Dependencias | Criterio de aceptación / verificación |
| --- | --- | --- | --- |
| UI-COMP-01 | Implantado local: Empresa navega al dashboard; hijos Personal, Documentos y Configuración | Navegación autorizada existente | Texto/icono navegan, chevron independiente, exactamente tres hijos, detalle mantiene selección; pruebas PHP/JS y revisión en navegador |
| UI-COMP-02 | Implantado local: dashboard real | Perfiles laborales, documentos autorizados, contratos y tareas existentes | No contar usuarios ni personas sin perfil como empleados activos; excluir colaboradores externos del KPI de empleados; conteo global de vencimientos independiente del preview limitado; null restringido distinto de 0 consultado |
| UI-COMP-03 | Implantado local: Personal y ficha completa | Person, HrEmploymentProfile, departamentos e historial existentes | Filtros/paginación SQL; alta transaccional sin usuario automático; número laboral único; edición con huella, conflicto 409 al quedar obsoleta; retorno conserva filtros |
| UI-COMP-04 | Implantado local: contratos, acreditaciones documentales y ausencias administrativas | HrContractChange, HrEmploymentDocument y HrLeaveRequest | Historial y documentos paginados, fechas/estado reales; no motivos/diagnósticos/adjuntos médicos en Empresa; documentos laborales de otros empleados requieren permiso sensible, incluso por enlace antiguo |
| UI-COMP-05 | Implantado local: archivo corporativo privado | Migración aditiva 2026_10_23_020000 y disco privado existente | Carga PDF/imagen/Word <=12 MB validada por contenido/extensión; metadatos, categorías, acceso restringido/administración de personal, autor, expiración; búsqueda y paginación SQL |
| UI-COMP-06 | Implantado local: versiones y trazabilidad documental | UI-COMP-05, AuditLog y SecurityEventRecorder | No sobrescribir versiones por ORM; descarga autorizada, vínculo documento/versión, hash íntegro, sin rutas de disco en DTO; bloqueo/revisión, reintento inicial idempotente; rollback de BD limpia nuevos archivos; archivar conserva historial |
| UI-COMP-07 | Implantado local: Configuración y accesos a herramientas reales | CompanyProfile y endpoints existentes con sus permisos | Editar datos corporativos sin copiar credenciales/IBAN al payload; identidad canónica; enlaces autorizados a usuarios, series, seguridad, auditoría y copias; sin exportaciones/restauraciones ficticias |
| UI-COMP-08 | Implantado local: adaptación visual y validación | Shell existente, CSS de Empresa, ES/EN | Cuatro vistas revisadas a 1440/820/390 px, ficha profesional y carga móvil; formularios etiquetados/foco, tablas con scroll interno y tarjetas móviles; tipos/lint/catálogo/build y pruebas documentados |
| UI-COMP-09 | Pendiente: contexto multiempresa integral | Empresa activa en sesión, pertenencia de HR/usuarios/almacenamiento e índices/autorización en todo el ERP | Aplicar empresa a todas las consultas, escrituras, descargas y exportaciones con pruebas IDOR; mientras exista más de un CompanyProfile, nuevas rutas Empresa bloquean con 409. No equivale a segregación global de HR legacy |
| UI-COMP-10 | Pendiente: registro estructurado de formación | Modelo aprobado de formación, emisor, realización, acreditación y permisos | Registrar curso y acreditación sin inferirlo de un PDF; caducidades consultables, auditoría y filtros SQL. Hoy solo se consultan certificados/prevención existentes y autorizados |
| UI-COMP-11 | Pendiente: carpetas y relaciones corporativas | Dominio de carpetas real y relación autorizada con empleado/proyecto/OT/cliente/proveedor | Crear/mover carpetas y enlaces sin duplicar archivos; aislar datos sensibles; validar permisos de ambos extremos y evitar referencias huérfanas; categorías actuales no se llaman carpetas |
| UI-COMP-12 | Pendiente: checklist/porcentaje verificable de configuración | Definición de criterios, denominador y evidencia por apartado | Cada estado deriva de validación real; distinguir no configurado/no consultado/no autorizado; no mostrar 85% ni conexiones activas por existir un formulario |
| UI-COMP-13 | Pendiente: conexiones y notificaciones corporativas | Catálogo de integraciones aprobado, verificaciones reales, configuración de canales y auditoría | Conectar/verificar/desconectar con permisos, errores y secretos protegidos; cambiar canales corporativos solo con backend; hoy se conservan las herramientas existentes, sin estado de salud simulado |
| UI-COMP-14 | Pendiente: archivo reforzado y ciclo de retención | Política de retención/recuperación, antivirus configurado, bloqueo de BD/almacenamiento y operaciones | Validar recuperación real y escaneo operativo; los eventos ORM impiden sobrescritura pero no sustituyen un almacén WORM ni restricciones de escritura directa SQL. No se ofrece eliminación definitiva |
| UI-COMP-15 | Pendiente: concurrencia integral entre formularios antiguos/nuevos | Unificación de bloqueos/revisión HR y CompanyProfile, pruebas PostgreSQL simultáneas | Dos sesiones reales no pierden cambios entre rutas legacy y nuevas; hoy se verifica edición obsoleta y bloqueo del flujo nuevo, no se certifica el sistema legacy completo |
| UI-COMP-16 | Pendiente: calendario/listados completos de alertas y notificaciones | Servicio común de vencimientos y política de deduplicación | Alertas ligadas al origen con permisos; no duplicar tareas al consultar; dashboard actual muestra próximos 30 días y previews acotados, total KPI global |
| UI-COMP-17 | Pendiente: revisión transversal de privacidad HR antigua | Matriz de acceso sensible y flujos HR fuera de Empresa | Revisar todos los endpoints/exports/tareas/mutaciones antiguos. Este corte protege DTO profesionales y accesos directos a fichas/documentos/lectura de ausencias; no declara una auditoría exhaustiva del ERP |

Verificación del corte: 100 pruebas dirigidas aprobadas (1165 aserciones), 30
aserciones de navegación frontend; compilación y controles frontend correctos.
Suite global: 871 pruebas, 862 aprobadas, 3 fallos y 1 error anteriores en Catálogo,
Analítica ejecutiva, identidad de proveedor y escenario de planificación; 5 omitidas.
Las últimas paginaciones se verificaron adicionalmente con la selección dirigida.
La migración fue aplicada únicamente a PostgreSQL local. No se crearon registros
de negocio de demostración en esa base; fixtures y cargas de prueba se ejecutaron
en SQLite aislado y almacenamiento temporal.

## Publicación general solicitada — 23/10/2026

El usuario autoriza publicar el diseño acumulado y continuar los detalles por
incrementos. Candidato reconciliado con `develop` y las funciones vigentes de
`main`; copia privada previa verificada y tablas nuevas protegidas frente a
API pública. Ver [registro de lanzamiento](release-general-redesign-2026-10-23.md).
Las limitaciones de UI-COMP y de cada sección siguen abiertas; la publicación
no las convierte en capacidades implantadas.

Publicado en producción el 23/10/2026: versión `v2026.10.23.1`, commit
`c8086d5e534fb6350163ab73ccbedf2474c0bac1`. Despliegue Laravel Cloud correcto,
16 rutas autenticadas comprobadas con HTTP 200 y componente esperado,
migraciones pendientes 0 y protección RLS de las siete tablas nuevas verificada.
Suite final: 912 aprobadas y 6 omitidas; CI aprobado. Sin datos locales de
demostración en producción. Evidencia y límites en el registro de lanzamiento.

| Seguimiento operativo | Estado | Dependencias | Criterio de aceptación |
| --- | --- | --- | --- |
| Copias PostgreSQL regulares y recuperación | Pendiente; salvaguarda excepcional cifrada creada para este lanzamiento | Configuración de copias, retención y custodia de claves aprobadas | Copias periódicas verificadas y simulacro completo de restauración en una base aislada; medir recuperación y conservar evidencia |
