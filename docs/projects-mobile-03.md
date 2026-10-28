# AREVANTES ERP — Proyectos e Instalaciones Móvil 03

Entrega local en `feature/projects-mobile-03`, sobre main `8974e9b`. Sin push, PR, preview, cambios Cloud ni despliegue.

## Implantado

- Proyectos, instalaciones y resumen de Postventa usan tarjetas y controles táctiles hasta 1023 px. Se conserva la tabla de escritorio desde 1024 px. Las tarjetas añaden responsable, ubicación y fechas reales; las instalaciones no solares no presentan potencia FV.
- Ficha compartida con proyección técnica aditiva por servicio: paneles, marca de inversor y batería registrados; alcance/tensión/fases eléctricos; seguridad; recarga; climatización. Los valores ausentes siguen siendo desconocidos. No se infieren equipos, potencia ni certificaciones.
- Órdenes relacionadas abren su ficha real y conservan pestaña, filtros y paginación de origen. Se mantiene el acceso a Comercial, instalaciones, Postventa y adjuntos privados con los permisos existentes.
- Incidencias existentes: búsqueda y estado en móvil, instalación y orden vinculadas. Se reutilizan los formularios y endpoints existentes.
- Adaptador local de Inertia Form que reutiliza las guardas publicadas: confirmación de descarte, errores conservados, bloqueo síncrono de envíos y limpieza controlada. Los diálogos existentes de Postventa registran sus formularios para proteger el cierre, incluido Escape y exterior. Sin persistencia de borradores ni un motor global nuevo.
- Formularios de instalación permanecen montados al cambiar de viewport o abrir Menú; etiquetas de campos reutilizados de Postventa asociadas a controles. Diálogos móviles con desplazamiento y altura limitada al viewport y sus safe areas. Los botones fijos del formulario de instalación se sitúan en el flujo móvil y se reserva espacio inferior: Guardar deja de quedar tapado por la barra PWA.

## Verificación

- 78 pruebas frontend aprobadas: navegación segura, contexto de proyecto/orden, cierre de diálogo y regresión de las guardas publicadas.
- 40 pruebas backend aprobadas, 764 aserciones: contratos por servicio y valores desconocidos, permisos, proyectos, Postventa, documentos privados y flujos existentes.
- Build, TypeScript, ESLint, catálogo i18n, Pint y `git diff --check` correctos. Sin ampliar exclusiones de traducción. Advertencia de tamaño de chunks de Vite existente.
- 91 comprobaciones de rutas/viewports: 13 recorridos × 320, 390, 412, 768, 1023, 1024 y 1440 px. Sin desbordamiento horizontal, sin errores de consola en la compilación final y navegación inferior solo por debajo de 1024.
- QA aislada SQLite, usuarios y datos sintéticos, correo/caché en array y cola sync. Borrador de instalación retenido tras error/salida cancelada, Menú y 1023/1024. Incidencia: cierre y Escape confirmados, descarte y reapertura vacía, validación del servidor conserva descripción; doble clic crea exactamente un registro. Retorno de orden a pestaña de planificación y búsqueda original comprobado.
- Evidencias locales no versionadas: `/tmp/projects-mobile-03/` (matriz y captura). Estas pruebas no equivalen a validar dispositivos físicos.

## Límites reales

- iPhone y Android físicos, teclado virtual, lectores de pantalla y PWA instalada pendientes de comprobación en HTTPS autorizado.
- El catálogo de servicios del servidor admite solar, electricidad, seguridad, recarga y climatización. No hay contrato técnico específico de redes: se conserva el tipo y las notas que ya existan, sin inventar campos o un servicio nuevo.
- Una instalación/proyecto se representa en el modelo existente `Installation`; no se crea una entidad ni relaciones nuevas. Componentes y materiales detallados siguen en Equipos/gestión existente; la marca de inversor no se presenta como cantidad o modelo confirmado.
- Expediente gobernado, cierre, certificaciones, mantenimiento avanzado, compromisos y facturación conservan sus reglas y presentación funcional. No se certifica ni modifica un estado fuera del servidor. Las tablas administrativas extensas conservan su desplazamiento interno.
- No cambia la autorización: los permisos globales de instalaciones y el alcance por asignación de órdenes e incidencias son los existentes. No se introducen nuevas policies.
