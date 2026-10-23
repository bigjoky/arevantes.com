# Lanzamiento general del ERP — 23/10/2026

Solicitud: publicar el diseño general acumulado y continuar después con mejoras
incrementales. Esta autorización sustituye la restricción de no publicar de los
cortes anteriores. No se copian datos locales, documentos ni archivos `.env`.

## Candidato y reconciliación

- Trabajo original conservado en `affd671`, rama `ai/sidebar-navigation-redesign`.
- Integración revisable en `release/general-redesign-2026-10-23`.
- Se incorpora `origin/develop` y el `main` ya publicado (`5cccc4a`).
- Inicio y shell nuevos prevalecen sobre versiones anteriores del mismo diseño.
- Se conservan visitas y sesiones comerciales/PWA, edición de pedidos,
  corrección de facturas emitidas, cobros de hitos previos a emitir factura y
  precisión de precios/total IVA incluido. Importes calculados con decimales,
  retención de historial/idempotencia y permisos conservados.
- Regresiones resueltas: acceso a reservas de planificación, identidad histórica
  del proveedor en pedidos, contratos de pruebas de catálogo/costes actuales y
  entrada Vite de páginas agrupadas (carga mediante el entry de Inertia).
- Traducciones de funciones publicadas y nuevas conciliadas por clave.

## Base de datos y salvaguarda

Producción: PostgreSQL 17.6, una empresa configurada. Se han reconciliado las
cuatro migraciones ya aplicadas que no estaban en la copia del diseño. Las doce
migraciones pendientes añaden campos/tablas/índices o protecciones; no borran
filas ni reconstruyen la base de datos. No hay presentación fiscal automática.
Las nuevas tablas de backend habilitan RLS y revocan acceso a los roles de API
pública de Supabase dentro de las migraciones de creación; una migración final
idempotente cubre bases locales donde ya existían. El propietario usado por
Laravel mantiene el acceso. Comprobado en PostgreSQL aislado.

La aplicación no tenía copias PostgreSQL regulares habilitadas. Se creó una
salvaguarda excepcional, sin alterar esa configuración ni ejecutar purgas:

- ID `02be34f0-bee5-4767-9e53-579ba7e2a769`.
- Disco privado de producción, objeto
  `release-snapshots/general-redesign-2026-10-23/02be34f0-bee5-4767-9e53-579ba7e2a769.dump.crypt`
  y manifiesto `.json`.
- Formato PostgreSQL custom cifrado con Laravel Crypt; conserva el APP_KEY
  actual para recuperarlo. No se almacena ninguna clave en Git o en este informe.
- Dump 2.071.336 bytes. SHA-256
  `c3ede385a7dea2302ead72d214693ddeaf0f6b4d42baf76396f2d431701ee563`.
- `pg_restore --list` correcto; lectura del objeto, descifrado y checksum
  correctos. No equivale a una prueba completa de restauración.
- Recuperación: obtener el objeto bajo autorización, descifrar mediante
  `Crypt::decryptString` con el APP_KEY vigente al crear la copia y restaurar el
  custom dump exclusivamente en una base aislada antes de cualquier recuperación
  productiva. El manifiesto identifica formato y checksum. No restaurar sobre
  producción como parte de esta publicación.

## Verificación del candidato

- Suite completa: 916 pruebas, 910 aprobadas y 6 omitidas; sin fallos.
- PostgreSQL aislado: 5 pruebas, 105 aserciones (concurrencia fiscal y guardas RLS).
- Navegación frontend: 30 + 16 + 16 + 15 aserciones correctas.
- TypeScript, ESLint, catálogo bilingüe, Composer y build comprobados.
- Continúa el aviso de tamaño de algunos chunks; no se ocultó ni se añadieron
  dependencias para este lanzamiento.
- Verificación visual previa de las secciones documentada en los informes
  individuales. Pendientes funcionales/datos no disponibles permanecen en el
  roadmap unificado, sin rellenar producción con datos de demostración.

## Publicación

Candidato preparado para PR a develop y posterior PR de lanzamiento a main.
Producción todavía debe confirmarse con el despliegue, commit efectivo,
migraciones y comprobaciones HTTP. Se completará este registro tras desplegar.
