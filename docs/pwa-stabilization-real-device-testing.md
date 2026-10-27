# AREVANTES PWA — Informe de estabilización y pruebas reales

Fecha: 23 de octubre de 2026. Nueva revisión de la copia actual; no sustituye ni repite el informe de implementación inicial.

## Dictamen

La navegación nueva y el sidebar anterior están separados por el layout y no se montan simultáneamente en las comprobaciones realizadas. La versión puede pasar a una ronda de pruebas controladas, pero **no está certificada para desplegar**: hay pérdida silenciosa del borrador de tarea al cerrar la hoja y quedan pruebas de sesión, actualización y teclado en teléfonos reales.

No se han implementado funcionalidades ni corregido código en esta revisión. Solo se añade este documento, se ejecutan pruebas y se generan recursos de compilación ignorados por Git. No se han cambiado puertos, certificados, configuración, permisos, datos locales ni producción; no se han creado tareas de prueba en la sesión del usuario.

## 1. Copia revisada y protección del trabajo

La instancia efectiva es `/Users/joaquinarevalobueno/.codex/worktrees/ffe1/arevantes.com`, `http://localhost:8000`, rama `feature/technician-skill-checklist`, HEAD `457ae23`. El directorio inicial de la conversación es otra copia (`ab03`); no se confunde con la que sirve la aplicación.

Se inspeccionaron `AGENTS.md`, status y diff antes de revisar. Se conservan los archivos modificados y nuevos de la implementación y del hotfix anterior, sin reset, checkout, staging ni sobrescritura. No se ha creado otra instancia. El informe anterior sigue en `docs/pwa-native-review.md` como antecedente, no como evidencia nueva de esta revisión.

## 2. Atribución de cambios

| Origen | Archivos / responsabilidad | Situación actual |
| --- | --- | --- |
| Antiguo menú lateral móvil | `components/ui/sidebar.tsx`, `app-sidebar-header.tsx`, `nav-main.tsx`; reglas `.mobile-navigation-panel` en `app.css`; `tests/mobile-navigation.test.mjs`; `docs/mobile-navigation-review.md` | Correcciones del drawer, foco, posición fija y preferencias independientes. Se conservan, pero ese drawer no es la navegación principal de la nueva PWA. |
| Nueva navegación PWA | `layouts/app/app-sidebar-layout.tsx`, `components/mobile/mobile-experience.tsx`, `lib/mobile/navigation.ts`, `hooks/use-mobile-viewport.ts`, `resources/css/mobile-app.css`, `tests/pwa-navigation.test.mjs` | Selección móvil/escritorio, barra inferior, Menú completo, pantallas auxiliares, historial, hoja de acciones y teclado. |
| Presentaciones móviles nuevas | `mobile-financial-home.tsx`, `mobile-agenda.tsx`, `mobile-task-form.tsx`, ramas en `dashboard/financial-home.tsx` y `pages/operations/agenda.tsx` | Reutilizan contratos y operaciones existentes. |
| Compartidos entre ambas presentaciones | `app-sidebar.tsx` (mapa de iconos exportado y extracción de SidebarCompany), `global-search.tsx`, `quick-create.tsx`, `types/auth.ts`, catálogo i18n | No son exclusivamente móviles. La búsqueda también cambia tratamiento de errores y navegación de resultados por teclado en escritorio. |
| Infraestructura PWA | `pwa-update.tsx`, manifest, service worker, `commercial-field-pwa.tsx`, `app.blade.php`, página offline y tests del worker | Instalación, activación explícita y caché pública. Mantienen identidad y alcance del worker anterior. |
| Documentación / contratos | `docs/roadmap.md`, informe inicial y `types/mobile.ts` | Capacidades pendientes documentadas; no implican endpoints disponibles. |

Los nombres anteriores son relativos a la copia efectiva. `app.css` mezcla la importación de estilos nuevos y las correcciones del drawer anterior: no debe revertirse o trasladarse entero atribuyéndolo a un solo origen. El corte para un futuro despliegue debe revisarse por cambios, incluyendo los archivos aún sin seguimiento.

## 3. Convivencia y límites responsive

`AppSidebarLayout` usa `useIsMobile(1024)`: por debajo de 1024 monta `MobileExperience` y omite `AppSidebar` y `AppSidebarHeader`; desde 1024 monta los componentes de escritorio. El CSS móvil usa `max-width:1023px`. No hay dos umbrales distintos en esta selección.

Comprobación nueva en el navegador con sesión local:

| Tamaño | Resultado observado |
| --- | --- |
| 320 × 568 | Una barra inferior, cero drawers antiguos; ancho del documento y viewport: 320. |
| 390 × 844 | Una barra inferior, cero drawers antiguos; ancho del documento y viewport: 390. |
| 1023 × 900 | Una barra inferior, cero drawers antiguos. |
| 1024 × 900 | Cero barras inferiores y cero drawers móviles. |
| 1440 × 900 | Sidebar y cabecera de escritorio presentes, cero barras inferiores; documento de 1440 px. |

No se han encontrado conflictos de montaje o anchura en estas pruebas. Eso no equivale a garantizar todas las rutas, orientación horizontal o estados de formularios al cruzar el breakpoint. Cambiar el ancho desmonta una presentación y monta otra: debe comprobarse la conservación de borradores durante rotación/multiventana.

## 4. Navegación, retroceso y formularios

Pruebas nuevas realizadas sobre Operaciones, sin registros ficticios:

- Menú abre una pantalla completa con URL `?mobile=menu`; el foco llega al título.
- Empresa se expande y muestra Personal, Documentos y Configuración.
- El retroceso del navegador desde Menú recupera `/operations`.
- La barra inferior abre Agenda en `/operations/agenda`; el retroceso vuelve a Operaciones. Agenda presenta el rango y el vacío autorizado real.
- Crear abre una única hoja modal y enfoca Título. Cerrar con Escape devuelve `/operations`, elimina el diálogo y recupera el foco en Crear.
- Se escribió un título sin enviarlo, se cerró con Escape y se reabrió: **el título estaba vacío y no hubo advertencia de descarte**. No se pulsó Crear tarea ni se modificó la base local.

**Hallazgo E-01 — pérdida de borrador (prioridad alta de UX):** la hoja se desmonta al cerrarse; no hay confirmación ni conservación. Antes del despliegue debe acordarse y corregirse el comportamiento para Escape, cerrar, gesto de retroceso y toque fuera de la hoja. Aceptación: un borrador no desaparece silenciosamente y una petición en curso no puede inducir un segundo envío accidental. No se implementa la solución en este encargo.

**Observación E-02 — foco al retroceder:** tras el retroceso nativo desde Menú se observó el foco en el documento, mientras el cierre modal con Escape sí lo devuelve a Crear. Revisar con VoiceOver/TalkBack y restaurar un punto de navegación útil si se confirma el problema. No confundir cambio de URL correcto con accesibilidad completa.

El formulario usa el POST existente `/operations/activities`, validación de servidor, título requerido y estado processing/offline. Las escrituras se verifican en tests aislados, no enviando datos desde la sesión del usuario. No se ha probado aquí cada formulario del ERP, la desconexión durante un POST ni un 419 por sesión caducada: son casos obligatorios de la ronda real.

## 5. Safe areas y escritorio

La revisión de código confirma `viewport-fit=cover`, `env(safe-area-inset-top/bottom)`, reserva para la barra, altura dinámica y escucha de `visualViewport`. El teclado se detecta con foco editable y reducción de altura superior a 150 px; es una heurística, no una garantía universal. Los tamaños del navegador de escritorio no reproducen el notch, el indicador inferior, las barras Safari ni un teclado real.

Pendiente físico: orientación vertical/horizontal, teclado abierto/cerrado, selector de fecha, zoom de texto, última fila y botón de envío visibles, y retorno de la barra al cerrar teclado. También iPad/multiventana alrededor de 1024 px.

En escritorio se conservan sidebar, cabecera y estructura de Operaciones en la comprobación a 1440 px. No hay regresión visible en esa muestra. **No se afirma que el escritorio sea idéntico en todos sus comportamientos**: existen modificaciones compartidas de búsqueda, selector corporativo e iconos. Requieren un recorrido con búsqueda con resultados y errores, selector de empresa/ejercicio y formularios representativos antes de liberar.

## 6. Caché, sesión y actualización

| Área | Evidencia de esta revisión | Riesgo / aceptación pendiente |
| --- | --- | --- |
| Service worker | Solo cachea aviso offline e iconos; navegación autenticada va a red; no intercepta API, tráfico externo o escrituras. Tests correctos. | Comprobar almacenamiento real y transición desde la versión instalada anterior. Esta whitelist no cubre por sí sola caché HTTP, historial o proxies. |
| Actualización | No hay skipWaiting automático en instalación. Activación por mensaje tras pulsar Actualizar. Se bloquea si hay campos de formulario abiertos. | El detector solo cubre elementos dentro de form, no cualquier estado sin guardar. Una ventana puede activar el worker compartido mientras otra contiene un borrador. Probar dos pestañas y PWA instalada; evitar pérdida de estado y mezcla incompatible de versiones. |
| Recarga | Solo la instancia que solicita actualizar tiene activada su bandera de recarga; listener se elimina al desmontar. | No se ha provocado una actualización nueva en este corte, ni se certifica coordinación entre ventanas. Requiere dos builds en el entorno de pruebas aprobado. |
| Sesión | UI móvil solicita historial cifrado de Inertia; logout vacía prefetch y claves. Middleware y permisos existentes; suite AuthenticationTest aprobada. | No se cierra la sesión actual del usuario para probar. Debe verificarse logout + Atrás + reapertura de instalada, caducidad, cambio de usuario y empresa; el cifrado nuevo no transforma entradas antiguas del historial. |
| Cabeceras | AddSecurityHeaders fija no-store explícito para commercial-field, login y logout; no lo fija explícitamente para todas las rutas nuevas del ERP. | Verificar respuestas HTML/Inertia autenticadas, documentos y CDN en staging. No se ha demostrado una filtración; tampoco se certifica no-store global con esta inspección. |
| Identidad | Se conserva id `/commercial-field` y scope `/`, cambia start_url al dashboard. | Probar actualización sobre instalación antigua y nueva instalación, sin duplicar apps ni perder acceso. |

No se necesita acceso a producción para estas pruebas. No hay soporte de escrituras offline: su ausencia debe mantenerse explícita y no convertirse en reintentos silenciosos.

## 7. Pruebas ejecutadas de nuevo

| Comando | Resultado de esta revisión |
| --- | --- |
| `npm run test:frontend` | 19 aprobadas, cero fallos. Incluye funciones de navegación, permisos, plataforma y worker; no son pruebas end-to-end completas. |
| `npm run types:check` | Correcto. |
| `npm run lint:check` | Correcto. |
| `php artisan test --compact --filter='GlobalSearchTest\|ActionCenterTest\|WorkspaceNavigationAuthorizationTest\|FinancialDashboardTest\|AuthenticationTest\|OperationsRedesignTest\|OperationsAdminTest'` | 49 aprobadas, 985 aserciones. SQLite en memoria y sesión array según phpunit.xml; no base de producción. |
| `npm run build` | Correcto, 17,11 s. Aviso de chunks de más de 500 kB. |
| `npm run i18n:check` | Falla: 20 textos ausentes en field-service y quotes/form, ajenos a las nuevas pantallas PWA pero presentes en la copia candidata. |
| `git diff --check` | Sin errores. |

Capturas de esta revisión: `pwa-stabilization-mobile.png` y `pwa-stabilization-desktop.png` junto a este documento. Son navegador de escritorio con viewport ajustado, **no fotografías ni pruebas de iPhone/Android reales**. No se ha repetido la suite completa de todo el ERP; el alcance dirigido se declara arriba.

## 8. Por qué el teléfono no puede abrir ahora localhost

En un teléfono, `localhost` es el propio teléfono. El servidor actual escucha en `[::1]:8000`, solo en el Mac. Además `public/hot` apunta a `http://localhost:5173`: aunque se abriera el puerto 8000 a la red, el teléfono intentaría obtener JavaScript de su propio puerto 5173.

La IP Wi-Fi detectada del Mac en esta revisión es `192.168.1.21` (puede cambiar). **`http://192.168.1.21:8000` no es accesible con la configuración actual y no debe presentarse como versión lista para pruebas.** No se ha ampliado la escucha ni abierto el firewall.

Para probar instalación, service worker y actualizaciones en ambos teléfonos debe utilizarse HTTPS válido y confiable. La excepción HTTP de localhost en el Mac no convierte una IP LAN HTTP en contexto seguro. Véase [MDN: uso de service workers](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API/Using_Service_Workers).

## 9. Preparación recomendada, solo después de aprobación

La opción recomendada para una ronda reproducible es **staging restringido con HTTPS válido, datos de prueba y recursos compilados**, separado de producción. Permite validar ambos sistemas sin instalar certificados raíz en teléfonos ni exponer el servidor de desarrollo del Mac. No se crea ni se publica ahora; no existe una URL de esta versión que pueda facilitar como ya disponible.

Pasos concretos que se ejecutarían tras aprobación:

1. Guardar un inventario del diff completo y de archivos nuevos de esta copia, conservando los cambios de otras tareas. Definir y revisar el corte candidato; no desplegar solo HEAD, porque no contiene la PWA local.
2. Preparar un entorno de pruebas separado en el alojamiento existente. Asignarle un hostname de pruebas propio y certificado TLS válido. El nombre exacto se elegirá al prepararlo; no usar el dominio productivo ni compartir cookies entre entornos.
3. Configurar base y almacenamiento aislados con datos sintéticos, APP_KEY exclusiva, APP_DEBUG=false, URL HTTPS y cookie de sesión propia, Secure y HttpOnly. Desactivar entregas reales de correo, pagos, integraciones y trabajos externos de prueba. No copiar secretos o datos productivos.
4. Restringir el acceso al equipo de pruebas mediante VPN o control de acceso que permita abrir HTML, manifest, iconos y worker desde los mismos teléfonos. Comprobar que el control no redirige esos recursos a una pantalla de acceso y rompe la instalación.
5. Compilar el corte (`npm ci`, `npm run build`) y servir los assets estáticos; sin `public/hot`, Vite ni URLs localhost en HTML. Aplicar únicamente la preparación normal del entorno aislado, sin ejecutar acciones sobre producción.
6. Verificar desde el Mac `/up`, login, certificado, manifest, iconos y worker con HTTP 200 y tipos de contenido correctos; después repetir desde cada teléfono. Comprobar las cabeceras de sesión y caché autenticada.
7. Entregar una URL HTTPS efectiva y cuentas de prueba separadas por rol. Registrar versión del corte, modelo del teléfono, SO y navegador. Mantener dos builds identificables para la prueba controlada de actualización.

**Alternativa sin staging:** HTTPS en la red privada mediante un proxy TLS delante de esta única instancia del Mac, con certificado confiable para un hostname que ambos teléfonos resuelvan a su IP LAN. Se deben servir assets compilados y retirar temporalmente el hot file con copia de seguridad; el proxy puede alcanzar localhost:8000 sin abrir PHP o Vite a toda la red. Exige preparar DNS, certificado y restauración del modo desarrollo. No se ha configurado. No se recomienda saltarse avisos TLS, desactivar controles del navegador ni abrir un túnel público sin control de acceso.

**HTTP por LAN solo para diagnóstico visual:** requeriría detener la instancia única, arrancar PHP ligado a la IP LAN en el mismo puerto 8000, usar recursos compilados sin hot file y permitir acceso limitado en una Wi-Fi de confianza. Luego se abriría `http://IP-DEL-MAC:8000` en ambos teléfonos y al terminar se restauraría `./scripts/dev-local.sh`. No es una prueba válida de la PWA completa y puede impedir el cifrado del historial por falta de contexto seguro. No usar credenciales o datos reales sobre ese HTTP. No se ha realizado esta reconfiguración.

## 10. Cómo abrirla en iPhone y Android cuando exista el acceso aprobado

### iPhone

1. Conectar a la red/VPN de pruebas y abrir la URL HTTPS efectiva en Safari. Si se usa la alternativa LAN, estar en la misma Wi-Fi que el Mac y mantenerlo despierto.
2. Verificar certificado válido y acceso; iniciar sesión con la cuenta de pruebas, no reutilizar la sesión del Mac.
3. Probar primero en Safari normal. Después Compartir → Añadir a pantalla de inicio; activar Abrir como app web si la versión de iOS lo ofrece y pulsar Añadir.
4. Abrir el icono nuevo y repetir login si lo solicita. Confirmar barra inferior, Menú, teclado y safe areas en modo instalado. Instalar desde el origen de staging, no desde arevantes.com.

Los nombres del menú varían con la versión de iOS. [Instrucciones oficiales de Apple](https://support.apple.com/en-gb/guide/iphone/iphea86e5236/ios).

### Android

1. Conectar a la red/VPN de pruebas y abrir la misma URL HTTPS en Chrome, con cuenta de pruebas propia.
2. Probar primero la pestaña normal. En el menú ⋮ elegir Instalar aplicación o Añadir a pantalla de inicio → Instalar, según versión; seguir el diálogo.
3. Abrirla desde el icono y comprobar que funciona como aplicación instalada. Un simple acceso directo que abre otra pestaña no certifica el modo standalone.
4. Repetir pruebas con el gesto/botón Atrás de Android, teclado y reentrada desde segundo plano.

[Instrucciones oficiales de Google](https://support.google.com/chrome/answer/9658361?co=GENIE.Platform%3DAndroid&hl=es).

## 11. Hoja de aceptación en dispositivos reales

Registrar cada caso por separado en Safari normal, iPhone instalada, Chrome normal y Android instalada; resultados pendientes, no aprobados por simulación.

| Caso | Procedimiento y resultado esperado |
| --- | --- |
| Inicio y navegación | Abrir los cinco destinos autorizados; entrar a una ficha; Menú y subsecciones navegables sin drawer superpuesto. Rol restringido no ve acciones ajenas y servidor las rechaza. |
| Atrás | Menú → atrás; ficha → atrás; búsqueda → resultado → atrás; gesto iOS y Android. URL, filtros, scroll y foco coherentes, sin bucles. |
| Formulario | Escribir, validar vacío, fecha, guardar una tarea sintética y comprobar una sola alta. Probar cerrar y atrás con borrador, envío lento y doble toque. E-01 debe estar resuelto. |
| Teclado / safe areas | Abrir teclado al final de la hoja, selector de fecha, rotar, ampliar texto; campos, botón final y barra accesibles, sin tapar contenido. |
| Sesión | Logout y Atrás, cerrar/reabrir instalada, caducar sesión en formulario, cambiar usuario/empresa. No revelar datos anteriores ni reenviar automáticamente tras 419. |
| Offline | Perder red antes y durante lectura/escritura. Aviso público en navegación sin datos privados guardados; no falsas confirmaciones ni cola de escrituras. |
| Actualización | Build A abierta con borrador; preparar B en staging autorizado. No recarga automática. Cerrar/guardar, actualizar expresamente, comprobar B. Repetir con dos ventanas, una con borrador. |
| Instalación anterior | Actualizar una instalación previa de commercial-field y comparar con instalación limpia. Identidad y arranque correctos, sin duplicados. |
| Escritorio | Recorrer dashboard, búsqueda, empresa/ejercicio y formulario a 1440 px; alternar alrededor de 1024 y comprobar borradores. |

Las actualizaciones de staging también requieren autorización: este informe no inicia ningún despliegue para ejecutar esos casos.

## 12. Qué resolver antes de desplegar

| Prioridad | Problema o puerta de validación | Criterio de cierre |
| --- | --- | --- |
| Alta | E-01: borrador descartado silenciosamente | Corrección acordada y pruebas de todos los cierres, sin duplicar envíos. |
| Alta | Sesión e historial sin certificación física | Logout/atrás, usuario distinto, expiración y transición desde historial antiguo aprobados. |
| Alta | Actualización entre ventanas / formularios | Dos builds, dos ventanas y estado sin guardar probados; corregir si hay pérdida o incompatibilidad. |
| Alta | Teclado, safe areas y rotación aún no probados en teléfonos | Hoja de aceptación aprobada en iPhone y Android instalados. |
| Media | E-02: foco tras retroceso de Menú | Revisión con lector de pantalla y restauración coherente. |
| Media | 20 cadenas i18n faltantes en la copia candidata | Resolver antes de declarar verde el control global o aprobar explícitamente su tratamiento en el corte. |
| Media | Política de caché HTTP de todo el ERP no certificada | Inspección de HTML/Inertia, documentos y capa CDN del staging; corregir cualquier almacenamiento privado no deseado. |
| Media | Regresión desktop compartida / rendimiento | Recorrido representativo y medición en teléfono/red real. Aviso de chunks no equivale por sí solo a fallo. |

La bandeja completa de notificaciones, Web Push y los iconos maskable certificados siguen siendo limitaciones de capacidad ya conocidas, no regresiones nuevas de estabilización. No se implementan aquí ni se presentan como disponibles.

**Estado final:** revisión y preparación documental completadas; autorización de despliegue pendiente. No se ha modificado funcionalidad. Commit: NO realizado. Push: NO realizado. Merge: NO realizado. Despliegue: NO realizado.
