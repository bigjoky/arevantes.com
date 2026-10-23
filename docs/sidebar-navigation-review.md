# Revisión manual — sidebar canónico

Rama: `ai/sidebar-navigation-redesign`. Implementación real sobre Laravel 13,
Inertia 3, React 19, TypeScript, Tailwind 4 y Radix. Cambios locales pendientes
sin commit, push, merge, rebase ni PR. Develop no ha sido modificado por esta tarea.
Vista local: http://localhost:8001/dashboard (servidor del worktree).

## Fuente y arquitectura

`app/Support/WorkspaceNavigation.php`, método `definition()`, define Inicio y
los nueve módulos con sus 30 secciones. El registro previo de destinos conserva
los gates existentes, filtrando tanto enlaces principales como secundarios.
`WorkspaceShortcuts` conserva destinos autorizados para favoritos antiguos.
No existe un árbol móvil separado. La navegación secundaria usa exactamente
los datos autorizados del sidebar.

Se mantienen las URLs originales. Se añade únicamente el hub GET
`/workspace/modules/{module}/{section}`, protegido por autenticación y la
presencia de la sección en la navegación autorizada; devuelve 403 para secciones
no autorizadas/inexistentes. No se han modificado roles ni ampliado permisos.
Herramientas auxiliares permanecen accesibles desde Empresa → Configuración.

## Interfaz e interacción

Sidebar de 238 px, verde corporativo sólido, wordmark ligero, iconos lineales,
selección translúcida y chevrons discretos. Persistencia local por ID de usuario;
la ruta activa tiene prioridad sobre módulos guardados cerrados. Coincidencia
por rutas descendientes y parámetros relevantes, conservando filtros y ejercicio.
Pie con empresa y ejercicio reales, preparado para la evolución del selector.

Escritorio utiliza sidebar fijo/colapsable; por debajo de 1024 px se reutiliza
el drawer Radix existente con ancho limitado a 238 px o 85vw. Los enlaces cierran
el drawer. Se mantienen teclado, foco y comportamiento off-canvas existentes.

Contabilidad contiene solo Vista general, Asientos, Plan contable, Informes
contables y Libros oficiales. Todos/borradores/plantillas/periódicos se resuelven
internamente. Diario, Mayor, situación, resultados, sumas/saldos y terceros
siguen disponibles dentro de Informes contables. Los apuntes siguen en su asiento.

## Pendientes y problemas conocidos

`docs/roadmap.md` registra dependencias y aceptación de proyectos independientes,
agenda, importación, informes de patrimonio y flujos, libros oficiales, periodos
fiscales, índice documental, índices dedicados de facturas/cobros y multiempresa.
Los hubs señalan capacidades pendientes sin simular controles ni generar datos.
La carga de asientos mantiene el límite previo; queda planificada su paginación.

Safari autenticado mostró el nuevo árbol, sus nueve grupos y las cinco secciones
contables al desplegar Contabilidad. La inspección detectó un contraste incorrecto
en tema claro y se corrigió mediante color explícito limitado al sidebar.
La comprobación visual posterior de esta corrección y del drawer en móvil/tablet
queda pendiente: el usuario pasó a utilizar Safari durante la verificación.
No se ha alterado su navegación para continuar las pruebas.

## Validación

- Suite PHP: 745 pruebas, 740 correctas, 5 omitidas, 11.367 aserciones, sin fallos.
- Pruebas de navegación: estructura canónica completa, todos los hubs y rutas
  de enlace, permisos, denegaciones y favoritos.
- Node: 11 aserciones de navegación profunda, filtros, permisos y ejercicio.
- TypeScript y ESLint de los archivos modificados: correctos.
- Pint de archivos PHP modificados: correcto.
- Catálogo ES/EN: completo (4.087 strings visibles).
- Compilación Vite: correcta; mantiene aviso de tamaño de bundles superior a 500 KB.
- `git diff --check`: correcto.

La primera suite coincidió con la reconstrucción de assets y encontró archivos
CSS de fuentes temporalmente ausentes y una traducción faltante; se corrigió la
traducción y la repetición completa, tras compilar, pasó sin fallos.

## Archivos y diff antes de este informe

Los ficheros nuevos aparecen como `??` y no entran en `git diff --stat` hasta
ser añadidos al índice. No se han añadido al índice para esta entrega.

```text
 M app/Support/WorkspaceNavigation.php
 M app/Support/WorkspaceShortcuts.php
 M docs/roadmap.md
 M lang/en.json
 M lang/es.json
 M resources/css/app.css
 M resources/js/components/app-sidebar.tsx
 M resources/js/components/favorites-bar.tsx
 M resources/js/components/nav-main.tsx
 M resources/js/components/ui/sidebar.tsx
 M resources/js/hooks/use-mobile.tsx
 M resources/js/i18n/catalog.json
 M resources/js/layouts/app/app-sidebar-layout.tsx
 M resources/js/pages/accounting/index.tsx
 M resources/js/pages/accounting/templates.tsx
 M resources/js/types/auth.ts
 M resources/js/types/navigation.ts
 M routes/web.php
 M tests/Feature/WorkspaceNavigationAuthorizationTest.php
?? app/Http/Controllers/WorkspaceModuleController.php
?? resources/js/components/module-navigation.tsx
?? resources/js/lib/workspace-navigation.ts
?? resources/js/pages/workspace/
?? tests/frontend/
```

```text
 app/Support/WorkspaceNavigation.php                | 123 ++++++++++++++++-
 app/Support/WorkspaceShortcuts.php                 |   3 +-
 docs/roadmap.md                                    |  23 ++++
 lang/en.json                                       |  37 +++++-
 lang/es.json                                       |  38 +++++-
 resources/css/app.css                              |  35 ++++-
 resources/js/components/app-sidebar.tsx            |  73 +++-------
 resources/js/components/favorites-bar.tsx          |   1 +
 resources/js/components/nav-main.tsx               | 147 +++++++++++++--------
 resources/js/components/ui/sidebar.tsx             |   4 +-
 resources/js/hooks/use-mobile.tsx                  |  47 +++----
 resources/js/i18n/catalog.json                     | 118 ++++++++++++++++-
 resources/js/layouts/app/app-sidebar-layout.tsx    |   2 +
 resources/js/pages/accounting/index.tsx            |  34 ++++-
 resources/js/pages/accounting/templates.tsx        |  16 ++-
 resources/js/types/auth.ts                         |   3 +
 resources/js/types/navigation.ts                   |   3 +
 routes/web.php                                     |   2 +
 .../WorkspaceNavigationAuthorizationTest.php       |  76 ++++++++---
 19 files changed, 609 insertions(+), 176 deletions(-)
```

Commit: NO realizado
Push: NO realizado
Merge: NO realizado
