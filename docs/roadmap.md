# Roadmap unificado de Arevantes

Este documento ordena la evolución del ERP web, sus APIs y los consumidores
móviles. `develop` es la línea común de integración del desarrollo web. La app
móvil mantiene su repositorio y ciclo de compilación independientes, pero debe
respetar los mismos contratos API, permisos y estados de negocio.

El roadmap se organiza por horizontes de capacidad, no por módulos aislados ni
por fechas artificiales. Ningún área debe avanzar al horizonte siguiente si las
dependencias que comparte con las demás siguen incompletas.

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

## Próximo corte recomendado

Construir la **aplicación controlada de mejoras aprobadas** como una segunda
acción explícita. Debe mostrar el cambio antes de confirmarlo, volver a comprobar
la versión destino, reutilizar los servicios versionados de cada área, ser
idempotente y registrar resultado o fallo en el expediente CAPA. Ningún adaptador
podrá crear compras, cambiar contratos o contactar clientes fuera de los límites
específicos que confirme la persona responsable.

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
