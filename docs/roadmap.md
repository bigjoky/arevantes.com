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
| Web pública       | Captación multirramo, formulario conectado al CRM, SEO técnico, metadatos sociales, accesibilidad y experiencia responsive.                                            |
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
- Publicar casos de uso solo cuando dispongan de alcance, ubicación, permiso e
  información verificable; nunca inventar resultados comerciales.

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

## Próximo corte recomendado

Iniciar el Horizonte 2 con el primer vertical de **servicio recurrente y
posventa**. El siguiente incremento debe convertir una instalación entregada en
un plan versionado de mantenimiento, con cobertura, frecuencia, SLA, garantía,
activos incluidos y próximas intervenciones, sin generar órdenes duplicadas.

CRM debe conservar el origen de renovaciones y ampliaciones; Operaciones y móvil
deben compartir agenda, versión de plantilla y cierre offline; Suministro debe
separar repuesto preventivo de reserva de proyecto; Finanzas debe preparar la
facturación periódica sin emitirla antes de aprobación; el portal debe mostrar
solo cobertura y fechas confirmadas; Analítica y Arevia deben explicar
renovación, riesgo y siguiente acción desde datos autorizados.
