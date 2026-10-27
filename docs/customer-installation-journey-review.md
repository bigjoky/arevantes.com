# Recorrido de cliente a instalación — revisión local, 24/10/2026

Esta revisión está publicada en producción, v2026.10.24.4 (PR #154), con autorización del usuario. Solo se trabaja en http://localhost:8000 y en bases de prueba locales separadas. Los expedientes con prefijo DEMO son ficticios y no acreditan trabajos, firmas o cobros reales.

## Diagnóstico y recorrido

El recorrido anterior mezclaba creación de proyecto con una visita obligatoria, guardaba la aceptación interna sin un justificante específico de papel y repartía los pendientes entre CRM, presupuesto, proyecto, ingeniería, órdenes, compras y facturación. Una oportunidad ganada, un anticipo confirmado y una instalación terminada no son el mismo hecho.

Recorrido propuesto: contacto → cualificación → visita cuando corresponda → propuesta → aceptación → preparación técnica/económica → materiales y planificación → ejecución → entrega → cierre administrativo.

## Capacidades y cambios

| Área | Capacidad aprovechada | Corrección local |
|---|---|---|
| Captación | Formulario web, visita comercial, resolución de cliente por teléfono y CRM | Email o teléfono obligatorio; sin necesidad de email; reutilización por teléfono normalizado y deduplicación transaccional PostgreSQL |
| CRM | Oportunidades, actividades, responsable y seguimiento | Recorrido compartido y presupuesto aceptado vigente, o último presupuesto cuando no hay aceptación |
| Presupuesto | Líneas, componentes, IVA, variantes y plantillas comerciales | Creación de plantilla más visible; diferenciada de las plantillas técnicas |
| Aceptación | Workflow de estados, aprobación y anticipo | Canal papel con fecha, firmante, actor, observaciones, versión y justificante privado descargable con permiso; reintentos sin sobrescritura |
| Proyecto | Installation y relaciones existentes | Alta sin fecha ni técnico; visita solo con fecha expresa; reutilización del proyecto/visita ligados a la oportunidad y rechazo de vínculos ambiguos |
| Preparación | Disponibilidad, habilidades, existencias, anticipo e ingeniería | Orden planificada permitida con anticipo pendiente sin reservas automáticas; ejecución sigue bloqueada. Validación técnica por visita completada o ingeniería aprobada, con dispensa administrativa motivada |
| Compras | Necesidades, RFQ, pedidos, recepciones y ledger de inventario | Aprovisionamiento serializado por proyecto; corrección de almacén de recepción mediante traslado compensatorio, conservando cantidades y series, con motivo y auditoría |
| Facturación | Hitos editables, cobro previo, emisión expresa y contabilidad existentes | Se conservan las reglas y documentos. Los borradores no presentan su fecha como emisión. Recorrido muestra anticipo y cobro/facturación separados |
| Ejecución | Checklist, firma, evidencias, tiempos, consumo y estados | Estado físico calculado entre órdenes; una visita no hace retroceder una instalación en ejecución; sin fecha no se afirma “programado” |
| Entrega y cierre | Actividades y auditoría existentes | Legalizaciones nuevas desde componentes marcadas como obligatorias; selección administrativa de tareas que condicionan la entrega, confirmación expresa y cierre/reapertura separados, con revisión de concurrencia |

## Reglas de cierre para revisar

Condiciones de cierre: al menos una orden de instalación/puesta en marcha completada, ninguna orden de esas clases abierta, entrega confirmada, tareas documentales explícitamente obligatorias completadas y presupuesto totalmente cobrado y facturado sin hitos por emitir, salvo excepción económica expresa de administrador con motivo obligatorio.

Una tarea informativa pendiente no bloquea el cierre. Cancelar una tarea documental obligatoria no acredita su realización: administración puede revisar el requisito con motivo. Reabrir no borra la instalación terminada ni el historial; se conserva la decisión anterior y se solicita nueva confirmación de entrega.

La política económica aprobada por el usuario permite una excepción administrativa expresa y motivada para pendientes de cobro o facturación. No se cambia fiscalidad ni se emiten documentos por cerrar. Rectificativas y diferencias entre contrato y facturación deben revisarse; no se compensan importes históricos automáticamente.

## Límites y dependencias

- Los requisitos técnicos históricos nulos se muestran sin definir; no se declara una visita existente solo por una fecha o una etapa CRM.
- Conceptos libres sin producto ni componentes se avisan como clasificación logística pendiente; no se supone que todo concepto libre sea material.
- Documentación sin tareas pendientes no equivale a legalización acreditada. La confirmación de entrega es una decisión humana trazable, no una certificación externa automática.
- Corrección de recepción limitada a material íntegro disponible, sin salidas posteriores; lo reservado/consumido requiere revisión y no se traslada silenciosamente.
- Se mantiene la arquitectura de permisos actual; no se introducen cuadrillas, vehículos, segregación multiempresa ni nuevas entidades paralelas.
- Las tareas de legalización históricas requieren revisar sus requisitos: no se inventa una clasificación retrospectiva.
- Se conserva el cierre físico anterior; no se marcan expedientes históricos como cerrados administrativamente.

## Verificación

- Suite general: 946 casos, 938 aprobados y 8 omitidos; 14.362 aserciones.
- PostgreSQL 18 local, base desechable separada: 43 pruebas aprobadas / 443 aserciones sobre recorrido, aceptación, cobros, cierre, compras, recepciones y órdenes. Dos pruebas adicionales de concurrencia real con conexiones independientes aprobadas / 16 aserciones, para conversión y aceptación en papel repetidas.
- Las pruebas de archivos se ejecutaron secuencialmente: ejecutar simultáneamente suites que comparten el disco falso de Laravel provoca interferencias entre pruebas. La repetición aislada pasó completa.
- Once pruebas dirigidas de compras y recepción aprobadas / 172 aserciones tras integrar la corrección en la pantalla habitual de aprovisionamiento.
- TypeScript, ESLint de las interfaces modificadas, Pint y compilación de producción correctos. La compilación mantiene el aviso de tamaño de paquetes existente.
- Revisión visual a 1440 px y 390 px: expediente, aceptación en papel, creación sin fecha/técnico, bloqueo explicado del cierre y corrección de almacén en compras. Sin desbordamiento horizontal de página en las vistas comprobadas.
- Datos de demostración ficticios; ninguna prueba destructiva ni modificación sobre producción.

## Recorrido para revisión local

1. Contacto sin correo y propuesta pendiente: http://localhost:8000/opportunities/1 y http://localhost:8000/quotes/2 . En la propuesta, desplegar «Registrar aceptación firmada en papel».
2. Proyecto con aceptación en papel y anticipo pendiente: http://localhost:8000/execution/projects/1 . Revisar «Recorrido del expediente» y sus siguientes acciones.
3. Proyecto creado sin fecha ni técnico, sin orden ni visita automática: http://localhost:8000/execution/projects/3 .
4. Instalación terminada con documentación y finanzas pendientes: http://localhost:8000/execution/projects/2 . No equivale a expediente cerrado.
5. Recepción parcial ficticia: http://localhost:8000/purchases/1?tab=receipts . Desplegar «Corregir almacén de recepción».

La sesión local de demostración está abierta. La versión revisada se ha publicado como v2026.10.24.4 y la excepción económica aprobada está disponible para revisión local.


Actualización de cierre económico (24/10/2026): decisión del usuario confirmada. Administración puede autorizar expresamente una excepción económica con motivo obligatorio. Se conservan cobros y facturas pendientes y su visibilidad; la auditoría registra actor, fecha, motivo e importes/documentos de la decisión. La excepción no omite órdenes o documentación obligatoria ni emite facturas o registra cobros. Implantado solo en local. Sustituye el criterio económico provisional anterior.

Verificación de la excepción económica: 16 pruebas dirigidas aprobadas (117 aserciones); PostgreSQL vuelve a comprobar aceptación, cierre y recorrido con el nuevo caso. Permisos insuficientes y motivo vacío rechazados; excepción económica no omite órdenes pendientes; expediente cerrado se identifica como «Cerrado con excepción económica». TypeScript, ESLint, Pint y compilación correctos; formulario revisado visualmente en móvil.


Publicado en producción el 24/10/2026 con autorización del usuario: PR #154, versión v2026.10.24.4, commit 8a176866deb332a6bc7b1b398f048b84d3f1a03d, deployment depl-a30eb6c5-0758-4617-8b76-5fe7c38b1479 confirmado correcto. Tres migraciones aplicadas, fuentes principales coincidentes con la versión, rutas registradas y consultas del recorrido en oportunidad/proyecto correctas en PostgreSQL de producción. Salud y login HTTP 200. No se han cargado datos de demostración ni ejecutado pruebas destructivas en producción. Copia exacta de publicación: 939 pruebas aprobadas / 8 omitidas; 44 pruebas PostgreSQL más dos de concurrencia aprobadas; 29 pruebas frontend y CI PHP 8.4/8.5 y calidad correctos.


<!-- Preserved production review before release integration -->

# Recorrido de cliente a instalación — revisión local, 24/10/2026

Publicación autorizada por el usuario el 24/10/2026, pendiente de confirmación del despliegue. Solo se trabaja en http://localhost:8000 y en bases de prueba locales separadas. Los expedientes con prefijo DEMO son ficticios y no acreditan trabajos, firmas o cobros reales.

## Diagnóstico y recorrido

El recorrido anterior mezclaba creación de proyecto con una visita obligatoria, guardaba la aceptación interna sin un justificante específico de papel y repartía los pendientes entre CRM, presupuesto, proyecto, ingeniería, órdenes, compras y facturación. Una oportunidad ganada, un anticipo confirmado y una instalación terminada no son el mismo hecho.

Recorrido propuesto: contacto → cualificación → visita cuando corresponda → propuesta → aceptación → preparación técnica/económica → materiales y planificación → ejecución → entrega → cierre administrativo.

## Capacidades y cambios

| Área | Capacidad aprovechada | Corrección local |
|---|---|---|
| Captación | Formulario web, visita comercial, resolución de cliente por teléfono y CRM | Email o teléfono obligatorio; sin necesidad de email; reutilización por teléfono normalizado y deduplicación transaccional PostgreSQL |
| CRM | Oportunidades, actividades, responsable y seguimiento | Recorrido compartido y presupuesto aceptado vigente, o último presupuesto cuando no hay aceptación |
| Presupuesto | Líneas, componentes, IVA, variantes y plantillas comerciales | Creación de plantilla más visible; diferenciada de las plantillas técnicas |
| Aceptación | Workflow de estados, aprobación y anticipo | Canal papel con fecha, firmante, actor, observaciones, versión y justificante privado descargable con permiso; reintentos sin sobrescritura |
| Proyecto | Installation y relaciones existentes | Alta sin fecha ni técnico; visita solo con fecha expresa; reutilización del proyecto/visita ligados a la oportunidad y rechazo de vínculos ambiguos |
| Preparación | Disponibilidad, habilidades, existencias, anticipo e ingeniería | Orden planificada permitida con anticipo pendiente sin reservas automáticas; ejecución sigue bloqueada. Validación técnica por visita completada o ingeniería aprobada, con dispensa administrativa motivada |
| Compras | Necesidades, RFQ, pedidos, recepciones y ledger de inventario | Aprovisionamiento serializado por proyecto; corrección de almacén de recepción mediante traslado compensatorio, conservando cantidades y series, con motivo y auditoría |
| Facturación | Hitos editables, cobro previo, emisión expresa y contabilidad existentes | Se conservan las reglas y documentos. Los borradores no presentan su fecha como emisión. Recorrido muestra anticipo y cobro/facturación separados |
| Ejecución | Checklist, firma, evidencias, tiempos, consumo y estados | Estado físico calculado entre órdenes; una visita no hace retroceder una instalación en ejecución; sin fecha no se afirma “programado” |
| Entrega y cierre | Actividades y auditoría existentes | Legalizaciones nuevas desde componentes marcadas como obligatorias; selección administrativa de tareas que condicionan la entrega, confirmación expresa y cierre/reapertura separados, con revisión de concurrencia |

## Reglas de cierre para revisar

Condiciones de cierre: al menos una orden de instalación/puesta en marcha completada, ninguna orden de esas clases abierta, entrega confirmada, tareas documentales explícitamente obligatorias completadas y presupuesto totalmente cobrado y facturado sin hitos por emitir, salvo excepción económica expresa de administrador con motivo obligatorio.

Una tarea informativa pendiente no bloquea el cierre. Cancelar una tarea documental obligatoria no acredita su realización: administración puede revisar el requisito con motivo. Reabrir no borra la instalación terminada ni el historial; se conserva la decisión anterior y se solicita nueva confirmación de entrega.

La política económica aprobada por el usuario permite una excepción administrativa expresa y motivada para pendientes de cobro o facturación. No se cambia fiscalidad ni se emiten documentos por cerrar. Rectificativas y diferencias entre contrato y facturación deben revisarse; no se compensan importes históricos automáticamente.

## Límites y dependencias

- Los requisitos técnicos históricos nulos se muestran sin definir; no se declara una visita existente solo por una fecha o una etapa CRM.
- Conceptos libres sin producto ni componentes se avisan como clasificación logística pendiente; no se supone que todo concepto libre sea material.
- Documentación sin tareas pendientes no equivale a legalización acreditada. La confirmación de entrega es una decisión humana trazable, no una certificación externa automática.
- Corrección de recepción limitada a material íntegro disponible, sin salidas posteriores; lo reservado/consumido requiere revisión y no se traslada silenciosamente.
- Se mantiene la arquitectura de permisos actual; no se introducen cuadrillas, vehículos, segregación multiempresa ni nuevas entidades paralelas.
- Las tareas de legalización históricas requieren revisar sus requisitos: no se inventa una clasificación retrospectiva.
- Se conserva el cierre físico anterior; no se marcan expedientes históricos como cerrados administrativamente.

## Verificación

- Suite general: 946 casos, 938 aprobados y 8 omitidos; 14.362 aserciones.
- PostgreSQL 18 local, base desechable separada: 43 pruebas aprobadas / 443 aserciones sobre recorrido, aceptación, cobros, cierre, compras, recepciones y órdenes. Dos pruebas adicionales de concurrencia real con conexiones independientes aprobadas / 16 aserciones, para conversión y aceptación en papel repetidas.
- Las pruebas de archivos se ejecutaron secuencialmente: ejecutar simultáneamente suites que comparten el disco falso de Laravel provoca interferencias entre pruebas. La repetición aislada pasó completa.
- Once pruebas dirigidas de compras y recepción aprobadas / 172 aserciones tras integrar la corrección en la pantalla habitual de aprovisionamiento.
- TypeScript, ESLint de las interfaces modificadas, Pint y compilación de producción correctos. La compilación mantiene el aviso de tamaño de paquetes existente.
- Revisión visual a 1440 px y 390 px: expediente, aceptación en papel, creación sin fecha/técnico, bloqueo explicado del cierre y corrección de almacén en compras. Sin desbordamiento horizontal de página en las vistas comprobadas.
- Datos de demostración ficticios; ninguna prueba destructiva ni modificación sobre producción.

## Recorrido para revisión local

1. Contacto sin correo y propuesta pendiente: http://localhost:8000/opportunities/1 y http://localhost:8000/quotes/2 . En la propuesta, desplegar «Registrar aceptación firmada en papel».
2. Proyecto con aceptación en papel y anticipo pendiente: http://localhost:8000/execution/projects/1 . Revisar «Recorrido del expediente» y sus siguientes acciones.
3. Proyecto creado sin fecha ni técnico, sin orden ni visita automática: http://localhost:8000/execution/projects/3 .
4. Instalación terminada con documentación y finanzas pendientes: http://localhost:8000/execution/projects/2 . No equivale a expediente cerrado.
5. Recepción parcial ficticia: http://localhost:8000/purchases/1?tab=receipts . Desplegar «Corregir almacén de recepción».

La sesión local de demostración está abierta. No se ha publicado esta versión y la excepción económica aprobada está disponible para revisión local.


Actualización de cierre económico (24/10/2026): decisión del usuario confirmada. Administración puede autorizar expresamente una excepción económica con motivo obligatorio. Se conservan cobros y facturas pendientes y su visibilidad; la auditoría registra actor, fecha, motivo e importes/documentos de la decisión. La excepción no omite órdenes o documentación obligatoria ni emite facturas o registra cobros. Implantado solo en local. Sustituye el criterio económico provisional anterior.

Verificación de la excepción económica: 16 pruebas dirigidas aprobadas (117 aserciones); PostgreSQL vuelve a comprobar aceptación, cierre y recorrido con el nuevo caso. Permisos insuficientes y motivo vacío rechazados; excepción económica no omite órdenes pendientes; expediente cerrado se identifica como «Cerrado con excepción económica». TypeScript, ESLint, Pint y compilación correctos; formulario revisado visualmente en móvil.
