# Actividad comercial de campo de AREVANTES

Rama: `feature/commercial-field-activity`. No instalar en producción ni fusionar en `main` para esta prueba. El módulo es parte del ERP Laravel/Inertia existente y se usa desde Safari en iPhone. La API permite integrarlo con el cliente móvil; este repositorio no incluye una aplicación iOS distribuible.

## Instalación local desde cero

Requisitos: PHP 8.4.1 o superior compatible con el lockfile, Composer, Node compatible con Vite 8 (Node 22.12+), npm y SQLite/PDO SQLite. Para staging PostgreSQL, conservar la conexión y secretos de su entorno aislado.

```sh
git clone --branch feature/commercial-field-activity https://github.com/bigjoky/arevantes.com.git arevantes-field
cd arevantes-field
composer install --no-interaction
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=CommercialFieldDemoSeeder
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

No sobrescribir un `.env` existente. La configuración de ejemplo usa SQLite (`database/database.sqlite`) y `APP_TIMEZONE=Europe/Madrid`. Si se cambia la zona horaria de una instalación existente, sus timestamps anteriores requieren un plan separado de conversión; no hacerlo durante esta prueba.

Abrir `http://127.0.0.1:8000/login`. Cuentas **solo locales**:

| Usuario | Correo | Contraseña |
| --- | --- | --- |
| Comercial | `campo.demo@example.test` | `FieldDemo-2026!` |
| Supervisor | `supervisor.demo@example.test` | `FieldDemo-2026!` |

El seeder rechaza entornos distintos de `local`/`testing`, no crea clientes y no modifica contraseñas de cuentas existentes. Nunca usar estas cuentas ni sus contraseñas en producción.

## Staging y permisos

Usar una base de datos separada y las credenciales de staging. Obtener primero una copia de seguridad. Actualizar **esta rama**, ejecutar `composer install`, `npm ci`, `php artisan migrate`, `npm run build` y `php artisan optimize:clear`. Mantener `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL` con la URL HTTPS de staging, cookies seguras y una clave propia del entorno. No ejecutar el seeder demo en staging.

Las migraciones nuevas son `2026_10_10_000000_extend_commercial_visits_for_prospecting` y `2026_10_10_010000_create_commercial_field_sessions`. La primera ya estaba en la rama: hace opcionales persona/oportunidad y añade tipo, resultado y ruta. La segunda añade sesiones, su relación con visitas, sector/instrucciones en rutas y permisos. No requiere un servicio externo, una clave GPS ni colas para registrar visitas.

Con un administrador autorizado, abrir **Administración de plataforma → Accesos y permisos** (`/admin/access`):

- Comercial: perfil Comercial y permiso **Registrar actividad de campo** (`record-commercial-field`). Se añade por defecto a los perfiles de sistema Comercial y Administración. Perfiles personalizados requieren asignación explícita.
- Gerencia/supervisor: conceder **Supervisar actividad de campo** (`supervise-commercial-field`) al usuario o perfil elegido. Administración lo recibe por defecto; los comerciales no ven a sus compañeros por defecto.
- Conversión: necesita además **Gestionar clientes** (`manage-customers`). Sin él se puede registrar puerta fría, pero no convertir.
- API móvil: mantiene Sanctum con capacidad `mobile` y el permiso existente `manage-operations`, además de los permisos de campo. Un token sin capacidad o un permiso revocado recibe 403.

Las denegaciones individuales prevalecen sobre el perfil. El rol textual `commercial` no sustituye al perfil de permisos. No hace falta crear un nuevo rol textual “gerente”.

Acceso: **CRM → Actividad de campo**, o `/commercial-field`. Se conserva el layout, logo, estilos y sidebar canónico del ERP.

## Prueba funcional exacta

1. Entrar como supervisor. En “Asignar sector a una ruta”, elegir Comercial demo, la fecha de hoy y “Sector de prueba”. Guardar. Se crea o actualiza su ruta del día sin clientes ficticios ni reemplazar paradas CRM.
2. Salir y entrar como comercial. Abrir CRM → Actividad de campo. Iniciar prospección.
3. Elegir Vivienda y Sin respuesta. Dejar “Usar hora actual al guardar” activado, seleccionar la ruta asignada, escribir una referencia opcional y guardar. Debe aparecer una visita sin enlace a oportunidad. No se crea ninguna persona ni oportunidad.
4. Registrar otra visita con resultado Interesado. GPS es opcional: “Añadir ubicación puntual” solicita permiso una sola vez; se puede denegar o quitar y seguir guardando. No registrar datos reales durante la prueba.
5. Pausar/finalizar sesión. Debe aparecer el tiempo cerrado y las visitas/hora. Reanudar crea otra sesión para excluir la pausa. Si se olvidó parar, indicar el fin real antes de cerrar; no puede preceder al inicio ni a una visita de esa sesión.
6. Pulsar “Convertir a oportunidad” en la visita interesada. Introducir un contacto de prueba identificable, teléfono o correo, servicio, canal, decisión de consentimiento y próxima acción futura. Guardar. Debe aparecer “Ver oportunidad”, con propietario comercial, fuente Prospección comercial y próxima acción. Solo hay una visita original y una oportunidad, incluso si se reenvía la conversión.
7. Si el contacto deniega consentimiento o ya está marcado No contactar, su siguiente acción es una tarea interna de revisión. No se envía ningún mensaje automáticamente. Sin respuesta/Sin interés/No contactar no ofrecen conversión.
8. Registrar una visita tras pausar, sin iniciar otra sesión. Debe aumentar “Sin tiempo cerrado” y no el numerador de visitas/hora.
9. Entrar como supervisor: seleccionar el período y comercial. Comprobar visitas, horas, interesados, conversiones, resultados y sesiones abiertas. Un comercial sin el permiso de supervisión solo recibe sus datos, incluso si modifica `user_id` en la URL.
10. En iPhone abrir la URL **HTTPS de staging**, entrar y repetir los pasos 2–6. Safari → Compartir → Añadir a pantalla de inicio es opcional. Sin HTTPS remoto, la geolocalización puede no estar disponible. No se requiere instalar una app de App Store.

## Qué significan los indicadores

`visitas/hora = visitas asociadas a sesiones cerradas dentro del período / duración real de esas sesiones dentro del período`. Se recortan las sesiones al rango solicitado. Las sesiones nuevas almacenan inicio/fin en UTC para medir cambios de horario de verano; se usan segundos reales antes de redondear horas a dos decimales. No se calcula una jornada fija ni se infiere tiempo de la primera/última visita. Las sesiones abiertas y visitas sin tiempo cerrado quedan visibles y excluidas del ratio. Un ratio sin horas es nulo (“Sin tiempo medido”). Las visitas anteriores al inicio del módulo no se reinterpretan como puerta fría.

“Interesados” cuenta el resultado interesado; “Convertidos” cuenta las visitas del período con oportunidad enlazada. No significa ventas ganadas ni facturación. No hay ranking, puntuación laboral ni decisiones automáticas sobre trabajadores. Las sesiones son una declaración de tiempo de prospección y no sustituyen el registro de jornada de RR. HH.

Se admiten visitas de los últimos 30 días y nunca futuras. La hora actual se toma al guardar; una visita histórica puede introducirse desmarcando esa opción. Solo se asocia a una sesión si su hora cae dentro de ella. Una ruta enlazada debe pertenecer al comercial y corresponder al día de la visita.

## API móvil

Base: `/api/mobile/v1`. Autenticarse mediante el endpoint existente `POST /auth/login` (incluido 2FA cuando proceda). Enviar `Authorization: Bearer <token>`. Todas las escrituras de campo requieren un encabezado `Idempotency-Key` UUID nuevo por operación lógica; reutilizar el mismo para un reintento. El middleware existente conserva y reproduce el resultado.

| Método | Endpoint | Uso |
| --- | --- | --- |
| GET | `/commercial/field?from=YYYY-MM-DD&to=YYYY-MM-DD&page=1` | Datos propios o equipo autorizado; hasta 366 días y 30 visitas por página |
| POST | `/commercial/field/sessions` | Iniciar/reanudar; 422 si ya existe una sesión abierta |
| PATCH | `/commercial/field/sessions/{id}/stop` | Cerrar; `ended_at` ISO opcional para fin real anterior |
| POST | `/commercial/field/visits` | Registrar puerta fría |
| POST | `/commercial/field/visits/{id}/convert` | Convertir la visita propia, reutilizando validación y flujo CRM |
| POST | `/commercial/field/routes` | Supervisor asigna `user_id`, `route_date`, `sector_label`, `instructions` opcionales |

Ejemplo de cuerpo de visita:

```json
{
  "client_uuid": "5b549607-179c-416a-bdae-173be46e720c",
  "prospect_type": "business",
  "outcome": "no_answer",
  "visited_at": "2026-10-10T08:15:00+02:00",
  "commercial_route_plan_id": null,
  "address": "Referencia de prueba",
  "notes": null
}
```

Sustituir la fecha por una real reciente. Tipos: `residential`, `business`, `community`. Resultados: `no_answer`, `not_interested`, `interested`, `follow_up`, `not_accessible`, `do_not_contact`. GPS opcional: `latitude`, `longitude`, `accuracy_meters`, con coordenadas emparejadas y precisión no negativa. Se normalizan instantes a `APP_TIMEZONE` antes de guardarlos.

`client_uuid` identifica la misma visita para reintentos; no reutilizarlo para otra visita ni para editar una visita. La conversión requiere nombre o empresa, móvil o correo, `preferred_contact_channel`, `consent_status`, `service_interest` y `next_action_at` futuro; usar las opciones que devuelve GET. No enviar datos personales para una visita sin contacto real. Los endpoints de leads, rutas y paradas anteriores se conservan. Las escrituras nuevas responden con IDs y estado mínimo, sin copiar notas/GPS al registro de idempotencia.

## Privacidad y retención

Diseño proporcionado: GPS puntual opcional por acción del usuario, sin seguimiento en segundo plano, sin trayectorias ni GPS de inicio/fin de sesión. Registro de visitas sin crear contactos para puertas sin respuesta. Acceso propio por defecto y supervisión explícita. Las notas y ubicación de campo se redactan en auditoría; las coordenadas nuevas no se copian a las paradas del planificador de rutas.

Valores operativos configurables en `.env`:

```dotenv
COMMERCIAL_FIELD_LOCATION_RETENTION_DAYS=30
COMMERCIAL_FIELD_ACTIVITY_RETENTION_DAYS=365
```

El comando existente `php artisan data:prune-retention --dry-run` muestra recuentos sin borrar. `php artisan data:prune-retention` elimina GPS pasado el plazo y visitas/sesiones de campo pasado el segundo plazo, sin eliminar personas u oportunidades CRM convertidas. Se ejecuta semanalmente con el scheduler existente; el borrado puede demorarse hasta la siguiente ejecución. Instalar el cron habitual `* * * * * cd /ruta/staging && php artisan schedule:run` o ejecutar `php artisan schedule:work` en local. Cambiar plazos requiere aprobación de la política de AREVANTES, no representa un plazo legal obligatorio.

Antes de uso con personal real, AREVANTES debe documentar finalidad, base jurídica, proporcionalidad, información previa al personal y representantes, responsables de acceso, procedimiento de derechos y plazos de conservación. El consentimiento del prospecto y el permiso del navegador para GPS no son la base jurídica del control laboral. El aviso de pantalla es una ayuda y no sustituye esa información organizativa. Referencia: [AEPD, protección de datos en relaciones laborales](https://www.aepd.es/documento/la-proteccion-de-datos-en-las-relaciones-laborales.pdf), apartado de geolocalización.

La retención de contactos/oportunidades, copias de seguridad, rutas CRM previas y registros de seguridad sigue las políticas existentes; debe revisarse por separado. No se garantiza cumplimiento jurídico solo por instalar código.

## Verificación y límites

```sh
php artisan wayfinder:generate --with-form
composer lint:check
php artisan test --compact
npm run test:frontend
npm run lint:check
npm run format:check
npm run types:check
npm run build
```

No hay modo sin conexión ni cola de sincronización móvil implementada en esta pantalla; los reintentos API usan el contrato existente de idempotencia. No se añadieron mapas ni un algoritmo nuevo de optimización. La asignación de sector es una etiqueta/instrucción sobre la ruta nativa y puede combinarse con su planificador CRM existente. El supervisor ve como máximo 50 rutas del período; las visitas sí se paginan. No hay edición general de visitas/sesiones cerradas: una rectificación posterior debe realizarse mediante el procedimiento administrativo de revisión, sin manipular resultados ni inventar contactos.

Reversión: la migración inicial deja intencionadamente persona/oportunidad como anulables al revertir para conservar visitas de puerta fría. No restablecer NOT NULL con registros nulos. Revertir la migración de sesiones elimina sesiones y metadatos; guardar copia antes. Para desactivar temporalmente, revocar los permisos nuevos, preservando los datos.

### Evidencia obtenida en esta entrega

- Suite PHP: 676 pruebas, 675 correctas y una omitida; 10.297 aserciones. La regresión afectada también pasó por separado (57 pruebas).
- Pruebas frontend Node: 7 correctas.
- Pint, ESLint, Prettier, TypeScript y compilación Vite: correctos; Vite mantiene su aviso de chunks mayores de 500 kB.
- Migración completa desde base SQLite vacía: correcta. API móvil previa y aislamiento de navegación: regresión verificada.
- WebKit con dispositivo iPhone 15 emulado: login, inicio de sesión, dos visitas, pausa, conversión y acceso a la oportunidad; 393 px sin desbordamiento horizontal. Panel de supervisor comprobado en escritorio.
- No se ha ejecutado contra PostgreSQL ni contra un staging externo o un iPhone físico. Validar esos entornos antes de autorizar un despliegue.
- `npm audit` detecta 8 avisos preexistentes (1 moderado, 4 altos, 3 críticos) en el lockfile sin modificar: baseline-browser-mapping, brace-expansion, browserslist, concurrently, js-yaml, maplibre-gl, shell-quote y source-map-js. La remediación de maplibre-gl propuesta por npm exige una versión mayor. No se mezcló esa actualización con este módulo; revisar antes de publicar producción.
