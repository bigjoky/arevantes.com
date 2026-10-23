# Corrección UX de Comercial → Clientes

## 1. Rama actual

`ai/sidebar-navigation-redesign`. Se conserva la rama y todo el trabajo sin commit de las fases previas.

## 2. Archivos de esta corrección

- `resources/js/pages/people/show.tsx`: compone únicamente la ficha.
- `resources/js/components/customers/customer-detail.tsx`: única implementación reutilizada; breadcrumb, regreso, cabecera y campos reales distribuidos.
- `resources/css/app.css`: elimina reglas de split pane y añade grid local responsivo.
- `resources/js/i18n/catalog.json`: tres entradas bilingües aditivas.
- `docs/roadmap.md`: actualiza el patrón canónico y su verificación.
- `docs/customers-full-page-correction.md`: entrega y evidencia de alcance.

## 3. Antes

La URL propia del cliente renderizaba CustomerList y CustomerDetail a la vez en
escritorio, dividiendo el ancho y comprimiendo ambos. En tablet/móvil se ocultaba
el listado mediante CSS.

## 4. Cambio estructural

La página show renderiza únicamente CustomerDetail dentro de PageShell. El
listado conserva su página completa. No existe split pane, drawer ni modal de
cliente. Se retiran las reglas desktop que comprimían tabla, filtros y fuente.
El resumen tiene dos columnas adaptables; identificación/dirección y métricas
se distribuyen internamente. Las relaciones ocupan todo el ancho en su pestaña.
No se añaden campos contractuales ni datos ficticios.

## 5. Routing final

Se reutiliza la equivalencia real existente:

- `/people`: listado de Clientes.
- `/people/{person}`: ficha propia del cliente.
- `/people/{person}?tab=quotes` y equivalentes: pestaña con URL propia.

No se renombra routing ni se rompen deep links.

## 6. Contexto de regreso

CustomerUrl transmite los filtros existentes en query params a la ficha y a sus
pestañas. Volver a clientes y breadcrumb recuperan esa misma búsqueda, filtros,
orden, dirección, página y tamaño de página. Verificado en navegador con
`search=Manuel`, `sort=name`, `direction=asc`, `per_page=30`; el test existente
comprueba también `page=3`. Refresh y back/forward funcionan con URL real.

## 7. Componentes reutilizados

CustomerDetail, RelationPanel, CustomerCrmDetails (formularios de contacto),
PageShell, Breadcrumbs, Button y Link de Inertia. CustomerList permanece sin
modificar. Se utiliza la convención shell-page-header para ocultar el breadcrumb
legacy redundante, sin cambiar el AppShell.

## 8. Una sola ficha

No se duplica CustomerDetail. No existe CustomerFullPage paralelo ni nueva lógica
de selección. Se mantienen tabs, permisos, acciones, contactos y carga existente.

## 9. Responsive

Desktop 1536×1024: listado completo o ficha completa, nunca simultáneos. Pestañas
verificadas a 1246 px (ancho interior del contenido, tras padding del shell).
Tablet 834×1112: ficha independiente, resumen en dos columnas adaptadas.
Móvil 390×844: una columna, back, acciones y tabs desplazables. El ancho de
scroll del documento es 390 px. Listado móvil y retorno conservan contexto.
Scroll natural de contenido; no se introducen scrolls por panel o por card.
Se conserva la preferencia actual de tema y la tipografía del sistema.

## 10. Validaciones

- `php artisan test --compact tests/Feature/CustomersRedesignTest.php tests/Feature/CustomerProfileTest.php`: 19 tests, 328 aserciones, todos pasan; permisos y relaciones cubiertos.
- `node tests/frontend/customer-navigation.mjs`: 15 aserciones, pasan.
- `npm run types:check`: pasa.
- ESLint específico de show y CustomerDetail: pasa.
- Prettier de los dos TSX y catálogo: pasa.
- `npm run i18n:check`: completo, 4236 textos visibles y 4725 entradas.
- `npm run build`: correcto, 13,72 s. Permanece el aviso previo de chunks >500 kB.
- `git diff --check`: pasa.
- Navegador: listado, selección sin listado simultáneo, breadcrumb, regreso,
  deep link, refresh, atrás/adelante, siete pestañas, editar y crear presupuesto
  en sus formularios originales (sin enviar formularios ni modificar registros),
  desktop/tablet/mobile. Captura: `/tmp/arevantes-clientes-full-page.png`.

## 11. Git status

Estado local acumulado, con cambios anteriores conservados; no staging ni
operaciones de entrega. Los paths adicionales de este estado pertenecen a fases
previas y no a esta corrección.

```text
 M app/Http/Controllers/OpportunityController.php
 M app/Http/Controllers/PersonController.php
 M app/Http/Controllers/SavedCrmFilterController.php
 M app/Services/Accounting/FinancialDashboardSummary.php
 M app/Services/Analytics/AnalyticsService.php
 M app/Services/Analytics/CommercialConversionAnalytics.php
 M app/Services/Crm/OpportunitySalesCycle.php
 M app/Services/Customers/CustomerProfile.php
 M app/Support/Crm/OpportunityCatalog.php
 M app/Support/WorkspaceNavigation.php
 M app/Support/WorkspaceShortcuts.php
 M docs/roadmap.md
 M lang/en.json
 M lang/es.json
 M resources/css/app.css
 M resources/js/components/app-sidebar-header.tsx
 M resources/js/components/app-sidebar.tsx
 M resources/js/components/dashboard/financial-home.tsx
 M resources/js/components/favorites-bar.tsx
 M resources/js/components/global-search.tsx
 M resources/js/components/nav-main.tsx
 M resources/js/components/nav-user.tsx
 M resources/js/components/page-shell.tsx
 M resources/js/components/quick-create.tsx
 M resources/js/components/ui/sidebar.tsx
 M resources/js/hooks/use-mobile.tsx
 M resources/js/i18n/catalog.json
 M resources/js/layouts/app/app-sidebar-layout.tsx
 M resources/js/pages/accounting/accounts.tsx
 M resources/js/pages/accounting/index.tsx
 M resources/js/pages/accounting/templates.tsx
 M resources/js/pages/dashboard.tsx
 M resources/js/pages/opportunities/index.tsx
 M resources/js/pages/opportunities/show.tsx
 M resources/js/pages/people/create.tsx
 M resources/js/pages/people/index.tsx
 M resources/js/pages/people/show.tsx
 M resources/js/types/auth.ts
 M resources/js/types/financial-dashboard.ts
 M resources/js/types/navigation.ts
 M resources/views/app.blade.php
 M routes/web.php
 M tests/Feature/CustomerProfileTest.php
 M tests/Feature/FinancialDashboardTest.php
 M tests/Feature/WorkspaceNavigationAuthorizationTest.php
?? app/Http/Controllers/CommercialDashboardController.php
?? app/Http/Controllers/WorkspaceModuleController.php
?? app/Services/Commercial/
?? app/Services/Customers/CustomerWorkspace.php
?? docs/app-shell-review.md
?? docs/commercial-dashboard-review.md
?? docs/commercial-navigation-correction.md
?? docs/crm-redesign-review.md
?? docs/customers-redesign-review.md
?? docs/home-dashboard-review.md
?? docs/sidebar-navigation-review.md
?? resources/js/components/crm/
?? resources/js/components/customers/
?? resources/js/components/dashboard/commercial-activity.tsx
?? resources/js/components/dashboard/commercial-evolution.tsx
?? resources/js/components/dashboard/home-heading.tsx
?? resources/js/components/module-navigation.tsx
?? resources/js/components/shell-context.tsx
?? resources/js/lib/workspace-navigation.ts
?? resources/js/pages/commercial/
?? resources/js/pages/people/legacy-index.tsx
?? resources/js/pages/people/legacy-show.tsx
?? resources/js/pages/workspace/
?? resources/js/types/commercial-dashboard.ts
?? tests/Feature/CommercialDashboardTest.php
?? tests/Feature/CrmRedesignTest.php
?? tests/Feature/CustomersRedesignTest.php
?? tests/frontend/
```

## 12. Resumen del diff incremental

Se retira CustomerList de show; se reutiliza el detalle con cabecera completa y
breadcrumb Comercial > Clientes > Cliente. CSS retira el split pane y amplía
la composición de los datos ya existentes. Catálogo aditivo (NIF/CIF, Municipio,
País); documentación actualizada. Comparación por hashes contra inicio de esta
fase: backend, controladores, queries, servicios, modelos, rutas, CustomerList,
CRM, Presupuestos, Sidebar, AppShell, dependencias y formularios preservados.
No hay formato masivo ni cambios de datos. El diff Git global incluye trabajo
sin commit anterior; la comparación incremental aísla estos seis archivos.

Commit: NO realizado
Push: NO realizado
Merge: NO realizado
