<?php

namespace App\Http\Middleware;

use App\Models\CompanyProfile;
use App\Services\Accounting\AccountingContext;
use App\Services\Accounting\AccountingOperationInput;
use App\Support\CompanyBrand;
use App\Support\WorkspaceNavigation;
use App\Support\WorkspaceShortcuts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $sharesWorkspace = $user !== null && ! $request->routeIs(
            'home',
            'public.*',
            'projects.*',
            'project-images.show',
            'services.show',
            'legal.*',
        );

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'accountingInputOptions' => fn () => AccountingOperationInput::options($request),
            'accountingContext' => fn () => app(AccountingContext::class)->forRequest($request),
            'branding' => fn () => app(CompanyBrand::class)->for($user ? CompanyProfile::first() : null),
            'localization' => [
                'current' => app()->getLocale(),
                'supported' => collect(config('localization.supported', []))->map(
                    fn (array $locale, string $code) => ['code' => $code, ...$locale],
                )->values(),
            ],
            'performance' => [
                'webVitalsSampleRate' => config('performance.web_vitals_sample_rate'),
            ],
            'auth' => [
                'user' => $user,
                'shortcuts' => fn () => $sharesWorkspace
                    ? app(WorkspaceShortcuts::class)->for($user)
                    : ['favorites' => [], 'recent' => []],
                'abilities' => fn () => $sharesWorkspace ? [
                    'manageAdmin' => Gate::allows('manage-admin'),
                    'manageAccess' => Gate::allows('manage-access'),
                    'exportAudit' => Gate::allows('export-audit'),
                    'viewSystemHealth' => Gate::allows('view-system-health'),
                    'viewAnalytics' => Gate::allows('view-analytics'),
                    'manageCustomers' => Gate::allows('manage-customers'),
                    'manageQuotes' => Gate::allows('manage-quotes'),
                    'approveQuotes' => Gate::allows('approve-quotes'),
                    'viewInstallations' => Gate::allows('view-installations'),
                    'manageInstallations' => Gate::allows('manage-installations'),
                    'useAi' => Gate::allows('use-ai'),
                    'manageOperations' => Gate::allows('manage-operations'),
                    'publishCustomerDocuments' => Gate::allows('publish-customer-documents'),
                    'manageCatalog' => Gate::allows('manage-catalog'),
                    'viewFinance' => Gate::allows('view-finance'),
                    'manageTreasury' => Gate::allows('manage-treasury'),
                    'manageAccounting' => Gate::allows('manage-accounting'),
                    'approvePayments' => Gate::allows('approve-payments'),
                    'manageTax' => Gate::allows('manage-tax'),
                    'managePurchases' => Gate::allows('manage-purchases'),
                    'viewPurchases' => Gate::allows('view-purchases'),
                    'manageStaff' => Gate::allows('manage-staff'),
                    'receivePurchases' => Gate::allows('receive-purchases'),
                    'approvePurchaseInvoices' => Gate::allows('approve-purchase-invoices'),
                    'approvePurchaseOrders' => Gate::allows('approve-purchase-orders'),
                    'managePurchasePayments' => Gate::allows('manage-purchase-payments'),
                    'managePurchaseSettings' => Gate::allows('manage-staff'),
                ] : [],
                'navigation' => fn () => $sharesWorkspace
                    ? app(WorkspaceNavigation::class)->for($user)
                    : [],
                'notifications' => fn () => $sharesWorkspace ? $user->unreadNotifications()->latest()->limit(5)->get()->map(fn ($notification) => [
                    'id' => $notification->id,
                    'message' => $notification->data['message'] ?? __('New notification'),
                    'url' => $notification->data['url'] ?? route('dashboard'),
                ]) : [],
            ],
            'workspaceContext' => fn () => $sharesWorkspace ? [
                'companyName' => CompanyProfile::value('legal_name') ?: config('company.legal_name'),
                'currentYear' => now()->year,
            ] : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
