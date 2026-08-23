# Roadmap unificado del ERP web

`develop` es la linea comun de integracion. Los modulos evolucionan sobre esta
base y no mantienen implementaciones funcionales aisladas.

## Nivel actual

| Area | Capacidades alineadas | Siguiente incremento |
| --- | --- | --- |
| CRM y ventas | ciclo comercial, captacion, cartera, agenda y rutas comerciales moviles offline, aprobaciones, opciones, SLA y trazabilidad | automatizacion de renovaciones y forecast por cohortes |
| Finanzas | contabilidad, banca, cobros, riesgo de clientes y alertas | cierre asistido y conciliacion de excepciones |
| Operaciones | planificacion, ordenes, mantenimiento, RMA y sincronizacion movil | capacidad predictiva y repuestos por criticidad |
| Suministro | compras, aprobaciones, reposicion, calidad y control tower | simulacion de abastecimiento y riesgo de proveedor |
| Recursos humanos | expediente, contratos, ausencias, tareas y controles sensibles | capacidad, turnos y coste operativo planificado |
| Portal de cliente | documentos protegidos, aceptacion y certificados publicados | autoservicio de incidencias y seguimiento de hitos |
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
