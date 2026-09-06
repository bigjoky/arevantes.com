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

## Arquitectura funcional objetivo — 20 áreas

La navegación, los permisos, la búsqueda y los futuros límites de dominio se
alinearán sobre estas veinte áreas. Esta taxonomía no obliga a mostrar veinte
entradas abiertas a todos los usuarios: el sidebar será jerárquico, plegable y
derivado de permisos, con accesos recientes y favoritos. Las rutas y datos
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
| P1        | Sidebar y arquitectura de módulos | Cuarto corte completado. Las 20 áreas con flujo real conservan accesos autorizados. Favoritos y accesos recientes se guardan por usuario. Ingeniería se conecta con Planificación mediante solicitudes y propuestas revisables de fecha, responsable y horas. Pendiente contrastar disponibilidad antes de generar trabajo operativo. |

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

Tras la aprobación de propuestas de fecha, responsable y horas, contrastar
disponibilidad, ausencias y solapes antes de generar una orden operativa revisable.
En paralelo, comparar en producción tiempos y tamaño
de las cargas semánticas mediante `Server-Timing` y percentiles p75.

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
agenda ni comprueba disponibilidad. El siguiente incremento contrastará ausencias
y solapes antes de crear una orden revisable. La interfaz comparte formularios
adaptables y traducción completa entre Ingeniería y Planificación.

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
