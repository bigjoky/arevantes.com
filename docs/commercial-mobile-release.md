# Comercial Móvil 01 — Corte aprobado 01D e integración 01E

Estado: candidata aprobada para revisión mediante PR. No publicada en producción.

## Alcance e identidad

Base verificada: `e426a7abdc223f1310ede034e13ac2c1d2bf5cfe`.
SHA-256 del diff 01D: `b037628de93ec074d38fe1840481bf00dbc87af01f2c84627b05d76865b76db1`.
Se aplica una sola vez el diff compuesto; no se reaplican 01B y R-02.

23 archivos de código/pruebas: tarjetas móviles CRM, clientes y presupuestos;
formularios y fichas adaptados; contexto de filtros al navegar; protección de
borradores; estilos y traducciones propios; pruebas asociadas. Esta documentación
y una sección seleccionada del roadmap completan la entrega. Se conservan los
formularios, cálculos, PDFs, permisos y contratos de negocio existentes.

R-01 permite Menú → Volver al formulario sin tratarlo como una salida que descarte
el borrador. R-02 conserva la ascendencia React y una sola instancia del contenido
al cruzar 1023/1024 px. La hoja de tareas abierta continúa disponible en escritorio,
incluso si se reabre con Adelante; su cierre devuelve el foco al control disponible.

Se excluyen los pendientes concurrentes de temporalidad, expedientes, Operaciones,
marca y Nexo, así como backend, migraciones, Cloud, infraestructura, manifest y worker.
El contenido ya integrado en la base permanece intacto.

## Validación

01D: 50 pruebas frontend, 90 backend / 1633 aserciones; build, tipos, lint y diff
correctos; 70 comprobaciones estructurales de viewport en 320, 390, 412, 768, 1023,
1024 y 1440 px sin violaciones. Interacción de navegador con datos sintéticos:
formularios CRM/clientes/presupuestos, contexto e historial, Menú y foco, descartes,
respuesta de validación y hoja de tareas. Sin operaciones comerciales reales.

Para repetir en una copia limpia con dependencias de los lockfiles:

```sh
# Entorno de pruebas aislado; nunca reutilizar .env ni DB de producción.
export APP_ENV=testing
export APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
export DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL=
export CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync
export MAIL_MAILER=array BROADCAST_CONNECTION=null
npm run build
npm run test:frontend
npm run types:check
npm run lint:check
npm run i18n:check
php artisan test --compact --filter='OpportunityTest|CustomersRedesignTest|CustomerProfileTest|CustomerCrmEvolutionTest|QuotesRedesignTest|QuoteCompositionTest|QuoteOptionSelectionTest|QuoteWorkflowSecurityTest|WorkspaceNavigationAuthorizationTest|MobileTaskSubmissionTest|QuotePdfTest'
git diff --check
```

Estos son los nombres reales de los scripts: no existen aliases `test`, `types`
o `i18n`. `lint:check` evita mutaciones de `lint --fix`. Build genera Wayfinder
antes de tipos. i18n devuelve 79 cadenas pendientes preexistentes: se documentan,
no se añaden traducciones ajenas. Las guardas de doble envío se prueban mediante
los tests; no se midió un contador de paquetes HTTP en navegador.

## Pendientes y aceptación

- Teléfonos físicos/HTTPS: instalación, teclado, safe areas, VoiceOver/TalkBack,
  suspensión, sesión y actualización. Aceptación: recorridos con cuentas de prueba
  en iOS/Safari y Android/Chrome, sin pérdida accidental de borradores.
- Red lenta: guardado válido con respuesta demorada y doble toque en dispositivo.
  Aceptación: un único guardado y valores conservados en error o cambio de tamaño.
- Paginación CRM: las tarjetas Kanban muestran los registros realmente cargados;
  Tabla permite recorrer resultados paginados. No se simulan registros ni etapas
  disponibles; las acciones siguen los permisos del backend.
- No hay recuperación persistente de borrador tras matar el proceso del sistema.
- Advertencias de tamaño del bundle y deuda i18n: preexistentes, fuera del corte.

## Integración y reversión

Revisar la PR y su CI antes de cualquier merge. Si main avanza, comparar de nuevo
el corte y repetir validación; no forzar aplicación ni copiar archivos completos
sobre mejoras ajenas. El push de esta rama no debe desplegar producción ni crear
previews sin autorización. No cambiar automatizaciones como parte de esta entrega.

Una futura reversión debe restaurar el artefacto anterior o revertir exclusivamente
el commit de esta PR. No hay migraciones nuevas ni rollback de datos en este corte.
No usar reset/clean en el checkout compartido. Después de un despliegue autorizado,
comprobar login/permisos, las tres áreas comerciales, PDFs/totales, borradores,
Menú/historial, tareas, breakpoints, escritorio, sesión y actualización de la PWA.
