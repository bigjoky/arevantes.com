# Roadmap unificado del ERP web

`develop` es la linea comun de integracion. Los modulos evolucionan sobre esta
base y no mantienen implementaciones funcionales aisladas.

## Nivel actual

| Area | Capacidades alineadas | Siguiente incremento |
| --- | --- | --- |
| CRM y ventas | ciclo comercial, captacion multirramo, cartera, agenda y rutas comerciales moviles offline, conversion atribuida por origen y comercial, aprobaciones, opciones, SLA y trazabilidad | automatizacion de renovaciones y forecast por linea de negocio y cohortes |
| Finanzas | contabilidad, banca, cobros, riesgo de clientes, alertas, cierre asistido, bloqueo por excepciones y escenarios de compra no comprometida | conciliacion guiada de excepciones y simulacion de caja integrada |
| Operaciones | planificacion y fichas tecnicas para solar, recarga, clima, electricidad y seguridad; ordenes, mantenimiento, RMA, sincronizacion movil, capacidad semanal y riesgo de repuestos criticos | plantillas certificables, puesta en marcha y mantenimiento preventivo por linea |
| Suministro | compras, aprobaciones, reposicion, calidad, control tower, criticidad, riesgo por fecha y alternativas de proveedor | conversion gobernada de escenarios en solicitudes de oferta |
| Recursos humanos | expediente, contratos, ausencias, tareas, controles sensibles y capacidad coordinada con operaciones | turnos y coste operativo planificado |
| Portal de cliente | instalaciones multirramo, documentos protegidos, aceptacion, certificados y autoservicio de incidencias | seguimiento de hitos y comunicaciones proactivas por tipo de instalacion |
| Analitica | drilldowns acotados, alertas e informes programados | objetivos versionados y explicacion de desviaciones |
| Arevia | conocimiento gobernado, acciones, URLs y contexto operativo | evaluacion continua y aprobacion humana por riesgo |
| Plataforma | permisos, navegacion autorizada, auditoria, seguridad, copias y observabilidad | modularizacion progresiva de rutas y proveedores |

## Criterio comun de entrega

Un incremento solo se considera alineado cuando incluye:

1. modelo y migracion reversibles;
2. autorizacion y validacion en servidor;
3. interfaz operable y estados vacios o de error;
4. trazabilidad de los cambios sensibles;
5. pruebas de flujo y regresion cruzada;
6. compilacion, tipos y formato correctos;
7. integracion en `develop` antes de abrir el siguiente incremento.

## Integraciones de esta iteracion

- Cobro de aceptacion con factura de anticipo, bloqueo operativo y liberacion
  tras conciliacion bancaria.
- Edicion completa e independiente de planes de mantenimiento.
- RMA trazable de activos enlazado al historial tecnico.
- Compatibilidad entre seleccion de opciones comerciales, anticipo y factura de
  saldo, sin sobrepasar el total aceptado.
- Proteccion de documentos compartidos verificada sobre la base comun.
- Navegacion del area autenticada construida en servidor con los mismos permisos
  que protegen cada ruta, incluidos submenus y accesos rapidos.
- Dashboard segmentado por capacidades para no consultar, mostrar ni enlazar
  informacion de areas no autorizadas.
- Acceso directo por URL cubierto por permisos y regresiones para perfiles
  parciales de solo consulta.
- Primer flujo comercial movil offline: visita geolocalizada, alta o
  reutilizacion del contacto, consentimiento, oportunidad asignada y siguiente
  accion, con idempotencia y autorizacion de servidor.
- Cartera y agenda comercial movil limitadas al propietario, almacenadas en el
  dispositivo y filtrables por vencimiento, prioridad y proxima accion.
- Ruta diaria comercial generada desde visitas y reuniones asignadas, optimizada
  por proximidad y ejecutable offline con llegada GPS, notas y cierre sincronizado.
- Conversion comercial explicable por origen y responsable, con valor ganado,
  tendencia mensual, calidad de atribucion y alcance propio en la app movil.
- Prevision unica de capacidad tecnica a seis semanas, compartida por Recursos
  Humanos y Operaciones, que descuenta ausencias aprobadas sin revelar sus
  motivos y detecta sobrecargas por semana y profesional.
- Criticidad y plazo objetivo configurables por repuesto, con simulacion cronologica
  de reservas, stock libre y entradas previstas; las carencias bloqueantes se
  comparten entre Suministro y Operaciones junto con su exposicion economica.
- Alternativas de proveedor priorizadas por llegada a tiempo, preferencia,
  scorecard y coste, ajustadas a MOQ y multiplos; Finanzas recibe el efectivo y
  fecha de pago recomendados como escenario aun no comprometido.
- Catalogo comun de lineas de negocio compartido por CRM, presupuestos,
  instalaciones, portal, analitica y APIs, con electricidad ampliada y la nueva
  rama de alarmas y sistemas de seguridad.
- Fichas tecnicas especificas para potencia, fases y certificacion electrica, y
  para tipologia, zonas, supervision y grado de los sistemas de seguridad.
- Checklists operativos diferenciados para instalaciones electricas y de
  seguridad, incluida la comprobacion funcional, trazabilidad de equipos y
  entrega al cliente.
- Captacion publica y comercial movil alineada con ambas ramas, manteniendo
  compatibilidad con los intereses y proyectos historicos.
