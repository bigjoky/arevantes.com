# AREVANTES ERP — Informe de estabilización Comercial Móvil 01B

25/10/2026 · Desarrollo local · Sin publicación.

## 1. Dictamen técnico

**NO APTO para preparar publicación todavía.** El código comercial puede aislarse y el corte aislado supera sus comprobaciones, pero se ha demostrado pérdida de borrador al cruzar el breakpoint global de 1023 a 1024 px. Este defecto ya existe en la base de integración; requiere resolver el ciclo de vida del layout antes de publicar una entrega que prometa conservar formularios móviles.

Esta fase no añade funcionalidades comerciales. Corrige la convivencia del guard comercial con las pantallas superpuestas de la PWA, verifica el historial y prepara un parche reproducible de 19 archivos. Se mantiene la rama y se conserva el resto del checkout.

## 2. Correcciones realizadas

**R-01 — Regreso desde Menú sobre un borrador comercial. Corregida.**

Reproducción anterior: abrir un cliente nuevo, escribir un nombre, abrir Menú y pulsar Volver. El guard comercial interpretaba el cierre de Menú como abandono del formulario y pedía descartar sus campos.

El guard recuerda en memoria la entrada Inertia subyacente. Compara el payload existente del historial, incluso cuando está cifrado como ArrayBuffer, sin descifrarlo ni introducir campos del formulario en almacenamiento. Si el destino es exactamente esa entrada y se está cerrando una pantalla PWA distinta de la hoja de tareas, realiza una sustitución cliente con `preserveState` y `preserveScroll`. Conserva el formulario montado. Una entrada distinta sigue requiriendo confirmación.

La hoja `mobile=create` conserva la responsabilidad de su propio guard; el comercial no intercepta su confirmación de salida ni su navegación GET. La desconexión móvil/deshabilitación del formulario cancela una confirmación comercial pendiente. Una entrada de historial inesperada/cíclica cae de forma segura al comportamiento de descarte, sin lanzar una excepción de comparación.

Cambios de código 01B limitados a:

- `resources/js/hooks/use-commercial-form-protection.tsx`.
- `resources/js/lib/mobile/commercial-history.ts`.
- `tests/commercial-mobile.test.mjs`.

No se han modificado `app.tsx`, MobileExperience, el layout, el guard de tareas, rutas, negocio, PDF, manifest ni worker durante 01B. La llamada de registro temprano en `app.tsx` procede de 01 y se ha auditado.

## 3. Resultado de las pruebas

Se distinguen la copia de trabajo mezclada y el corte aislado sobre una base fija. Los números corresponden a las últimas ejecuciones indicadas; la copia mezclada siguió recibiendo cambios de otras tareas.

| Control | Checkout de trabajo | Corte aislado |
| --- | --- | --- |
| Frontend `npm run test:frontend` | 49 correctas, 0 fallos | 45 correctas, 0 fallos |
| Backend comercial, roles, tareas y PDF | 90 correctas / 1.633 aserciones | 90 correctas / 1.633 aserciones |
| `npm run types:check` | Correcto | Correcto |
| `npm run lint:check` | Correcto en última ejecución | Correcto |
| `npm run build` | Correcto; avisos de tamaño de chunks/plugins | Correcto; mismos tipos de aviso |
| `npm run i18n:check` | 79 pendientes | Las mismas 79 ya existen en la base fija sin parche |
| `git diff --check` | Correcto | Parche aplicable y hashes de los 19 archivos verificados |

La diferencia 49/45 pruebas frontend procede de pruebas de otras tareas que no se incluyeron en el corte; no se han omitido pruebas comerciales para obtener el resultado aislado.

Comando backend:

```sh
php artisan test --compact --filter='OpportunityTest|CustomersRedesignTest|CustomerProfileTest|CustomerCrmEvolutionTest|QuotesRedesignTest|QuoteCompositionTest|QuoteOptionSelectionTest|QuoteWorkflowSecurityTest|WorkspaceNavigationAuthorizationTest|MobileTaskSubmissionTest|QuotePdfTest'
```

La copia aislada se obtuvo de `git archive`, aplicando el parche, copiando `vendor` físicamente y reutilizando las dependencias Node instaladas. `composer.json`, `composer.lock`, `package.json` y `package-lock.json` coincidían con la base fija. No se copió `.env`. Las pruebas usaron SQLite `:memory:`, caché/sesión/correo de pruebas y cola síncrona; no se ejecutaron migraciones ni seeders contra bases existentes.

La primera ejecución backend aislada se inició antes de terminar el build y produjo 39 fallos por ausencia de `public/build/manifest.json`. Tras completar el build se repitió: 90/90 correctas. Fue una dependencia de preparación del entorno, sin corrección de código ni ocultación de fallos.

Las cinco funciones de cálculo `roundMoney`, `lineAmounts`, `calculate`, `lineTotal` y `componentCost` son idénticas a las de la base fija al compararlas con el impresor AST de TypeScript. También se ejecutan casos de IVA, descuento, opciones no seleccionadas, totales con IVA y redondeo. Las pruebas backend cubren composición, versiones/estados, seguridad de workflow y PDF; no se editan sus plantillas.

Pruebas nuevas/reforzadas 01B: equivalencia de entradas cifradas clonadas, rechazo de entradas diferentes/incompletas/cíclicas y retirada idempotente de veinte guards consecutivos, comprobando después que no interceptan al router. Se conserva la prueba de registro único antes de Inertia y delegación cuando no hay guard activo.

## 4. Regresiones detectadas

| Hallazgo | Origen | Estado / aceptación |
| --- | --- | --- |
| R-01: cerrar Menú exige descartar el borrador comercial | Guard introducido en 01 | Corregido en 01B; volver conserva campos y foco, salir del formulario sigue pidiendo confirmación |
| R-02: cruzar 1023→1024 desmonta el formulario y pierde datos | Layout global preexistente, confirmado también en la base fija | **Bloqueo pendiente**; conservar la misma instancia de página al cambiar de shell, sin almacenar datos sensibles ni reconstruir formularios |
| 79 traducciones pendientes | Ya presentes en `e426a7a`, antes del corte | Seguimiento preexistente; conjunto exactamente igual con y sin parche |
| Error ESLint inicial en `tests/business-time.test.mjs` y errores intermedios en archivos de temporalidad/Operaciones | Otros cambios concurrentes | Última ejecución global correcta; no se editaron esos archivos desde 01B |

R-02 se reprodujo expresamente a 1023 y 1024 px: un presupuesto nuevo con precio 100 mostraba subtotal 100, IVA 21 y total 121 antes del cambio; después, los campos volvían a sus valores iniciales y el total era 0. La misma estructura condicional `isMobile ? <MobileExperience>{children}</MobileExperience> : …{children}` aparece en `app-sidebar-layout.tsx` de la base fija. No se atribuye a las correcciones del guard.

No se ha ampliado esta revisión para rehacer ese layout global compartido, suscrito además a otras modificaciones locales. Debe resolverse de forma controlada y probar también el resto de páginas antes de aprobar publicación.

## 5. Estado del historial Inertia

- Registro temprano único, anterior a `createInertiaApp`; sin formulario registrado no detiene el router.
- Registro por efecto solo con formulario comercial móvil habilitado. Retirada en desmontaje o cambio a escritorio; la prueba verifica que callbacks de instancias anteriores no quedan activos.
- Sin interceptación comercial de POST/PUT/PATCH. Los guardados y sus redirecciones se verifican por las pruebas de negocio; el guard no añade una petición GET al guardar.
- Estado/errores se conservan tras fallo; el reinicio ocurre por descarte confirmado o éxito, reutilizando `TaskDraftGuard` e Inertia.
- Dos intentos consecutivos de Atrás, continuar y finalmente descartar terminaron en el listado sin bucle.
- Retorno de Menú sobre cliente modificado: sin confirmación de descarte, nombre conservado y foco en el botón Menú.
- Presupuesto con dos líneas (100 y 50; descuento 10% en la segunda): tras Menú→Volver mantuvo subtotal 150, descuento 5, IVA 30,45 y total 175,45. No se guardó.
- Los helpers mantienen filtro/búsqueda/página/cliente y eliminan comandos de editor/pantalla superpuesta; solo producen rutas internas conocidas.
- `pushState` se utiliza únicamente para restituir la entrada existente mientras se confirma una salida real. No se añaden valores de formularios ni nuevas claves de datos sensibles al historial.

Límite: la recuperación tras cierre forzado del sistema operativo no puede garantizarse mediante memoria y `beforeunload`. No se ha creado persistencia de borradores. Los gestos de navegación física y la matriz completa Atrás/Adelante del navegador requieren validación en dispositivos reales.

## 6. Compatibilidad con la PWA existente

El helper `navigation.ts` y `task-draft-guard.ts` de la copia coinciden byte a byte con la base fija utilizada. Se han reutilizado sus contratos, sin copiar al parche modificaciones globales locales no publicadas.

Se comprobó la hoja global de tareas desde el listado de clientes: escribir título, retroceder, mostrar el mensaje específico de tarea, continuar y descartar. Usó su propia confirmación; no apareció la confirmación comercial. No se creó ninguna tarea.

No se ha tocado el service worker, el manifest, la identidad instalada ni las integraciones. Tampoco se ha certificado una actualización de la PWA instalada o un despliegue productivo en esta revisión.

## 7. Compatibilidad visual y con escritorio

Matriz realizada en navegador con sesión local, **no teléfonos físicos**:

- Anchos: 320, 390, 412, 768, 1023, 1024 y 1440 px, altura 900 px.
- Ocho rutas por ancho: CRM, Clientes, Presupuestos, cliente nuevo, presupuesto nuevo, ficha CRM, ficha cliente y ficha presupuesto.
- Resultado: **56/56 sin desbordamiento horizontal del documento**, con encabezado presente. Esto no certifica por sí solo contraste, lector de pantalla o toda interacción interna.
- Escritorio conserva tablas/Kanban; móvil muestra tarjetas/edición vertical. No hay cambio visual global añadido en 01B.
- Se verificaron foco de confirmación, «Seguir editando», regreso desde Menú y errores/estados mediante pruebas existentes y auditoría de componentes. La validación de VoiceOver/TalkBack y teclado virtual queda pendiente.
- El cambio de presentación sí presenta R-02, aunque cada ancho por separado sea correcto.

Evidencia: [matriz JSON](commercial-mobile-01b/viewport-matrix.json), capturas `viewport-320.png` a `viewport-1440.png`, [regreso de Menú](commercial-mobile-01b/menu-return-keeps-draft.png), [guard global de tareas](commercial-mobile-01b/global-task-own-confirmation.png), [dos líneas](commercial-mobile-01b/two-lines-after-menu.png), [antes a 1023](commercial-mobile-01b/breakpoint-1023-before.png), [después a 1024](commercial-mobile-01b/breakpoint-1024-after.png) y [observación del breakpoint](commercial-mobile-01b/breakpoint-observation.json).

## 8. Inventario de cambios propios y concurrentes

Checkout efectivo: `/Users/joaquinarevalobueno/.codex/worktrees/ffe1/arevantes.com`; URL única `http://localhost:8000`.

Rama: `fix/work-order-layout`. HEAD conservado: `e4484989e160c548eac0ae50120d08a8ef640962`.

Se reutilizó el inventario de 107 archivos pendientes de 01. Al comenzar 01B se guardaron hashes de 200 archivos pendientes en `/tmp/commercial-mobile-01b-before.json`. El checkout continuó cambiando por otras tareas; por eso un diff global no equivale a este corte.

El [inventario JSON](commercial-mobile-01b/source-inventory.json) clasifica los archivos pendientes, su presencia en los hashes de 01 y variaciones desde el inicio de 01B. No incluye contenido de secretos ni variables de entorno.

El corte contiene 19 archivos:

**15 exclusivos comerciales respecto al inventario inicial 01:** `app.tsx`; CRM workspace; customer-list/detail; person-form; people/create/edit; opportunities/index; quotes/index; CSS comercial; MobileOpportunities; hook de protección; commercial-navigation; commercial-history; prueba frontend comercial.

**4 compartidos al comenzar 01:** opportunities/show, quotes/form, quotes/show y catálogo i18n. Comparados con la base fija de integración, sus diferencias corresponden al alcance comercial. Los bloques previos de expedientes/aceptación ya están en esa base: no se arrastran como cambios nuevos del parche.

Documentación: los informes/capturas de 01 y 01B son propios; `docs/roadmap.md` es compartido y solo debe recibir las entradas comerciales, nunca sustituirse íntegramente.

Quedan fuera del corte los cambios locales de expedientes, temporalidad, Operaciones/planificación, branding, PDFs, composer, backend, migraciones y componentes globales PWA. No se han revertido ni formateado desde 01B.

## 9. Estrategia segura de integración

Artefactos preparados, **sin aplicarlos al checkout actual ni crear commit**:

- [delivery.patch](commercial-mobile-01b/delivery.patch).
- [delivery-manifest.json](commercial-mobile-01b/delivery-manifest.json): base fija, SHA256 del parche y de cada archivo final.
- Base inmutable: `e426a7abdc223f1310ede034e13ac2c1d2bf5cfe` (referencia local de `main`, merge PR #157). No se afirma que consultar esa referencia certifique el despliegue actual.
- SHA256 del parche: `d6df8b48e0fa489c10f095d3ef16aca498dd0b6ea0be3b5400d0b621bf94eefc`.

Se verificó `git apply --check` sobre archivos limpios de esa base y se aplicó exclusivamente en una carpeta temporal. Los 19 hashes finales coincidieron. Después se construyó una copia completa aislada de esa misma base con el parche, sin `.env`, y se ejecutaron los controles de la sección 3. No se cambió de rama ni se alteró el índice del checkout de trabajo.

Secuencia posterior propuesta:

1. Resolver R-02 en una revisión mínima del ciclo de vida del shell; repetir la prueba de dos borradores y cambio de breakpoint, además de la navegación no comercial.
2. Actualizar el parche y manifiesto contra una base fija aprobada; si `main` ha avanzado, volver a probar la aplicación del parche sobre el nuevo SHA, sin asumir compatibilidad.
3. Repetir build **antes** de backend, frontend, tipos, lint, traducciones y comprobaciones de permisos/PDF. Revisar los hunks compartidos y añadir solo las entradas comerciales del roadmap/documentación.
4. Realizar la validación física de PWA iPhone/Android, sesión, teclado, Atrás y actualización, sin datos reales de prueba.
5. Con autorización posterior, preparar una rama y un commit limitado al corte revisado. No usar `git add .`, ni un commit global, ni incluir cambios locales ajenos para satisfacer dependencias.
6. Revisar y autorizar PR/publicación en una fase posterior. Esta revisión no ejecuta esos pasos.

El resultado aislado demuestra que no es necesario integrar los cambios locales incompletos de temporalidad o expedientes para incorporar el alcance comercial sobre la base especificada. El bloqueo R-02 es una dependencia global ya presente, no una excusa para incluir todo el checkout.

## 10. Bloqueos y riesgos pendientes

- **R-02: pérdida de datos al cruzar el breakpoint. Prioridad alta y bloqueo de preparación de publicación.**
- Validación en teléfonos físicos, lectores de pantalla, teclado y gestos del sistema pendiente.
- 79 traducciones preexistentes, con conjunto exacto igual al de la base. No se corrigieron cadenas ajenas para obtener un control verde.
- El checkout compartido es mutable: usar el parche y sus hashes para revisar alcance, no el estado global como paquete de entrega.
- Las pruebas frontend de política no equivalen a una cobertura completa de eventos reales de navegador; el informe distingue recorridos interactivos comprobados y limitaciones.

## 11. Recomendación

**No apto para preparar publicación hasta resolver R-02.** El aislamiento técnico está preparado y probado, y R-01 está corregida. No hay autorización implícita para publicar ni para incorporar el resto de trabajos. Se detiene esta fase con el corte, el inventario y la evidencia disponibles para revisión.

Commit: NO realizado
Push: NO realizado
Merge: NO realizado
Despliegue: NO realizado
