# Despliegue en Laravel Cloud

Laravel Cloud es el destino de los nuevos despliegues. El workflow SSH de GitHub se conserva temporalmente como mecanismo de recuperación mientras se valida Cloud y se migra la producción actual.

## Estrategia de transición

1. Crear un entorno `staging` en una región de la UE con PostgreSQL 18 de desarrollo.
2. Configurar variables, workers, scheduler y endpoint `/up`.
3. Ejecutar migraciones y pruebas de humo sin datos de producción.
4. Migrar adjuntos al bucket privado S3-compatible de Laravel Cloud.
5. Ensayar la migración de SQLite a PostgreSQL y reconciliar recuentos e importes.
6. Crear el entorno `production`, importar los datos, validar y cambiar el dominio.
7. Retirar el despliegue SSH cuando haya pasado el periodo de observación.

## Requisitos antes del corte de producción

- Configurar `PRIVATE_FILESYSTEM_DISK=s3` para documentos que deban sobrevivir a un despliegue.
- Verificar las copias gestionadas de PostgreSQL y realizar una restauración de prueba.
- Mantener `APP_DEBUG=false` y generar un `APP_KEY` exclusivo por entorno.
- Ejecutar la cola como proceso persistente y el scheduler mediante Laravel Cloud.
- Validar autenticación, CRM, generación de presupuestos, subida y descarga de adjuntos, correo y `/up`.
- Comparar por tabla los recuentos y totales financieros entre SQLite y PostgreSQL.

## Operación

Todos los comandos de Laravel Cloud se ejecutan de forma no interactiva y los despliegues se monitorizan hasta obtener un estado terminal. Un despliegue no se considera completado únicamente porque el código se haya enviado.

La organización del proyecto queda fijada en `.cloud/config.json`. Los secretos y variables de entorno nunca se guardan en Git.

## Almacenamiento privado

El bucket `arevantes-production-private` está en `eu-central-1`, bajo jurisdicción UE, con visibilidad privada. Facturas, justificantes fiscales, documentos laborales, partes, firmas y fuentes de conocimiento se sirven siempre mediante controladores autenticados.

Antes de activar el bucket en un entorno con archivos locales, ejecutar:

```bash
php artisan storage:migrate-private local s3
```

El comando copia sin borrar el origen, omite objetos idénticos y verifica SHA-256 después de cada escritura. Solo después de una migración correcta se configura `PRIVATE_FILESYSTEM_DISK=s3`.
