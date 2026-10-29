# Almacén y Compras Móvil 04

29/10/2026. Implementación local en `feature/supply-mobile-04`, base `34c79ff0001d4eee85d60d49f2b5c22f0dea063b`. Sin publicación.

## Implementación

Catálogo, existencias por almacén, necesidades, reservas, movimientos, transporte, pedidos y recepciones adaptados hasta 1023 px. Tablas de escritorio conservadas desde 1024. Accesos autorizados desde Almacén y enlaces mínimos desde instalación y orden de trabajo, con retorno saneado y filtros conservados.

Se reutilizan formularios, diálogos accesibles, protección de borradores y endpoints publicados. Confirmaciones y bloqueo de envíos simultáneos en operaciones relevantes; errores conservan los valores. El descarte limpia también las líneas controladas del editor de movimientos. La orden permite reservar y consumir con sus permisos reales. Las únicas ampliaciones backend son proyecciones de unidad/opciones autorizadas y permiso para mostrar el enlace de materiales.

No hay migraciones, servicios de stock nuevos, cambios de valoración, contabilidad, infraestructura ni secretos versionados. El stock disponible procede del backend; los agregados globales se identifican como tales.

## Verificación

- Frontend: 85 pruebas aprobadas.
- Backend: 49 pruebas, 1036 aserciones; SQLite aislado, sesiones/caché/correo de prueba.
- Build, TypeScript, ESLint, catálogo ES/EN, Pint y diff sin errores. Build conserva advertencia de tamaño de chunks.
- 119 comprobaciones de renderizado: 17 rutas por 320, 390, 412, 768, 1023, 1024 y 1440 px; sin desbordamiento horizontal y navegación móvil solo donde corresponde. Sin errores de consola en la revisión final.
- En navegador local con datos sintéticos: consumo excesivo rechazado conservando valores; consumo de 2 unidades con doble confirmación registra una sola salida; recepción parcial de 3 registra una sola recepción; entrega de 3 y devolución parcial de 1 trasladan stock sin consumo adicional. Físico total: 100 + 3 - 2 = 101. Consulta sin mutaciones cubierta por backend.
- Borradores conservados al cruzar 1023/1024 y al volver desde Menú. Descarte del movimiento devuelve una línea con cantidad 1/coste 0. Protección de reserva y producto comprobada. Datos y operaciones exclusivamente locales.

## Límites reales

El modelo no ofrece estado de entrega pendiente/preparación: no se inventan indicadores ni acciones. Las entregas existentes son traslados ya registrados. No existe ACL individual por almacén; se respetan los gates y almacenes activos/usables existentes. Las reservas manuales no disponen de clave idempotente de servidor: las guardas impiden doble envío en la misma instancia y se conservan transacciones/bloqueos de disponibilidad, pero no se promete deduplicación entre pestañas o reintentos independientes. Consumo, transporte y recepción reutilizan las claves existentes.

No se han ensayado carreras nuevas con procesos PostgreSQL ni teléfonos físicos. Quedan pendientes teclado, instalación PWA, VoiceOver/TalkBack y safe areas en iPhone/Android reales. La validación responsive no sustituye esa prueba física.

## Entrega

Commit exclusivo, sin push, PR, preview ni despliegue. La instancia temporal de QA se restaura a la copia local anterior al finalizar; este checkout no se anuncia como visible en esa copia habitual.
