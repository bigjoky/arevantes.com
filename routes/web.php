<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AccountingTemplateController;
use App\Http\Controllers\ActionCenterController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AfterSalesController;
use App\Http\Controllers\AiFeedbackController;
use App\Http\Controllers\AreviaChatController;
use App\Http\Controllers\BankingController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ChartOfAccountsController;
use App\Http\Controllers\CorporateTaxController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentDeliveryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FiscalSettingsController;
use App\Http\Controllers\GeocodingController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\InstallationAiController;
use App\Http\Controllers\InstallationController;
use App\Http\Controllers\InstallationDeyeController;
use App\Http\Controllers\InstallationHuaweiController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\KnowledgeDocumentController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PurchaseComparisonController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseInboxController;
use App\Http\Controllers\PurchaseMailboxController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RemoteStationController;
use App\Http\Controllers\SalesDocumentController;
use App\Http\Controllers\SalesOperationsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StaffAccessController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\SupplierInvoiceDocumentController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\WebsiteContactController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\WorkOrderMaterialsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
Route::post('contact', WebsiteContactController::class)->middleware('throttle:5,1')->name('website.contact');
Route::get('aviso-legal', [LegalController::class, 'notice'])->name('legal.notice');
Route::get('privacidad', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('cookies', [LegalController::class, 'cookies'])->name('legal.cookies');
Route::get('documents/shared/{token}', [DocumentDeliveryController::class, 'show'])->name('documents.shared.show');
Route::post('documents/shared/{token}/respond', [DocumentDeliveryController::class, 'respond'])->middleware('throttle:10,1')->name('documents.shared.respond');
Route::get('documents/shared/{token}/pdf', [DocumentDeliveryController::class, 'pdf'])->middleware('throttle:30,1')->name('documents.shared.pdf');
Route::get('portal/{token}', [CustomerPortalController::class, 'show'])->middleware('throttle:60,1')->name('customer-portal.show');
Route::post('portal/{token}/tickets', [CustomerPortalController::class, 'storeTicket'])->middleware('throttle:10,1')->name('customer-portal.tickets.store');
Route::get('portal/{token}/documents/{kind}/{document}/pdf', [CustomerPortalController::class, 'pdf'])->middleware('throttle:30,1')->name('customer-portal.documents.pdf');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('search', GlobalSearchController::class)->middleware('throttle:60,1')->name('global-search');
    Route::get('action-center', ActionCenterController::class)->middleware('throttle:60,1')->name('action-center');
    Route::post('notifications/read', function (Request $request) {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    })->name('notifications.read');
    Route::get('arevia/conversation', [AreviaChatController::class, 'show'])->middleware('can:use-ai')->name('arevia.conversation');
    Route::post('arevia/messages', [AreviaChatController::class, 'store'])->middleware('can:use-ai')->name('arevia.messages.store');
    Route::post('arevia/messages/{aiMessage}/feedback', [AreviaChatController::class, 'feedback'])->middleware('can:use-ai')->name('arevia.messages.feedback');
    Route::post('geocoding/search', [GeocodingController::class, 'search'])
        ->middleware('throttle:30,1')
        ->name('geocoding.search');
    Route::post('remote-stations/refresh', [RemoteStationController::class, 'refresh'])
        ->middleware(['can:manage-installations', 'throttle:10,1'])
        ->name('remote-stations.refresh');
    Route::resource('people', PersonController::class)->middleware('can:manage-customers');
    Route::resource('opportunities', OpportunityController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:manage-customers');
    Route::post('people/{person}/portal', [CustomerPortalController::class, 'invite'])->middleware('can:manage-customers')->name('people.portal.invite');
    Route::delete('people/{person}/portal/{access}', [CustomerPortalController::class, 'revoke'])->middleware('can:manage-customers')->name('people.portal.revoke');
    Route::resource('staff', StaffController::class)->middleware('can:manage-staff');
    Route::post('staff/{staff}/access', [StaffAccessController::class, 'store'])->middleware('can:manage-staff')->name('staff.access.store');
    Route::patch('staff/{staff}/access', [StaffAccessController::class, 'update'])->middleware('can:manage-staff')->name('staff.access.update');
    Route::delete('staff/{staff}/access', [StaffAccessController::class, 'destroy'])->middleware('can:manage-staff')->name('staff.access.destroy');
    Route::get('knowledge', [KnowledgeDocumentController::class, 'index'])->middleware('can:manage-staff')->name('knowledge.index');
    Route::post('knowledge', [KnowledgeDocumentController::class, 'store'])->middleware('can:manage-staff')->name('knowledge.store');
    Route::post('knowledge/{knowledgeDocument}/reindex', [KnowledgeDocumentController::class, 'reindex'])->middleware('can:manage-staff')->name('knowledge.reindex');
    Route::delete('knowledge/{knowledgeDocument}', [KnowledgeDocumentController::class, 'destroy'])->middleware('can:manage-staff')->name('knowledge.destroy');
    Route::get('operations', [OperationsController::class, 'index'])->middleware('can:manage-operations')->name('operations.index');
    Route::get('work-orders', [WorkOrderController::class, 'index'])->middleware('can:manage-operations')->name('work-orders.index');
    Route::get('after-sales', [AfterSalesController::class, 'index'])->middleware('can:manage-operations')->name('after-sales.index');
    Route::post('after-sales/tickets', [AfterSalesController::class, 'storeTicket'])->middleware('can:manage-operations')->name('after-sales.tickets.store');
    Route::patch('after-sales/tickets/{ticket}', [AfterSalesController::class, 'updateTicket'])->middleware('can:manage-operations')->name('after-sales.tickets.update');
    Route::post('after-sales/tickets/{ticket}/work-order', [AfterSalesController::class, 'createWorkOrder'])->middleware('can:manage-operations')->name('after-sales.tickets.work-order');
    Route::post('after-sales/plans', [AfterSalesController::class, 'storePlan'])->middleware('can:manage-installations')->name('after-sales.plans.store');
    Route::patch('after-sales/plans/{plan}', [AfterSalesController::class, 'updatePlan'])->middleware('can:manage-installations')->name('after-sales.plans.update');
    Route::patch('after-sales/warranties/{serial}', [AfterSalesController::class, 'updateWarranty'])->middleware('can:manage-installations')->name('after-sales.warranties.update');
    Route::post('work-orders', [WorkOrderController::class, 'store'])->middleware('can:manage-installations')->name('work-orders.store');
    Route::patch('work-orders/{workOrder}', [WorkOrderController::class, 'update'])->middleware('can:manage-operations')->name('work-orders.update');
    Route::patch('work-orders/{workOrder}/schedule', [WorkOrderController::class, 'updateSchedule'])->middleware('can:manage-installations')->name('work-orders.schedule.update');
    Route::post('work-orders/{workOrder}/materials/procure', [WorkOrderMaterialsController::class, 'procure'])->middleware('can:manage-catalog')->name('work-orders.materials.procure');
    Route::post('work-orders/{workOrder}/materials', [WorkOrderMaterialsController::class, 'reserve'])->middleware('can:manage-catalog')->name('work-orders.materials.reserve');
    Route::post('work-orders/{workOrder}/materials/{reservation}/consume', [WorkOrderMaterialsController::class, 'consume'])->middleware('can:manage-operations')->name('work-orders.materials.consume');
    Route::delete('work-orders/{workOrder}/materials/{reservation}', [WorkOrderMaterialsController::class, 'release'])->middleware('can:manage-catalog')->name('work-orders.materials.release');
    Route::post('work-orders/{workOrder}/timer', [WorkOrderController::class, 'start'])->middleware('can:manage-operations')->name('work-orders.timer.start');
    Route::patch('time-entries/{timeEntry}/stop', [WorkOrderController::class, 'stop'])->middleware('can:manage-operations')->name('time-entries.stop');
    Route::post('work-orders/{workOrder}/time-entries', [WorkOrderController::class, 'storeTime'])->middleware('can:manage-installations')->name('work-orders.time-entries.store');
    Route::delete('time-entries/{timeEntry}', [WorkOrderController::class, 'destroyTime'])->middleware('can:manage-installations')->name('time-entries.destroy');
    Route::patch('work-orders/{workOrder}/checklist/{item}', [WorkOrderController::class, 'toggleChecklist'])->middleware('can:manage-operations')->name('work-orders.checklist.update');
    Route::post('work-orders/{workOrder}/attachments', [WorkOrderController::class, 'storeAttachments'])->middleware('can:manage-operations')->name('work-orders.attachments.store');
    Route::get('work-orders/{workOrder}/attachments/{attachment}', [WorkOrderController::class, 'downloadAttachment'])->middleware('can:manage-operations')->name('work-orders.attachments.download');
    Route::delete('work-orders/{workOrder}/attachments/{attachment}', [WorkOrderController::class, 'destroyAttachment'])->middleware('can:manage-operations')->name('work-orders.attachments.destroy');
    Route::post('work-orders/{workOrder}/signature', [WorkOrderController::class, 'sign'])->middleware('can:manage-operations')->name('work-orders.signature.store');
    Route::get('work-orders/{workOrder}/signature', [WorkOrderController::class, 'signature'])->middleware('can:manage-operations')->name('work-orders.signature.show');
    Route::get('catalog', [CatalogController::class, 'index'])->middleware('can:manage-catalog')->name('catalog.index');
    Route::get('inventory', [InventoryController::class, 'index'])->middleware('can:manage-catalog')->name('inventory.index');
    Route::get('purchases', [PurchaseController::class, 'index'])->middleware('can:manage-catalog')->name('purchases.index');
    Route::get('purchase-inbox', [PurchaseInboxController::class, 'index'])->middleware('can:manage-catalog')->name('purchase-inbox.index');
    Route::post('purchase-inbox', [PurchaseInboxController::class, 'store'])->middleware('can:manage-catalog')->name('purchase-inbox.store');
    Route::put('purchase-inbox/mailbox', [PurchaseMailboxController::class, 'update'])->middleware('can:manage-staff')->name('purchase-inbox.mailbox.update');
    Route::post('purchase-inbox/mailbox/sync', [PurchaseMailboxController::class, 'sync'])->middleware('can:manage-catalog')->name('purchase-inbox.mailbox.sync');
    Route::get('purchase-inbox/{inboxDocument}', [PurchaseInboxController::class, 'show'])->middleware('can:manage-catalog')->name('purchase-inbox.show');
    Route::get('purchase-inbox/{inboxDocument}/download', [PurchaseInboxController::class, 'download'])->middleware('can:manage-catalog')->name('purchase-inbox.download');
    Route::post('purchase-inbox/{inboxDocument}/extract', [PurchaseInboxController::class, 'extract'])->middleware('can:manage-catalog')->name('purchase-inbox.extract');
    Route::patch('purchase-inbox/{inboxDocument}', [PurchaseInboxController::class, 'update'])->middleware('can:manage-catalog')->name('purchase-inbox.update');
    Route::post('purchase-inbox/{inboxDocument}/convert', [PurchaseInboxController::class, 'convert'])->middleware('can:manage-catalog')->name('purchase-inbox.convert');
    Route::post('purchase-inbox/{inboxDocument}/reject', [PurchaseInboxController::class, 'reject'])->middleware('can:manage-catalog')->name('purchase-inbox.reject');
    Route::get('expenses', [ExpenseController::class, 'index'])->middleware('can:manage-catalog')->name('expenses.index');
    Route::get('finance', [FinanceController::class, 'index'])->middleware('can:manage-catalog')->name('finance.index');
    Route::get('accounting', [AccountingController::class, 'index'])->middleware('can:manage-catalog')->name('accounting.index');
    Route::post('accounting/sync', [AccountingController::class, 'sync'])->middleware('can:manage-catalog')->name('accounting.sync');
    Route::post('accounting/entries', [AccountingController::class, 'store'])->middleware('can:manage-catalog')->name('accounting.entries.store');
    Route::post('accounting/entries/{entry}/reverse', [AccountingController::class, 'reverse'])->middleware('can:manage-catalog')->name('accounting.entries.reverse');
    Route::post('accounting/years/{fiscalYear}/close', [AccountingController::class, 'close'])->middleware('can:manage-staff')->name('accounting.years.close');
    Route::post('accounting/years/{fiscalYear}/opening', [AccountingController::class, 'opening'])->middleware('can:manage-staff')->name('accounting.years.opening');
    Route::patch('accounting/mappings/expense-categories/{category}', [AccountingController::class, 'updateExpenseCategory'])->middleware('can:manage-catalog')->name('accounting.mappings.expense-categories.update');
    Route::patch('accounting/mappings/tax-rates/{taxRate}', [AccountingController::class, 'updateTaxRate'])->middleware('can:manage-catalog')->name('accounting.mappings.tax-rates.update');
    Route::get('accounting/accounts', [ChartOfAccountsController::class, 'index'])->middleware('can:manage-catalog')->name('accounting.accounts.index');
    Route::post('accounting/accounts', [ChartOfAccountsController::class, 'store'])->middleware('can:manage-staff')->name('accounting.accounts.store');
    Route::patch('accounting/accounts/{account}', [ChartOfAccountsController::class, 'update'])->middleware('can:manage-staff')->name('accounting.accounts.update');
    Route::delete('accounting/accounts/{account}', [ChartOfAccountsController::class, 'destroy'])->middleware('can:manage-staff')->name('accounting.accounts.destroy');
    Route::get('accounting/templates', [AccountingTemplateController::class, 'index'])->middleware('can:manage-catalog')->name('accounting.templates.index');
    Route::post('accounting/templates', [AccountingTemplateController::class, 'store'])->middleware('can:manage-catalog')->name('accounting.templates.store');
    Route::patch('accounting/templates/{template}', [AccountingTemplateController::class, 'update'])->middleware('can:manage-catalog')->name('accounting.templates.update');
    Route::delete('accounting/templates/{template}', [AccountingTemplateController::class, 'destroy'])->middleware('can:manage-catalog')->name('accounting.templates.destroy');
    Route::post('accounting/templates/{template}/execute', [AccountingTemplateController::class, 'execute'])->middleware('can:manage-catalog')->name('accounting.templates.execute');
    Route::get('corporate-tax', [CorporateTaxController::class, 'index'])->middleware('can:manage-catalog')->name('corporate-tax.index');
    Route::post('corporate-tax/returns', [CorporateTaxController::class, 'prepare'])->middleware('can:manage-staff')->name('corporate-tax.prepare');
    Route::post('corporate-tax/returns/{corporateTaxReturn}/file', [CorporateTaxController::class, 'file'])->middleware('can:manage-staff')->name('corporate-tax.file');
    Route::get('corporate-tax/returns/{corporateTaxReturn}/receipt', [CorporateTaxController::class, 'receipt'])->middleware('can:manage-catalog')->name('corporate-tax.receipt');
    Route::get('tax', [TaxController::class, 'index'])->middleware('can:manage-catalog')->name('tax.index');
    Route::get('tax/export', [TaxController::class, 'export'])->middleware('can:manage-catalog')->name('tax.export');
    Route::post('tax/returns', [TaxController::class, 'prepare'])->middleware('can:manage-staff')->name('tax.returns.prepare');
    Route::post('tax/returns/{taxReturn}/file', [TaxController::class, 'file'])->middleware('can:manage-staff')->name('tax.returns.file');
    Route::get('tax/returns/{taxReturn}/receipt', [TaxController::class, 'receipt'])->middleware('can:manage-catalog')->name('tax.returns.receipt');
    Route::post('tax/returns/{taxReturn}/payments', [TaxController::class, 'pay'])->middleware('can:manage-staff')->name('tax.returns.payments.store');
    Route::get('banking', [BankingController::class, 'index'])->middleware('can:manage-catalog')->name('banking.index');
    Route::post('banking/accounts', [BankingController::class, 'storeAccount'])->middleware('can:manage-catalog')->name('banking.accounts.store');
    Route::post('banking/accounts/{account}/import', [BankingController::class, 'import'])->middleware('can:manage-catalog')->name('banking.import');
    Route::post('banking/accounts/{account}/auto-reconcile', [BankingController::class, 'auto'])->middleware('can:manage-catalog')->name('banking.auto');
    Route::post('banking/transactions/{transaction}/reconcile', [BankingController::class, 'reconcile'])->middleware('can:manage-catalog')->name('banking.reconcile');
    Route::delete('banking/reconciliations/{reconciliation}', [BankingController::class, 'undo'])->middleware('can:manage-catalog')->name('banking.reconciliations.undo');
    Route::post('banking/transactions/{transaction}/post', [BankingController::class, 'postTransaction'])->middleware('can:manage-catalog')->name('banking.transactions.post');
    Route::post('banking/postings/{posting}/reverse', [BankingController::class, 'reversePosting'])->middleware('can:manage-catalog')->name('banking.postings.reverse');
    Route::post('banking/transfers/{transfer}/reverse', [BankingController::class, 'reverseTransfer'])->middleware('can:manage-catalog')->name('banking.transfers.reverse');
    Route::get('company-fiscal', [FiscalSettingsController::class, 'index'])->middleware('can:manage-staff')->name('fiscal-settings.index');
    Route::post('company-fiscal/company', [FiscalSettingsController::class, 'updateCompany'])->middleware('can:manage-staff')->name('fiscal-settings.company.update');
    Route::get('company-fiscal/logo', [FiscalSettingsController::class, 'logo'])->middleware('can:manage-staff')->name('fiscal-settings.logo');
    Route::post('company-fiscal/years', [FiscalSettingsController::class, 'storeYear'])->middleware('can:manage-staff')->name('fiscal-settings.years.store');
    Route::post('company-fiscal/years/{fiscalYear}/close', [FiscalSettingsController::class, 'closeYear'])->middleware('can:manage-staff')->name('fiscal-settings.years.close');
    Route::patch('company-fiscal/series/{series}', [FiscalSettingsController::class, 'updateSeries'])->middleware('can:manage-staff')->name('fiscal-settings.series.update');
    Route::get('finance/export', [FinanceController::class, 'export'])->middleware('can:manage-catalog')->name('finance.export');
    Route::post('expenses', [ExpenseController::class, 'store'])->middleware('can:manage-catalog')->name('expenses.store');
    Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->middleware('can:manage-catalog')->name('expenses.show');
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware('can:manage-catalog')->name('expenses.destroy');
    Route::get('expenses/{expense}/attachments/{attachment}', [ExpenseController::class, 'download'])->middleware('can:manage-catalog')->name('expenses.attachments.download');
    Route::delete('expenses/{expense}/attachments/{attachment}', [ExpenseController::class, 'destroyAttachment'])->middleware('can:manage-catalog')->name('expenses.attachments.destroy');
    Route::post('expenses/{expense}/payments', [ExpenseController::class, 'payExpense'])->middleware('can:manage-catalog')->name('expenses.payments.store');
    Route::post('supplier-invoices/{purchaseDocument}/payments', [ExpenseController::class, 'paySupplierInvoice'])->middleware('can:manage-catalog')->name('supplier-invoices.payments.store');
    Route::delete('supplier-payments/{payment}', [ExpenseController::class, 'destroyPayment'])->middleware('can:manage-catalog')->name('supplier-payments.destroy');
    Route::post('purchases', [PurchaseController::class, 'store'])->middleware('can:manage-catalog')->name('purchases.store');
    Route::get('purchases/{purchaseDocument}', [PurchaseController::class, 'show'])->middleware('can:manage-catalog')->name('purchases.show');
    Route::post('purchases/{purchaseDocument}/convert', [PurchaseController::class, 'convert'])->middleware('can:manage-catalog')->name('purchases.convert');
    Route::post('purchases/{purchaseDocument}/receive', [PurchaseController::class, 'receive'])->middleware('can:manage-catalog')->name('purchases.receive');
    Route::post('purchases/{purchaseDocument}/return', [PurchaseController::class, 'returnGoods'])->middleware('can:manage-catalog')->name('purchases.return');
    Route::post('purchases/{purchaseDocument}/invoice', [PurchaseController::class, 'invoice'])->middleware('can:manage-catalog')->name('purchases.invoice');
    Route::post('supplier-invoices/{purchaseDocument}/approve', [PurchaseController::class, 'approveInvoice'])->middleware('can:manage-catalog')->name('supplier-invoices.approve');
    Route::post('supplier-invoices/{purchaseDocument}/credit-note', [PurchaseController::class, 'creditNote'])->middleware('can:manage-catalog')->name('supplier-invoices.credit-note');
    Route::post('supplier-invoices/{purchaseDocument}/document', [SupplierInvoiceDocumentController::class, 'store'])->middleware('can:manage-catalog')->name('supplier-invoices.document.store');
    Route::get('supplier-invoices/{purchaseDocument}/document', [SupplierInvoiceDocumentController::class, 'download'])->middleware('can:manage-catalog')->name('supplier-invoices.document.download');
    Route::post('supplier-invoices/{purchaseDocument}/document/extract', [SupplierInvoiceDocumentController::class, 'extract'])->middleware('can:manage-catalog')->name('supplier-invoices.document.extract');
    Route::post('supplier-invoices/{purchaseDocument}/document/verify', [SupplierInvoiceDocumentController::class, 'verify'])->middleware('can:manage-catalog')->name('supplier-invoices.document.verify');
    Route::get('purchase-comparisons/{batch}', [PurchaseComparisonController::class, 'show'])->middleware('can:manage-catalog')->name('purchase-comparisons.show');
    Route::patch('purchase-comparisons/requests/{purchaseDocument}', [PurchaseComparisonController::class, 'respond'])->middleware('can:manage-catalog')->name('purchase-comparisons.respond');
    Route::post('purchase-comparisons/requests/{purchaseDocument}/select', [PurchaseComparisonController::class, 'select'])->middleware('can:manage-catalog')->name('purchase-comparisons.select');
    Route::post('inventory/warehouses', [InventoryController::class, 'storeWarehouse'])->middleware('can:manage-catalog')->name('inventory.warehouses.store');
    Route::patch('inventory/warehouses/{warehouse}', [InventoryController::class, 'updateWarehouse'])->middleware('can:manage-catalog')->name('inventory.warehouses.update');
    Route::post('inventory/movements', [InventoryController::class, 'storeMovement'])->middleware('can:manage-catalog')->name('inventory.movements.store');
    Route::post('inventory/reservations', [InventoryController::class, 'storeReservation'])->middleware('can:manage-catalog')->name('inventory.reservations.store');
    Route::delete('inventory/reservations/{reservation}', [InventoryController::class, 'releaseReservation'])->middleware('can:manage-catalog')->name('inventory.reservations.release');
    Route::post('catalog/products', [CatalogController::class, 'storeProduct'])->middleware('can:manage-catalog')->name('catalog.products.store');
    Route::patch('catalog/products/{product}', [CatalogController::class, 'updateProduct'])->middleware('can:manage-catalog')->name('catalog.products.update');
    Route::delete('catalog/products/{product}', [CatalogController::class, 'destroyProduct'])->middleware('can:manage-catalog')->name('catalog.products.destroy');
    Route::post('catalog/suppliers', [CatalogController::class, 'storeSupplier'])->middleware('can:manage-catalog')->name('catalog.suppliers.store');
    Route::patch('catalog/suppliers/{supplier}', [CatalogController::class, 'updateSupplier'])->middleware('can:manage-catalog')->name('catalog.suppliers.update');
    Route::delete('catalog/suppliers/{supplier}', [CatalogController::class, 'destroySupplier'])->middleware('can:manage-catalog')->name('catalog.suppliers.destroy');
    Route::post('operations/activities', [OperationsController::class, 'store'])->middleware('can:manage-operations')->name('activities.store');
    Route::patch('operations/activities/{activity}', [OperationsController::class, 'update'])->middleware('can:manage-operations')->name('activities.update');
    Route::delete('operations/activities/{activity}', [OperationsController::class, 'destroy'])->middleware('can:manage-operations')->name('activities.destroy');
    Route::post('operations/activities/{activity}/work-order', [OperationsController::class, 'convertToWorkOrder'])->middleware('can:manage-installations')->name('activities.work-order.store');
    Route::get('quotes/create', [QuoteController::class, 'create'])->middleware('can:manage-installations')->name('quotes.create');
    Route::post('quotes', [QuoteController::class, 'store'])->middleware('can:manage-installations')->name('quotes.store');
    Route::get('quotes/{quote}', [QuoteController::class, 'show'])->middleware('can:manage-installations')->name('quotes.show');
    Route::get('quotes/{quote}/edit', [QuoteController::class, 'edit'])->middleware('can:manage-installations')->name('quotes.edit');
    Route::put('quotes/{quote}', [QuoteController::class, 'updateDocument'])->middleware('can:manage-installations')->name('quotes.document.update');
    Route::patch('quotes/{quote}/status', [QuoteController::class, 'updateStatus'])->middleware('can:manage-installations')->name('quotes.status.update');
    Route::post('quotes/{quote}/project', [QuoteController::class, 'startProject'])->middleware('can:manage-installations')->name('quotes.project.store');
    Route::delete('quotes/{quote}', [QuoteController::class, 'destroy'])->middleware('can:manage-installations')->name('quotes.destroy');
    Route::post('quotes/{quote}/send', [DocumentDeliveryController::class, 'sendQuote'])->middleware('can:manage-installations')->name('quotes.send');
    Route::get('quotes/{quote}/pdf', [SalesDocumentController::class, 'quotePdf'])->middleware('can:manage-installations')->name('quotes.pdf');
    Route::post('quotes/{quote}/convert', [SalesDocumentController::class, 'convert'])->middleware('can:manage-installations')->name('quotes.convert');
    Route::get('sales', [SalesDocumentController::class, 'index'])->middleware('can:manage-installations')->name('sales.index');
    Route::get('sales-workflow', [SalesOperationsController::class, 'index'])->middleware('can:manage-installations')->name('sales-workflow.index');
    Route::post('sales-workflow/{salesDocument}/reserve', [SalesOperationsController::class, 'reserve'])->middleware('can:manage-catalog')->name('sales-workflow.reserve');
    Route::post('sales-workflow/{salesDocument}/procure', [SalesOperationsController::class, 'procure'])->middleware('can:manage-catalog')->name('sales-workflow.procure');
    Route::get('sales/create', [SalesDocumentController::class, 'create'])->middleware('can:manage-installations')->name('sales-documents.create');
    Route::post('sales', [SalesDocumentController::class, 'store'])->middleware('can:manage-installations')->name('sales-documents.store');
    Route::get('sales/{salesDocument}/edit', [SalesDocumentController::class, 'edit'])->middleware('can:manage-installations')->name('sales-documents.edit');
    Route::put('sales/{salesDocument}', [SalesDocumentController::class, 'update'])->middleware('can:manage-installations')->name('sales-documents.update');
    Route::get('sales/{salesDocument}', [SalesDocumentController::class, 'show'])->middleware('can:manage-installations')->name('sales-documents.show');
    Route::delete('sales/{salesDocument}', [SalesDocumentController::class, 'destroy'])->middleware('can:manage-installations')->name('sales-documents.destroy');
    Route::get('sales/{salesDocument}/pdf', [SalesDocumentController::class, 'pdf'])->middleware('can:manage-installations')->name('sales-documents.pdf');
    Route::post('sales/{salesDocument}/send', [DocumentDeliveryController::class, 'sendSalesDocument'])->middleware('can:manage-installations')->name('sales-documents.send');
    Route::post('sales/{salesDocument}/issue', [SalesDocumentController::class, 'issue'])->middleware('can:manage-installations')->name('sales-documents.issue');
    Route::post('sales/{salesDocument}/convert', [SalesDocumentController::class, 'convertDocument'])->middleware('can:manage-installations')->name('sales-documents.convert');
    Route::post('sales/{salesDocument}/payments', [SalesDocumentController::class, 'storePayment'])->middleware('can:manage-installations')->name('sales-documents.payments.store');
    Route::delete('sales/{salesDocument}/payments/{payment}', [SalesDocumentController::class, 'destroyPayment'])->middleware('can:manage-installations')->name('sales-documents.payments.destroy');
    Route::post('sales/{salesDocument}/credit-note', [SalesDocumentController::class, 'creditNote'])->middleware('can:manage-installations')->name('sales-documents.credit-note');
    Route::post('sales/{salesDocument}/dispatch', [SalesDocumentController::class, 'dispatch'])->middleware('can:manage-installations')->name('sales-documents.dispatch');
    Route::get('admin', [AdminController::class, 'index'])->middleware('can:manage-staff')->name('admin.index');
    Route::get('admin/users', [AdminController::class, 'users'])->middleware('can:manage-staff')->name('admin.users.index');
    Route::get('admin/backups', [AdminController::class, 'backups'])->middleware('can:manage-staff')->name('admin.backups.index');
    Route::get('admin/audit', [AdminController::class, 'audit'])->middleware('can:manage-staff')->name('admin.audit.index');
    Route::patch('admin/users/{user}', [AdminController::class, 'updateUser'])->middleware('can:manage-staff')->name('admin.users.update');
    Route::post('admin/backups', [AdminController::class, 'backup'])->middleware('can:manage-staff')->name('admin.backups.store');
    Route::get('admin/backups/{backup}', [AdminController::class, 'downloadBackup'])->middleware('can:manage-staff')->name('admin.backups.download');
    Route::post('installations/{installation}/ai/briefing', [InstallationAiController::class, 'briefing'])
        ->middleware('can:use-ai')
        ->name('installations.ai.briefing');
    Route::get('installations/{installation}/ai/briefings/{aiGeneration:uuid}', [InstallationAiController::class, 'status'])
        ->middleware('can:use-ai')
        ->name('installations.ai.briefing.status');
    Route::post('installations/{installation}/ai/briefings/{aiGeneration:uuid}/activities', [InstallationAiController::class, 'createActivities'])
        ->middleware('can:manage-operations')
        ->name('installations.ai.briefing.activities');
    Route::post('installations/{installation}/ai/briefings/{aiGeneration:uuid}/feedback', [AiFeedbackController::class, 'store'])
        ->middleware('can:use-ai')
        ->name('installations.ai.briefing.feedback');
    Route::post('knowledge/feedback/{aiFeedback}/approve', [AiFeedbackController::class, 'approve'])
        ->middleware('can:manage-staff')
        ->name('knowledge.feedback.approve');
    Route::post('installations/{installation}/deye/sync', [InstallationDeyeController::class, 'sync'])
        ->middleware('can:sync-installations')
        ->name('installations.deye.sync');
    Route::post('installations/{installation}/huawei/sync', [InstallationHuaweiController::class, 'sync'])
        ->middleware('can:sync-installations')
        ->name('installations.huawei.sync');
    Route::resource('installations', InstallationController::class)
        ->except(['index', 'show'])
        ->middleware('can:manage-installations');
    Route::resource('installations', InstallationController::class)
        ->only(['index', 'show'])
        ->middleware('can:view-installations');
});

require __DIR__.'/settings.php';
