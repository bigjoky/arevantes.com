<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

class WorkspaceNavigation
{
    /** Canonical module tree; both sidebar and internal navigation consume it. */
    public function for(User $user): array
    {
        $destinations = collect($this->destinations($user))->flatMap(fn ($item) => $item['children'] ?? [$item])->keyBy('href');
        $modules = [];
        $used = [];
        foreach ($this->definition() as $definition) {
            [$id, $title, $icon, $sections] = $definition;
            $parentPath = $definition[4] ?? null;
            $navigable = $parentPath !== null && $destinations->has(strtok($parentPath, '?#'));
            if ($navigable) {
                $used[strtok($parentPath, '?#')] = true;
            }
            $children = [];
            foreach ($sections as [$sectionId, $label, $paths, $ability, $pending]) {
                if ($ability && ! Gate::forUser($user)->allows($ability)) {
                    continue;
                }
                $links = [];
                foreach ($paths as $path => $override) {
                    $base = strtok($path, '?#');
                    if ($destinations->has($base)) {
                        $link = $destinations[$base];
                        $link['href'] = $path;
                        if ($override) {
                            $link['title'] = __($override);
                        }
                        $links[] = $link;
                        $used[$base] = true;
                    }
                }
                if ($links === [] && (! $ability || ! Gate::forUser($user)->allows($ability))) {
                    continue;
                }
                $hub = route('workspace.module', ['module' => $id, 'section' => $sectionId], absolute: false);
                $children[] = [
                    'id' => $id.'.'.$sectionId, 'title' => __($label), 'href' => in_array($id, ['projects', 'operations', 'supply', 'tax', 'accounting', 'company'], true) && $links !== [] ? $links[0]['href'] : (($id === 'accounting' && $sectionId === 'entries') || (count($links) === 1 && $pending === []) ? $links[0]['href'] : $hub),
                    'links' => $links, 'pending' => array_map(fn ($text) => __($text), $pending),
                ];
            }
            if ($children !== [] || $navigable) {
                $modules[] = ['id' => $id, 'code' => $id, 'title' => __($title), 'icon' => $icon, 'href' => $navigable ? $parentPath : $children[0]['href'], 'children' => $children, ...($navigable ? ['navigable' => true] : [])];
            }
        }
        // Existing administration/BI and auxiliary tools remain accessible inside Empresa.
        $remaining = $destinations->reject(fn ($item, $href) => isset($used[$href]) || $href === '/dashboard')->values()->all();
        foreach ($modules as &$module) {
            if ($module['id'] !== 'company') {
                continue;
            }
            if ($remaining !== [] && ! collect($module['children'])->contains('id', 'company.settings')) {
                $module['children'][] = ['id' => 'company.settings', 'title' => __('Configuración'), 'href' => route('workspace.module', ['module' => 'company', 'section' => 'settings'], absolute: false), 'links' => [], 'pending' => []];
            }
            foreach ($module['children'] as &$child) {
                if ($child['id'] === 'company.settings') {
                    $child['links'] = array_merge($child['links'], $remaining);
                }
            }
            unset($child);
        }
        unset($module);

        return [['id' => 'home', ...$this->item('Inicio', route('dashboard', absolute: false), 'home')], ...$modules];
    }

    /** All authorized destinations, including internal tools, preserve existing favorites. */
    public function availableLinks(User $user): array
    {
        return collect($this->for($user))->flatMap(fn ($module) => isset($module['children'])
            ? collect($module['children'])->flatMap(fn ($child) => [$child, ...$child['links']]) : [$module])->merge(collect($this->destinations($user))->flatMap(fn ($item) => $item['children'] ?? [$item]))->values()->all();
    }

    private function definition(): array
    {
        return [
            ['commercial', 'Comercial', 'briefcase-business', [
                ['crm', 'CRM', ['/opportunities' => 'CRM'], null, []],
                ['customers', 'Clientes', ['/people' => 'Clientes'], null, []],
                ['quotes', 'Presupuestos', ['/quotes' => 'Presupuestos'], null, []],
            ], '/commercial'],
            ['projects', 'Proyectos', 'building-2', [
                ['projects', 'Proyectos', ['/execution/projects' => 'Proyectos', '/engineering' => 'Expedientes técnicos'], 'view-installations', []],
                ['installations', 'Instalaciones', ['/installations' => 'Instalaciones', '/assets' => 'Installed equipment'], null, []],
                ['after-sales', 'Postventa', ['/after-sales?view=installations' => 'Postventa', '/maintenance' => null, '/contracts-services' => null, '/quality/service' => null], null, []],
            ], '/execution'],
            ['operations', 'Operaciones', 'wrench', [
                ['planning', 'Planificación', ['/operations/planning' => 'Planificación', '/operations/activities' => 'Actividades', '/planning-scenarios' => null, '/planning-requests' => null], null, []],
                ['work-orders', 'Órdenes de trabajo', ['/work-orders' => 'Órdenes de trabajo'], null, []],
                ['agenda', 'Agenda', ['/operations/agenda' => 'Agenda'], 'manage-operations', []],
            ], '/operations'],
            ['supply', 'Almacén y compras', 'boxes', [
                ['products', 'Productos', ['/catalog' => 'Productos', '/catalog/settings' => null], null, []],
                ['inventory', 'Inventario', ['/inventory' => 'Inventario', '/inventory/reservations' => null, '/inventory/movements' => null, '/inventory/warehouses' => null, '/inventory/serials' => null, '/inventory/counts' => null], null, []],
                ['suppliers', 'Proveedores', ['/suppliers' => 'Proveedores', '/supplier-intelligence' => null, '/supplier-quality' => null], null, []],
                ['purchases', 'Compras', ['/purchases' => 'Compras', '/purchase-inbox' => null, '/supply-control' => null, '/supply-analytics' => null, '/replenishment' => null], null, []],
            ], '/supply'],
            ['billing', 'Facturación', 'badge-euro', [
                ['customers', 'Facturas de clientes', ['/sales' => 'Facturas de clientes'], null, []],
                ['suppliers', 'Facturas de proveedores', ['/billing/suppliers' => 'Facturas de proveedores'], null, []],
                ['payments', 'Cobros y pagos', ['/billing/payments' => 'Cobros y pagos'], 'view-finance', []],
            ], '/billing'],
            ['accounting', 'Contabilidad', 'scale', [
                ['entries', 'Asientos', ['/accounting?view=journal&area=entries' => 'Todos', '/accounting?view=journal&area=entries&entry_status=draft' => 'Borradores', '/accounting/templates?tab=recurring' => 'Periódicos', '/accounting/templates' => 'Plantillas'], 'view-finance', ['Importación de asientos']],
                ['accounts', 'Plan contable', ['/accounting/accounts' => 'Plan contable'], null, []],
                ['reports', 'Informes contables', ['/accounting?view=balance&area=reports' => 'Balance de situación', '/accounting?view=profit&area=reports' => 'Pérdidas y ganancias', '/accounting?view=journal&area=reports' => 'Libro Diario', '/accounting?view=ledger&area=reports' => 'Libro Mayor', '/accounting?view=trial&area=reports' => 'Balance de sumas y saldos', '/accounting?view=parties&area=reports' => 'Saldos de terceros'], 'view-finance', ['Estado de cambios en el patrimonio neto', 'Estado de flujos de efectivo']],
                ['books', 'Libros oficiales', [], 'view-finance', ['Generación, consulta y exportación de libros contables oficiales']],
            ], '/accounting?area=overview'],
            ['finance', 'Finanzas', 'wallet-cards', [
                ['treasury', 'Tesorería', ['/finance?area=treasury' => 'Tesorería'], 'view-finance', []],
                ['banking', 'Bancos y conciliación', ['/banking' => 'Bancos y conciliación'], null, []],
                ['forecasts', 'Previsiones', ['/finance?area=forecasts' => 'Previsiones'], 'view-finance', []],
            ], '/finance'],
            ['tax', 'Fiscal', 'file-text', [
                ['taxes', 'Impuestos', ['/fiscal/impuestos' => 'Impuestos', '/tax' => 'Impuestos', '/tax/issued' => null, '/tax/received' => null], null, []],
                ['models', 'Modelos', ['/fiscal/modelos' => 'Modelos', '/corporate-tax' => null, '/tax/models/303' => 'Modelo 303'], null, []],
                ['periods', 'Periodos fiscales', ['/fiscal/periodos' => 'Periodos fiscales'], 'view-finance', []],
            ], '/fiscal'],
            ['company', 'Empresa', 'settings-2', [
                ['staff', 'Personal', ['/company/staff' => 'Personal', '/my-human-resources' => 'Mi RR. HH.', '/human-resources' => null, '/human-resources/employees' => null, '/human-resources/leave' => null, '/human-resources/tasks' => null, '/human-resources/departments' => null, '/staff' => null], null, []],
                ['documents', 'Documentos', ['/company/documents' => 'Documentos', '/knowledge' => null], null, []],
                ['settings', 'Configuración', ['/company/settings' => 'Configuración', '/company-fiscal' => null], null, []],
            ], '/company'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function destinations(User $user): array
    {
        return array_values(array_filter([
            $this->whenAny($user, ['manage-admin', 'manage-staff', 'manage-tax'], fn () => $this->item('Empresa', '/company')),
            $this->when($user, 'manage-staff', fn () => $this->item('Personal', '/company/staff')),
            $this->whenAny($user, ['manage-admin', 'manage-staff'], fn () => $this->item('Documentos', '/company/documents')),
            $this->whenAny($user, ['manage-admin', 'manage-tax'], fn () => $this->item('Configuración', '/company/settings')),

            $this->when($user, 'view-installations', fn () => $this->item('Proyectos', route('execution.dashboard', absolute: false))),
            $this->when($user, 'view-installations', fn () => $this->item('Proyectos', route('execution.projects.index', absolute: false))),
            $this->when($user, 'manage-tax', fn () => $this->item('Modelo 303', route('tax.models.303', absolute: false))),
            $this->whenAny($user, ['manage-installations', 'view-finance'], fn () => $this->item('Facturación', route('billing.index', absolute: false))),
            $this->when($user, 'view-finance', fn () => $this->item('Facturas de proveedores', route('billing.suppliers', absolute: false))),
            $this->when($user, 'view-finance', fn () => $this->item('Cobros y pagos', route('billing.payments', absolute: false))),
            $this->item('Dashboard', route('dashboard', absolute: false), 'layout-grid'),
            $this->item('Mi RR. HH.', route('my-human-resources.index', absolute: false), 'calendar-days'),
            $this->section('PTY', 'People & companies', 'users-round', [
                $this->when($user, 'manage-customers', fn () => $this->item('Customers', route('people.index', absolute: false))),
                $this->when($user, 'manage-catalog', fn () => $this->item('Suppliers', route('suppliers.index', absolute: false))),
                $this->when($user, 'manage-admin', fn () => $this->item('Fiscal directory', route('fiscal-entities.index', absolute: false))),
            ]),
            $this->section('CRM', 'CRM', 'briefcase-business', [
                $this->whenAny($user, ['manage-customers', 'manage-quotes', 'manage-installations'], fn () => $this->item('Comercial', route('commercial.index', absolute: false))),
                $this->when($user, 'manage-customers', fn () => $this->item('Opportunities', route('opportunities.index', absolute: false))),
                $this->whenAny($user, ['record-commercial-field', 'supervise-commercial-field'], fn () => $this->item('Actividad de campo', route('commercial-field.index', absolute: false))),
            ]),
            $this->section('SAL', 'Sales', 'badge-euro', [
                $this->when($user, 'manage-quotes', fn () => $this->item('Proposals', route('quotes.index', absolute: false))),
                $this->when($user, 'manage-installations', fn () => $this->item('Sales documents', route('sales.index', absolute: false))),
            ]),
            $this->section('ENG', 'Engineering', 'scale', [
                $this->when($user, 'view-installations', fn () => $this->item('Technical dossiers', route('engineering.index', absolute: false))),
            ]),
            $this->section('PRJ', 'Projects', 'building-2', [
                $this->when($user, 'view-installations', fn () => $this->item('Installations', route('installations.index', absolute: false))),
            ]),
            $this->section('PLN', 'Planning', 'calendar-clock', [
                $this->when($user, 'manage-admin', fn () => $this->item('Planning scenarios', route('planning-scenarios.index', absolute: false))),
                $this->whenAny($user, ['manage-operations', 'manage-catalog', 'manage-purchases', 'manage-treasury'], fn () => $this->item('Planning requests', route('planning-scenario-requests.index', absolute: false))),
            ]),
            $this->section('OPS', 'Operations / SAT', 'wrench', [
                $this->when($user, 'manage-operations', fn () => $this->item('Planificación', route('operations.planning', absolute: false))),
                $this->when($user, 'manage-operations', fn () => $this->item('Agenda', route('operations.agenda', absolute: false))),
                $this->when($user, 'manage-operations', fn () => $this->item('Actividades', route('operations.activities', absolute: false))),
                $this->when($user, 'manage-operations', fn () => $this->item('Operaciones', route('operations.index', absolute: false))),
                $this->when($user, 'manage-operations', fn () => $this->item('Work orders', route('work-orders.index', absolute: false))),
            ]),
            $this->whenAny($user, ['manage-catalog', 'view-purchases'], fn () => $this->item('Almacén y compras', route('supply.index', absolute: false))),
            $this->section('PUR', 'Purchases', 'shopping-cart', [
                $this->when($user, 'manage-purchases', fn () => $this->item('Document inbox', route('purchase-inbox.index', absolute: false))),
                $this->when($user, 'view-purchases', fn () => $this->item('Purchases', route('purchases.index', absolute: false))),
                $this->when($user, 'view-purchases', fn () => $this->item('Order control', route('supply-control.index', absolute: false))),
                $this->when($user, 'manage-purchases', fn () => $this->item('Supply analytics', route('supply-analytics', absolute: false))),
                $this->when($user, 'manage-purchases', fn () => $this->item('Supplier intelligence', route('supplier-intelligence.index', absolute: false))),
                $this->when($user, 'manage-purchases', fn () => $this->item('Replenishment', route('replenishment.index', absolute: false))),
            ]),
            $this->section('INV', 'Inventory', 'boxes', [
                $this->when($user, 'manage-catalog', fn () => $this->item('Products & services', route('catalog.index', absolute: false))),
                $this->when($user, 'manage-catalog', fn () => $this->item('Stock', route('inventory.index', absolute: false))),
                $this->when($user, 'manage-catalog', fn () => $this->item('Reservations', route('inventory.reservations.index', absolute: false))),
                $this->when($user, 'manage-catalog', fn () => $this->item('Movements', route('inventory.movements.index', absolute: false))),
                $this->when($user, 'manage-catalog', fn () => $this->item('Warehouses', route('inventory.warehouses.index', absolute: false))),
                $this->when($user, 'manage-catalog', fn () => $this->item('Configuration', route('catalog.settings', absolute: false))),
                $this->when($user, 'view-purchases', fn () => $this->item('Serial traceability', route('inventory.serials.index', absolute: false))),
                $this->when($user, 'manage-catalog', fn () => $this->item('Stock counts', route('stock-counts.index', absolute: false))),
            ]),
            $this->section('AST', 'Assets', 'boxes', [
                $this->when($user, 'manage-operations', fn () => $this->item('Installed equipment', route('assets.index', absolute: false))),
            ]),
            $this->section('MNT', 'Maintenance', 'calendar-clock', [
                $this->when($user, 'manage-operations', fn () => $this->item('Preventive & corrective', route('maintenance.index', absolute: false))),
            ]),
            $this->section('FIN', 'Finance', 'wallet-cards', [
                $this->when($user, 'view-finance', fn () => $this->item('Expenses', route('expenses.index', absolute: false))),
                $this->when($user, 'view-finance', fn () => $this->item('Overview', route('finance.index', absolute: false))),
                $this->when($user, 'view-finance', fn () => $this->item('Banking', route('banking.index', absolute: false))),
            ]),
            $this->section('ACC', 'Accounting', 'scale', [
                $this->when($user, 'view-finance', fn () => $this->item('Journal & reports', route('accounting.index', absolute: false))),
                $this->when($user, 'view-finance', fn () => $this->item('Chart of accounts', route('accounting.accounts.index', absolute: false))),
                $this->when($user, 'view-finance', fn () => $this->item('Entry templates', route('accounting.templates.index', absolute: false))),
                $this->when($user, 'view-finance', fn () => $this->item('Fiscal', '/fiscal')),
                $this->when($user, 'view-finance', fn () => $this->item('Impuestos', '/fiscal/impuestos')),
                $this->when($user, 'view-finance', fn () => $this->item('Modelos', '/fiscal/modelos')),
                $this->when($user, 'view-finance', fn () => $this->item('Periodos fiscales', '/fiscal/periodos')),
                $this->when($user, 'view-finance', fn () => $this->item('VAT', route('tax.index', absolute: false))),
                $this->when($user, 'view-finance', fn () => $this->item('Issued invoice register', route('tax.issued', absolute: false))),
                $this->when($user, 'view-finance', fn () => $this->item('Received invoice register', route('tax.received', absolute: false))),
                $this->when($user, 'view-finance', fn () => $this->item('Corporate tax', route('corporate-tax.index', absolute: false))),
            ]),
            $this->section('HRM', 'Human resources', 'user-cog', [
                $this->when($user, 'manage-staff', fn () => $this->item('Resumen', route('human-resources.index', absolute: false))),
                $this->when($user, 'manage-staff', fn () => $this->item('Fichas laborales', route('human-resources.employees.index', absolute: false))),
                $this->when($user, 'manage-staff', fn () => $this->item('Ausencias', route('human-resources.leave.index', absolute: false))),
                $this->when($user, 'manage-staff', fn () => $this->item('Tareas y onboarding', route('human-resources.tasks.index', absolute: false))),
                $this->when($user, 'manage-staff', fn () => $this->item('Departamentos', route('human-resources.departments.index', absolute: false))),
                $this->when($user, 'manage-staff', fn () => $this->item('Equipo y accesos', route('staff.index', absolute: false))),
            ]),
            $this->section('DOC', 'Documentation', 'book-open-text', [
                $this->when($user, 'manage-staff', fn () => $this->item('Arevia knowledge', route('knowledge.index', absolute: false))),
            ]),
            $this->section('BI', 'Management / BI', 'chart-spline', [
                $this->when($user, 'view-analytics', fn () => $this->item('Analytics', route('analytics.index', absolute: false))),
                $this->whenAny($user, ['manage-operations', 'manage-purchases', 'manage-customers'], fn () => $this->item('Improvement decisions', route('improvement-decisions.index', absolute: false))),
            ]),
            $this->section('QMS', 'Quality', 'shield-check', [
                $this->when($user, 'manage-operations', fn () => $this->item('Service quality', route('service-quality.index', absolute: false))),
                $this->when($user, 'view-purchases', fn () => $this->item('Quality claims', route('supplier-quality.index', absolute: false))),
            ]),
            $this->section('CS', 'Customer service', 'headphones', [
                $this->when($user, 'manage-operations', fn () => $this->item('After-sales', route('after-sales.index', absolute: false))),
            ]),
            $this->section('CTR', 'Contracts & services', 'briefcase-business', [
                $this->when($user, 'manage-operations', fn () => $this->item('Recurring services', route('contracts-services.index', absolute: false))),
            ]),
            $this->section('SYS', 'Platform administration', 'settings-2', [
                $this->when($user, 'manage-admin', fn () => $this->item('Web y proyectos', route('website.projects.index', absolute: false))),
                $this->when($user, 'manage-tax', fn () => $this->item('Company & fiscal settings', route('fiscal-settings.index', absolute: false))),
                $this->when($user, 'manage-access', fn () => $this->item('Access & permissions', route('admin.access.index', absolute: false))),
                $this->when($user, 'manage-admin', fn () => $this->item('Backups', route('admin.backups.index', absolute: false))),
                $this->when($user, 'export-audit', fn () => $this->item('Audit log', route('admin.audit.index', absolute: false))),
                $this->when($user, 'view-system-health', fn () => $this->item('System health', route('admin.health.index', absolute: false))),
            ]),
        ]));
    }

    /** @return array{title: string, href: string, icon?: string} */
    private function item(string $title, string $href, ?string $icon = null): array
    {
        $title = __($title);

        return array_filter(compact('title', 'href', 'icon'));
    }

    /** @param array<int, array<string, mixed>|null> $children */
    private function section(string $code, string $title, string $icon, array $children): ?array
    {
        $children = array_values(array_filter($children));

        return $children === [] ? null : [
            'title' => __($title),
            'code' => $code,
            'href' => $children[0]['href'],
            'icon' => $icon,
            'children' => $children,
        ];
    }

    /** @return array<string, mixed>|null */
    private function when(User $user, string $ability, callable $item): ?array
    {
        return Gate::forUser($user)->allows($ability) ? $item() : null;
    }

    /** @param list<string> $abilities */
    private function whenAny(User $user, array $abilities, callable $item): ?array
    {
        return collect($abilities)->contains(fn (string $ability): bool => Gate::forUser($user)->allows($ability)) ? $item() : null;
    }
}
