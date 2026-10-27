# Rediseño corporativo público — 26/10/2026

## Auditoría y alcance

Stack conservado: Laravel, Inertia 3, React 19, TypeScript y Vite. Portada previa en `welcome.tsx`; tres servicios configurados, portfolio publicado con imágenes y páginas individuales. SVG originales en `public/brand/arevantes-logo.svg` y `arevantes-logo-dark.svg`, sin modificaciones. Se usa explícitamente `canonicalLogoUrl` en el sitio público, incluso con sesión iniciada. Verde oficial `#003c32`, Instrument Sans existente y Georgia del sistema para titulares; sin dependencias nuevas.

Contacto conservado: POST `/contact`, CSRF de Inertia, throttle 5/minuto, honeypot `company_url`, consentimiento, validación de email/teléfono y mensaje, deduplicación, CRM, asignación y notificaciones. El formulario público nuevo utiliza ese mismo contrato. No se ha modificado `WebsiteContactController`, el catálogo CRM, modelos ni bases de datos.

Se ha añadido exclusivamente la exclusión de `public/*` del layout autenticado y de la carga de contexto ERP. El resto de condiciones de layout, rutas autenticadas y componentes del ERP se conserva. No se han creado migraciones, cuentas ni solicitudes de prueba en la base local o en producción.

## Páginas y componentes

- Inicio: hero, cuatro áreas, hasta tres proyectos publicados, proceso de cuatro fases, empresa y CTA. El ancla antigua `#contacto` conduce al CTA, desde el que se accede al formulario independiente.
- `/servicios`: índice de las cuatro áreas.
- `/servicios/energia`, `/servicios/electricidad`, `/servicios/seguridad`: rutas conservadas, con nuevo hero, soluciones, proceso, proyectos consultados y preguntas frecuentes.
- `/servicios/redes`: página editorial y contacto por correo, sin simular soporte de categoría CRM.
- `/empresa`, `/proceso`, `/contacto`: páginas independientes.
- `/proyectos` y `/proyectos/{slug}`: portfolio y ficha conservan datos, filtros admitidos, imágenes, fechas, localizaciones publicables y relacionados del backend. Estado vacío explícito cuando una consulta devuelve cero resultados. Sin casos nuevos ni cifras añadidas.
- Alias descriptivos de los tres servicios redirigen con 301 a las rutas existentes.
- Actualidad: futura página pública bajo la misma familia `public/*`; no se publica ruta, enlace vacío ni contenido ficticio.

Componentes compartidos: cabecera con desplegable nativo y menú móvil, footer, layout con SEO, migas de pan, enlaces, CTA, cuadrícula de servicios, proceso y sección/tarjeta de proyectos. Estilos encapsulados bajo `.av-site` en `resources/css/public-site.css`; no se cambia el CSS del ERP. Foco visible, skip link, etiquetas y errores asociados, Escape para cerrar navegación, movimiento reducido, tamaños de imágenes y lazy loading fuera del hero.

## Archivos

Nuevos: `PublicPageController.php`, `resources/css/public-site.css`, `components/public/site-content.ts`, `site-layout.tsx`, `pages/public/page.tsx`, `contact.tsx` y `PublicCorporatePagesTest.php`.

Actualizados: `routes/web.php`, `SitemapController.php`, `HandleInertiaRequests.php` (exclusión de páginas públicas), `config/public_services.php` (nombres/título), `resources/js/app.tsx` (una condición de layout público), cabecera/pie/tarjeta públicos, portada, servicios, índice/ficha de proyectos, `resources/views/app.blade.php` (metadatos públicos), `WebsiteMetadataTest.php` y roadmap.

## Fotografía y capacidades pendientes

Se reutilizan los WebP ya incluidos en el repositorio. Las fotografías de servicios se identifican como ilustrativas, sin atribuirlas a una ejecución. El portfolio utiliza únicamente el estado de publicación existente; la publicación previa no constituye una nueva auditoría de procedencia de cada fotografía. No se han usado imágenes generadas ni añadido miembros, testimonios, proyectos o estadísticas.

La fotografía panorámica aprobada del mockup no está disponible como recurso independiente con procedencia/licencia acreditada. No se extrae del mockup ni se usa la captura como fondo. Para igualar su dirección fotográfica deben aportarse originales autorizados; el layout y las referencias de imágenes permiten sustituirlos sin rehacer componentes.

Redes usa una composición gráfica de cableado claramente ilustrativa, pendiente de fotografía propia. La consulta por correo está disponible; habilitar la categoría en el formulario exige una ampliación autorizada del catálogo e integración CRM. No se asignan estas consultas a electricidad o mantenimiento.

Las marcas LONGi, Huawei, Deye y FelicityESS se mencionan como habituales, sin logotipos inventados ni acreditación oficial. No se ofrecen fichas técnicas inexistentes. La historia detallada, retratos y credenciales de equipo requieren material verificable del cliente.

## Verificación e instancia local

Se instalaron las dependencias bloqueadas existentes con Composer y npm. Tipos, build, ESLint de archivos afectados, Prettier y Pint comprobados. El build conserva avisos de tamaño de bundles existentes.

67 pruebas de backend y 674 aserciones correctas (públicas, portfolio, contacto, SEO, autenticación y dashboard) en SQLite en memoria; pruebas frontend del repositorio: 32/32. La validación de contacto comprueba el flujo CRM en pruebas aisladas, no una entrega real de correo a destinatarios externos.

Playwright sobre la única instancia `http://localhost:8000`: diez páginas a 390, 768 y 1440 px (30 recorridos), HTTP 200, sin errores JavaScript ni desbordamiento horizontal. Revisados visualmente portada móvil/escritorio, renovables móvil/escritorio y contacto. Menú móvil, Escape y preselección `electrical` comprobados. Los 23 enlaces internos únicos detectados responden HTTP 200. Capturas en `output/playwright/public-*.png`. No se afirma una auditoría WCAG completa ni prueba en dispositivos físicos.

La instancia existente sirve la copia `ffe1`. Los archivos de esta intervención se integraron selectivamente en esa copia, contrastando su contenido de partida y conservando trabajo ajeno. En `app.tsx` se añadió únicamente la condición pública. No se inició otra instancia ni se abrió otro puerto. Cambios locales, sin publicación ni despliegue.
