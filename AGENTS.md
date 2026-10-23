# Aplicación de diseños por secciones

Al aplicar un diseño proporcionado por el usuario, seguir el criterio permanente
«Implantación del nuevo diseño por secciones» de `docs/roadmap.md`:

- Revisar campos, filtros, acciones y permisos contra las capacidades reales.
- Implementar la interfaz y preparar contratos aditivos para los datos pendientes.
- Registrar en el roadmap unificado cada carencia con dependencias, estado y
  criterio de aceptación; actualizar también lo implantado y su verificación.
- Distinguir capacidad no disponible de datos consultados y vacíos. No fabricar
  datos ni habilitar acciones sin backend autorizado.
- Aplicar este criterio al libro diario y a las siguientes secciones. No anticipar
  diseños aún no proporcionados ni considerar publicado el trabajo local.

# Instancia local única

- Usar `http://localhost:8000` como única URL local de Arevantes.
- Iniciar esta copia con `./scripts/dev-local.sh`; Vite usa 5173 solo como auxiliar de recarga automática.
- Antes de iniciar un servidor, comprobar si 8000 ya está en uso y reutilizar la instancia; no abrir puertos alternativos.
- Los cambios de otras ramas o copias deben integrarse en la copia que sirve esa instancia antes de anunciarlos como visibles.
- Mantener separadas la base local y producción. Una prueba local no equivale a publicación.
