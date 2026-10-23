# Revisión del shell de AREVANTES — 06/10/2026

## 1. Rama utilizada

`ai/sidebar-navigation-redesign`. Se continúa el trabajo local previo del sidebar, sin staging, commit, push, merge, rebase ni PR. El checkout de develop no se ha modificado. Vista local: http://localhost:8001/accounting?view=journal&area=entries&year=2026.

## 2. Arquitectura

Se consolida AppShell/AppSidebarLayout existentes. ShellContext comparte breadcrumbs y ejercicio validado; sidebar y topbar consumen ese contexto. El documento tiene el scroll vertical principal, el sidebar permanece fijo y la topbar sticky. PageShell ofrece full, standard (1600 px), focused (960 px) y master-detail sin imponer un ancho máximo a todas las páginas. Los layouts públicos, auth y portales conservan su tratamiento.

## 3. Componentes

Nuevo ShellContext. Ampliados PageShell, PageHeader y PageActions; integrados AppSidebarLayout, AppSidebarHeader, AppSidebar, NavMain, NavUser, QuickCreate, GlobalSearch y ModuleNavigation. Ajustes puntuales en Sidebar y viewport-fit. La navegación canónica y sus helpers/hubs proceden del trabajo anterior presente en este mismo árbol local.

## 4. Páginas migradas

Inicio financiero y operativo; contabilidad existente (diario/asientos, mayor, saldos, balances, resultados y mapeos); plan contable; plantillas/periódicos; hubs de Vista general, Informes contables y Libros oficiales. Formulario de cliente en modo focused. Otras páginas privadas heredan shell/topbar y mantienen sus contenidos; los headers anteriores reciben breadcrumb de respaldo cuando falta PageHeader. No se rediseñan todas las tablas ni todos los módulos.

## 5. Responsive

Desktop: sidebar fijo de 238 px, topbar de 60 px, búsqueda y acciones visibles. Tablet: drawer común por debajo de 1024 px. Mobile: topbar compacta por debajo de 640 px, búsqueda en diálogo, acciones secundarias en sheet, nuevo asiento fijado abajo y safe areas. Las tablas existentes conservan scroll horizontal local; listas/cards específicas quedan para el rediseño de cada módulo.

## 6. Sidebar

Se mantienen Inicio, los nueve módulos y sus treinta secciones, gates, desplegables y persistencia. Ajuste de integración: Asientos enlaza directamente con su listado real; Periódicos precede a Plantillas en las tabs internas. Empresa/ejercicio permanecen en el pie. No hay empresas ficticias ni selector multiempresa simulado.

## 7. Topbar

Compartida en páginas privadas: búsqueda real, creación rápida autorizada, centro de acción y usuario. Idioma, favoritos y Arevia siguen disponibles en herramientas. No hay una segunda navegación global permanente ni empresa/ejercicio duplicados arriba.

## 8. PageHeader

API reutilizable para breadcrumbs, título, descripción, metadatos, acciones primarias/secundarias y tabs. Breadcrumbs derivan de navegación autorizada con posibilidad de override y conservan ejercicio. Las tabs representan vistas internas, no módulos hermanos. Los hubs usan sus tarjetas reales y no duplican esas tarjetas en tabs.

## 9. Búsqueda real y futuro

Se reutiliza /search sin ampliar su backend: clientes, oportunidades, instalaciones/expedientes, órdenes, ventas, compras y proveedores según permisos. Cmd/Ctrl+K, flechas, Enter, Escape, combobox/listbox, debounce y cancelación de peticiones; diálogo móvil. Presupuestos, productos, asientos, documentos y command palette de acciones quedan en roadmap con dependencias de fuentes y permisos. No se afirma que existan esas búsquedas.

## 10. Rutas

El shell no incorpora endpoints nuevos. El diff acumulado incluye el hub GET /workspace/modules/{module}/{section} de la fase anterior del sidebar, con comprobación de autorización y ejercicio. Se mantienen destinos y formularios existentes; enlaces contables conservan contexto.

## 11. Permisos

Se consumen gates existentes tanto en navegación como búsqueda/creación/acciones. Nuevo asiento sigue requiriendo manageAccounting y ejercicio abierto; formulario y backend originales. Hubs rechazan destinos no autorizados. No se alteran roles, modelos, migraciones ni políticas.

## 12. Validación

- Suite PHP: 740 pruebas correctas, 5 omitidas, 11.367 aserciones.
- Verificación final dirigida de navegación/traducción: 17 pruebas, 508 aserciones.
- Navegación frontend: 11 aserciones correctas.
- TypeScript, ESLint dirigido, Prettier dirigido, Pint, catálogo ES/EN y build correctos. Último build: 12,90 s.
- Sesión autenticada independiente: 1440×900, 834×1112 y 390×844. Verificados drawer, cierre con Escape, búsqueda móvil, Cmd+K desktop, menú usuario/herramientas, acciones secundarias, apertura del formulario de asiento sin enviarlo, sticky durante scroll y ausencia de overflow global.
- Páginas prioritarias visitadas: Inicio, Asientos, Vista general, Plan contable, Informes, Libros oficiales y Periódicos. Ejercicio 2025 conservado entre listado y Periódicos; restaurado 2026 al terminar.
- Consola final sin errores ni warnings. No se enviaron formularios de negocio durante la revisión visual.

## 13. Git status

Árbol local con cambios acumulados del sidebar y shell, sin staging. Antes de este informe: 29 archivos tracked modificados y nuevos archivos del hub, componentes/contexto/helpers, pruebas frontend e informes. No se han modificado .env ni dependencias. El informe y roadmap documentan el resultado manualmente revisable.

## 14. Git diff

Diff tracked acumulado antes del informe: 1.626 inserciones y 527 eliminaciones en 29 archivos; no incluye archivos nuevos. No corresponde exclusivamente al shell, porque conserva el trabajo anterior sin commit. Cambios principales: layout/topbar/header reutilizables, búsqueda responsive, contexto de ejercicio, tokens CSS y migración de páginas prioritarias. Las modificaciones backend de navegación/hubs y sus pruebas corresponden a la fase anterior; en esta fase se ajusta la integración del destino Asientos y orden de tabs.

## 15. Problemas y deuda detectados

Warning de build por chunk superior a 500 KB. Límite existente de carga de asientos (250), tablas móviles con scroll horizontal y adopción progresiva de PageHeader en otros módulos. Importación, informes patrimoniales/flujos y libros oficiales pendientes de capacidades reales; multiempresa pendiente del contexto existente. Algunas traducciones heredadas de menús/plantillas requieren revisión editorial. No se amplía el alcance para resolverlas. Dependencias, estados y aceptación están en docs/roadmap.md.

## 16. Confirmación final

Commit: NO realizado
Push: NO realizado
Merge: NO realizado
