# Publicación de Arevantes mediante GitHub

La instalación de producción empieza vacía. La base de datos, los documentos y el archivo `.env` local no se copian al servidor.

## 1. Requisitos del alojamiento

- Acceso SSH mediante clave.
- PHP 8.4.1 o superior con `mbstring`, `intl`, `PDO SQLite` y `SQLite3`.
- Posibilidad de cambiar la raíz del dominio a la carpeta `public` del proyecto.
- Cron con frecuencia de un minuto.
- Certificado SSL para `arevantes.com` y `www.arevantes.com`.

La raíz pública del dominio debe apuntar a:

```text
RUTA_DE_DESPLIEGUE/public
```

## 2. Archivo de entorno del servidor

Crear `RUTA_DE_DESPLIEGUE/.env` directamente por SSH. Nunca guardarlo en GitHub. Partir de `.env.example` y establecer como mínimo:

```dotenv
APP_NAME=Arevantes
APP_ENV=production
APP_KEY=base64:CLAVE_GENERADA
APP_DEBUG=false
APP_URL=https://www.arevantes.com
APP_LOCALE=es

DB_CONNECTION=sqlite
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=log
FORTIFY_REGISTRATION_ENABLED=false

MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=contact@arevantes.com
MAIL_FROM_NAME=Arevantes
```

Antes del primer despliegue, generar una clave desde la copia local del proyecto:

```bash
php artisan key:generate --show
```

Copiar el valor mostrado en `APP_KEY` dentro del `.env` remoto. Este comando no modifica el `.env` local.

Las credenciales de correo, Huawei, Deye e inteligencia artificial se completan únicamente en este archivo del servidor.

## 3. Secretos de GitHub

En el repositorio, abrir **Settings → Environments → New environment** y crear `production`. Añadir estos secretos:

| Secreto          | Contenido                               |
| ---------------- | --------------------------------------- |
| `DEPLOY_HOST`    | Host SSH o `212.227.231.36`             |
| `DEPLOY_PORT`    | Puerto SSH, normalmente `22`            |
| `DEPLOY_USER`    | Usuario SSH del alojamiento             |
| `DEPLOY_PATH`    | Ruta absoluta de la aplicación          |
| `DEPLOY_SSH_KEY` | Clave SSH privada exclusiva para GitHub |

La clave pública correspondiente debe añadirse a `~/.ssh/authorized_keys` en el alojamiento.

## 4. Cron para alojamiento compartido

Configurar estas dos tareas en el panel del proveedor, sustituyendo la ruta y el ejecutable PHP si es necesario:

```cron
* * * * * cd RUTA_DE_DESPLIEGUE && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd RUTA_DE_DESPLIEGUE && php artisan queue:work --stop-when-empty --tries=3 --timeout=120 >> /dev/null 2>&1
```

No se inicia Laravel Reverb en alojamiento compartido. La plataforma funciona sin tiempo real mediante `BROADCAST_CONNECTION=log`. Si el proveedor admite procesos residentes, Reverb puede configurarse posteriormente.

## 5. Primer despliegue

En GitHub abrir **Actions → deploy production → Run workflow**, escribir `DEPLOY` y ejecutar. El flujo:

1. Compila PHP y React.
2. Comprueba la versión PHP remota.
3. Activa el modo mantenimiento si ya existe una instalación.
4. Sincroniza el código sin tocar `.env`, almacenamiento ni SQLite.
5. Ejecuta migraciones y optimizaciones.
6. Comprueba `https://www.arevantes.com/up`.

## 6. Primer administrador

Después del primer despliegue, entrar por SSH y ejecutar:

```bash
cd RUTA_DE_DESPLIEGUE
php artisan arevantes:create-admin correo@arevantes.com --name="Nombre del administrador"
```

La contraseña se solicita de forma oculta y no queda guardada en GitHub ni en el historial de la terminal.

## 7. Sitemap

Cuando la web responda por HTTPS, Google podrá volver a leer:

```text
https://www.arevantes.com/sitemap.xml
```
