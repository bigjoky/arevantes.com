<?php

namespace App\Providers\Domain;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\BankTransactionPosting;
use App\Models\BankTransfer;
use App\Models\CollectionAction;
use App\Models\CollectionActionEvent;
use App\Models\CompanyProfile;
use App\Models\DocumentSeries;
use App\Models\FinancialAlertAction;
use App\Models\FinancialBudget;
use App\Models\FinancialBudgetPeriod;
use App\Models\FinancialForecastItem;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\SupplierPayment;
use App\Models\TaxReturnPayment;
use App\Observers\AuditableObserver;
use App\Observers\BankReconciliationFinancialObserver;
use App\Observers\CollectionActionHistoryObserver;
use App\Observers\SalesDocumentFinancialObserver;
use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        CompanyProfile::observe(AuditableObserver::class);
        FiscalYear::observe(AuditableObserver::class);
        DocumentSeries::observe(AuditableObserver::class);
        BankAccount::observe(AuditableObserver::class);
        BankTransaction::observe(AuditableObserver::class);
        BankReconciliation::observe(AuditableObserver::class);
        BankReconciliation::observe(BankReconciliationFinancialObserver::class);
        BankTransactionPosting::observe(AuditableObserver::class);
        BankTransfer::observe(AuditableObserver::class);
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
    }
}
