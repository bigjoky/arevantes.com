# Flujo de trabajo Git de Arevantes

## Ramas

- `main`: estado publicado en producción. Solo recibe cambios desde una pull request de lanzamiento.
- `develop`: integración de cambios ya revisados y preparados para la próxima publicación.
- `feature/<nombre>`: nuevas funcionalidades creadas desde `develop`.
- `fix/<nombre>`: correcciones normales creadas desde `develop`.
- `hotfix/<nombre>`: correcciones urgentes creadas desde `main`.

No se desarrolla ni se hacen commits directamente sobre `main`.

## Trabajo habitual

```bash
git switch develop
git pull --ff-only
git switch -c feature/nombre-breve
```

Los commits deben ser pequeños, coherentes y seguir esta forma:

```text
feat(compras): añade facturación parcial del transporte
fix(fiscalidad): corrige el redondeo del IVA
test(compras): cubre abonos parciales
docs(git): documenta el flujo de publicación
chore(deploy): actualiza el despliegue
```

Antes de subir una rama:

```bash
git status
git diff --check
composer validate --strict
php artisan test
npm run types:check
npm run build
```

Después:

```bash
git push -u origin feature/nombre-breve
```

Se abre una pull request contra `develop`. Solo se integra cuando las comprobaciones de GitHub pasan y la revisión confirma el alcance.

## Publicación

1. Abrir una pull request de `develop` a `main`.
2. Revisar migraciones, variables de entorno y compatibilidad con producción.
3. Esperar a que todas las comprobaciones terminen correctamente.
4. Integrar la pull request.
5. Crear una etiqueta de versión.
6. Ejecutar manualmente `deploy production` en GitHub Actions escribiendo `DEPLOY`.
7. Verificar la portada, `/up` y las funciones afectadas.

La integración en `main` no debe lanzar automáticamente una publicación.

## Seguridad y datos

- Nunca versionar `.env`, claves, contraseñas, bases de datos, copias de seguridad, documentos ni archivos de `storage`.
- No modificar una migración que ya se haya ejecutado en producción; crear una migración nueva.
- Una migración destructiva requiere copia de seguridad y revisión específica.
- Los cambios locales ajenos a la tarea activa se conservan y no se incluyen en sus commits.

## Correcciones urgentes

```bash
git switch main
git pull --ff-only
git switch -c hotfix/nombre-breve
```

El hotfix se revisa contra `main`, se publica y después se incorpora también a `develop`.
