# AREVANTES ERP — Informe de corrección R-02 / Comercial Móvil 01C

26/10/2026 · Local · Sin commit ni publicación.

## 1. Causa raíz demostrada

La página Inertia se recibía como `children` en `AppSidebarLayout`, bajo `ShellContext`, `AppShell`/`SidebarProvider` y `AppContent`/`SidebarInset`. El layout alternaba dos árboles: `MobileExperience → página` y `Fragment → cabecera + página`. Al cruzar 1024 px cambiaba el padre reconciliado de la página. React desmontaba la instancia y sus estados, refs, formularios y callbacks de peticiones.

La evidencia anterior de 01B mostraba un presupuesto pasando de total 121 a 0 al cruzar 1023→1024. El patrón también está en la base fija de integración. No era un error del motor de cálculo ni una necesidad de persistir borradores.

## 2. Solución arquitectónica aplicada

Hay un único camino de montaje en todos los anchos:

```text
ShellContext
  AppShell / SidebarProvider
    AppSidebar (solo escritorio)
    AppContent / SidebarInset
      MobileExperience (misma instancia)
        chrome móvil o chrome de escritorio
        div key="inertia-page-content" (misma instancia)
          página Inertia (una sola copia)
        Sheet global de tarea (misma instancia mientras esté abierta)
```

`MobileExperience` recibe `active` y `desktopChrome`. Al cambiar de ancho modifica las clases y los elementos de navegación; no sustituye el padre de `children`. En escritorio los dos contenedores de presentación usan `display: contents`, conservando la geometría flex del escritorio. El host de página tiene clave constante, no dependiente del viewport.

Las cabeceras, barra inferior, FAB y pantallas Menú/Buscar/Perfil solo se presentan cuando corresponde. La página solo se oculta y se vuelve `inert` si hay una pantalla móvil superpuesta **y** la presentación móvil está activa. En escritorio no quedan pantallas móviles invisibles interceptando eventos.

La hoja de tareas abierta permanece visible y montada al pasar a escritorio. Se añaden reglas de tamaño, contraste y espaciado únicamente para esa hoja preexistente en ≥1024 px. No se crea otro formulario ni se guarda su contenido fuera de la instancia actual.

Se conservan providers, permisos, safe areas móviles y contratos Inertia. La activación del cifrado de historial y el aviso de actualización siguen limitados a la presentación móvil. No se modifican manifest ni service worker.

## 3. Archivos modificados

Incremento de código de esta fase, cuatro archivos:

1. `resources/js/layouts/app/app-sidebar-layout.tsx`: montaje único y chrome de escritorio pasado como contenido de presentación.
2. `resources/js/components/mobile/mobile-experience.tsx`: presentación activable, host de página estable, continuidad de Sheet y foco alternativo hacia un control conectado del shell vigente.
3. `resources/css/mobile-app.css`: reglas de continuidad de la hoja de tareas y su confirmación en escritorio; sin cambio del diseño habitual de las páginas de escritorio.
4. `tests/responsive-shell-continuity.test.mjs`: cinco pruebas de regresión.

Además: este informe, evidencia y manifiesto/parche en `docs/commercial-mobile-01c/`, y una entrada aditiva en `docs/roadmap.md`.

No se han editado guards comerciales, `TaskDraftGuard`, rutas, modelos, políticas, controladores, cálculos, PDFs, marca, configuración Cloud ni integraciones durante 01C.

## 4. Comportamiento antes y después

| Situación | Antes | Después |
| --- | --- | --- |
| Formulario 1023→1024→1023 | Se desmontaba y reiniciaba | Conserva estado, campos y la instancia del host |
| Presupuesto de varias líneas | Podía perder líneas y totales | Mantiene dos líneas y los mismos importes |
| Tarea móvil abierta al pasar a escritorio | Se desmontaba junto con MobileExperience | Sigue abierta con un solo formulario y el mismo borrador |
| Regreso de Menú después de cambiar de presentación | El control original podía estar desconectado | Foco en el control vigente de Menú o de la barra lateral |
| Adelante reabre una tarea en escritorio | No existía una transición segura al desmontarse el shell | Se reactiva la hoja existente; puede cerrarse y devuelve el foco |

Durante el desarrollo se detectó y retiró una suspensión de listeners en escritorio que impedía reactivar el guard al volver con Adelante. La versión final conserva el algoritmo original del guard de tareas: sus callbacks no intervienen sin tarea activa y sus listeners se retiran al desmontarse el componente. Se añadió una prueba del callback real para ese caso. No se sustituyó el guard por un motor nuevo.

## 5. Resultados de los seis casos de prueba

Pruebas interactivas en navegador local con sesión autorizada. Los nombres y notas introducidos eran textos efímeros de comprobación; los formularios se descartaron al terminar. No se crearon clientes, oportunidades, presupuestos ni tareas válidos.

### A — Cliente: correcto

Se introdujeron nombre, apellidos y notas a 1023 px, se pasó a 1024 y se volvió a 1023. Los campos conservaron sus valores. El campo de nombre mantuvo su identidad accesible (índice 32 en el árbol) y el foco/estado de las notas se conservaron. DOM: un host de página, un formulario y un único `#first_name` en los tres pasos. No se pulsó Guardar.

Evidencia: `case-a.json`, `case-a-final-ax.txt`, `customer-1023.png` y `customer-1024.png`. El primer JSON contiene diferencias de accesibilidad entre capturas; la ausencia de una línea en un diff no significa que se vaciara. El árbol completo final y las capturas confirman conservación.

### B — Presupuesto: correcto

Dos líneas: primera, cantidad 2 y precio 100; segunda, cantidad 1, precio 50 y descuento 10%; IVA 21%. En 1023→1024→1023 permanecieron exactamente:

- Subtotal: 250.
- Descuento: 5.
- IVA: 51,45.
- Total: 296,45.
- Dos controles de descripción, un formulario y un host de página.

Se conservaron las descripciones y no se duplicaron líneas. No se pulsó Guardar. Evidencia: `case-b.json`, `quote-1023.png`, `quote-1024.png`.

### C — CRM: correcto

Una oportunidad nueva con título y notas conservó ambos en los tres anchos. El diálogo y su formulario permanecieron abiertos. Después, Cancelar siguió mostrando la confirmación comercial; se descartó el borrador sin crearlo. Evidencia: `case-c.json`.

### D — Guard global: correcto

La tarea móvil abierta conservó el título a 1023, 1024 y de nuevo 1023. Hubo una hoja y un formulario de tarea en cada paso; en escritorio no quedó barra inferior móvil. Cerrar en escritorio mostró la confirmación específica de tarea; Seguir editando conservó el borrador y Descartar cerró la hoja.

El árbol de accesibilidad redactó el valor del título en este formulario; se verificó visualmente en las capturas, no se interpretó `<redacted>` como pérdida de datos. Evidencia: `case-d.json`, `task-1023.png`, `task-1024.png`.

### E — Navegación: correcto en los recorridos comprobados

CRM con búsqueda DEMO y vista Tabla: abrir ficha, Atrás, Adelante y Atrás conservó el contexto y devolvió el listado. Menú abierto al cruzar a escritorio dejó un host visible, cero pantallas Menú móviles, cero barras inferiores y el contenido sin `hidden`/`inert`. Al regresar a móvil, Volver devolvió el foco al botón Menú actual.

También se comprobó cerrar una tarea en escritorio, volver a abrirla mediante Adelante y cerrarla de nuevo: cero diálogos y cero formularios residuales, foco en el botón de la barra lateral. Se esperó al cierre real de las animaciones antes de continuar la secuencia.

Evidencia: `case-e.json`, `task-forward-desktop.json`.

### F — Petición en curso: correcto en la respuesta de validación probada

Se pulsó Guardar una vez en un presupuesto sin cliente, con una línea de precio 100. Se observó el botón deshabilitado, indicando petición en curso, **antes** de cambiar a 1024 px. El servidor devolvió «El campo persona id es obligatorio.»; el error y la descripción permanecieron en escritorio y al volver a 1023. El formulario siguió siendo una sola instancia y pudo descartarse normalmente.

No se volvió a pulsar Guardar ni se introdujo un mecanismo que reenvíe al montar: las pruebas de guard cubren el bloqueo síncrono de doble envío. No se dispone de un contador de paquetes HTTP del navegador; no se afirma una medición de tráfico que no se haya realizado. El caso interactivo prueba continuidad de una respuesta real rechazada. El guardado válido, sus redirecciones y las escrituras autorizadas se verifican mediante backend aislado; no se realizó un guardado válido en la base compartida.

Evidencia: `case-f.json`, `case-f-1024-ax.txt`, `validation-response-desktop.png`.

## 6. Matriz de viewports

70 comprobaciones: diez rutas a 320, 390, 412, 768, 1023, 1024 y 1440 px, altura 900. Rutas: CRM, Clientes, Presupuestos, cliente nuevo, presupuesto nuevo, ficha CRM, ficha cliente, ficha presupuesto, Inicio y Operaciones. Estas dos últimas se revisaron como control del layout compartido, sin modificar sus módulos.

Resultados:

- 70/70 con un único `data-inertia-page-container`.
- 0 desbordamientos horizontales del documento.
- Móvil: una barra inferior y ningún sidebar de escritorio.
- Escritorio: un sidebar y ninguna barra inferior móvil.
- 0 pantallas móviles residuales en vistas normales.
- 0 páginas ocultas o `inert` en vistas normales.

Evidencia: [matriz JSON](commercial-mobile-01c/viewport-matrix.json) y capturas `viewport-320.png` a `viewport-1440.png`. Las transiciones con formularios se prueban aparte en A–F; comprobar un ancho aislado no bastaba para detectar R-02.

## 7. Compatibilidad con escritorio

Se mantienen AppShell, SidebarProvider, AppContent, AppSidebarHeader, breadcrumbs y sus permisos. El wrapper es estable y usa `display: contents` en escritorio. No se monta una segunda copia de la página ni se oculta un formulario duplicado.

Las pantallas normales de escritorio no reciben navegación móvil activa. La única superficie móvil que puede seguir visible en escritorio es una tarea ya abierta o reabierta desde su entrada de historial: es una transición deliberada para conservar el trabajo, con cierre y foco comprobados.

## 8. Compatibilidad con la PWA

MobileExperience conserva su instancia al redimensionar; la página y la hoja de tarea conservan sus respectivas instancias. No hay persistencia nueva en localStorage, sessionStorage, IndexedDB, historial ni servidor. No se modifican identidad instalada, worker, caché, actualizaciones de recursos ni integraciones.

Los guards mantienen su política: la protección comercial sigue activándose en móvil y el guard de tarea decide sobre la tarea activa. El cambio de presentación no llama a reset, submit ni al descarte del formulario. Los providers no cambian de identidad por un breakpoint.

Las comprobaciones se hicieron en navegador con viewport adaptado. No son pruebas físicas de iPhone/Android, teclado virtual o VoiceOver/TalkBack.

## 9. Pruebas automatizadas y comandos

| Comprobación | Checkout compartido | Base fija + 01B + incremento R-02 |
| --- | --- | --- |
| `npm run test:frontend` | 56 correctas en última ejecución | 50 correctas |
| Backend relevante | 90 correctas / 1.633 aserciones | 90 correctas / 1.633 aserciones |
| `npm run types:check` | Correcto | Correcto |
| `npm run lint:check` | Correcto en última ejecución | Correcto |
| `npm run build` | Correcto | Correcto |
| `npm run i18n:check` | 79 pendientes preexistentes | Mismo catálogo base; no se modifica |
| `git diff --check` / aplicación del parche | Correcto | Incremento aplicable y hashes verificados |

Comando backend:

```sh
php artisan test --compact --filter='OpportunityTest|CustomersRedesignTest|CustomerProfileTest|CustomerCrmEvolutionTest|QuotesRedesignTest|QuoteCompositionTest|QuoteOptionSelectionTest|QuoteWorkflowSecurityTest|WorkspaceNavigationAuthorizationTest|MobileTaskSubmissionTest|QuotePdfTest'
```

El build mantiene avisos de chunks >500 kB/tiempos de plugins; no son errores. Hubo un error transitorio de orden de imports en `resources/js/pages/sales/show.tsx` procedente de cambios concurrentes; esta fase no editó ese archivo y la última ejecución global ya pasa. No se han corregido las 79 cadenas de otras tareas.

Las cinco pruebas nuevas verifican: una sola página bajo padre estable; host con clave constante fuera de ramas condicionales; Sheet no condicionada por el breakpoint; foco sobre el control conectado o su sustituto; reactivación del callback real de historial de tareas en escritorio y limpieza de listeners. Son pruebas estructurales de reconciliación y de funciones/efectos reales extraídos mediante TypeScript; no se presentan como un renderer DOM completo. Se complementan con las pruebas interactivas A–F.

Control negativo: ejecutadas contra los dos archivos anteriores a 01C, las pruebas dan 3 fallos y 2 correctas; con la corrección pasan las cinco. Esto confirma que detectan el árbol anterior, además de observar continuidad en navegador.

La verificación aislada no usó `.env` ni bases existentes. Se construyó el frontend antes de ejecutar backend; SQLite `:memory:`, caché/sesión/correo de pruebas y cola síncrona. No se ejecutaron migraciones ni seeders contra la aplicación compartida o producción.

## 10. Inventario y aislamiento de cambios

Checkout efectivo: `/Users/joaquinarevalobueno/.codex/worktrees/ffe1/arevantes.com`. URL única: `http://localhost:8000`. Rama conservada: `fix/work-order-layout`; HEAD `e4484989e160c548eac0ae50120d08a8ef640962`.

Se inventariaron 241 archivos pendientes antes de editar, con hashes en `/tmp/commercial-01c-before.json`, y se conservaron copias de los tres archivos compartidos modificados. Los cambios concurrentes ajenos no se han revertido. El inventario final está en `source-inventory.json`.

Artefactos separados:

- [r02-increment.patch](commercial-mobile-01c/r02-increment.patch), cuatro archivos.
- [increment-manifest.json](commercial-mobile-01c/increment-manifest.json), base y hashes de entrada/salida.
- El parche comercial 01B de 19 archivos permanece sin modificar, SHA256 `d6df8b48e0fa489c10f095d3ef16aca498dd0b6ea0be3b5400d0b621bf94eefc`.
- Orden probado en carpeta temporal: base fija `e426a7abdc223f1310ede034e13ac2c1d2bf5cfe` → parche 01B → incremento R-02.

El incremento conserva el formato de la CSS de la base y añade solo las reglas R-02; no arrastra las diferencias de formato previas del CSS compartido. El manifiesto distingue hash de trabajo y hash de destino cuando difieren por ese motivo.

No se aplicaron parches al checkout compartido ni se cambiaron ramas, índice o referencias. Se probaron exclusivamente en carpetas temporales obtenidas con `git archive`, sin credenciales, reutilizando dependencias instaladas. El corte no incluye backend, temporalidad, expedientes, marca, PDFs ni otros cambios locales.

Para integrar posteriormente: verificar hashes y base aprobada, aplicar 01B y después R-02 en una copia limpia, repetir los controles y revisar los hunks de los componentes compartidos. Añadir solo estas entradas de documentación al roadmap, sin sustituir el archivo compartido completo. Si la base avanza, volver a validar; no usar un commit global del checkout.

## 11. Riesgos pendientes

- Pruebas físicas de PWA instalada, teclado, lectores de pantalla y gestos del sistema pendientes; no se afirman realizadas.
- El caso F interactivo cubre rechazo por validación durante una petición; un guardado válido durante una respuesta artificialmente ralentizada no se provocó en la base compartida. El backend aislado cubre guardados/redirecciones autorizados y las pruebas de guard el doble envío.
- Las 79 traducciones pendientes siguen siendo deuda preexistente.
- El checkout continúa recibiendo modificaciones concurrentes. La unidad revisable es el incremento con hashes, no todo el diff local.
- No se garantiza recuperar memoria tras cierre forzado del proceso del navegador/sistema operativo; no se añadió persistencia para hacerlo.

## 12. Dictamen

**APTO para preparar integración del corte aislado Comercial 01/01B + R-02.** El bloqueo R-02 queda resuelto en los recorridos comprobados: la página no cambia de padre al cruzar el breakpoint, conserva los formularios y mantiene la tarea en curso.

Este dictamen no autoriza publicación ni mezcla de cambios concurrentes. La validación física y la revisión del corte sobre la base final siguen siendo pasos previos a cualquier despliegue. Se detiene aquí para revisión y aprobación.

Commit: NO realizado
Push: NO realizado
Merge: NO realizado
Despliegue: NO realizado
