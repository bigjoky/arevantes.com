# Estructura del proyecto

```text
arevantes.com/
|-- app/                  Dominio y aplicacion Laravel
|-- bootstrap/            Arranque de Laravel
|-- config/               Configuracion del ERP
|-- database/             Migraciones, factories y seeders
|-- docs/                 Documentacion tecnica y operativa
|-- public/               Entrada web y recursos publicos
|-- resources/            Interfaz Inertia/React del ERP
|-- routes/               Rutas web, API y consola
|-- storage/              Datos generados en ejecucion
|-- tests/                Pruebas del ERP
`-- .worktrees/           Ramas de trabajo locales (ignorada por Git)
```

## Principios

1. La raiz es la unica aplicacion Laravel y conserva la estructura convencional.
2. Los artefactos generados y las dependencias no se duplican en Git.
3. Los worktrees son infraestructura local; no son modulos de produccion.
4. Ningun codigo puede depender de una ruta absoluta del equipo de desarrollo.

## Comandos

Desde la raiz:

```bash
composer dev
npm run build
```
