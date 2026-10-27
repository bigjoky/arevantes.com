# AREVANTES PWA — Corrección E-01 y propuesta de validación móvil

23/10/2026. Copia efectiva: `/Users/joaquinarevalobueno/.codex/worktrees/ffe1/arevantes.com`, única instancia `http://localhost:8000`. Rama conservada: `feature/technician-skill-checklist`, HEAD `457ae23`. Este documento recoge una corrección posterior al informe de estabilización; no cambia retrospectivamente sus resultados.

## Corrección realizada

E-01 ocurría porque cerrar la hoja desmontaba el formulario y descartaba sus campos sin comprobar cambios. Ahora se conserva el mismo formulario Inertia y el mismo POST `/operations/activities`; no se modifican módulos, permisos, rutas, modelos, validaciones o tablas.

La hoja consulta `isDirty` y `processing` mediante la referencia pública de Form. Un guard local a esa instancia coordina los cierres; no almacena los valores del borrador ni los copia a localStorage, sessionStorage, IndexedDB o un servicio global.

- Sin modificaciones: cierre directo.
- Con modificaciones: diálogo Radix existente, título y explicación accesibles, «Seguir editando» y «Descartar cambios».
- Continuar o pulsar Escape en la confirmación: conserva todos los campos y errores. El exterior de la confirmación no descarta.
- Descartar: resetAndClearErrors antes del cierre, con una sola continuación pendiente.
- Guardado correcto: limpia el estado y cierra sin preguntar. Un fallo de validación no resetea los campos ni errores.
- Envío: bloqueo síncrono antes de iniciar la petición, además de processing y formulario inert. Un segundo envío o cierre mientras está en curso no inicia otra petición ni descarta el borrador; se muestra un aviso al intentar cerrar. No es idempotencia de servidor ni protección frente a envíos desde otros dispositivos.

El cierre por botón, Escape y exterior pasa por la misma decisión. El retroceso de Inertia requiere tratamiento específico: popstate no es cancelable, por lo que se mantiene la hoja montada y se restaura una entrada cliente antes de pedir confirmación. Un ref conserva el estado de esa travesía durante el render de Inertia. Cancelar deja la edición intacta; confirmar cierra. Los enlaces GET dentro de la hoja también se protegen y se reanudan solo tras descarte confirmado. Las redirecciones del POST de guardado no se confunden con un retroceso.

Se añade beforeunload para una salida del documento con cambios o petición en curso. En ese caso el navegador controla el mensaje nativo y puede limitarlo, especialmente en móviles; no se promete un diálogo web personalizado al cerrar forzosamente el sistema o terminar el proceso.

## Accesibilidad y E-02

El foco inicial de la confirmación va explícitamente a «Seguir editando», incluyendo apertura por toque exterior. Al cancelar vuelve al control de la hoja que tenía el foco o a Título si ese control dejó de existir. Escape en la confirmación continúa editando; no descarta.

E-02 se corrige dentro de MobileExperience: cuando se abandona una pantalla auxiliar por retroceso nativo, se enfoca su botón de apertura. Se comprobó Menú → retroceso: elemento activo BUTTON, texto Menú. Antes quedaba en el documento. La comprobación no equivale a certificar VoiceOver o TalkBack en un teléfono físico.

## Archivos de este incremento

| Archivo | Cambio |
| --- | --- |
| `resources/js/components/mobile/mobile-experience.tsx` | Cierres protegidos, diálogo, retención de la hoja durante retroceso, protección GET/beforeunload y restauración de foco E-02. |
| `resources/js/components/mobile/mobile-task-form.tsx` | Referencia pública de Form y callbacks de envío/finalización; disableWhileProcessing. Se conservan los campos y endpoint. |
| `resources/js/lib/mobile/task-draft-guard.ts` | Coordinación efímera de una hoja: confirmación, descarte, éxito y bloqueo de envío. |
| `resources/css/mobile-app.css` | Contraste explícito de la confirmación móvil, sin cambiar el tema de escritorio. |
| `resources/js/i18n/catalog.json` | Cinco textos nuevos ES/EN de confirmación y envío. |
| `tests/task-draft-guard.test.mjs` | Diez casos de estado del guard, incluidos los cinco orígenes de cierre. |
| `tests/Feature/MobileTaskSubmissionTest.php` | Dos pruebas del contrato real: errores sin alta y creación válida aislada. |
| `docs/roadmap.md` | Estado y aceptación de E-01/E-02 y staging aún no creado. |
| Este documento y `docs/pwa-e01-confirmation.png` | Entrega y evidencia del incremento. |

Los cambios del rediseño PWA y del drawer anterior ya existentes permanecen. No se ha hecho reset, clean, stash, staging, cambio de rama ni eliminación de archivos.

## Verificación realizada

### Pruebas automáticas

- `npm run test:frontend`: **29 aprobadas**, cero fallos; 19 anteriores y 10 nuevas. Las nuevas pruebas ejercitan cierre limpio, confirmación, cancelar conservando campos/errores, descarte único, bloqueo durante petición, doble envío, fallo y éxito. Los casos de origen invocan la política común; no se presentan como automatización de eventos DOM.
- `php artisan test --compact --filter='MobileTaskSubmissionTest|OperationsAdminTest|OperationsRedesignTest|AuthenticationTest|WorkspaceNavigationAuthorizationTest'`: **40 aprobadas, 827 aserciones**. Base SQLite en memoria y sesión array de phpunit.xml. Incluye dos pruebas nuevas; no se escribe en la base local o productiva.
- `npm run lint:check`, `npm run types:check`, `npm run build`: correctos. Se mantiene el aviso de chunks superiores a 500 kB.
- `vendor/bin/pint --test tests/Feature/MobileTaskSubmissionTest.php`: correcto.
- `composer check-platform-reqs --no-dev`: requisitos instalados correctos en PHP 8.5.12. Composer emite un aviso de deprecación de su propia dependencia, sin fallo de requisitos.
- `git diff --check`: sin errores.
- `npm run i18n:check`: siguen los mismos 20 textos previos ajenos a PWA en field-service y quotes/form; las cinco cadenas nuevas tienen catálogo. No se incluyen correcciones ajenas en este incremento.

### Navegador local con viewport móvil

Se recorrieron realmente los controles, sin crear tareas en la sesión:

| Caso | Resultado |
| --- | --- |
| Cerrar hoja limpia con Escape | Cierra sin confirmación; al reabrir está limpia. |
| Escape con cambios | Abre confirmación. |
| Seguir editando | Conserva título, fecha/hora y prioridad; comprobado visualmente. |
| Escape en confirmación | Vuelve a la hoja sin descartar. |
| Toque exterior con cambios | Abre confirmación y enfoca Seguir editando. |
| Retroceso con cambios y cancelar | Restaura URL de creación y conserva los tres campos visibles. |
| Botón cerrar y confirmar descarte | Cierra; al reabrir, título/fecha vacíos y prioridad Media. |
| Enlace Abrir Operaciones, limpio | Navega y desmonta la hoja. |
| Enlace Abrir Operaciones, modificado | Pide confirmación; al descartar, completa la navegación. |
| Menú → retroceso | Foco en botón Menú y cero diálogos. |

Petición lenta/doble toque y validación del servidor están cubiertos por pruebas del guard y contrato backend, no por una simulación de red en la sesión del usuario. La prueba integrada de esos casos en navegador y dispositivos físicos continúa en el plan de staging. No se afirma haber probado un iPhone o Android real.

## Riesgos pendientes

1. Validar los gestos de retroceso, teclado, selector de fecha, safe areas, rotación y lectores de pantalla en Safari/iPhone y Chrome/Android instalados.
2. Probar una petición lenta, 419, pérdida de conexión y 500 con la hoja visible; confirmar campos, errores y ausencia de duplicados. El guard bloquea doble pulsación dentro de la instancia, no sustituye idempotencia backend.
3. No hay persistencia del borrador tras cierre forzado del proceso, recarga confirmada, logout o cambio de presentación al cruzar 1024 px. No se introduce un motor de borradores para resolver esos escenarios. La rotación iPad/multiventana necesita prueba específica.
4. El guard de E-01 solo protege esta hoja; no modifica el comportamiento de los demás formularios del ERP. Comprobación posterior a 1440 px: sidebar de escritorio presente, cero barras móviles y cero diálogos.
5. Continúan las puertas del informe anterior: actualización entre ventanas/builds, logout + Atrás y caché HTTP de respuestas privadas. El service worker no se ha modificado en este incremento.
6. La validación dirigida no sustituye la suite completa ni la aprobación de la versión candidata. Permanecen el aviso de tamaño de chunks y los faltantes i18n previos.

## Propuesta concreta de staging HTTPS — no creada

### Destino propuesto y aislamiento

Proponer una aplicación de pruebas separada **Arevantes-PWA-QA**, entorno **validation**, en la organización/alojamiento Laravel Cloud existente y región europea. Estos son nombres propuestos, no recursos existentes ni reservados. Usar un único servidor web de tamaño mínimo adecuado y una base PostgreSQL exclusiva; capacidad y coste requieren aprobación antes de provisionar.

No usar el entorno production de Arevantes, su base, bucket, variables, APP_KEY o dominio. `.cloud/config.json` de esta copia apunta a producción: **no ejecutar un deploy con ese contexto**. Preparar otro contexto aislado y verificar explícitamente aplicación/entorno destino antes de cualquier operación autorizada.

Debe existir una barrera de acceso externa al login del ERP: preferiblemente restricción de ingreso a la IP de salida de la red/VPN de pruebas, cubriendo tanto el hostname personalizado como el técnico. Si la plataforma no permite garantizarlo, resolver un proxy de acceso privado y el cierre del origen antes de publicar. No se ha verificado ni se presume una función nativa de allowlist en la cuenta Cloud. Esta capacidad y su coste son una condición previa, no una protección ya activada. No sustituirla por una URL difícil de adivinar o robots.txt.

### Requisitos comprobados en el repositorio

- composer.json: PHP `^8.4.1`, Laravel `^13.7`, Inertia `^3.0`. Usar PHP 8.5 con parche de seguridad vigente; local 8.5.12 satisface el lock. Verificar también pdo_pgsql para PostgreSQL.
- Vite instalado exige Node `^20.19.0 || >=22.12.0`; CI usa Node 22 y PHP 8.4/8.5. Propuesta: Node 22 mantenido que cumpla ese mínimo y lockfiles del corte revisado.
- Build: `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`, `npm ci`, `npm run build`. Document root `public`; no public/hot ni Vite/localhost en el HTML de staging.
- Las migraciones se ejecutarían únicamente contra la base nueva tras verificar host/base/usuario. No ejecutar composer setup contra producción ni seeders de forma indiscriminada.
- Documentos privados necesitan disco persistente separado. El despliegue documentado usa S3 privado; preparar bucket QA propio si las fichas/documentos se van a probar. No reutilizar `arevantes-production-private` ni su política.
- Hay colas y tareas programadas con sincronizaciones de fabricantes, contabilidad, correo, backups y purchase-inbox. **No habilitar el scheduler general ni workers con credenciales reales.** Para esta ronda, cola aislada y sin procesos automáticos; habilitar solo el procesamiento de prueba que se revise expresamente.
- DatabaseSeeder crea un Test User y sincroniza ubicaciones; no proporciona un juego de cuentas por rol listo para QA. No usar sus contraseñas de fábrica ni los demo seeders sin revisar.

Estos puntos se verificaron en composer.json, package-lock/node_modules, phpunit.xml, .github/workflows/tests.yml, routes/console.php, .env.example, DatabaseSeeder y docs/deployment-laravel-cloud.md. No se consultaron secretos ni se modificó infraestructura remota.

### Configuración que se aprobaría antes de publicar

- Nombre visual AREVANTES QA; hostname exclusivo con TLS válido, sin cookies compartidas con arevantes.com.
- APP_KEY exclusiva, APP_DEBUG=false; runtime con medidas equivalentes a producción. La cabecera CSP de esta aplicación solo se activa con APP_ENV=production: usar ese modo en el entorno QA aislado o revisar expresamente una equivalencia antes de la prueba. El nombre del entorno Cloud puede seguir siendo validation.
- APP_URL HTTPS; SESSION_COOKIE propia, SESSION_DOMAIN sin dominio padre compartido, SESSION_SECURE_COOKIE=true, HttpOnly y SameSite=lax. Base, sesiones, cache, colas y almacenamiento independientes.
- MAIL_MAILER=log o sumidero aprobado, BROADCAST_CONNECTION=log para esta ronda, sin credenciales de integraciones, fabricantes, IA, pagos, inbox o APNs de producción. Revisar cualquier servicio habilitado antes de probarlo.
- Recursos públicos de instalación (manifest, iconos, worker y aviso offline) accesibles desde la red autorizada; respuestas privadas sin caché compartida. Verificar que la barrera no redirige la descarga del worker a HTML de autenticación.
- Sin push-to-deploy ni automatizaciones de preview activadas. Selección explícita del corte; main y el flujo productivo permanecen fuera de esta preparación.

### Datos sintéticos y cuentas

Preparar después de aprobación un bootstrap exclusivo de QA: cuentas nominales admin/manager, comercial, técnico y un rol restringido, con credenciales aleatorias entregadas por canal privado. Al menos dos usuarios de prueba para comprobar aislamiento; nunca claves predeterminadas o expuestas en este documento.

Dataset mínimo: cliente y proyecto sintéticos, dos tareas de distintos responsables, una vencida, una sin fecha, y un documento ficticio no sensible. Usar direcciones example.test, importes inventados claramente marcados QA y ningún dato/backup productivo. El bootstrap debe ser explícito, repetible y limitado a la base QA; aún no se implementa ni ejecuta.

### Secuencia con aprobación

1. Aprobar recursos, aislamiento, barrera de ingreso y presupuesto. Verificar que el origen no permite eludir la restricción; detener si no puede garantizarse.
2. Aprobar cómo versionar el corte local para Cloud. Cloud despliega código de Git: **los cambios sin commit de esta copia no aparecen al desplegar HEAD**. Este encargo no autoriza commit, push ni PR; hará falta autorización separada para preparar ese corte en una rama de QA.
3. Solo entonces crear aplicación/entorno y recursos aislados, configurar secretos nuevos, TLS y control de acceso, revisar los comandos de build/deploy con destino explícito.
4. Validar base vacía y migraciones; cargar únicamente datos sintéticos y cuentas de prueba. Verificar cabeceras, recursos estáticos, ausencia de localhost y permisos por rol.
5. Entregar la URL HTTPS efectiva y las credenciales de pruebas por separado. No existe todavía una URL lista.
6. Ejecutar la matriz del informe de estabilización, más E-01/E-02, en navegador normal y modo instalado. Registrar modelo, versión del SO/navegador, corte exacto, resultados y capturas.
7. Con aprobación específica, preparar builds A/B para actualización, conservando un borrador en una segunda ventana. No se inicia esa publicación desde este documento.
8. Retirar acceso y recursos QA según plazo aprobado; no borrar ni tocar datos productivos.

El flujo Git y TLS de Cloud está documentado en [Laravel](https://laravel.com/cloud/deploy-a-laravel-app). La separación de recursos de preview está recomendada por [Laravel](https://laravel.com/blog/preview-environments-and-scale-to-zero-better-together). No se da por comprobada una configuración concreta de esta cuenta remota.

### Acceso desde teléfonos

`localhost:8000` en el teléfono apunta al teléfono; la instancia actual del Mac no queda accesible por copiar esa URL. No se ha ampliado la escucha a LAN ni creado un túnel. Las pruebas completas requieren un origen HTTPS confiable; [MDN explica el requisito de service worker](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API/Using_Service_Workers).

Cuando el staging esté aprobado y preparado:

1. Conectar ambos teléfonos a la Wi-Fi/VPN autorizada por la barrera. Abrir la URL HTTPS efectiva y verificar que no hay advertencias de certificado.
2. **iPhone:** abrir Safari, iniciar sesión QA, probar en pestaña normal; Compartir → Añadir a pantalla de inicio y Abrir como app web si aparece. Abrir el icono y repetir la prueba instalada. [Apple](https://support.apple.com/en-gb/guide/iphone/iphea86e5236/ios).
3. **Android:** abrir Chrome, iniciar sesión QA, probar en pestaña normal; ⋮ → Instalar aplicación o Añadir a pantalla de inicio → Instalar, según versión. Comprobar modo instalado, no solo un acceso directo a pestaña. [Google](https://support.google.com/chrome/answer/9658361?co=GENIE.Platform%3DAndroid&hl=es).
4. Repetir cerrar limpio/modificado, cancelar/descarte, los tres campos, Escape con teclado externo, gestos Atrás, toque exterior, petición lenta, doble toque y errores. Comprobar safe areas/teclado y foco. El Mac no necesita estar encendido cuando se use el staging remoto.
5. Fuera de la red autorizada el acceso debe rechazarse. No introducir contraseñas reales ni instalar certificados para eludir avisos.

## Git y autorización

Estado al cierre: 40 archivos modificados o nuevos contando archivos individuales (`git status --porcelain -uall`), con índice vacío (`git diff --cached --stat` sin salida). Rama y HEAD sin cambios. Se conserva el trabajo anterior y se añaden los archivos enumerados, sin staging de Git. No se crea ni publica staging. La propuesta requiere aprobación del usuario antes de recursos, cambios de acceso o publicación; el coste y la barrera efectiva deben estar confirmados antes de ejecutar.

**Commit: NO. Push: NO. Merge: NO. Despliegue: NO.**
