# AREVANTES — PWA adaptativa iOS / Android

Corte local del 23/10/2026. Copia efectiva:
`/Users/joaquinarevalobueno/.codex/worktrees/ffe1/arevantes.com`, URL única
`http://localhost:8000`. Rama activa: `feature/technician-skill-checklist`.

## Seguridad del trabajo y publicación anterior

Se inspeccionaron status, diff, rama y archivos nuevos antes de editar. Existían
los cambios locales del menú móvil; se conservaron. Por eso no se cambió de rama
ni se creó `ai/pwa-native-redesign` sobre una copia compartida con trabajo pendiente.
La instantánea previa de cambios rastreados quedó en `/tmp/pwa-before.patch`.
No se cambian bases de datos, secretos, permisos ni modelos de negocio.

La publicación anterior del arreglo del drawer quedó completada por autorización
previa: versión `v2026.10.23.4`, PR #149 a main y #150 a develop, commit productivo
`f79866655e90b34c491c261158d8c747808ac9b6`. Laravel Cloud confirmó
`depl-a30d18c4-0f17-46a4-b2ca-f892e2ba9dba` succeeded, 14:40:54–14:42:38 UTC;
`/up` HTTP 200 y CSS público con `.mobile-navigation-panel` verificado.
**Ese despliegue no incluye este rediseño PWA posterior.** Para el corte PWA no
se realizan commits, pushes, fusiones ni nuevas PR.

## Auditoría y arquitectura

React 19, Inertia 3, Laravel 13, Tailwind 4 y Radix existentes. La capa móvil se
monta en el layout compartido por debajo de 1024 px, conservando el breakpoint
del sidebar. No hay otra aplicación React ni otra jerarquía de rutas de negocio.
Desktop sigue montando el sidebar, cabecera y contenidos originales.

La única fuente de módulos/rutas/permisos sigue siendo `auth.navigation` del
backend. La barra inferior deriva sus destinos de ese catálogo; un destino no
autorizado no se añade. Menú y sus grupos usan los iconos compartidos del sidebar.
Las vistas auxiliares usan la URL actual con `mobile=menu|search|notifications|
profile|create` mediante router.push/replace de Inertia. Conservan filtros y
props, no piden datos de todos los módulos y no recargan el documento. Atrás
recupera el estado anterior; no hay listener global que impida salir de la PWA.
Escape cierra las pantallas auxiliares y Radix gestiona las hojas modales.

Plataforma: tamaño por media query, capacidad táctil/puntero, pistas de plataforma,
modo instalado y conexión; no se basa exclusivamente en user-agent. iOS usa una
selección ligera y lectura vertical; Android usa indicador de barra y KPIs en
rejilla de dos columnas. Si no se reconoce la plataforma, hay una presentación
universal funcional. Son componentes web estilizados, no controles nativos.

## Estado de los 32 apartados solicitados

| Apartado | Qué existía / se reutiliza | Qué se implementa | Límites / validación adicional |
| --- | --- | --- | --- |
| 1. Rama | Copia compartida con cambios del menú | Se conserva la rama activa y el trabajo previo | No se cambia la rama de otra tarea |
| 2. Archivos | Shell y módulos existentes | Inventario al final del informe | El diff incluye el arreglo anterior aún pendiente en esta copia local |
| 3. Arquitectura | Laravel/Inertia/React | MobileExperience condicional por breakpoint, sin nuevo backend | No es otra app ni un router paralelo |
| 4. Reutilización | Nav, permisos, favoritos, búsqueda, cuenta, Radix, servicios | Extracción del selector corporativo sin cambiar su DOM desktop | Ningún catálogo alternativo |
| 5. Componentes nuevos | Componentes funcionales existentes | Shell móvil, Inicio, Agenda, formulario de tarea, actualización y viewport | Presentación y adaptadores, no modelos nuevos |
| 6. iOS/Android | Media queries y capacidades del navegador | Variantes de barra y densidad por plataforma con fallback | No probadas en teléfonos físicos |
| 7. Barra inferior | Rutas reales | Inicio, Agenda, Comercial, Operaciones y Menú; estado activo y permisos | Menos destinos si no están autorizados |
| 8. Menú | auth.navigation y FavoritesBar persistido | Pantalla completa, búsqueda, grupos independientes, favoritos reales | No se inventan favoritos; botón estrella reutilizado |
| 9. Inicio | FinancialOverview y dashboard por rol | Tarjetas móviles, fecha, actividad/tareas reales y tabla mensual desplegable | No se inventan métricas ni proyectos; roles sin finanzas mantienen su dashboard autorizado |
| 10. Agenda | OperationsWorkspace y sus fuentes | Hoy/Semana/Próximas, lista cronológica, hora/zona/origen y enlaces | Próximas se limita al rango devuelto, explícito en UI; no hay otro calendario |
| 11. Detalles | Fichas completas existentes | Cabecera con vuelta contextual; rejillas móviles a una columna, pestañas desplazables y acciones táctiles | No había proyectos en la sesión revisada; falta prueba representativa de cada ficha en dispositivo |
| 12. Búsqueda | Endpoint GlobalSearch con permisos | Pantalla integrada, debounce/cancelación existente, filtro por grupo, teclado y errores | Resultados dependen de los registros reales autorizados |
| 13. Notificaciones | Preview personal de cinco no leídas y marcar todas | Pantalla real, estado vacío, lectura masiva existente y acceso al centro de acción desde cuenta | Historial completo, paginación, lectura individual y prioridades pendientes; no se muestran controles ficticios |
| 14. Acciones rápidas | QuickCreate y POST /operations/activities | Hoja de tarea real para manageOperations: título, fecha/hora, prioridad, asignación al usuario actual; otras altas autorizadas | Relaciones avanzadas en Operaciones; sin reintentos ni escrituras offline |
| 15. Perfil | Auth.user, selector corporativo y rutas existentes | Datos reales, empresa/ejercicio, preferencias/idioma, centro de acción, instalación y logout | No se inventa un centro de ayuda nuevo |
| 16. Manifest | commercial-field.webmanifest | Nombre ERP, Inicio como arranque y colores corporativos; id/scope estables | Mantiene identidad instalada; la actualización del icono/arranque en SO requiere validación real |
| 17. Service worker | Worker raíz existente | Activación explícita mediante mensaje; conserva network para navegación | No se registra otro worker |
| 18. Caché | Solo offline e iconos públicos | Continúa whitelist de tres archivos; sin cache de HTML privado/APIs/escrituras | Sin operaciones offline ni sincronización de datos privados |
| 19. Instalación | Iconos any y apple-touch-icon aprobados; guía y beforeinstallprompt | Guía general del ERP y metadatos iOS existentes | Maskable no certificado: pendiente recurso/zona segura; instalación física pendiente |
| 20. Actualizaciones | Registro con updateViaCache none e Inertia asset version | Aviso, actualización explícita, guardia conservadora de formulario abierto, listener con limpieza al desmontar | No recarga automática ni detección supuestamente infalible de dirty state; cerrar formulario primero |
| 21. Seguridad | Sesión y autorización del backend | Historial móvil cifrado con Inertia; logout vacía claves y prefetch; sin tokens en almacenamiento propio | No se afirma auditoría exhaustiva ni validación física logout+atrás |
| 22. Permisos | Catálogo y middlewares existentes | Destinos/acciones derivados del contexto autorizado; no cambia el servidor | Pruebas existentes de permisos y búsqueda ejecutadas |
| 23. Accesibilidad | Radix y controles existentes | Navegación con etiquetas, foco de pantalla, Escape, vuelta, áreas táctiles, movimiento reducido | No hay certificación WCAG ni prueba con VoiceOver/TalkBack físico |
| 24. Responsive | Breakpoint 1024 y safe areas existentes | Barra/página con safe areas sin duplicar padding; visualViewport para teclado y hoja desplazable | Teclado real, rotación y barras Safari pendientes |
| 25. Desktop | Layout y dashboard aprobados | Rama desktop conserva su estructura, sidebar y componentes | Verificado a 1440 px; no se reescriben tablas/gráficos/formularios |
| 26. Tests | Suites backend/frontend existentes | 19 pruebas frontend; 49 backend relevantes / 985 aserciones | Resultados detallados debajo |
| 27. Build | Vite y controles existentes | Build, tipos y lint correctos | Advertencia previa de chunks >500 kB; catálogo tiene 20 faltantes previos ajenos a PWA |
| 28. Dispositivos | Navegador integrado desktop disponible | Pruebas de tamaño y UI real, sin falsear user-agent ni generar datos | Sin Safari iPhone, Chrome Android ni instalación física |
| 29. Git status | Trabajo previo sin ocultar | Se registra el estado final sin staging/commit de PWA | No se publica este corte |
| 30. Git diff | Diferencias revisables | Solo presentación móvil, manifest/worker, contratos y documentación | No descartar cambios del menú anterior |
| 31. Limitaciones | Capacidades conocidas | Se distinguen vacíos consultados y capacidades no disponibles | Roadmap UI-PWA-05/07/08/09 y rendimiento |
| 32. Pendiente | Roadmap unificado | Dependencias, estado y aceptación por carencia | Sin prometer Push universal, datos demo ni Nexo |

## Pruebas ejecutadas

- `npm run test:frontend`: 19 aprobadas. Incluye navegación por permisos,
  selección activa, conservación de filtros y validación de vistas, plataforma
  táctil/fallback, activación explícita del worker y prohibición de cachear datos
  privados/escrituras; también las regresiones del menú anterior.
- `php artisan test --compact --filter='GlobalSearchTest|ActionCenterTest|WorkspaceNavigationAuthorizationTest|FinancialDashboardTest'`:
  22 aprobadas, 687 aserciones.
- `php artisan test --compact --filter='AuthenticationTest|OperationsRedesignTest'`:
  17 aprobadas, 214 aserciones.
- `php artisan test --compact --filter=OperationsAdminTest`:
  10 aprobadas, 84 aserciones. Total dirigido: 49 / 985, sin fallos.
- `npm run lint:check`, `npm run types:check`, `npm run build`: correctos.
- `npm run i18n:check`: quedan 20 textos previos de `field-service.tsx` y
  `quotes/form.tsx` correspondientes a habilidades/plantillas ya existentes.
  Las cadenas nuevas de este corte tienen catálogo ES/EN; no se oculta el fallo
  global ni se cambian pantallas ajenas para hacerlo pasar.
- `git diff --check`: sin errores al cierre.

Navegador real de escritorio a 320/390/1440 px: barra única, cero drawers móviles,
Menú completo, filtro Empresa y despliegue de subsecciones, navegación a Proyectos
con resultado real vacío, Agenda/rango real vacío, retroceso desde pantalla/hoja,
perfil y selector corporativo, avisos vacíos, búsqueda, hoja de tarea con contrato
real y validación requerida. A 320 px, scrollWidth=viewport=320. A 1440 px,
sidebar presente y cero barras móviles. Se revisó también 820 px en el corte del
menú previo; no se presenta eso como prueba adicional completa del nuevo PWA.

Se observó el aviso de nueva versión del worker y su actualización solo tras el
clic explícito; no había formulario abierto. No se creó una tarea de demostración
en la base local para obtener una captura; las escrituras se verifican en las
pruebas aisladas. No se cerró la sesión del usuario durante la inspección visual. El cifrado se
aplica al historial de esta presentación móvil; no reescribe retrospectivamente
entradas sin cifrar de versiones anteriores. La validación logout+atrás debe
incluir esa transición antes de certificar el comportamiento en dispositivos.
Capturas locales: `/tmp/arevantes-pwa-home-mobile.png`,
`/tmp/arevantes-pwa-menu-mobile.png`, `/tmp/arevantes-pwa-task-mobile.png`.

## Capacidades pendientes y fuentes

`resources/js/types/mobile.ts` prepara contratos aditivos para la bandeja completa
sin fingir que existe un endpoint. UI-PWA-05 conserva las dependencias de historial,
paginación, lectura individual y prioridades. UI-PWA-06 quedó resuelto reutilizando
la creación operativa real; no se crea un segundo motor de tareas.

Web Push no existe en este corte: falta suscripción/autorización por dispositivo,
servicio de entrega y revocación. WebKit documenta soporte para apps añadidas a
Inicio y permisos solicitados tras una interacción explícita, no al abrir la app:
https://webkit.org/blog/13878/ y
https://developer.apple.com/documentation/usernotifications/sending-web-push-notifications-in-web-apps-and-browsers.
No se promete entrega garantizada ni compatibilidad universal. Los iconos maskable
necesitan zona segura verificada; se conservan los aprobados con purpose any:
https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Manifest/Reference/icons.

## Inventario del corte

Nuevos: `resources/css/mobile-app.css`,
`resources/js/components/mobile/mobile-experience.tsx`,
`resources/js/components/mobile/mobile-financial-home.tsx`,
`resources/js/components/mobile/mobile-agenda.tsx`,
`resources/js/components/mobile/mobile-task-form.tsx`,
`resources/js/components/mobile/pwa-update.tsx`,
`resources/js/hooks/use-mobile-viewport.ts`,
`resources/js/lib/mobile/navigation.ts`, `resources/js/types/mobile.ts`,
`tests/pwa-navigation.test.mjs` y este informe.

Adaptados: `app-sidebar-layout.tsx`, `app-sidebar.tsx` (selector/iconos compartidos),
`financial-home.tsx`, `operations/agenda.tsx`, `global-search.tsx`, `quick-create.tsx`
(catálogo exportado sin variar desktop), `commercial-field-pwa.tsx`, `types/auth.ts`,
catálogo ES/EN, `app.css`, `app.blade.php`, manifest, worker y offline.html,
pruebas del worker y roadmap. Se conservan los cambios locales heredados en
`app-sidebar-header.tsx`, `nav-main.tsx`, `ui/sidebar.tsx`,
`tests/mobile-navigation.test.mjs` y `docs/mobile-navigation-review.md`.

Commit de PWA: no realizado. Push de PWA: no realizado. Merge de PWA: no realizado.

## Estado y diff finales

```text
 M docs/roadmap.md
 M public/commercial-field-sw.js
 M public/commercial-field.webmanifest
 M public/pwa/offline.html
 M resources/css/app.css
 M resources/js/components/app-sidebar-header.tsx
 M resources/js/components/app-sidebar.tsx
 M resources/js/components/commercial-field-pwa.tsx
 M resources/js/components/dashboard/financial-home.tsx
 M resources/js/components/global-search.tsx
 M resources/js/components/nav-main.tsx
 M resources/js/components/quick-create.tsx
 M resources/js/components/ui/sidebar.tsx
 M resources/js/i18n/catalog.json
 M resources/js/layouts/app/app-sidebar-layout.tsx
 M resources/js/pages/operations/agenda.tsx
 M resources/js/types/auth.ts
 M resources/views/app.blade.php
 M tests/commercial-field-pwa.test.mjs
?? docs/mobile-navigation-review.md
?? docs/pwa-native-review.md
?? resources/css/mobile-app.css
?? resources/js/components/mobile/
?? resources/js/hooks/use-mobile-viewport.ts
?? resources/js/lib/mobile/
?? resources/js/types/mobile.ts
?? tests/mobile-navigation.test.mjs
?? tests/pwa-navigation.test.mjs
```

Resumen rastreado (los archivos nuevos del inventario aún no están en staging):

```text
 docs/roadmap.md                                    |  33 ++++
 public/commercial-field-sw.js                      |   8 +-
 public/commercial-field.webmanifest                |  24 ++-
 public/pwa/offline.html                            |  10 +-
 resources/css/app.css                              |  59 +++++++
 resources/js/components/app-sidebar-header.tsx     |   5 +-
 resources/js/components/app-sidebar.tsx            |  30 ++--
 resources/js/components/commercial-field-pwa.tsx   |   9 +-
 .../js/components/dashboard/financial-home.tsx     |   7 +
 resources/js/components/global-search.tsx          |  31 +++-
 resources/js/components/nav-main.tsx               |   6 +-
 resources/js/components/quick-create.tsx           |   4 +-
 resources/js/components/ui/sidebar.tsx             |  22 ++-
 resources/js/i18n/catalog.json                     | 196 +++++++++++++++++++++
 resources/js/layouts/app/app-sidebar-layout.tsx    |   9 +-
 resources/js/pages/operations/agenda.tsx           |   8 +
 resources/js/types/auth.ts                         |   1 +
 resources/views/app.blade.php                      |   3 +-
 tests/commercial-field-pwa.test.mjs                |  32 +++-
 19 files changed, 436 insertions(+), 61 deletions(-)
```
