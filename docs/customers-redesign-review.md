# Entrega: Comercial → Clientes

Fecha: 6 de octubre de 2026. Implementación local, pendiente de revisión.

## 1. Rama utilizada

`ai/sidebar-navigation-redesign`. Se mantienen todos los cambios pendientes de las fases anteriores.

## 2. Archivos de esta fase

Backend:
- `app/Http/Controllers/PersonController.php`
- `app/Services/Customers/CustomerWorkspace.php` (nuevo)
- `app/Services/Customers/CustomerProfile.php`

Frontend:
- `resources/js/pages/people/index.tsx`
- `resources/js/pages/people/show.tsx`
- `resources/js/pages/people/legacy-index.tsx` (conserva interfaz compartida con personal)
- `resources/js/pages/people/legacy-show.tsx` (conserva personal y exporta formularios de contactos existentes)
- `resources/js/components/customers/customer-list.tsx`
- `resources/js/components/customers/customer-detail.tsx`
- `resources/js/components/customers/types.ts`
- `resources/js/components/customers/use-customer-navigation.ts`
- `resources/css/app.css` (solo reglas añadidas y limitadas a Clientes)
- `resources/js/i18n/catalog.json` (entradas bilingües nuevas)

Pruebas y documentación:
- `tests/Feature/CustomerProfileTest.php` (contrato de la nueva ficha)
- `tests/Feature/CustomersRedesignTest.php`
- `tests/frontend/customer-navigation.mjs`
- `docs/roadmap.md`
- Este informe.

## 3. Modelo encontrado

`Person`, con `category=customer`, SoftDeletes y teléfono normalizado existente.
Identidad: nombre, apellidos, razón social, tax_id/document_number. Clasificación:
residential/business/community/public_sector y lead/active/inactive/former.
Incluye email, teléfono, dirección general, fiscal, idioma, canal, consentimiento,
intereses y origen. Relaciones reales: contactos, etiquetas, consentimientos,
oportunidades, instalaciones propias/compartidas y portal. Quote y SalesDocument
se relacionan mediante person_id. No se ha creado otra entidad cliente.

## 4. Listado

Búsqueda remota con debounce de 300 ms por nombre completo, razón social,
identificación fiscal/documental, email y teléfono. Filtros: estado, tipo,
provincia, origen, etiqueta, con oportunidades, con presupuestos y archivados.
Orden: nombre, alta, última actividad CRM y volumen autorizado. Paginación real:
15/30/50 registros; search, filtros, sort, direction y page en URL. Los posibles
duplicados no requieren descargar toda la cartera al navegador.

## 5. Volumen

Suma de `SalesDocument.amount`, IVA incluido, de tipo invoice y estado
issued/partial/paid/overdue del cliente, todos los periodos. Excluye borradores y
anuladas. No descuenta rectificativas: es facturación bruta emitida, no ventas
netas, cobros ni valor de oportunidades. El pendiente usa amount-paid_amount
sobre issued/partial/overdue. El bloque Facturas también muestra borradores y
anuladas como documentos, sin incorporarlos al volumen.

## 6. Última actividad

La columna «Última actividad CRM» procede de MAX(CrmActivity.created_at) para
registros directos de person_id, sin instalación ni orden de trabajo y excluyendo
borrados. Representa el último registro CRM directo, no todos los contactos ni
la última modificación de Person. La fuente se explica en el encabezado.

## 7. Ficha 360º

`/people/{id}` permite abrir otra pestaña, copiar URL y refrescar. En escritorio
conviven listado y detalle; debajo de 1280 px se muestra el detalle separado con
«Volver a clientes». Las pestañas son enlaces semánticos con aria-current y
navegación por teclado; tab y relation_page viven en URL. El cierre conserva
search, filtros y página. Resumen limita cada relación a tres registros; las
pestañas consultan y paginan únicamente su dominio.

## 8. Oportunidades

Opportunity.person_id; etapa real, referencia, importe estimado y propietario de
la oportunidad. Enlace a `/opportunities/{id}`. No modifica Kanban, pipeline,
filtros, notas, tareas ni permisos del CRM.

## 9. Presupuestos

Quote.person_id; estado, importe, número y fecha. Enlace al presupuesto original.
«Crear presupuesto» abre `/quotes/create?customer={id}`; el controlador existente
preselecciona el cliente. No se guarda ningún documento desde la ficha.

## 10. Proyectos

EngineeringDossier relacionado por oportunidad del cliente o por instalación
propia/compartida. Se identifica como expediente técnico y enlaza al ancla real
`/engineering#dossier-{id}`. No se inventa un proyecto a partir de una oportunidad.

## 11. Instalaciones

Installation.person_id y pivot installation_person, sin duplicar instalaciones
compartidas. Muestra referencia, estado y ubicación/comercial de la instalación;
enlaza a su ficha original. No atribuye ese comercial al cliente.

## 12. Facturación

SalesDocument de tipo invoice por person_id. Consulta, importes, pendiente,
volumen y pestaña requieren manage-installations, el gate real de `/sales/{id}`.
Con permiso de Clientes únicamente no se serializan datos financieros, ni
presupuestos/proyectos/documentos de dominios restringidos. Solicitar directamente
una pestaña no autorizada devuelve 403.

## 13. Documentos

WorkOrderAttachment con kind=document, de órdenes vinculadas a instalaciones
propias/compartidas. Expone origen (orden), tamaño, fecha, estado y descarga
original. Requiere manage-operations y aplica asignación de orden para técnicos.
No serializa path ni crea repositorio paralelo. El modelo no tiene relación
directa de Person con un repositorio general de archivos.

## 14. Actividad

CrmActivity, OpportunityEvent, Quote, SalesDocument (invoice) y WorkOrder forman una consulta UNION paginada, ordenada por
fecha; incluye tareas/llamadas/notas y eventos reales de oportunidad. Los eventos
enlazan a la oportunidad; las actividades operativas requieren manage-operations
para mostrarse/enlazarse. Se aplican las restricciones de técnicos. No pretende
ser auditoría consolidada de todos los dominios.

## 15. Contactos

CustomerContact soporta contactos múltiples, principal y roles. Se muestran en
Resumen. «Gestionar contactos, etiquetas y preferencias» reutiliza los formularios
existentes y sus endpoints; no crea un segundo CRUD ni fusiona automáticamente.

## 16. Direcciones

Person contiene dirección general y fiscal; Installation contiene ubicación de
obra independiente. Se muestra la fiscal cuando difiere para evitar duplicados.
No se asume que la dirección del cliente es la dirección de instalación. El
formulario real continúa gestionando ambas.

## 17. Permisos y alcance

Clientes/CRM: manage-customers; Presupuestos: manage-quotes; Proyectos e
Instalaciones: view-installations; Facturas: manage-installations; Documentos:
manage-operations. Actividad CRM directa y eventos de oportunidad se pueden ver
con manage-customers; información operativa respeta manage-operations y las
restricciones reales de técnicos. No existe asignación directa comercial-cliente
ni scope «mis clientes» que pueda aplicarse sin inventarlo. Se conserva el sistema
de perfiles y overrides existente.

## 18. Rendimiento

Listado paginado con withCount y subconsultas de agregación; no carga relaciones
por cada fila. Prueba con 1 y 31 clientes: número de consultas constante (máximo
6 en el servicio de listado). Eager loading acotado de propietarios/comerciales y
origen documental. Resumen: tres por dominio; pestañas: 15 por página. El contrato
anterior customer360 queda opcional y autorizado, sin cargarlo por defecto. No
hay migraciones ni dependencias nuevas.

## 19. Responsive y accesibilidad

1536 px: master/detail; 834 px: ficha separada; 390 px: lista en tarjetas, ficha a
pantalla completa, pestañas desplazables y regreso con búsqueda. Comprobado ancho
390/390 px sin desbordamiento horizontal. Links/buttons/labels semánticos, foco
visible, encabezados de tabla, estado de carga y skeletons. Respeta el tema actual
del shell, sin modificar preferencias globales.

## 20. Validación

73 tests PHP pasan; la comprobación adicional del timeline restringido también pasa: 55 de la suite principal y 18 de
regresión Clientes/Personal/Acceso. Pasan 15 aserciones frontend de Clientes y
16 de navegación, TypeScript, ESLint, i18n (4.234 textos visibles; 4.722 entradas
bilingües), Pint, Prettier y git diff --check. Build final correcto en 14,91 s, con el
aviso previo de chunks mayores de 500 kB. Se cubren búsqueda,
filtros, orden, paginación, deep links, autorización, agregación financiera,
relaciones reales, documentos de técnicos, fechas, duplicados y consultas.
También se ejecutan los tests existentes de Clientes, Portal, CRM, Dashboard
Comercial y Navegación, TypeScript, ESLint, catálogo bilingüe, Pint, Prettier y
build. En navegador se comprobaron las siete pestañas de relaciones, refresh,
búsqueda, edición existente, presupuesto preseleccionado y responsive.

## 21. Git status

Se conserva el estado modificado y sin seguimiento anterior, junto con los
archivos de esta fase. No hay staging, operaciones de entrega ni publicación.
No se modifica .env, secretos ni datos de negocio.

## 22. Resumen de diff

Cambios limitados a Clientes, integración de lectura autorizada, estilos locales,
catálogo, pruebas y documentación. Comparación por hashes al inicio de la fase:
CRM, Dashboard Comercial, Sidebar, AppShell, modelos y routes/web.php preservados.
Wayfinder regenera archivos ignorados de PersonController durante el build.

## 23. Límites y deuda técnica

Responsable directo/comercial del cliente y su filtro pendientes; propietarios de
opps/comerciales de instalaciones no son esa asignación. Documentos cubre el
sistema real de órdenes, no un repositorio cliente inexistente. Última interacción
consolidada entre dominios y filtros de inactividad pendientes. No añade sectores,
imágenes, sedes múltiples ni validación fiscal completa. Coincidencias por
NIF/email/teléfono generan advertencia limitada a cinco perfiles, sin merge.
Todos estos puntos tienen dependencias y aceptación en el roadmap. No se amplía
ningún otro módulo.

## 24. Confirmación

Commit: NO realizado
Push: NO realizado
Merge: NO realizado
