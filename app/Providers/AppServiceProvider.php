<?php

namespace App\Providers;

use App\Contracts\PurchaseMailboxClient;
use App\Models\AccountingPeriod;
use App\Models\AccountingRecurringRun;
use App\Models\AccountingTemplate;
use App\Models\AssetEvent;
use App\Models\BankAccount;
use App\Models\BankFinancialProduct;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationRule;
use App\Models\BankReconciliationRuleVersion;
use App\Models\BankReconciliationRun;
use App\Models\BankReconciliationRunItem;
use App\Models\BankTransaction;
use App\Models\BankTransactionPosting;
use App\Models\BankTransfer;
use App\Models\CollectionAction;
use App\Models\CollectionActionEvent;
use App\Models\CompanyProfile;
use App\Models\CorporateTaxReturn;
use App\Models\CrmActivity;
use App\Models\DocumentSeries;
use App\Models\FieldServiceProfile;
use App\Models\FinancialAlertAction;
use App\Models\FinancialBudget;
use App\Models\FinancialBudgetPeriod;
use App\Models\FinancialForecastItem;
use App\Models\FiscalYear;
use App\Models\Installation;
use App\Models\InstallationAsset;
use App\Models\JournalEntry;
use App\Models\MaintenancePlan;
use App\Models\Opportunity;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\PurchaseDocumentLine;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\ServiceTicket;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TaxReturn;
use App\Models\TaxReturnPayment;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderTemplate;
use App\Observers\AuditableObserver;
use App\Observers\BankReconciliationFinancialObserver;
use App\Observers\CollectionActionHistoryObserver;
use App\Observers\OpportunityStageObserver;
use App\Observers\PartyLedgerObserver;
use App\Observers\SalesDocumentFinancialObserver;
use App\Observers\SupplierPriceObserver;
use App\Observers\WorkOrderObserver;
use App\Policies\CrmActivityPolicy;
use App\Services\Mailbox\NativeImapPurchaseMailboxClient;
use App\Services\Security\PermissionResolver;
use App\Services\Security\SecurityEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PurchaseMailboxClient::class, NativeImapPurchaseMailboxClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureAreviaRateLimits();
        Person::observe(AuditableObserver::class);
        Person::observe(PartyLedgerObserver::class);
        Supplier::observe(AuditableObserver::class);
        Supplier::observe(PartyLedgerObserver::class);
        Installation::observe(AuditableObserver::class);
        InstallationAsset::observe(AuditableObserver::class);
        AssetEvent::observe(AuditableObserver::class);
        FieldServiceProfile::observe(AuditableObserver::class);
        MaintenancePlan::observe(AuditableObserver::class);
        ServiceTicket::observe(AuditableObserver::class);
        WorkOrderTemplate::observe(AuditableObserver::class);
        WorkOrder::observe(AuditableObserver::class);
        WorkOrder::observe(WorkOrderObserver::class);
        Opportunity::observe(AuditableObserver::class);
        Opportunity::observe(OpportunityStageObserver::class);
        Quote::observe(AuditableObserver::class);
        CompanyProfile::observe(AuditableObserver::class);
        FiscalYear::observe(AuditableObserver::class);
        DocumentSeries::observe(AuditableObserver::class);
        BankAccount::observe(AuditableObserver::class);
        BankFinancialProduct::observe(AuditableObserver::class);
        BankTransaction::observe(AuditableObserver::class);
        BankReconciliation::observe(AuditableObserver::class);
        BankReconciliationRule::observe(AuditableObserver::class);
        BankReconciliationRuleVersion::observe(AuditableObserver::class);
        BankReconciliationRun::observe(AuditableObserver::class);
        BankReconciliationRunItem::observe(AuditableObserver::class);
        BankReconciliation::observe(BankReconciliationFinancialObserver::class);
        BankTransactionPosting::observe(AuditableObserver::class);
        BankTransfer::observe(AuditableObserver::class);
        AccountingPeriod::observe(AuditableObserver::class);
        AccountingTemplate::observe(AuditableObserver::class);
        AccountingRecurringRun::observe(AuditableObserver::class);
        SalesDocumentPayment::observe(AuditableObserver::class);
        SalesDocument::observe(SalesDocumentFinancialObserver::class);
        SupplierPayment::observe(AuditableObserver::class);
        JournalEntry::observe(AuditableObserver::class);
        FinancialForecastItem::observe(AuditableObserver::class);
        FinancialBudget::observe(AuditableObserver::class);
        FinancialBudgetPeriod::observe(AuditableObserver::class);
        FinancialAlertAction::observe(AuditableObserver::class);
        CollectionAction::observe(AuditableObserver::class);
        CollectionAction::observe(CollectionActionHistoryObserver::class);
        CollectionActionEvent::observe(AuditableObserver::class);
        PaymentBatch::observe(AuditableObserver::class);
        PaymentBatchItem::observe(AuditableObserver::class);
        TaxReturn::observe(AuditableObserver::class);
        TaxReturnPayment::observe(AuditableObserver::class);
        CorporateTaxReturn::observe(AuditableObserver::class);
        PurchaseDocument::observe(AuditableObserver::class);
        PurchaseDocumentLine::observe(SupplierPriceObserver::class);
        User::observe(AuditableObserver::class);
        Event::listen(Login::class, function (Login $event): void {
            if (Schema::hasTable('security_events')) {
                app(SecurityEventRecorder::class)->record('login_succeeded', $event->user, [
                    'user_agent' => request()->userAgent(),
                ]);
            }
        });
        Event::listen(Failed::class, function (Failed $event): void {
            if (Schema::hasTable('security_events')) {
                app(SecurityEventRecorder::class)->record('login_failed', $event->user, [], 'warning');
            }
        });
        Event::listen(Lockout::class, function (): void {
            if (Schema::hasTable('security_events')) {
                app(SecurityEventRecorder::class)->record('login_locked_out', null, [], 'critical', true);
            }
        });
        Event::listen(Logout::class, function (Logout $event): void {
            if (Schema::hasTable('security_events')) {
                app(SecurityEventRecorder::class)->record('logout', $event->user);
            }
        });
        Event::listen(PasswordReset::class, function (PasswordReset $event): void {
            if (Schema::hasTable('security_events')) {
                app(SecurityEventRecorder::class)->record('password_reset', $event->user, [], 'warning', true);
            }
        });
    }

    private function configureAuthorization(): void
    {
        Gate::policy(CrmActivity::class, CrmActivityPolicy::class);
        $permissions = [
            'manage-admin', 'manage-access', 'export-audit', 'view-system-health',
            'manage-customers', 'approve-quotes', 'view-installations', 'manage-installations', 'sync-installations',
            'use-ai', 'manage-operations', 'manage-catalog', 'view-finance', 'manage-treasury',
            'manage-accounting', 'approve-payments', 'approve-budgets', 'manage-tax',
            'view-purchases', 'manage-purchases', 'receive-purchases', 'approve-purchase-invoices',
            'approve-purchase-orders', 'manage-purchase-payments', 'manage-staff', 'view-sensitive-hr', 'manage-sensitive-hr',
            'view-analytics', 'view-analytics-executive', 'view-analytics-sales',
            'view-analytics-operations', 'view-analytics-supply', 'view-analytics-finance',
        ];
        foreach ($permissions as $permission) {
            Gate::define($permission, fn (User $user) => app(PermissionResolver::class)->allows($user, $permission));
        }
    }

    private function configureAreviaRateLimits(): void
    {
        $key = fn (Request $request) => (string) ($request->user()?->id ?? $request->ip());

        RateLimiter::for('arevia-messages', fn (Request $request) => Limit::perMinute(config('arevia.rate_limits.messages_per_minute', 8))->by($key($request)));
        RateLimiter::for('arevia-briefings', fn (Request $request) => Limit::perMinute(config('arevia.rate_limits.briefings_per_minute', 4))->by($key($request)));
        RateLimiter::for('arevia-actions', fn (Request $request) => Limit::perMinute(config('arevia.rate_limits.actions_per_minute', 12))->by($key($request)));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
