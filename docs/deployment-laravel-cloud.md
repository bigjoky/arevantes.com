# Publicación de Arevantes en Laravel Cloud

## Destino de producción

- Organización: `Joaquin`
- Aplicación: `Arevantes`
- Entorno: `production`
- Rama desplegada: `main`
- Región: `eu-central-1`
- URL técnica: `https://arevantes-production-qb0eq7.laravel.cloud`

Los identificadores públicos del destino están versionados en `.cloud/config.json`. Las claves API,
variables de entorno y credenciales nunca se guardan en el repositorio.

## Flujo de publicación

Publicación selectiva 05/10/2026: PR131, español predeterminado e inglés opcional,
sin fiscalidad pendiente de develop ni nuevas migraciones. Versión efectiva
`v2026.10.05.2`, commit `f5890858f81fbabc0429aac95b1f8eecd4e2f843`, deployment
`depl-a2e82a80-ddc1-460a-81fc-b038e2266f30` confirmado correcto. `/up` y `/login`
HTTP 200; HTML del login sin cookies en español. La etiqueta `v2026.10.05.1`
se creó antes del merge y referencia el baseline previo: no identifica este cambio.

1. Integrar mediante pull request los cambios verificados de `develop` en `main`.
2. Crear la etiqueta de versión correspondiente.
3. Laravel Cloud inicia automáticamente el despliegue de `main` en `production` mediante Push to
   Deploy.
4. Monitorizar el despliegue desde Laravel Cloud o con el CLI:

   ```bash
   cloud deploy:monitor Arevantes production -n
   ```

5. Confirmar que la versión publicada coincide con el commit de `main` y comprobar:

   ```bash
   curl --fail --show-error --silent https://arevantes-production-qb0eq7.laravel.cloud/up
   curl --fail --show-error --silent https://arevantes-production-qb0eq7.laravel.cloud/login >/dev/null
   ```

## Despliegue manual controlado

Si Push to Deploy no inicia el despliegue, se puede lanzar manualmente desde una copia limpia de
`main`:

```bash
cloud deploy Arevantes production -n
cloud deploy:monitor Arevantes production -n
```

Nunca se considera terminada una publicación hasta que el monitor indique éxito y las comprobaciones
HTTP respondan correctamente.

## Configuración actual

Laravel Cloud ejecuta estas fases:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan optimize
```

Después del build ejecuta las migraciones con `php artisan migrate --force`. Cualquier cambio en estos
comandos, variables, dominio, base de datos o caché debe revisarse primero en el entorno y documentarse
en la misma pull request.

## Persistencia y almacenamiento privado

- La base de datos de producción usa PostgreSQL.
- Los documentos privados se almacenan mediante `PRIVATE_FILESYSTEM_DISK=s3` en el bucket privado
  `arevantes-production-private`, ubicado en `eu-central-1`.
- Facturas, justificantes fiscales, documentos laborales, partes, firmas y fuentes de conocimiento
  se sirven siempre mediante controladores autenticados.

Antes de activar el bucket en un entorno que todavía conserve archivos locales, ejecutar:

```bash
php artisan storage:migrate-private local s3
```

El comando copia sin borrar el origen, omite objetos idénticos y verifica SHA-256 después de cada
escritura. Solo después de una migración correcta se configura `PRIVATE_FILESYSTEM_DISK=s3`.

## Controles de producción

- Mantener `APP_DEBUG=false` y un `APP_KEY` exclusivo para el entorno.
- Verificar periódicamente las copias de PostgreSQL y realizar pruebas de restauración.
- Ejecutar las colas como procesos persistentes y el scheduler mediante Laravel Cloud.
- Validar autenticación, CRM, finanzas, documentos privados, correo y `/up` después de cambios de
  infraestructura.

## Recuperación

El workflow SSH heredado no forma parte del flujo de publicación. La recuperación debe priorizar el
historial de despliegues de Laravel Cloud. Mientras siga existiendo el alojamiento anterior, su
procedimiento queda documentado en [`deployment-github.md`](deployment-github.md) únicamente como
referencia temporal.
