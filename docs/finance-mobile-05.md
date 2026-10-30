# Tesorería y Administración Móvil 05

30/10/2026. Checkout independiente `feature/finance-mobile-05`. No publicado.

## Implantación

Tesorería, previsiones, bancos, conciliación, vencimientos, cobros/pagos y facturas utilizan los contratos existentes. Tarjetas y formularios hasta 1023 px, tablas desde 1024; títulos financieros legibles cuando las acciones ocupan toda la fila. Accesos móviles y retorno saneado entre los listados y las fichas, con filtros, ejercicio y paginación. Los filtros se mantienen en un único árbol al cambiar de tamaño.

Las operaciones Inertia Form reutilizan ProjectForm y sus guardas publicadas, con confirmación financiera local antes del envío. Los formularios useForm de conciliación reutilizan las mismas guardas: cierre protegido, valores/errores conservados, claves existentes y bloqueo síncrono. Los permisos, motivos, revisión, procedencia del dinero, comprobantes y controles contables ACC-01/ACC-02 permanecen en el servidor. No se cambian endpoints, reglas, cuentas históricas, importes, IVA, inversión del sujeto pasivo ni cierres. No hay migraciones ni cambios de infraestructura.

## Verificación

94 pruebas frontend, 130 backend/1912 aserciones, build, TypeScript, ESLint, i18n y Pint. Matriz de 17 rutas por siete viewports: 320, 390, 412, 768, 1023, 1024 y 1440. 119 comprobaciones sin desbordamiento ni títulos de ancho cero. Build mantiene advertencia de tamaño de chunks.

Prueba de navegador exclusivamente en SQLite sintético: borrador de previsión conservado al cruzar 1023/1024, cierre/cancelación/descarte, error del servidor con valores conservados, doble pulsación con una sola previsión registrada. Borrador de investigación bancaria conserva el motivo al continuar editando. Sin cobros, pagos, conciliaciones ni asientos nuevos en la base QA; solo una previsión sintética. Catálogo de exclusiones i18n intacto; se normalizaron dos llaves de estilo del test heredado, sin cambiar sus aserciones.

## Límites del backend conservados

El saldo disponible bancario confirmado, histórico completo de saldos y aislamiento multientidad no están implementados por los contratos actuales. Se mantienen explícitos como no disponibles, sin presentar previsiones como saldos confirmados. Los permisos por rol/registro existentes se conservan; no se afirma una separación entre empresas que el modelo no ofrece. No se incorporan conexiones bancarias, liquidaciones reales ni asientos automáticos adicionales.

Las relaciones solo se enlazan cuando existen. Si falta una operación, se utiliza su procedimiento autorizado existente; no se deduce conciliación por coincidencia visual. No se modifican las herramientas especializadas de importación de extractos ni sus controles de servidor. No se ha ensayado en teléfonos físicos: instalación, teclado, safe areas y lectores requieren validación en iOS/Android reales con HTTPS autorizado.

## Entrega

Commit exclusivo preparado localmente. Sin push, PR, previews ni despliegue. No se modificaron datos productivos ni secretos. Los datos sintéticos y configuración local de QA quedan excluidos de Git.
