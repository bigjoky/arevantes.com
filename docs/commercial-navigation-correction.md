# Corrección incremental de navegación Comercial

Rama: `ai/sidebar-navigation-redesign`.

## Archivos de esta corrección

- [app/Support/WorkspaceNavigation.php](/Users/joaquinarevalobueno/.codex/worktrees/ab03/arevantes.com/app/Support/WorkspaceNavigation.php)
- [resources/js/components/nav-main.tsx](/Users/joaquinarevalobueno/.codex/worktrees/ab03/arevantes.com/resources/js/components/nav-main.tsx)
- [resources/js/lib/workspace-navigation.ts](/Users/joaquinarevalobueno/.codex/worktrees/ab03/arevantes.com/resources/js/lib/workspace-navigation.ts)
- [resources/js/types/auth.ts](/Users/joaquinarevalobueno/.codex/worktrees/ab03/arevantes.com/resources/js/types/auth.ts)
- [resources/js/types/navigation.ts](/Users/joaquinarevalobueno/.codex/worktrees/ab03/arevantes.com/resources/js/types/navigation.ts)
- [tests/Feature/WorkspaceNavigationAuthorizationTest.php](/Users/joaquinarevalobueno/.codex/worktrees/ab03/arevantes.com/tests/Feature/WorkspaceNavigationAuthorizationTest.php)
- [tests/frontend/workspace-navigation.mjs](/Users/joaquinarevalobueno/.codex/worktrees/ab03/arevantes.com/tests/frontend/workspace-navigation.mjs)
- [docs/roadmap.md](/Users/joaquinarevalobueno/.codex/worktrees/ab03/arevantes.com/docs/roadmap.md)
- Este informe.

Antes: Comercial era un botón de acordeón con hijo Comercial redundante. Ahora: padre con `href` y capacidad optativa `navigable`, enlace al dashboard y botón hermano para el acordeón. Solo Comercial activa esta capacidad.

Rutas existentes conservadas: `/commercial` dashboard, `/opportunities` CRM, `/people` clientes, `/quotes` presupuestos. No se crean redirecciones.

Validación: 24 tests PHP, 766 aserciones; 16 aserciones frontend; TypeScript, ESLint y build correctos. Build mantiene el aviso previo de tamaño de chunks. Verificados los ocho casos solicitados, escritorio 1440 px, tablet 834 px y móvil 390 px, teclado, estado activo, persistencia tras recargar y objetivo táctil 44 × 44 px.

Comparación SHA-256 respecto al inicio de esta corrección: controllers, componentes CRM/dashboard, servicios comerciales y rutas preservados. No se modifica pipeline, permisos, queries ni dependencias.

`git status` conserva los cambios modificados y sin seguimiento de las fases anteriores, junto con esta corrección; sin staging ni operaciones de entrega. `git diff --check` correcto. El diff global incluye las fases anteriores; esta corrección se limita a configuración, componente, coincidencia de rutas, tipos, pruebas y documentación.

Commit: NO realizado
Push: NO realizado
Merge: NO realizado
