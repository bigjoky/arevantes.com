<?php

namespace App\Providers;

use App\Contracts\PurchaseMailboxClient;
use App\Models\AccountingPeriod;
use App\Models\AccountingRecurringRun;
use App\Models\AccountingTemplate;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\BankTransactionPosting;
use App\Models\BankTransfer;
use App\Models\CollectionAction;
use App\Models\CollectionActionEvent;
use App\Models\CompanyProfile;
use App\Models\CrmActivity;
use App\Models\DocumentSeries;
use App\Models\FinancialAlertAction;
use App\Models\FinancialBudget;
use App\Models\FinancialBudgetPeriod;
use App\Models\FinancialForecastItem;
use App\Models\FiscalYear;
use App\Models\Installation;
use App\Models\JournalEntry;
use App\Models\Opportunity;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\PurchaseDocumentLine;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TaxReturnPayment;
use App\Models\User;
use App\Models\WorkOrder;
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
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        Person::observe(AuditableObserver::class);
        Person::observe(PartyLedgerObserver::class);
        Supplier::observe(PartyLedgerObserver::class);
        Installation::observe(AuditableObserver::class);
        WorkOrder::observe(WorkOrderObserver::class);
        Opportunity::observe(AuditableObserver::class);
        Opportunity::observe(OpportunityStageObserver::class);
        CompanyProfile::observe(AuditableObserver::class);
        FiscalYear::observe(AuditableObserver::class);
        DocumentSeries::observe(AuditableObserver::class);
        BankAccount::observe(AuditableObserver::class);
        BankTransaction::observe(AuditableObserver::class);
        BankReconciliation::observe(AuditableObserver::class);
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
        TaxReturnPayment::observe(AuditableObserver::class);
        PurchaseDocument::observe(AuditableObserver::class);
        PurchaseDocumentLine::observe(SupplierPriceObserver::class);
        User::observe(AuditableObserver::class);
        Event::listen(Login::class, function (Login $event): void {
            if (Schema::hasTable('security_events')) {
                app(SecurityEventRecorder::class)->record('login_succeeded', $event->user, [
                    'user_agent' => request()->userAgent(),
                ], 'info', true);
            }
        });
    }

    private function configureAuthorization(): void
    {
        Gate::policy(CrmActivity::class, CrmActivityPolicy::class);
        $permissions = [
            'manage-admin', 'manage-access', 'export-audit', 'view-system-health',
            'manage-customers', 'view-installations', 'manage-installations', 'sync-installations',
            'use-ai', 'manage-operations', 'manage-catalog', 'view-finance', 'manage-treasury',
            'manage-accounting', 'approve-payments', 'approve-budgets', 'manage-tax',
            'view-purchases', 'manage-purchases', 'receive-purchases', 'approve-purchase-invoices',
            'manage-purchase-payments', 'manage-staff', 'view-sensitive-hr', 'manage-sensitive-hr',
            'view-analytics',
        ];
        foreach ($permissions as $permission) {
            Gate::define($permission, fn (User $user) => app(PermissionResolver::class)->allows($user, $permission));
        }
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
