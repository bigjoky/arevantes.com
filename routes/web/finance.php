<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AccountingTemplateController;
use App\Http\Controllers\BankingController;
use App\Http\Controllers\ChartOfAccountsController;
use App\Http\Controllers\CorporateTaxController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FiscalSettingsController;
use App\Http\Controllers\TaxController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('finance', [FinanceController::class, 'index'])->middleware('can:view-finance')->name('finance.index');
    Route::get('finance/export', [FinanceController::class, 'export'])->middleware('can:view-finance')->name('finance.export');
    Route::post('finance/forecast-items', [FinanceController::class, 'storeForecast'])->middleware('can:manage-treasury')->name('finance.forecast-items.store');
    Route::patch('finance/forecast-items/{forecastItem}', [FinanceController::class, 'updateForecast'])->middleware('can:manage-treasury')->name('finance.forecast-items.update');
    Route::delete('finance/forecast-items/{forecastItem}', [FinanceController::class, 'destroyForecast'])->middleware('can:manage-treasury')->name('finance.forecast-items.destroy');
    Route::post('finance/budgets', [FinanceController::class, 'storeBudget'])->middleware('can:manage-treasury')->name('finance.budgets.store');
    Route::post('finance/budgets/import', [FinanceController::class, 'importBudgets'])->middleware('can:manage-treasury')->name('finance.budgets.import');
    Route::post('finance/budgets/copy', [FinanceController::class, 'copyBudget'])->middleware('can:manage-treasury')->name('finance.budgets.copy');
    Route::post('finance/budgets/{year}/approve', [FinanceController::class, 'approveBudget'])->middleware('can:approve-budgets')->name('finance.budgets.approve');
    Route::post('finance/budgets/{year}/reopen', [FinanceController::class, 'reopenBudget'])->middleware('can:approve-budgets')->name('finance.budgets.reopen');
    Route::get('finance/budgets/{year}/versions/{financialBudgetVersion}/export', [FinanceController::class, 'exportBudgetVersion'])->middleware('can:view-finance')->name('finance.budgets.versions.export');
    Route::delete('finance/budgets/{financialBudget}', [FinanceController::class, 'destroyBudget'])->middleware('can:manage-treasury')->name('finance.budgets.destroy');
    Route::patch('finance/alert-settings', [FinanceController::class, 'updateAlertSettings'])->middleware('can:manage-treasury')->name('finance.alert-settings.update');
    Route::patch('finance/alerts', [FinanceController::class, 'updateFinancialAlert'])->middleware('can:manage-treasury')->name('finance.alerts.update');
    Route::put('finance/collections/{salesDocument}', [FinanceController::class, 'storeCollectionAction'])->middleware('can:manage-treasury')->name('finance.collections.store');
    Route::post('finance/payment-batches', [FinanceController::class, 'storePaymentBatch'])->middleware('can:manage-treasury')->name('finance.payment-batches.store');
    Route::post('finance/payment-batches/{paymentBatch}/approve', [FinanceController::class, 'approvePaymentBatch'])->middleware('can:approve-payments')->name('finance.payment-batches.approve');
    Route::post('finance/payment-batches/{paymentBatch}/cancel', [FinanceController::class, 'cancelPaymentBatch'])->middleware('can:manage-treasury')->name('finance.payment-batches.cancel');
    Route::get('finance/payment-batches/{paymentBatch}/sepa', [FinanceController::class, 'exportPaymentBatch'])->middleware('can:approve-payments')->name('finance.payment-batches.sepa');
    Route::post('finance/payment-batches/{paymentBatch}/execute', [FinanceController::class, 'executePaymentBatch'])->middleware('can:approve-payments')->name('finance.payment-batches.execute');

    Route::get('accounting', [AccountingController::class, 'index'])->middleware('can:view-finance')->name('accounting.index');
    Route::post('accounting/sync', [AccountingController::class, 'sync'])->middleware('can:manage-accounting')->name('accounting.sync');
    Route::post('accounting/entries', [AccountingController::class, 'store'])->middleware('can:manage-accounting')->name('accounting.entries.store');
    Route::post('accounting/entries/{entry}/reverse', [AccountingController::class, 'reverse'])->middleware('can:manage-accounting')->name('accounting.entries.reverse');
    Route::post('accounting/years/{fiscalYear}/close', [AccountingController::class, 'close'])->middleware('can:manage-staff')->name('accounting.years.close');
    Route::post('accounting/years/{fiscalYear}/opening', [AccountingController::class, 'opening'])->middleware('can:manage-staff')->name('accounting.years.opening');
    Route::patch('accounting/mappings/expense-categories/{category}', [AccountingController::class, 'updateExpenseCategory'])->middleware('can:manage-accounting')->name('accounting.mappings.expense-categories.update');
    Route::patch('accounting/mappings/tax-rates/{taxRate}', [AccountingController::class, 'updateTaxRate'])->middleware('can:manage-accounting')->name('accounting.mappings.tax-rates.update');
    Route::get('accounting/accounts', [ChartOfAccountsController::class, 'index'])->middleware('can:view-finance')->name('accounting.accounts.index');
    Route::post('accounting/accounts', [ChartOfAccountsController::class, 'store'])->middleware('can:manage-staff')->name('accounting.accounts.store');
    Route::patch('accounting/accounts/{account}', [ChartOfAccountsController::class, 'update'])->middleware('can:manage-staff')->name('accounting.accounts.update');
    Route::delete('accounting/accounts/{account}', [ChartOfAccountsController::class, 'destroy'])->middleware('can:manage-staff')->name('accounting.accounts.destroy');
    Route::get('accounting/templates', [AccountingTemplateController::class, 'index'])->middleware('can:view-finance')->name('accounting.templates.index');
    Route::post('accounting/templates', [AccountingTemplateController::class, 'store'])->middleware('can:manage-accounting')->name('accounting.templates.store');
    Route::patch('accounting/templates/{template}', [AccountingTemplateController::class, 'update'])->middleware('can:manage-accounting')->name('accounting.templates.update');
    Route::delete('accounting/templates/{template}', [AccountingTemplateController::class, 'destroy'])->middleware('can:manage-accounting')->name('accounting.templates.destroy');
    Route::post('accounting/templates/{template}/execute', [AccountingTemplateController::class, 'execute'])->middleware('can:manage-accounting')->name('accounting.templates.execute');

    Route::get('banking', [BankingController::class, 'index'])->middleware('can:view-finance')->name('banking.index');
    Route::post('banking/accounts', [BankingController::class, 'storeAccount'])->middleware('can:manage-treasury')->name('banking.accounts.store');
    Route::post('banking/accounts/{account}/import', [BankingController::class, 'import'])->middleware('can:manage-treasury')->name('banking.import');
    Route::post('banking/accounts/{account}/auto-reconcile', [BankingController::class, 'auto'])->middleware('can:manage-treasury')->name('banking.auto');
    Route::post('banking/transactions/{transaction}/reconcile', [BankingController::class, 'reconcile'])->middleware('can:manage-treasury')->name('banking.reconcile');
    Route::delete('banking/reconciliations/{reconciliation}', [BankingController::class, 'undo'])->middleware('can:manage-treasury')->name('banking.reconciliations.undo');
    Route::post('banking/transactions/{transaction}/post', [BankingController::class, 'postTransaction'])->middleware('can:manage-accounting')->name('banking.transactions.post');
    Route::post('banking/postings/{posting}/reverse', [BankingController::class, 'reversePosting'])->middleware('can:manage-accounting')->name('banking.postings.reverse');
    Route::post('banking/transfers/{transfer}/reverse', [BankingController::class, 'reverseTransfer'])->middleware('can:manage-treasury')->name('banking.transfers.reverse');

    Route::get('tax', [TaxController::class, 'index'])->middleware('can:view-finance')->name('tax.index');
    Route::get('tax/export', [TaxController::class, 'export'])->middleware('can:view-finance')->name('tax.export');
    Route::post('tax/returns', [TaxController::class, 'prepare'])->middleware('can:manage-tax')->name('tax.returns.prepare');
    Route::post('tax/returns/{taxReturn}/file', [TaxController::class, 'file'])->middleware('can:manage-tax')->name('tax.returns.file');
    Route::get('tax/returns/{taxReturn}/receipt', [TaxController::class, 'receipt'])->middleware('can:view-finance')->name('tax.returns.receipt');
    Route::post('tax/returns/{taxReturn}/payments', [TaxController::class, 'pay'])->middleware('can:manage-tax')->name('tax.returns.payments.store');
    Route::get('corporate-tax', [CorporateTaxController::class, 'index'])->middleware('can:view-finance')->name('corporate-tax.index');
    Route::post('corporate-tax/returns', [CorporateTaxController::class, 'prepare'])->middleware('can:manage-tax')->name('corporate-tax.prepare');
    Route::post('corporate-tax/returns/{corporateTaxReturn}/file', [CorporateTaxController::class, 'file'])->middleware('can:manage-tax')->name('corporate-tax.file');
    Route::get('corporate-tax/returns/{corporateTaxReturn}/receipt', [CorporateTaxController::class, 'receipt'])->middleware('can:view-finance')->name('corporate-tax.receipt');

    Route::get('company-fiscal', [FiscalSettingsController::class, 'index'])->middleware('can:manage-tax')->name('fiscal-settings.index');
    Route::post('company-fiscal/company', [FiscalSettingsController::class, 'updateCompany'])->middleware('can:manage-tax')->name('fiscal-settings.company.update');
    Route::get('company-fiscal/logo', [FiscalSettingsController::class, 'logo'])->middleware('can:manage-tax')->name('fiscal-settings.logo');
    Route::post('company-fiscal/years', [FiscalSettingsController::class, 'storeYear'])->middleware('can:manage-tax')->name('fiscal-settings.years.store');
    Route::post('company-fiscal/years/{fiscalYear}/close', [FiscalSettingsController::class, 'closeYear'])->middleware('can:manage-tax')->name('fiscal-settings.years.close');
    Route::post('company-fiscal/years/{fiscalYear}/reopen', [FiscalSettingsController::class, 'reopenYear'])->middleware('can:manage-tax')->name('fiscal-settings.years.reopen');
    Route::post('company-fiscal/years/{fiscalYear}/opening', [FiscalSettingsController::class, 'openNextYear'])->middleware('can:manage-tax')->name('fiscal-settings.years.opening');
    Route::patch('company-fiscal/series/{series}', [FiscalSettingsController::class, 'updateSeries'])->middleware('can:manage-tax')->name('fiscal-settings.series.update');

    Route::get('expenses', [ExpenseController::class, 'index'])->middleware('can:manage-catalog')->name('expenses.index');
    Route::post('expenses', [ExpenseController::class, 'store'])->middleware('can:manage-catalog')->name('expenses.store');
    Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->middleware('can:manage-catalog')->name('expenses.show');
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware('can:manage-catalog')->name('expenses.destroy');
    Route::get('expenses/{expense}/attachments/{attachment}', [ExpenseController::class, 'download'])->middleware('can:manage-catalog')->name('expenses.attachments.download');
    Route::delete('expenses/{expense}/attachments/{attachment}', [ExpenseController::class, 'destroyAttachment'])->middleware('can:manage-catalog')->name('expenses.attachments.destroy');
    Route::post('expenses/{expense}/payments', [ExpenseController::class, 'payExpense'])->middleware('can:manage-treasury')->name('expenses.payments.store');
    Route::post('supplier-invoices/{purchaseDocument}/payments', [ExpenseController::class, 'paySupplierInvoice'])->middleware('can:manage-treasury')->name('supplier-invoices.payments.store');
    Route::delete('supplier-payments/{payment}', [ExpenseController::class, 'destroyPayment'])->middleware('can:manage-treasury')->name('supplier-payments.destroy');
});
