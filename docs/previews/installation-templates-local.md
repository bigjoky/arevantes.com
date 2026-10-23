# Prueba local de instalaciones predefinidas

URL: http://localhost:8000/quotes/create

Cuenta exclusiva de demostración: `plantillas@example.test` / `Demo-Plantillas-2026!`.
Base local SQLite: `database/installation-templates.sqlite` (ignorada por Git).
Los ejemplos y precios son ficticios. No se han copiado datos de producción.

## Probar

1. Seleccionar una plantilla y pulsar «Añadir líneas al presupuesto».
2. Ajustar cantidades, precios, IVA o componentes. El cliente se elige en el formulario habitual.
3. Guardar el presupuesto como borrador.
4. Para crear otra plantilla, preparar las líneas y abrir «Guardar o actualizar una plantilla con las líneas actuales».
5. Darle un nombre y guardar como nueva plantilla, o actualizar la seleccionada.

Añadir una plantilla conserva las líneas existentes; sustituye únicamente la línea inicial vacía. Las copias tienen identificadores independientes, también para componentes y grupos de alternativas. Cambiar o eliminar una plantilla no modifica las propuestas existentes. Las plantillas no guardan cliente, proyecto ni datos personales.

## Arrancar de nuevo

Desde esta carpeta, con el `.env` local ya configurado:

```sh
php artisan migrate
php artisan db:seed --class=QuoteTemplateDemoSeeder
./scripts/dev-local.sh
```

El seeder solo funciona en local/testing. No despliega ni modifica producción.

La instancia única sirve esta copia del proyecto. Vite usa el puerto auxiliar 5173 para recarga automática; no es una segunda aplicación. No iniciar servidores alternativos en 8001, 8023 u otros puertos. Los cambios hechos en otras ramas o copias deben integrarse aquí para verlos en la instancia única.
