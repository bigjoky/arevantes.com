<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

class WorkspaceNavigation
{
    /** @return array<int, array<string, mixed>> */
    public function for(User $user): array
    {
        return array_values(array_filter([
            $this->item('Dashboard', route('dashboard', absolute: false), 'layout-grid'),
            $this->item('Mi RR. HH.', route('my-human-resources.index', absolute: false), 'calendar-days'),
            $this->section('PTY', 'People & companies', 'users-round', [
                $this->when($user, 'manage-customers', fn () => $this->item('Customers', route('people.index', absolute: false))),
                $this->when($user, 'manage-catalog', fn () => $this->item('Suppliers', route('suppliers.index', absolute: false))),
            ]),
            $this->section('CRM', 'CRM', 'briefcase-business', [
                $this->when($user, 'manage-customers', fn () => $this->item('Opportunities', route('opportunities.index', absolute: false))),
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
                $this->when($user, 'manage-operations', fn () => $this->item('Planning', route('operations.index', absolute: false))),
                $this->when($user, 'manage-operations', fn () => $this->item('Work orders', route('work-orders.index', absolute: false))),
            ]),
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
                $this->when($user, 'manage-catalog', fn () => $this->item('Inventory', route('inventory.index', absolute: false))),
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
                $this->when($user, 'view-finance', fn () => $this->item('VAT', route('tax.index', absolute: false))),
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
