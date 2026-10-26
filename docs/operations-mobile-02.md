# AREVANTES ERP — Operaciones Móvil 02

## Funcionalidades implementadas

- Inicio móvil con accesos a Mis trabajos, trabajos de hoy, agenda y planificación autorizada; resúmenes de órdenes reales y acceso al listado completo. Búsqueda y filtros originales de estado, tipo, técnico, fechas y asignación, sin duplicar sus campos ni crear otra fuente de datos.
- Tarjetas y ficha adaptadas hasta 1023 px; tablas y planificación de escritorio conservadas desde 1024 px. Instrucciones, observaciones, técnico, fechas, checklist, historial y ubicación registrada. Mapas usa exclusivamente la dirección existente; no solicita geolocalización.
- Formularios existentes de alta, reprogramación y transición con la protección publicada reutilizada. Latch síncrono de envío también en escritorio. No se cambian estados, validaciones, políticas ni reglas de cierre.
- Partes mediante los endpoints existentes de inicio/parada del temporizador, con notas y kilometraje; fotos/documentos mediante la carga privada existente. No se publican adjuntos al cliente ni se certifica automáticamente una orden.
- Contexto seguro de orden → agenda/listado y entre pestañas, conservando vista, fecha y filtros. Se descartan destinos externos y parámetros de comandos.
- Único cambio de proyección backend: temporizador abierto del usuario autenticado para esa orden, limitado a id/inicio, sin importes ni atributos calculados. La autorización por registro sigue ejecutándose antes de esa consulta.

## Archivos principales

`resources/js/components/operations/{mobile-home,mobile-execution,workspace}.tsx`, `resources/js/pages/work-orders/{list,show,create}.tsx`, `resources/js/pages/operations/dashboard.tsx`, `resources/js/components/mobile/mobile-agenda.tsx`, `resources/js/hooks/use-operations-form-protection.ts`, `resources/js/lib/operations/navigation.ts`, `resources/css/operations-mobile.css`, `app/Services/Operations/OperationsWorkspace.php` y traducciones aditivas propias.

## Pruebas y resultados

- Build, TypeScript, ESLint y git diff --check correctos. Advertencias habituales de tamaño de chunks.
- 57 pruebas frontend aprobadas, incluidas contexto de agenda, rechazo de destinos externos, reutilización del guard, doble envío, error/reintento y kilometraje vacío.
- 59 pruebas backend / 1001 aserciones aprobadas: OperationsMobileTest, OperationsRedesignTest, OperationsAdminTest, WorkOrderTest, WorkOrderEvidenceRequirementsTest, FieldServiceOperationsTest, TechnicianSkillChecklistTest, WorkspaceNavigationAuthorizationTest y MobileTaskSubmissionTest. Incluyen permisos por rol/registro, transiciones, checklist, evidencias, actuación y proyección privada del temporizador.
- i18n: 79 incidencias preexistentes, salida exactamente igual a la base; se añadieron únicamente las traducciones propias.
- Navegador sobre copia independiente en localhost:8000 y SQLite QA nueva con datos sintéticos: 63 comprobaciones (nueve rutas por 320, 390, 412, 768, 1023, 1024 y 1440 px), sin desbordamiento raíz ni contenido duplicado. Listado/búsqueda, ficha, tareas, documentos, partes, creación, agenda y planificación.
- Borrador de nueva orden conservado al cruzar 1023/1024 y Menú → Volver; continuar y descartar comprobados. Parte guardado desde escritorio tras empezar en móvil. Error de cierre por checklist/firma/foto conserva notas y errores; el servidor no permite finalizar. Agenda → orden → agenda mantiene día y fecha.
- Guardado real del parte sintético con kilometraje vacío: normalizado a cero porque la columna existente es no nula aunque el endpoint acepte nullable. La prueba detectó el error antes de esa adaptación; no se modificó la API ni la base. Backend conserva su validación de valores negativos.
- Pruebas backend con SQLite :memory:, sesiones/caché/mail array, sin correos externos. Sin datos ni escrituras productivas. Evidencia local en `/tmp/arevantes-operations-mobile-02/`; no se incluye la base QA, .env, contraseña ni dependencias en Git. Instancia local habitual restaurada al terminar.

## Limitaciones pendientes

Prioridad propia, contactos de llamada/email y asignaciones múltiples no existen en el contrato actual de la orden; no se inventan. El enlace a gestión avanzada conserva los formularios administrativos originales: no se afirma que reciban las nuevas guardas. Clasificación especializada de evidencias y firma permanecen en sus flujos existentes; las reglas de cierre siguen siendo obligatorias. Pendiente validación física de PWA instalada en iPhone/Android, teclado, safe areas y accesibilidad. El navegador simulado no sustituye esa validación. Carencias, dependencias y aceptación registradas en el roadmap.

## Commit preparado

Rama local `feature/operations-mobile-02`, basada en main `dc7074eace324b23c53e1e7b0e2762881e1e569a`. Commit limitado a esta entrega; el hash exacto se comunica al finalizar.

## Estado de publicación

Solo local. Sin push, Pull Request, previews ni despliegue. Comercial, identidad, Nexo, expedientes, manifest, service worker e infraestructura no se modifican. Los formularios operativos permanecen montados entre breakpoints; las herramientas de ejecución están también disponibles en escritorio para continuar un borrador iniciado en móvil. Se conserva su estructura de navegación y tablas.
