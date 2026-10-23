# Corrección del menú móvil — 23/10/2026

## Entorno y alcance

Copia efectiva: `/Users/joaquinarevalobueno/.codex/worktrees/ffe1/arevantes.com`,
servida en `http://localhost:8000`. Rama creada para este cambio: `ai/mobile-navigation-fix-local`. Al cierre la
rama activa es `feature/technician-skill-checklist`: cambió durante otro trabajo
en la copia compartida; se conserva sin hacer checkout, commit ni integración.
El trabajo empezó con árbol limpio en ab03; al aclarar el usuario la instancia,
se transfirió únicamente el parche de navegación a ffe1 (también limpio al
inspeccionarlo), sin fusiones ni commits. Se retiró el parche de ab03 y se detuvo
el servidor alternativo 8001. Se conserva la instancia 8000 existente.

Archivos de este cambio: `resources/css/app.css`,
`resources/js/components/ui/sidebar.tsx`,
`resources/js/components/app-sidebar-header.tsx`,
`resources/js/components/nav-main.tsx`, `tests/mobile-navigation.test.mjs`,
este informe y `docs/roadmap.md`.

## Diagnóstico y solución

El selector CSS global `[data-sidebar='sidebar']` impone `position: relative`
fuera de las capas Tailwind, anulando el `fixed` del Sheet portallado a body.
El overlay conserva su posición fija: se oscurece la página, mientras el panel
pierde el anclaje al viewport. No era un problema que requiriera elevar z-index.
Además, la rama móvil ocultaba todos los botones directos, incluido el cierre.

La regla específica móvil restaura `position: fixed`, altura 100dvh (fallback
100vh) y ancho min(360px,88vw). Se conserva Radix Dialog/Sheet: portal en body,
modalidad, foco, Escape, interacción exterior y bloqueo de scroll. Se muestra
el cierre existente. El disparador expone aria-expanded/haspopup y conserva
una referencia React para devolver el foco. Las navegaciones Inertia cierran
el menú; cambiar a escritorio elimina el estado móvil abierto.

Encabezado/pie no encogen y el contenido central tiene scroll independiente y
overscroll contenido. Se aplican safe areas arriba/abajo una sola vez, también
al cierre superior; se respeta movimiento reducido. Colores, wordmark existente
e iconos provienen del shell actual. Los ajustes visuales están limitados al
panel móvil. No se cambian rutas, catálogo, dashboards, modelos ni base de datos.

NavMain sigue usando `auth.navigation`: nombres/iconos navegan a las rutas
existentes, chevrons expanden sin navegar; Contabilidad conserva su acceso
explícito. Las preferencias móviles se separan de las de escritorio y expandir
en móvil no cambia el estado colapsado del desktop. Ruta hija activa abre su
grupo; el selector corporativo existente conserva datos y permisos reales.

## Verificación ejecutada

- Navegador integrado de escritorio, tamaños 390×844, 320×568, 820×640 y
  1440×900. No se trata de Safari iOS ni de un iPhone físico.
- Estado cerrado/apertura, panel visible fijo, fondo detrás, marca, módulos
  autorizados, expansión Comercial/Empresa sin cambio de URL, navegación a
  Documentos y a dashboard Finanzas, cierre tras navegar, ruta hija activa.
- Cierre con X, Escape y toque en franja exterior; al finalizar cierre no queda
  diálogo, body overflow vuelve a visible y foco retorna a hamburguesa.
- Selector de empresa abre sobre el menú y permite volver con Escape.
- A 320 px: panel 281,59 px, documento 320 px; scroll central 315 px sobre
  contenido 732 px y alto visible 417 px, scroll de la página de fondo 0.
- A 390 px: panel 343,20 px por 844 px. A 820 px: panel 360 px por 640 px.
- A 1440 px: sidebar desktop presente y ningún panel móvil; no se cambian
  sus estilos ni controles. Sin desbordamiento horizontal observado.
- `npm run test:frontend`: 14 pruebas aprobadas (3 regresiones estructurales
  del modal y 11 existentes). Los tests estructurales no sustituyen interacción.
- `php artisan test --compact --filter=WorkspaceNavigationAuthorizationTest`:
  11 pruebas, 515 aserciones, correctas (permisos y navegación canónica).
- Typecheck y lint correctos; build correcto con aviso existente de chunks
  superiores a 500 kB. Revisión de hooks/foco/aislamiento responsive realizada.

## Límites y estado Git

Pendiente comprobación en Safari de iPhone real, Dynamic Island, barras dinámicas,
orientación horizontal y modo instalado. No se afirma certificación iOS.
La gestión de scroll/foco depende de Radix existente; se ha comprobado en el
navegador disponible, sin reemplazarla por una implementación improvisada.
No hay campos/datos nuevos pendientes: la navegación reutiliza el contrato real.

Al cierre, cambios locales sin commit. Durante el trabajo aparecieron cambios
ajenos en FieldServiceSettingsController, TechnicianSchedule y field-service.tsx;
no se modificaron y ya no aparecen en el diff final. No forman parte de esta
corrección. El estado final contiene cinco archivos modificados y dos nuevos
(informe y pruebas), todos de navegación/documentación. No se publica a producción.

## Publicación autorizada

El usuario autoriza posteriormente publicar esta corrección. Se prepara como
hotfix aislado desde origin/main en hotfix/mobile-navigation, conservando
los demás trabajos locales. La autorización posterior permite commit, push,
PR y fusión para esta publicación. No incluye migraciones ni cambios de negocio.
La validación de iPhone real continúa pendiente después de publicar.
