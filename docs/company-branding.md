# Logo y marca de empresa

Implementación local del 25/10/2026, integrada en la copia `ffe1` que sirve `http://localhost:8000`. No publicada en producción.

## Arquitectura

Se reutilizan `CompanyProfile`, `logo_path`, el disco privado y la operación transaccional de `FiscalSettingsController`. No hay migraciones ni un segundo sistema de ajustes. El nuevo POST `/company-fiscal/branding` permite modificar únicamente el recurso, sin reenviar ni sobrescribir los datos fiscales; conserva la autorización `manage-tax` del formulario original. El GET del recurso admite usuarios autenticados para poder mostrarlo en el shell a todos los roles, sin conceder escritura.

`App\Support\CompanyBrand` resuelve nombre, URL, estado personalizado y URI para PDF. `HandleInertiaRequests` comparte `branding`; `useCompanyBrand()` y `BrandLogo` lo consumen. Las vistas públicas y de acceso reciben el SVG oficial; los archivos privados personalizados se usan en el área autenticada y documentos. La URL contiene la referencia de archivo para invalidar caché al sustituirlo. Los archivos ausentes y la restauración usan el SVG maestro, nunca texto o un icono genérico.

`public/brand/arevantes-logo.svg` es una copia byte por byte del archivo entregado, sin optimización ni cambios de colores, paths o proporciones. SHA-256: `ccf9d9cb2ef4a56ed3ba9e89f8f87cd51ff378a10e8671ff971dbc7607a10394`. Se ha eliminado `arevantes-logo-light.svg`, el símbolo dibujado manualmente y los wordmarks de texto del shell. No se han alterado textos editoriales o nombres de empresa que no simulan un logotipo. El símbolo decorativo del sidebar se retira porque sus estilos sesgaban y recortaban la marca; el resto de estructura y navegación se conserva.

Conexiones: sidebar completo y plegado, cabecera, shell móvil, layouts de acceso, configuración Empresa y fiscal, portal/documento compartido, páginas públicas de inicio/servicios/legal, cabecera/pie públicos, metadatos corporativos y servicios PDF de presupuestos/venta/compra.

## Uploads

Máximo 2 MB. SVG validado como XML sin DTD ni entidades y sin acceso de red. Solo se permiten elementos geométricos y atributos estáticos: se rechazan scripts, eventos, estilos, imágenes, enlaces, foreignObject, instrucciones de procesamiento y referencias externas. Un SVG válido se almacena sin modificar. PNG/JPEG/WebP se comprueban como imágenes. Respuesta privada sin caché, con `nosniff` y CSP restrictiva. Sustitución transaccional y limpieza del recurso anterior; una validación fallida conserva el recurso existente.

## Verificación y límites

Resultado: **37 pruebas, 380 aserciones, todas correctas** en la copia de trabajo y en la instancia integrada. Pruebas: `ExpenseEvidenceAndCompanyLogoTest`, `CompanyRedesignTest`, `FiscalSettingsTest`, `QuotePdfTest`, `CompanyBrandPdfTest` y `SvgDisplayP3Test`. Cobertura del fallback, contrato Inertia después de recargar, bytes originales, permisos, persistencia, sustitución, restauración y rechazo de SVG peligrosos. TypeScript, build, Pint, ESLint y Prettier sobre los archivos afectados.

Resuelto el lector de colores PDF: parche reproducible y bloqueado por SHA-256 de `php-svg-lib`, instalado mediante Composer Patches. Interpreta la notación estática `color(display-p3 R G B)` del maestro y realiza la conversión estándar al espacio sRGB de salida del motor PDF, sin modificar el SVG, generar otra versión, ni rasterizar. No es un exportador PDF ICC de gama amplia ni un parser completo de CSS Color 4. Matrices y límites documentados en `patches/README.md`. Tests verifican los dos colores de salida y comandos vectoriales, con ausencia de objetos raster. Revisión visual de las tres páginas del presupuesto generado; también eliminada la recreación textual del logo en la cabecera de continuación. Los documentos archivados se mantienen intactos.

Integración finalizada en `ffe1` mediante fusión por archivo, conservando su navegación móvil, cambios de formularios públicos, rutas adicionales y el resto de su roadmap. Respaldo de los archivos previos fuera del repositorio en `/tmp/arevantes-branding-before-integration.tar.gz`; no se abrieron puertos nuevos. Validación con navegador en la instancia única: previsualización, subida del SVG, guardado, recarga con referencia privada persistida, restauración y nueva recarga con `/brand/arevantes-logo.svg`. Imágenes cargadas a 1890 × 288, `object-fit: contain` y sin filtros. Cuenta temporal eliminada tras la comprobación.

Inicialización exclusivamente local: la base SQLite tenía un logo WebP previo; se dejó `logo_path = null` para activar el maestro oficial solicitado, conservando ese archivo sin cambios y su referencia previa en el respaldo de QA. Las pruebas automatizadas usan SQLite en memoria; no se accedió a producción. La captura final está en `output/playwright/company-branding-restored.png`; muestra los colores oficiales también sobre el fondo existente del sidebar, conservado por petición expresa.

## Archivos modificados o añadidos

- `app/Http/Controllers/FiscalSettingsController.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Services/Documents/DocumentPdf.php`
- `app/Services/Documents/PurchaseDocumentPdf.php`
- `docs/roadmap.md`
- `public/brand/arevantes-logo-light.svg`
- `public/brand/arevantes-logo.svg`
- `resources/js/components/app-logo-icon.tsx`
- `resources/js/components/app-logo.tsx`
- `resources/js/components/app-sidebar-header.tsx`
- `resources/js/components/app-sidebar.tsx`
- `resources/js/components/brand-logo.tsx`
- `resources/js/components/mobile/mobile-experience.tsx`
- `resources/js/components/public/site-footer.tsx`
- `resources/js/components/public/site-header.tsx`
- `resources/js/pages/company/workspace.tsx`
- `resources/js/pages/fiscal-settings/index.tsx`
- `resources/js/pages/legal/show.tsx`
- `resources/js/pages/services/show.tsx`
- `resources/js/pages/welcome.tsx`
- `resources/views/app.blade.php`
- `routes/web.php`
- `tests/Feature/ExpenseEvidenceAndCompanyLogoTest.php`
- `app/Rules/SafeCompanyLogo.php`
- `app/Support/CompanyBrand.php`
- `docs/company-branding.md`
- `composer.json`
- `composer.lock`
- `patches.lock.json`
- `patches/php-svg-lib-display-p3.patch`
- `patches/README.md`
- `tests/Unit/SvgDisplayP3Test.php`
- `tests/Feature/CompanyBrandPdfTest.php`
- `tests/Feature/QuotePdfTest.php`
- `resources/views/pdf/quote.blade.php`
- `resources/views/pdf/sales-document.blade.php`
- `resources/views/pdf/purchase-document.blade.php`

## Ajuste posterior del sidebar

A petición expresa del usuario se elimina la línea decorativa externa (`.sidebar-brand::after`) y se aplica `brightness(0) invert(1)` únicamente a las imágenes de `.sidebar-brand`, tanto desplegado como plegado. Es una excepción visual autorizada para el sidebar; no modifica el SVG ni crea otra versión. Su trazo original se mantiene y también se presenta blanco. Fuera del sidebar se mantienen los colores oficiales.

Verificación del ajuste: navegador en `localhost:8000`, pseudoelemento `::after` ausente, filtro blanco en los dos logos del sidebar y `filter: none` en la vista previa de Empresa. SVG idéntico al original; Prettier y build correctos en ambas copias. Captura: `output/playwright/sidebar-white-logo.png`. Cuenta temporal de verificación eliminada.
