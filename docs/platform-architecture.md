# Arquitectura modular de la plataforma

Esta guía define los límites que permiten desarrollar varias áreas en paralelo sin volver a concentrar rutas, navegación, permisos o contratos en un único archivo.

## Ramas de área

Las ramas nuevas parten de `develop` y usan nombres directos, sin el prefijo `topic/`:

```text
crm-sales-evolution
operations-field-service
supply-chain-evolution
finance-accounting-evolution
human-resources-evolution
```

Cada rama mantiene su alcance de dominio. Los cambios transversales se coordinan primero en `platform-foundation` o en una rama específica de integración.

## Rutas por dominio

`routes/web.php` contiene únicamente las rutas públicas y carga los archivos de `routes/web/`:

| Archivo | Responsabilidad |
| --- | --- |
| `core.php` | dashboard, búsqueda, notificaciones y servicios comunes autenticados |
| `crm.php` | clientes, oportunidades, presupuestos, ventas e instalaciones |
| `human-resources.php` | equipo, expedientes, ausencias, tareas y autoservicio |
| `operations.php` | planificación, órdenes de trabajo y posventa |
| `supply.php` | catálogo, inventario, compras y proveedores |
| `finance.php` | tesorería, contabilidad, fiscalidad y gastos |
| `administration.php` | administración, conocimiento, backups y auditoría |
| `integrations.php` | Arevia, IA y sincronizaciones externas |

Una ruta nueva debe añadirse al archivo propietario del dominio. No se añaden rutas autenticadas directamente a `routes/web.php`.

## Navegación

Cada área expone su navegación desde `resources/js/navigation/`. El agregador `navigation/index.ts` solo compone módulos; no contiene condiciones específicas de negocio.

La visibilidad se decide mediante `PlatformAbilities`, nunca comparando directamente `user.role`. Backend y frontend comparten el mismo catálogo definido por `PlatformAbility`.

## Autorización

- Toda ruta que modifica datos debe declarar una capacidad o usar una policy.
- Los administradores conservan acceso global mediante `Gate::before`.
- Los permisos por rol viven en `PlatformAbility::roles()`.
- Si un módulo necesita mayor granularidad, se añade una capacidad nueva antes de utilizarla en rutas o navegación.
- Las policies se usan cuando la decisión depende del registro concreto y no solo del área.

## Proveedores de dominio

Los registros de infraestructura se separan en `app/Providers/Domain/`:

| Proveedor | Responsabilidad |
| --- | --- |
| `CrmServiceProvider` | observers de personas, oportunidades e instalaciones |
| `OperationsServiceProvider` | observers de órdenes de trabajo |
| `SupplyServiceProvider` | compras, proveedores y cliente de buzón de compras |
| `FinanceServiceProvider` | observers financieros, contables y fiscales |

`AuthorizationServiceProvider` registra policies y capacidades. `AppServiceProvider` se reserva para convenciones globales del framework. Un módulo nuevo registra su infraestructura en un proveedor propio y lo declara en `bootstrap/providers.php`.

## Contratos visuales

Las páginas nuevas reutilizan los componentes de `resources/js/components/page-shell.tsx`:

- `PageShell` para espaciado y ancho general.
- `PageHeader` para título, descripción y acciones.
- `PageTabs` para navegación secundaria.
- `TableViewport` para tablas adaptables.
- `PaginationBar` para colecciones paginadas.

Los estados de carga, error, vacío y sin permisos deben estar definidos. Las acciones principales deben funcionar en escritorio y móvil.

## Migraciones

- No se modifica una migración ya ejecutada correctamente en producción.
- El nombre del archivo incluye una marca temporal no utilizada por otra rama activa.
- Los índices compuestos reciben un nombre explícito menor de 64 caracteres.
- Las migraciones deben funcionar con SQLite en pruebas y MySQL en producción.
- Las operaciones potencialmente destructivas requieren migración separada y estrategia de recuperación.

## Contratos entre módulos

- Un módulo puede relacionarse con modelos públicos de otro dominio, pero la lógica de negocio se invoca mediante un servicio del dominio propietario.
- Los cambios de estado importantes deben encapsularse en workflows, observers o eventos con pruebas.
- Los dashboards consumen métricas explicables; no duplican reglas de cálculo.
- Los archivos compartidos (`AppServiceProvider`, `HandleInertiaRequests`, navegación agregada y tipos globales) se modifican solo cuando cambia un contrato transversal.

## Definición de terminado

Antes de integrar una rama:

```bash
git diff --check
composer validate --strict
composer lint
php artisan test
npm run format
npm run lint
npm run types:check
npm run build
```

También debe comprobarse que las rutas tienen nombre y autorización, que la interfaz es responsive y que cualquier flujo nuevo incluye pruebas del caso correcto, errores de validación y permisos.
