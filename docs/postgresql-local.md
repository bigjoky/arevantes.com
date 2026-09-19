# PostgreSQL local de Arevantes

Comprobado el 12/09/2026: Apple Container y PostgreSQL funcionan; Laravel
conecta a la base `arevantes`. El contenedor no se ha recreado ni se han
cambiado credenciales o datos.

| Campo                           | Valor                                                             |
| ------------------------------- | ----------------------------------------------------------------- |
| Host estable desde macOS        | `127.0.0.1`                                                       |
| Puerto                          | `5432`                                                            |
| Base                            | `arevantes`                                                       |
| Usuario actualmente configurado | `developer` (administrador local compartido)                      |
| Contenedor                      | `apple-dev-postgres`                                              |
| Volumen                         | `apple-dev-postgres-data`, montado en `/var/lib/postgresql`       |
| Motor comprobado                | PostgreSQL 18.6                                                   |
| SSL local                       | Desactivado en servidor; cliente `disable`, o `prefer` en Laravel |
| Web local                       | `http://localhost:8000`                                           |

La contraseña está en `.env` y en la ficha privada entregada en
`output/arevantes-postgresql-local.txt`, excluida de Git y con permisos 0600.
No copiarla a este documento, commits o incidencias. El usuario no es exclusivo
de esta aplicación; no modificarlo sin evaluar los demás proyectos locales.

La IP interna del contenedor puede variar. Utilizar el puerto publicado en
loopback, no esa IP. Tampoco confundir el puerto web 8000 con PostgreSQL 5432.

Para diagnosticar:

```sh
container system status
container list
container exec apple-dev-postgres pg_isready -h 127.0.0.1 -p 5432
```

Si el motor está detenido, `container system start`; si el contenedor está
detenido, `container start apple-dev-postgres`. No borrar el volumen, ejecutar
`migrate:fresh` o cambiar contraseñas como primer intento de recuperación.

Se corrigió la URL local de Laravel para incluir el puerto 8000 y se limpió su
caché de configuración. No se ha diagnosticado una caída de PostgreSQL ni se
ha configurado un nuevo arranque automático del sistema.
