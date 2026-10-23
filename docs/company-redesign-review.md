# Empresa — revisión de implantación local, 23/10/2026

Referencia visual y especificación del usuario leídas. Este informe distingue
backend operativo de interfaces preparadas y capacidades todavía pendientes.
No representa publicación ni certificación de seguridad del ERP completo.

## 1. Rama actual

`ai/sidebar-navigation-redesign`. Se preservó el árbol sucio encontrado al iniciar.
Estado y diff iniciales guardados en `/tmp/company-initial-status.txt` y
`/tmp/company-initial.diff`; huellas en `/tmp/company-baseline-hashes.json`.
No se ejecutó ninguna operación de commit, push, merge, rebase, stash o limpieza.

## 2. Archivos modificados

Nuevos:

- `app/Http/Controllers/CompanyWorkspaceController.php`, `CompanyStaffController.php`, `CompanyDocumentController.php`.
- `app/Http/Middleware/CompanyWorkspaceContext.php`.
- `app/Models/CompanyDocument.php`, `CompanyDocumentVersion.php`.
- `app/Services/Company/CompanyWorkspace.php`.
- `database/migrations/2026_10_23_020000_create_corporate_document_archive.php`.
- `resources/js/pages/company/{workspace,employee,document,common}.tsx`.
- `resources/css/company.css`.
- `tests/Feature/CompanyRedesignTest.php` y este informe; `docs/company-redesign-git-status.txt` recoge el estado final.

Integraciones imprescindibles modificadas: `routes/web.php`,
`app/Support/WorkspaceNavigation.php`, controladores `Staff`, `HrEmployment`,
`HrEmployeeDossier`, `HrLeave`, `HumanResources` y concern `ManagesPersonProfiles`;
catálogos `lang/{es,en}.json` y `resources/js/i18n/catalog.json`;
`WorkspaceNavigationAuthorizationTest.php`, `tests/frontend/workspace-navigation.mjs`
y `docs/roadmap.md`. El shell y sus componentes visuales no fueron reescritos.

## 3. Modelos y servicios existentes encontrados

Existían Person/Staff (empleado no equivale a User), HrEmploymentProfile,
HrDepartment, HrContractChange, HrEmploymentDocument, HrLeaveRequest,
HrEmployeeTask, CompanyProfile, permisos, AuditLog, SecurityEventRecorder y
herramientas de configuración fiscal/usuarios/seguridad/copias. Se reutilizan.
KnowledgeDocument es un índice de conocimiento de IA y se conserva sin
convertirlo en archivo corporativo. No había un archivo corporativo genérico
compatible: se añade únicamente ese dominio mínimo.

## 4. Arquitectura implementada

Controlador de consulta y DTO profesionales, servicio de agregados y consultas
SQL, controladores de escritura separados y tres páginas de ficha/listado.
Migración aditiva crea documentos y versiones con FK e índices. No modifica
contabilidad, nóminas, facturación ni motores fiscales. `down()` rehúsa destruir
historial si existen documentos. La migración se aplicó a PostgreSQL local;
no se ha desplegado remotamente.

## 5. Navegación

Empresa abre `/company`; conserva exactamente Personal, Documentos y
Configuración. Se reutiliza el chevron independiente. Los enlaces legacy
continúan disponibles según permiso. Rutas de ficha mantienen el apartado activo.
Se verificaron permisos de entrada y la navegación autorizada del servidor.
No se añade otro sidebar ni otra jerarquía.

## 6. Dashboard

Cuatro tarjetas reales: perfiles laborales activos de empleados, documentos
activos autorizados, estado de datos corporativos y vencimientos de 30 días.
No cuenta usuarios, colaboradores externos ni personas sin perfil como empleados
activos. Departamentos se calculan solo sobre perfiles activos con departamento
registrado; no se inventa una distribución para los restantes. Se reutilizan
contratos, caducidades y tareas existentes. Previews acotados, KPI global sin
truncar. `null` significa restringido; `0`/`[]` consultado y vacío.
No se implementa un porcentaje de configuración sin denominador verificable.

## 7. Personal

Listado con búsqueda, departamento, estado, orden SQL y páginas de diez. Ficha
profesional a ancho completo con retorno a filtros. Alta transaccional reutiliza
Person + actualización profesional HR + historial; exige número de empleado
único, sin crear User. Validación fallida revierte el alta. Edición controla una
huella obsoleta y mantiene auditoría/historial. No acepta salario como campo de
esta escritura. Se conserva el expediente avanzado autorizado existente.
No se duplica el motor de administración HR ni se crean cuentas automáticas.

## 8. Contratos, formación y ausencias

Contratos reutilizan perfil e historial profesional; documentos y cambios se
paginan. Formación consulta certificados/prevención ya existentes y solo con
permiso sensible. No se infieren curso, emisor ni realización: registro
estructurado pendiente. Ausencias muestran únicamente fechas y estado;
se omiten motivos, tipos médicos, notas y adjuntos. La lectura legacy de
ausencias redirige al resumen seguro cuando falta permiso sensible.
Las herramientas avanzadas conservan sus reglas existentes; no se duplica
el servicio de bajas, vacaciones o aprobación.

## 9. Documentos y almacenamiento

Carga real PDF/JPEG/PNG/Word hasta 12 MB, extensión y MIME comprobados; validación
Word y antivirus si ya está configurado mediante SafeUpload. Nombre saneado,
almacenamiento privado, título/descripción/categoría/caducidad y visibilidad.
Metadatos y archivo se registran con transacción y limpieza si falla BD.
Reintento inicial con token/fingerprint no duplica; un cuerpo distinto produce 409.
Nueva versión conserva anteriores, exige revisión vigente y documento activo;
archivo conserva historial. Versiones con autor y fecha, listado e historial
paginados. Descarga verifica permiso, relación documento/versión, existencia y
SHA-256; usa attachment, no-store y nosniff. No se envían paths, tokens o hashes
al navegador. No se ha implementado visor activo de archivos ni eliminación
permanente. Categorías son reales; carpetas y enlaces a otros módulos pendientes.

## 10. Configuración

Se consulta CompanyProfile con whitelist de campos de empresa. Edición delega
al endpoint fiscal existente y su permiso `manage-tax`, conservando validación
y auditoría. No se vuelcan IBAN/BIC, secretos o rutas de logo al nuevo payload.
Se reutilizan marca canónica y herramientas de recursos corporativos.
Preferencias, numeración, seguridad, auditoría y copias enlazan a destinos
existentes. Estado descriptivo; no se simula exportación/restauración o checklist del 85%.

## 11. Usuarios y permisos

Se reutiliza PermissionResolver y gates del servidor, no ocultación meramente
visual. Personal: manage-staff. Archivo: lectura admin o personal, restringido
solo admin; carga/edición/versiones solo manage-admin. Configuración: admin o
manage-tax; edición solo manage-tax; enlaces a usuarios/auditoría según sus
gates. Acceso sensible laboral separado. Se comprueban enlaces antiguos y se
redactan datos personales en DTO profesionales/listados HR; los documentos
«employee» de otro empleado no se vuelven visibles por manage-staff.
No se cambió la pertenencia de usuarios ni se concedieron permisos nuevos.

## 12. Integraciones

Existían herramientas distribuidas; se conservan. Pestaña preparada con estado
honesto no disponible. No hay verificación universal de conexión ni nuevo
editor de notificaciones corporativas. Pendientes UI-COMP-12/13, sin conexión
ficticia ni desarrollo Nexo.

## 13. Auditoría

Se reutilizan observers de Person/CompanyProfile/HR e historial contractual.
Archivo corporativo registra alta, cambios de campos y nueva versión sin
volcar contenido, rutas o descripciones privadas al log. Descargas generan
SecurityEventRecorder con IDs. Historial consultable y paginado. No se inventan
actores o eventos para llenar las vistas.

## 14. Seguridad

Permisos backend y whitelist, descargas privadas, integridad, subida limitada,
transacciones y revisiones. Pruebas cubren IDOR entre documentos/versiones,
privilegios insuficientes, disco público rechazado, corrupción/ausencia del
archivo, archivo ejecutable/MIME incoherente, historial conservado y limpieza
por error de auditoría. Se bloquean nuevas rutas si hay varios CompanyProfile:
no existe un contexto multiempresa completo para HR. No se afirma aislamiento
multiempresa global, antivirus operativo sin configuración, WORM ni protección
contra escrituras directas SQL. Revisión transversal HR antigua pendiente.

## 15. Responsive

Revisión local en navegador de Codex a 1440×1000, 820×1180 y 390×844:
Empresa, Personal, Documentos y Configuración; ficha profesional desktop/móvil
y carga privada móvil. Tablas con scroll interno y tarjetas móviles, pestañas
horizontales, formularios con etiquetas y foco. Colores de Empresa acotados a
su CSS; preferencias del shell conservadas. No se subieron archivos ni crearon
empleados de demostración en la base local. Evidencias `/tmp/company-*.png`.
No se certifica comparación pixel a pixel ni todos los lectores de pantalla.

## 16. Tests ejecutados y resultados

- Baseline integrado anterior:72 pruebas,928 aserciones aprobadas.
- Corte final dirigido:100 pruebas,1165 aserciones aprobadas (incluye Empresa,
  HR, datos fiscales/logo, autorizaciones de navegación, seguridad y traducciones).
- Empresa:22 pruebas; cubren lectura/escritura, separación Employee/User,
  rollback/duplicados, datos sensibles, páginas/filtros, archivo/versiones,
  permisos, integridad e idempotencia. Fixture SQLite aislado y disco temporal.
- Navegación frontend:30 aserciones aprobadas.
- Suite completa:871 pruebas,862 aprobadas,3 fallos,1 error y5 omitidas.
  Incidencias anteriores:CatalogTest espera catalog/index pero recibe
  supply/products; ExecutiveAnalyticsTest falta profitability.planned_margin;
  SupplierIdentityTest falta supplierIdentity.legal_name;
  PlanningScenarioTest recibe null en assertCount. No se alteraron esos módulos.
- TypeScript, ESLint global y catálogo ES/EN correctos; Pint aplicado a los
  archivos de este corte. Ninguna prueba de carga sobre datos locales reales.
- Última paginación y permisos HR verificados adicionalmente con selección
  dirigida. No hay prueba simultánea de concurrencia PostgreSQL certificada.

## 17. Resultado del build

Build Vite correcto. Mantiene aviso de chunks mayores de 500 KB del proyecto;
no se añadieron dependencias ni se hizo un cambio global de empaquetado.

## 18. Git status y resumen del diff

Árbol previamente sucio preservado. Comparación SHA-256 contra baseline delimita
los cambios de esta tarea a Empresa, navegación, permisos de integración,
traducciones y pruebas/documentación. No se modificaron los archivos previos de
Facturación/Contabilidad/Finanzas/Fiscal. Sin borrados de archivos preexistentes.
Estado/diff finales conservados como evidencia local; `git diff --check` correcto.
No se interpreta la existencia de cambios locales como publicación en develop.

## 19. Funcionalidades pendientes

Roadmap unificado UI-COMP-09..17: multiempresa, formación estructurada,
carpetas/relaciones, checklist verificable, integraciones/canales corporativos,
retención y recuperación reforzadas, concurrencia legacy integral y alertas
completas. Herramientas de usuarios, fiscalidad y seguridad se reutilizan como
accesos a sus implementaciones reales, no como una reimplementación nueva.

## 20. Limitaciones y deuda técnica

Sin cambios destructivos ni datos ficticios en la base local. La unicidad del
número laboral no deduplica identidades humanas con distintos números. El
archivo nuevo no importa automáticamente adjuntos antiguos ni documentos de IA.
Versiones inmutables por ORM no equivalen a inmutabilidad de infraestructura.
El flujo nuevo controla revisiones obsoletas; los formularios legacy no comparten
todos esos bloqueos. La edición corporativa reutiliza el endpoint existente,
sin nueva revisión optimista global. Previews de tareas/vencimientos limitados;
no equivalen a un registro completo ni al envío de notificaciones.
El diseño de referencia orienta la composición; datos, permisos y capacidades
reales prevalecen sobre cifras y acciones dibujadas. Trabajo local revisable,
pendientes explícitos: no se declara una implantación productiva completa.

Estado al cierre original de este informe: commit, push y merge no realizados.
La publicación posterior se documenta en release-general-redesign-2026-10-23.md.
