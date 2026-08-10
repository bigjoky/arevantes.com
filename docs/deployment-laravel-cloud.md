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

## Recuperación

El workflow SSH heredado no forma parte del flujo de publicación. La recuperación debe priorizar el
historial de despliegues de Laravel Cloud. Mientras siga existiendo el alojamiento anterior, su
procedimiento queda documentado en [`deployment-github.md`](deployment-github.md) únicamente como
referencia temporal.
