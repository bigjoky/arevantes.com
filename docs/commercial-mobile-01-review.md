# AREVANTES ERP — Fase Comercial Móvil 01

Informe local de implementación y verificación · 25/10/2026.

## A. Resumen

Adaptación móvil de CRM, Clientes y Presupuestos, reutilizando las rutas, permisos, formularios y cálculos existentes. No se han creado registros de negocio, ejecutado seeders, migrado la base de la aplicación ni publicado código en esta fase.

CRM dispone de tarjetas con etapa real, cliente, responsable, importe y próxima actividad cuando existe. El cambio de etapa utiliza exclusivamente `available_stages` y el callback existente. Hay búsqueda visible con debounce y acceso al formulario de filtros existente. La tabla y el Kanban de escritorio se conservan.

Clientes reutiliza las tarjetas y la ficha 360 existentes. Las tarjetas resumen no repiten NIF/CIF; muestran tipo/localidad disponibles y acciones `tel:`/`mailto:` cuando hay datos. Creación y edición conservan sus campos y validación de servidor; se adaptan a una columna y controles táctiles.

Presupuestos reutiliza listado, filtros, estados, paginación y ficha. Las líneas de detalle y edición se leen en vertical; la edición añade etiquetas visibles y accesibles a los controles existentes. Se conserva el motor de cálculo, composición, opciones, descuentos, IVA, numeración y PDF.

Los tres editores comerciales móviles incorporan confirmación de descarte, conservación del formulario al seguir editando, bloqueo síncrono de doble envío y conservación de valores/errores tras un fallo. No hay persistencia de borradores.

## B. Pantallas

| Pantalla | Móvil (<1024 px) | Escritorio |
| --- | --- | --- |
| CRM listado | Tarjetas; búsqueda; filtros existentes; selector de transiciones autorizadas | Kanban/tabla existentes |
| CRM calendario | Agenda y paginación existentes | Sin sustitución |
| CRM oportunidad | Una columna, enlaces contextuales al listado, acciones existentes | Estructura conservada |
| CRM crear/editar | Editor existente y confirmación accesible de descarte | Cierre y envío convencionales |
| Clientes listado | Tarjetas y contacto inmediato; filtros táctiles | Tabla existente |
| Cliente 360 | Resumen vertical, relaciones y pestañas existentes | Ficha existente |
| Cliente crear/editar | Una columna; validación y protección de borrador | Formulario existente; personal excluido de la protección comercial |
| Presupuestos listado | Tarjetas con referencia, fecha, cliente, estado e importe del contrato autorizado | Tabla/Kanban/calendario existentes |
| Presupuesto detalle | Líneas verticales con descuento, IVA y total | Tabla existente |
| Presupuesto crear/editar | Mismos campos y composición; líneas verticales etiquetadas; protección de borrador | Tabla de edición existente |

El resumen tabular duplicado del CRM Kanban se oculta solo en móvil: las mismas oportunidades ya están en las tarjetas principales. Las tareas y actividad permanecen accesibles.

## C. Arquitectura y auditoría

| Área | Contratos y operaciones reutilizados |
| --- | --- |
| CRM | `OpportunityController`, `Opportunity`, `CrmActivity`, `/opportunities`, ficha, creación/actualización, etapa, actividades, notas y archivo; permiso `manage-customers`; etapas de configuración y transiciones calculadas en servidor |
| Clientes | `PersonController`, `Person`, `/people`, perfiles, contactos, etiquetas, consentimientos y relaciones; permiso `manage-customers`; permisos específicos de facturación en las métricas existentes |
| Presupuestos | `QuoteController`, `Quote`, contratos de listado/detalle, `/quotes`, edición, estados, versiones, composición, PDF y conversión; `manage-quotes` y permisos adicionales existentes para aprobación/conversión |
| Navegación | Enlaces Inertia y parámetros reales; contexto de búsqueda, filtro y página; sin nuevas rutas |

Nuevos componentes/módulos:

- `MobileOpportunities`: presentación de los registros reales; sin catálogo paralelo de etapas.
- `useCommercialFormProtection`: guard por instancia del formulario, diálogo Radix existente, foco inicial en «Seguir editando», retorno al control que abrió la confirmación, Escape seguro y estado de petición.
- `commercial-navigation`: construye enlaces internos conservando contexto y eliminando comandos de apertura de editor/pantalla móvil.
- `commercial-history`: delegación de `popstate` registrada antes de inicializar Inertia. Solo actúa si un formulario comercial móvil se ha registrado y está modificado/enviándose.
- `commercial-mobile.css`: estilos de esta fase, mayoritariamente bajo el breakpoint móvil; sin cambios en el shell global.

La comprobación del retroceso detectó una incompatibilidad real: registrar el guard cuando se monta la página permitía que Inertia empezase a restaurar otra página y desmontase el formulario. Se añadieron únicamente importación y llamada de instalación en `app.tsx`, antes de `createInertiaApp`. La restauración reutiliza el estado de historial existente; no serializa campos, no desactiva su cifrado ni modifica el MobileAppShell. El navegador conserva el componente montado antes de preguntar. Se verificó la corrección con un borrador de cliente y el botón Atrás del navegador.

Se reutiliza `TaskDraftGuard`; no se modifica su implementación ni la hoja global de tareas. El diálogo del editor CRM se integra dentro del editor para conservar el foco entre diálogos anidados.

## D. Seguridad y conservación

- No se modifican rutas, controladores, políticas, permisos, modelos ni validaciones de negocio como parte de esta fase.
- Las acciones de CRM siguen limitadas a las transiciones del servidor; los importes de presupuestos proceden del contrato protegido por `manage-quotes`. No se inventa un permiso de importes que el contrato no contiene.
- Los controles de error, operación pendiente y éxito reutilizan Inertia y las respuestas existentes. Se bloquean salida y segundo envío mientras una petición está en curso.
- El guard solo recibe `isDirty`, `processing` y la función de reinicio. No almacena valores en localStorage, sessionStorage, IndexedDB ni un nuevo estado de historial.
- El cierre confirmado reinicia campos/errores; continuar editando no los toca. El error de servidor no ejecuta el reinicio de éxito.
- En recarga/salida completa se utiliza `beforeunload` nativo. El cierre forzado del sistema operativo no permite garantizar recuperación sin almacenamiento persistente; no se introduce ese almacenamiento.
- La barra inferior, Menú, MobileAppShell, manifest y service worker no se han editado en esta fase. Los archivos de esos componentes que aparecen pendientes pertenecen al trabajo previo o concurrente.
- No se han enviado correos, generado documentos oficiales ni ejecutado acciones comerciales sobre los registros existentes.
- La sesión local mostraba registros DEMO ya existentes de otras tareas. Esta fase no los ha insertado ni ejecutado su seeder. Las pruebas backend usan la base de pruebas aislada.

## E. Pruebas

| Comando/comprobación | Resultado |
| --- | --- |
| `npm run test:frontend` | 42 pruebas correctas, 0 fallos |
| `php artisan test --compact --filter=…` (comando completo a continuación) | 86 pruebas correctas; 1.580 aserciones; última ejecución 10,7 s |
| `npm run types:check` | Correcto |
| ESLint sobre todos los archivos TS/TSX y prueba de esta fase | Correcto |
| `npm run lint:check` global, última ejecución | 1 error ajeno a esta fase: `tests/business-time.test.mjs:7`, `process` no definido |
| `npm run build` | Correcto; aviso de chunks >500 kB y tiempos de plugins |
| `npm run i18n:check` | 79 cadenas pendientes en otros cambios del checkout; ninguna de las nuevas cadenas comerciales de esta fase figura en la lista |
| `git diff --check` | Correcto |

Comando backend ejecutado:

```sh
php artisan test --compact --filter='OpportunityTest|CustomersRedesignTest|CustomerProfileTest|CustomerCrmEvolutionTest|QuotesRedesignTest|QuoteCompositionTest|QuoteOptionSelectionTest|QuoteWorkflowSecurityTest|WorkspaceNavigationAuthorizationTest'
```

Pruebas nuevas en `tests/commercial-mobile.test.mjs`: conservación de parámetros de contexto en los tres módulos, retorno limitado a rutas internas, eliminación de comandos de apertura, cinco casos de cálculo real del editor (IVA, descuento, opciones no seleccionadas, redondeo de total con IVA y totales vacíos), registro temprano del guard, delegación normal de historial y retirada del guard. Se ejecutan también las pruebas existentes de `TaskDraftGuard`: cierre limpio/sucio, continuar, descarte, Escape/exterior/GET, petición en curso, fallo de validación, éxito y doble envío. Las pruebas de política no sustituyen la comprobación de integración visual.

Verificación interactiva realizada en el navegador local:

- CRM: tarjetas, etapas y transiciones reales; editor limpio tras descarte; X y Escape muestran confirmación; continuar conserva el título; descarte cierra y limpia; foco inicial seguro y retorno al editor.
- Clientes: listado Android e iPhone, ficha, alta; cancelar pide confirmación; continuar conserva nombre; retroceso del navegador conserva el componente y pide confirmación; descarte vuelve al listado sin crear cliente.
- Presupuestos: listado, ficha con líneas y totales, editor en una columna; volver pide confirmación; continuar conserva notas y líneas. Prueba efímera de cantidad 2, precio 100, descuento 10%, IVA 21%: base 200, descuento 20, IVA 37,80 y total 217,80. No se guardó el presupuesto de prueba.
- DOM: ancho de documento igual al viewport (390/412 px) en listados/fichas revisados y editor de presupuesto; celdas de edición de 306 px en viewport de 390 px.
- Escritorio 1440 px: Kanban CRM presente, tarjetas móviles ausentes; tabla de presupuestos presente; navegación lateral existente.

Limitaciones: no se han realizado pruebas en teléfonos físicos, VoiceOver/TalkBack ni teclado virtual real. Los errores de validación y permisos se cubren por las pruebas aisladas; no se forzaron escrituras de prueba en la base de la aplicación. No se certifica cada variante de formulario, dispositivo, gesto del sistema, adjunto o PDF por una captura.

## F. Evidencia visual

Capturas del navegador real con sesión local; no son mockups ni dispositivos físicos:

| Vista | Evidencia |
| --- | --- |
| iPhone 390×844 | [CRM](commercial-mobile-01/iphone-crm.png), [Clientes](commercial-mobile-01/iphone-customers.png), [Cliente](commercial-mobile-01/iphone-customer-detail.png), [Presupuestos](commercial-mobile-01/iphone-quotes.png), [Presupuesto](commercial-mobile-01/iphone-quote-detail.png), [Líneas de edición](commercial-mobile-01/iphone-quote-lines.png), [Confirmación](commercial-mobile-01/iphone-draft.png) |
| Android 412×915 | [CRM](commercial-mobile-01/android-crm.png), [Clientes](commercial-mobile-01/android-customers.png), [Presupuestos](commercial-mobile-01/android-quotes.png), [Borrador conservado tras Atrás](commercial-mobile-01/android-back-keeps-form.png) |
| Escritorio 1440×900 | [CRM](commercial-mobile-01/desktop-crm.png), [Presupuestos](commercial-mobile-01/desktop-quotes.png) |

## G. Git y archivos

Checkout servido y editado: `/Users/joaquinarevalobueno/.codex/worktrees/ffe1/arevantes.com`, `http://localhost:8000`. Rama `fix/work-order-layout`; HEAD `e4484989e160c548eac0ae50120d08a8ef640962`, sin cambios de referencia en esta fase. Al inicio había 107 archivos pendientes registrados mediante hashes en `/tmp/commercial-mobile-01-before.json`.

La consulta remota de solo lectura de esta fase encontró `main` en `11ba268bb286ff126113117d55a9f76bc7b7e140`. Esa referencia Git no demuestra por sí sola el despliegue actual; no se ha desplegado ni certificado una nueva versión productiva aquí.

Archivos modificados por esta fase (rutas relativas al checkout):

- `resources/js/app.tsx` (registro temprano del guard; tres líneas).
- `resources/js/components/crm/crm-workspace.tsx`.
- `resources/js/components/customers/customer-list.tsx`, `customer-detail.tsx`.
- `resources/js/components/people/person-form.tsx`.
- `resources/js/pages/people/create.tsx`, `edit.tsx`.
- `resources/js/pages/opportunities/index.tsx`, `show.tsx`.
- `resources/js/pages/quotes/index.tsx`, `show.tsx`, `form.tsx`.
- `resources/js/i18n/catalog.json` (entradas de esta fase).
- `docs/roadmap.md` (estado, carencias y criterios de aceptación).

Archivos nuevos de esta fase:

- `resources/css/commercial-mobile.css`.
- `resources/js/components/commercial/mobile-opportunities.tsx`.
- `resources/js/hooks/use-commercial-form-protection.tsx`.
- `resources/js/lib/mobile/commercial-navigation.ts`, `commercial-history.ts`.
- `tests/commercial-mobile.test.mjs`.
- Este informe y las capturas en `docs/commercial-mobile-01/`.

El diff global también incluye trabajo previo y concurrente de expedientes, temporalidad y PWA. No representa una entrega comercial aislada. Los hashes muestran modificaciones concurrentes fuera del alcance durante la comprobación, incluidos controladores y componentes móviles; no se han revertido ni corregido desde esta fase. Se preservaron los bloques preexistentes de ProjectJourney, aceptación en papel, validación técnica y plantillas en los tres archivos compartidos. La mayor parte del volumen de diff de `person-form.tsx` corresponde a la indentación al envolver el formulario con la confirmación; no se reconstruyen sus campos.

## H. Pendientes y criterios de aceptación

1. **Validación física**: iPhone/Safari instalado y Android/Chrome instalado; teclado, safe areas, gestos de retroceso, cambio de orientación, lector de pantalla y regreso desde tel/mail. Depende de una versión de pruebas aprobada. Aceptación: recorridos CRM→cliente→presupuesto, guardado/error/descarte y sesión sin pérdida ni doble envío.
2. **Calidad global del checkout**: resolver el error ESLint de temporalidad y las 79 cadenas pendientes en sus tareas propietarias. Aceptación: comprobaciones globales verdes antes de proponer publicación; no se atribuyen a los nuevos módulos de esta fase.
3. **Contexto de scroll**: se conservan parámetros y el historial existente; la restauración exacta de posición tras cada combinación de filtros/relaciones debe validarse en teléfonos. No se añade almacenamiento persistente.
4. **Pipeline cargado**: el contrato Kanban entrega registros del pipeline; la paginación completa se ofrece en Tabla y se indica expresamente en móvil. No se inventa un total distinto ni se convierte el payload limitado en un listado completo.
5. **Temas y navegación global**: el checkout mostraba contrastes oscuros en la cabecera/barra global previos a esta fase. Se mejoraron solo los controles y superficies comerciales; revisar los componentes globales en una tarea propia si persiste en dispositivos reales.
6. **Preparación de publicación**: aislar y revisar el corte comercial frente a las modificaciones concurrentes. Requiere autorización posterior; no hay commit ni paquete productivo generado por esta fase.

Commit: NO realizado
Push: NO realizado
Merge: NO realizado
Despliegue: NO realizado
