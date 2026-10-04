<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AccountingTemplateController;
use App\Http\Controllers\ActionCenterController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AfterSalesController;
use App\Http\Controllers\AiFeedbackController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AreviaChatController;
use App\Http\Controllers\AssetRmaController;
use App\Http\Controllers\BankingController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ChartOfAccountsController;
use App\Http\Controllers\CorporateTaxController;
use App\Http\Controllers\CrmSettingsController;
use App\Http\Controllers\CustomerConsentController;
use App\Http\Controllers\CustomerContactController;
use App\Http\Controllers\CustomerMergeController;
use App\Http\Controllers\CustomerPhoneLookupController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\CustomerTagController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentDeliveryController;
use App\Http\Controllers\EngineeringDossierController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseIssuerLookupController;
use App\Http\Controllers\FieldServiceSettingsController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FiscalSettingsController;
use App\Http\Controllers\GeocodingController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HrDepartmentController;
use App\Http\Controllers\HrEmployeeDossierController;
use App\Http\Controllers\HrEmploymentController;
use App\Http\Controllers\HrLeaveController;
use App\Http\Controllers\HrTaskController;
use App\Http\Controllers\HumanResourcesController;
use App\Http\Controllers\InstallationAiController;
use App\Http\Controllers\InstallationAssetController;
use App\Http\Controllers\InstallationController;
use App\Http\Controllers\InstallationDeyeController;
use App\Http\Controllers\InstallationHuaweiController;
use App\Http\Controllers\InstallationPersonController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\KnowledgeDocumentController;
use App\Http\Controllers\KnowledgeSourceController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MaintenancePlanMaterialController;
use App\Http\Controllers\MyHumanResourcesController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PlanningScenarioController;
use App\Http\Controllers\PlanningScenarioRequestController;
use App\Http\Controllers\PortfolioProjectAdminController;
use App\Http\Controllers\PortfolioProjectImageController;
use App\Http\Controllers\PublicProjectController;
use App\Http\Controllers\PublicServiceController;
use App\Http\Controllers\PurchaseApprovalRuleController;
use App\Http\Controllers\PurchaseComparisonController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseDeliveryController;
use App\Http\Controllers\PurchaseInboxController;
use App\Http\Controllers\PurchaseMailboxController;
use App\Http\Controllers\PurchaseSettingsController;
use App\Http\Controllers\PurchaseWorkflowController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RecurringServiceCorrectivePlanController;
use App\Http\Controllers\RecurringServiceImprovementController;
use App\Http\Controllers\RecurringServiceQualityController;
use App\Http\Controllers\RemoteStationController;
use App\Http\Controllers\ReplenishmentController;
use App\Http\Controllers\ReplenishmentTargetController;
use App\Http\Controllers\SalesDocumentController;
use App\Http\Controllers\SalesOperationsController;
use App\Http\Controllers\SavedCrmFilterController;
use App\Http\Controllers\SerialTraceabilityController;
use App\Http\Controllers\ServiceCycleCommitmentController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StaffAccessController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\SupplierContactController;
use App\Http\Controllers\SupplierIntelligenceController;
use App\Http\Controllers\SupplierInvoiceDocumentController;
use App\Http\Controllers\SupplierQualityController;
use App\Http\Controllers\SupplyAnalyticsController;
use App\Http\Controllers\SupplyControlTowerController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\WebsiteContactController;
use App\Http\Controllers\WebVitalController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\WorkOrderMaterialsController;
use App\Http\Controllers\WorkspaceShortcutController;
use App\Http\Middleware\ProtectCustomerPortalLinks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::patch('locale', [LocaleController::class, 'update'])->name('locale.update');
Route::get('proyectos', [PublicProjectController::class, 'index'])->name('projects.index');
Route::get('proyectos/{project}', [PublicProjectController::class, 'show'])->name('projects.show');
Route::get('proyectos/imagenes/{portfolioProjectImage}', PortfolioProjectImageController::class)->name('project-images.show');
Route::get('servicios/{service}', PublicServiceController::class)
    ->whereIn('service', ['energia', 'electricidad', 'seguridad'])
    ->name('services.show');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
Route::post('contact', WebsiteContactController::class)->middleware('throttle:5,1')->name('website.contact');
Route::post('performance/web-vitals', [WebVitalController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('performance.web-vitals.store');
Route::get('aviso-legal', [LegalController::class, 'notice'])->name('legal.notice');
Route::get('privacidad', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('cookies', [LegalController::class, 'cookies'])->name('legal.cookies');
Route::middleware(ProtectCustomerPortalLinks::class)->group(function (): void {
    Route::get('documents/shared/{token}', [DocumentDeliveryController::class, 'show'])->name('documents.shared.show');
    Route::post('documents/shared/{token}/respond', [DocumentDeliveryController::class, 'respond'])->middleware('throttle:10,1')->name('documents.shared.respond');
    Route::get('documents/shared/{token}/pdf', [DocumentDeliveryController::class, 'pdf'])->middleware('throttle:30,1')->name('documents.shared.pdf');
    Route::get('portal/{token}', [CustomerPortalController::class, 'show'])->middleware('throttle:60,1')->name('customer-portal.show');
    Route::post('portal/{token}/tickets', [CustomerPortalController::class, 'storeTicket'])->middleware('throttle:10,1')->name('customer-portal.tickets.store');
    Route::get('portal/{token}/documents/{kind}/{document}/pdf', [CustomerPortalController::class, 'pdf'])->middleware('throttle:30,1')->name('customer-portal.documents.pdf');
    Route::post('portal/{token}/documents/{kind}/{document}/accept', [CustomerPortalController::class, 'accept'])->middleware('throttle:10,1')->name('customer-portal.documents.accept');
    Route::patch('portal/{token}/preferences', [CustomerPortalController::class, 'updatePreferences'])->middleware('throttle:10,1')->name('customer-portal.preferences.update');
    Route::post('portal/{token}/surveys', [CustomerPortalController::class, 'storeSurvey'])->middleware('throttle:10,1')->name('customer-portal.surveys.store');
    Route::get('portal/{token}/certificates/{attachment}', [CustomerPortalController::class, 'certificate'])->middleware('throttle:30,1')->name('customer-portal.certificates.download');
});

Route::middleware(['auth'])->group(function () {
    Route::patch('/workspace/favorite', [WorkspaceShortcutController::class, 'update'])->name('workspace.favorite');
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('dashboard/installation-map', [DashboardController::class, 'installationMap'])
        ->middleware('can:view-installations')
        ->name('dashboard.installation-map');
    Route::resource('website/projects', PortfolioProjectAdminController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['projects' => 'portfolioProject'])
        ->names('website.projects')
        ->middleware('can:manage-admin');
    Route::delete('website/project-images/{portfolioProjectImage}', [PortfolioProjectAdminController::class, 'destroyImage'])
        ->middleware('can:manage-admin')
        ->name('website.project-images.destroy');
    Route::get('analytics', [AnalyticsController::class, 'index'])->middleware('can:view-analytics')->name('analytics.index');
    Route::get('analytics/export/{format}', [AnalyticsController::class, 'export'])->middleware('can:view-analytics')->name('analytics.export');
    Route::post('analytics/alerts', [AnalyticsController::class, 'storeAlert'])->middleware('can:view-analytics')->name('analytics.alerts.store');
    Route::patch('analytics/alerts/{analyticsAlert}', [AnalyticsController::class, 'updateAlert'])->middleware('can:view-analytics')->name('analytics.alerts.update');
    Route::delete('analytics/alerts/{analyticsAlert}', [AnalyticsController::class, 'destroyAlert'])->middleware('can:view-analytics')->name('analytics.alerts.destroy');
    Route::post('analytics/subscriptions', [AnalyticsController::class, 'storeSubscription'])->middleware('can:view-analytics')->name('analytics.subscriptions.store');
    Route::delete('analytics/subscriptions/{analyticsReportSubscription}', [AnalyticsController::class, 'destroySubscription'])->middleware('can:view-analytics')->name('analytics.subscriptions.destroy');
    Route::get('search', GlobalSearchController::class)->middleware('throttle:60,1')->name('global-search');
    Route::get('action-center', ActionCenterController::class)->middleware('throttle:60,1')->name('action-center');
    Route::post('notifications/read', function (Request $request) {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    })->name('notifications.read');
    Route::get('arevia/conversation', [AreviaChatController::class, 'show'])->middleware('can:use-ai')->name('arevia.conversation');
    Route::post('arevia/messages', [AreviaChatController::class, 'store'])->middleware(['can:use-ai', 'throttle:arevia-messages'])->name('arevia.messages.store');
    Route::post('arevia/messages/{aiMessage}/feedback', [AreviaChatController::class, 'feedback'])->middleware('can:use-ai')->name('arevia.messages.feedback');
    Route::get('arevia/sources/{knowledgeDocument}/versions/{version}', [KnowledgeSourceController::class, 'show'])->middleware('can:use-ai')->name('arevia.sources.show');
    Route::post('geocoding/search', [GeocodingController::class, 'search'])
        ->middleware(['can:manage-installations', 'throttle:30,1'])
        ->name('geocoding.search');
    Route::post('remote-stations/refresh', [RemoteStationController::class, 'refresh'])
        ->middleware(['can:manage-installations', 'throttle:10,1'])
        ->name('remote-stations.refresh');
    Route::post('people/{person}/restore', [PersonController::class, 'restore'])
        ->middleware('can:manage-customers')
        ->name('people.restore');
    Route::resource('people', PersonController::class)->middleware('can:manage-customers');
    Route::post('people/{person}/contacts', [CustomerContactController::class, 'store'])->middleware('can:manage-customers')->name('people.contacts.store');
    Route::patch('people/{person}/contacts/{contact}', [CustomerContactController::class, 'update'])->middleware('can:manage-customers')->name('people.contacts.update');
    Route::delete('people/{person}/contacts/{contact}', [CustomerContactController::class, 'destroy'])->middleware('can:manage-customers')->name('people.contacts.destroy');
    Route::post('people/{person}/tags', [CustomerTagController::class, 'store'])->middleware('can:manage-customers')->name('people.tags.store');
    Route::delete('people/{person}/tags/{tag}', [CustomerTagController::class, 'destroy'])->middleware('can:manage-customers')->name('people.tags.destroy');
    Route::post('people/{person}/consents', [CustomerConsentController::class, 'store'])->middleware('can:manage-customers')->name('people.consents.store');
    Route::post('people/{person}/merge', CustomerMergeController::class)->middleware('can:manage-customers')->name('people.merge');
    Route::post('crm/filters', [SavedCrmFilterController::class, 'store'])->middleware('can:manage-customers')->name('crm-filters.store');
    Route::get('crm/customers/lookup', CustomerPhoneLookupController::class)
        ->middleware(['can:manage-customers', 'throttle:60,1'])
        ->name('crm-customers.lookup');
    Route::delete('crm/filters/{filter}', [SavedCrmFilterController::class, 'destroy'])->middleware('can:manage-customers')->name('crm-filters.destroy');
    Route::patch('crm/settings', [CrmSettingsController::class, 'update'])->middleware('can:manage-customers')->name('crm-settings.update');
    Route::post('opportunities/{opportunity}/activities', [OpportunityController::class, 'storeActivity'])
        ->middleware('can:manage-customers')
        ->name('opportunities.activities.store');
    Route::patch('opportunities/{opportunity}/activities/{activity}/complete', [OpportunityController::class, 'completeActivity'])
        ->middleware('can:manage-customers')
        ->name('opportunities.activities.complete');
    Route::post('opportunities/{opportunity}/notes', [OpportunityController::class, 'storeNote'])
        ->middleware('can:manage-customers')
        ->name('opportunities.notes.store');
    Route::post('opportunities/{opportunity}/restore', [OpportunityController::class, 'restore'])
        ->middleware('can:manage-customers')
        ->name('opportunities.restore');
    Route::resource('opportunities', OpportunityController::class)->only(['index', 'show', 'store', 'update', 'destroy'])->withTrashed(['show'])->middleware('can:manage-customers');
    Route::get('engineering', [EngineeringDossierController::class, 'index'])->middleware('can:view-installations')->name('engineering.index');
    Route::post('engineering', [EngineeringDossierController::class, 'store'])->middleware('can:manage-installations')->name('engineering.store');
    Route::patch('engineering/{engineeringDossier}', [EngineeringDossierController::class, 'update'])->middleware('can:manage-installations')->name('engineering.update');
    Route::patch('engineering/{engineeringDossier}/transition', [EngineeringDossierController::class, 'transition'])->middleware('can:manage-installations')->name('engineering.transition');
    Route::patch('engineering/{engineeringDossier}/planning', [EngineeringDossierController::class, 'planning'])->name('engineering.planning');
    Route::patch('engineering/{engineeringDossier}/schedule', [EngineeringDossierController::class, 'schedule'])->middleware('can:manage-operations')->name('engineering.schedule');
    Route::get('engineering/{engineeringDossier}/availability', [EngineeringDossierController::class, 'availability'])->middleware('can:manage-operations')->name('engineering.availability');
    Route::post('engineering/{engineeringDossier}/work-order', [EngineeringDossierController::class, 'createWorkOrder'])->middleware('can:manage-operations')->name('engineering.work-order');
    Route::post('people/{person}/portal', [CustomerPortalController::class, 'invite'])->middleware('can:manage-customers')->name('people.portal.invite');
    Route::delete('people/{person}/portal/{access}', [CustomerPortalController::class, 'revoke'])->middleware('can:manage-customers')->name('people.portal.revoke');
    Route::delete('document-deliveries/{delivery}', [DocumentDeliveryController::class, 'revoke'])->middleware('can:manage-installations')->name('document-deliveries.revoke');
    Route::resource('staff', StaffController::class)->middleware('can:manage-staff');
    Route::get('human-resources', HumanResourcesController::class)->middleware('can:manage-staff')->name('human-resources.index');
    Route::get('human-resources/employees', [HrEmploymentController::class, 'index'])->middleware('can:manage-staff')->name('human-resources.employees.index');
    Route::get('human-resources/employees/{staff}', [HrEmployeeDossierController::class, 'show'])->middleware('can:manage-staff')->name('human-resources.employees.show');
    Route::put('human-resources/employees/{staff}', [HrEmploymentController::class, 'update'])->middleware('can:manage-staff')->name('human-resources.employees.update');
    Route::put('human-resources/employees/{staff}/sensitive', [HrEmploymentController::class, 'updateSensitive'])->middleware('can:manage-sensitive-hr')->name('human-resources.employees.sensitive.update');
    Route::post('human-resources/employees/{staff}/emergency-contacts', [HrEmployeeDossierController::class, 'storeContact'])->middleware('can:manage-sensitive-hr')->name('human-resources.employees.contacts.store');
    Route::delete('human-resources/employees/{staff}/emergency-contacts/{contact}', [HrEmployeeDossierController::class, 'destroyContact'])->middleware('can:manage-sensitive-hr')->name('human-resources.employees.contacts.destroy');
    Route::post('human-resources/employees/{staff}/documents', [HrEmployeeDossierController::class, 'storeDocument'])->middleware('can:manage-sensitive-hr')->name('human-resources.employees.documents.store');
    Route::get('human-resources/employees/{staff}/documents/{document}', [HrEmployeeDossierController::class, 'downloadDocument'])->middleware('can:manage-staff')->name('human-resources.employees.documents.download');
    Route::delete('human-resources/employees/{staff}/documents/{document}', [HrEmployeeDossierController::class, 'destroyDocument'])->middleware('can:manage-sensitive-hr')->name('human-resources.employees.documents.destroy');
    Route::get('human-resources/employees/{staff}/export', [HrEmployeeDossierController::class, 'export'])->middleware('can:view-sensitive-hr')->name('human-resources.employees.export');
    Route::resource('human-resources/departments', HrDepartmentController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:manage-staff')->names('human-resources.departments');
    Route::resource('human-resources/leave', HrLeaveController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:manage-staff')->names('human-resources.leave');
    Route::put('human-resources/leave/policies/{policy}', [HrLeaveController::class, 'updatePolicy'])->middleware('can:manage-staff')->name('human-resources.leave.policies.update');
    Route::put('human-resources/leave/balances/{staff}/{policy}', [HrLeaveController::class, 'updateBalance'])->middleware('can:manage-staff')->name('human-resources.leave.balances.update');
    Route::get('human-resources/leave/{leave}/attachments/{attachment}', [HrLeaveController::class, 'downloadAttachment'])->middleware('can:view-sensitive-hr')->name('human-resources.leave.attachments.download');
    Route::resource('human-resources/tasks', HrTaskController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:manage-staff')->names('human-resources.tasks');
    Route::get('my-human-resources', [MyHumanResourcesController::class, 'index'])->name('my-human-resources.index');
    Route::post('my-human-resources/leave', [MyHumanResourcesController::class, 'store'])->name('my-human-resources.leave.store');
    Route::patch('my-human-resources/leave/{leave}/cancel', [MyHumanResourcesController::class, 'cancel'])->name('my-human-resources.leave.cancel');
    Route::patch('my-human-resources/tasks/{task}/complete', [MyHumanResourcesController::class, 'completeTask'])->name('my-human-resources.tasks.complete');
    Route::get('my-human-resources/documents/{document}', [MyHumanResourcesController::class, 'downloadDocument'])->name('my-human-resources.documents.download');
    Route::get('my-human-resources/leave/{leave}/attachments/{attachment}', [MyHumanResourcesController::class, 'downloadLeaveAttachment'])->name('my-human-resources.leave.attachments.download');
    Route::post('staff/{staff}/access', [StaffAccessController::class, 'store'])->middleware('can:manage-access')->name('staff.access.store');
    Route::post('staff/{staff}/access/link', [StaffAccessController::class, 'link'])->middleware('can:manage-access')->name('staff.access.link');
    Route::patch('staff/{staff}/access', [StaffAccessController::class, 'update'])->middleware('can:manage-access')->name('staff.access.update');
    Route::delete('staff/{staff}/access', [StaffAccessController::class, 'destroy'])->middleware('can:manage-access')->name('staff.access.destroy');
    Route::get('knowledge', [KnowledgeDocumentController::class, 'index'])->middleware('can:manage-staff')->name('knowledge.index');
    Route::post('knowledge', [KnowledgeDocumentController::class, 'store'])->middleware('can:manage-staff')->name('knowledge.store');
    Route::patch('knowledge/{knowledgeDocument}', [KnowledgeDocumentController::class, 'update'])->middleware('can:manage-staff')->name('knowledge.update');
    Route::post('knowledge/{knowledgeDocument}/reindex', [KnowledgeDocumentController::class, 'reindex'])->middleware('can:manage-staff')->name('knowledge.reindex');
    Route::delete('knowledge/{knowledgeDocument}', [KnowledgeDocumentController::class, 'destroy'])->middleware('can:manage-staff')->name('knowledge.destroy');
    Route::get('operations', [OperationsController::class, 'index'])->middleware('can:manage-operations')->name('operations.index');
    Route::get('work-orders', [WorkOrderController::class, 'index'])->middleware('can:manage-operations')->name('work-orders.index');
    Route::get('after-sales', [AfterSalesController::class, 'index'])->middleware('can:manage-operations')->name('after-sales.index');
    Route::get('maintenance', [AfterSalesController::class, 'maintenance'])->middleware('can:manage-operations')->name('maintenance.index');
    Route::get('assets', [AfterSalesController::class, 'assets'])->middleware('can:manage-operations')->name('assets.index');
    Route::get('contracts-services', [AfterSalesController::class, 'contracts'])->middleware('can:manage-operations')->name('contracts-services.index');
    Route::get('quality/service', [RecurringServiceQualityController::class, 'index'])->middleware('can:manage-operations')->name('service-quality.index');
    Route::get('after-sales/quality', [RecurringServiceQualityController::class, 'index'])->middleware('can:manage-operations')->name('after-sales.quality.index');
    Route::get('improvement-decisions', RecurringServiceImprovementController::class)->name('improvement-decisions.index');
    Route::post('after-sales/quality/analyse', [RecurringServiceQualityController::class, 'analyse'])->middleware('can:manage-operations')->name('after-sales.quality.analyse');
    Route::patch('after-sales/quality/deviations/{deviation}', [RecurringServiceQualityController::class, 'review'])->middleware('can:manage-operations')->name('after-sales.quality.review');
    Route::post('after-sales/quality/deviations/{deviation}/corrective-plans', [RecurringServiceCorrectivePlanController::class, 'store'])->middleware('can:manage-operations')->name('after-sales.quality.corrective-plans.store');
    Route::patch('after-sales/quality/corrective-plans/{correctivePlan}', [RecurringServiceCorrectivePlanController::class, 'transition'])->middleware('can:manage-operations')->name('after-sales.quality.corrective-plans.transition');
    Route::post('after-sales/quality/corrective-plans/{correctivePlan}/actions', [RecurringServiceCorrectivePlanController::class, 'storeAction'])->middleware('can:manage-operations')->name('after-sales.quality.corrective-plans.actions.store');
    Route::patch('after-sales/quality/corrective-actions/{correctiveAction}', [RecurringServiceCorrectivePlanController::class, 'updateAction'])->middleware('can:manage-operations')->name('after-sales.quality.corrective-actions.update');
    Route::post('after-sales/quality/corrective-plans/{correctivePlan}/proposals', [RecurringServiceCorrectivePlanController::class, 'storeProposal'])->middleware('can:manage-operations')->name('after-sales.quality.corrective-plans.proposals.store');
    Route::patch('after-sales/quality/corrective-proposals/{correctiveProposal}', [RecurringServiceCorrectivePlanController::class, 'decideProposal'])->name('after-sales.quality.corrective-proposals.decision');
    Route::post('improvement-decisions/{correctiveProposal}/apply', [RecurringServiceCorrectivePlanController::class, 'applyProposal'])->name('improvement-decisions.apply');
    Route::patch('improvement-decisions/{correctiveProposal}/supersede', [RecurringServiceCorrectivePlanController::class, 'supersedeProposal'])->name('improvement-decisions.supersede');
    Route::post('after-sales/tickets', [AfterSalesController::class, 'storeTicket'])->middleware('can:manage-operations')->name('after-sales.tickets.store');
    Route::patch('after-sales/tickets/{ticket}', [AfterSalesController::class, 'updateTicket'])->middleware('can:manage-operations')->name('after-sales.tickets.update');
    Route::post('after-sales/tickets/{ticket}/work-order', [AfterSalesController::class, 'createWorkOrder'])->middleware('can:manage-operations')->name('after-sales.tickets.work-order');
    Route::post('after-sales/plans', [AfterSalesController::class, 'storePlan'])->middleware('can:manage-installations')->name('after-sales.plans.store');
    Route::patch('after-sales/plans/{plan}', [AfterSalesController::class, 'updatePlan'])->middleware('can:manage-installations')->name('after-sales.plans.update');
    Route::post('after-sales/plans/{plan}/renewal', [AfterSalesController::class, 'prepareRenewal'])->middleware('can:manage-customers')->name('after-sales.plans.renewal');
    Route::post('after-sales/plans/{plan}/materials', [MaintenancePlanMaterialController::class, 'store'])->middleware('can:manage-installations')->name('after-sales.plans.materials.store');
    Route::delete('after-sales/plans/{plan}/materials/{material}', [MaintenancePlanMaterialController::class, 'destroy'])->middleware('can:manage-installations')->name('after-sales.plans.materials.destroy');
    Route::post('after-sales/plans/{plan}/commitments', [ServiceCycleCommitmentController::class, 'store'])->middleware('can:manage-installations')->name('after-sales.plans.commitments.store');
    Route::post('after-sales/commitments/{commitment}/materialize', [ServiceCycleCommitmentController::class, 'materialize'])->middleware('can:manage-installations')->name('after-sales.commitments.materialize');
    Route::post('after-sales/commitments/{commitment}/schedule/confirm', [ServiceCycleCommitmentController::class, 'confirmSchedule'])->middleware('can:manage-installations')->name('after-sales.commitments.schedule.confirm');
    Route::post('after-sales/commitments/{commitment}/billing', [ServiceCycleCommitmentController::class, 'prepareBilling'])->middleware('can:manage-treasury')->name('after-sales.commitments.billing.store');
    Route::post('after-sales/commitments/{commitment}/communications', [ServiceCycleCommitmentController::class, 'prepareCommunication'])->middleware('can:manage-customers')->name('after-sales.commitments.communications.store');
    Route::post('after-sales/commitments/{commitment}/communications/{communication}/confirm', [ServiceCycleCommitmentController::class, 'confirmCommunication'])->middleware('can:manage-customers')->name('after-sales.commitments.communications.confirm');
    Route::post('after-sales/commitments/{commitment}/communications/{communication}/sent', [ServiceCycleCommitmentController::class, 'markCommunicationSent'])->middleware('can:manage-customers')->name('after-sales.commitments.communications.sent');
    Route::patch('after-sales/warranties/{serial}', [AfterSalesController::class, 'updateWarranty'])->middleware('can:manage-installations')->name('after-sales.warranties.update');
    Route::post('after-sales/sla-policies', [AfterSalesController::class, 'storeSlaPolicy'])->middleware('can:manage-installations')->name('after-sales.sla-policies.store');
    Route::patch('work-orders/bulk-reschedule', [WorkOrderController::class, 'bulkReschedule'])->middleware('can:manage-installations')->name('work-orders.bulk-reschedule');
    Route::post('work-orders', [WorkOrderController::class, 'store'])->middleware('can:manage-installations')->name('work-orders.store');
    Route::patch('work-orders/{workOrder}', [WorkOrderController::class, 'update'])->middleware('can:manage-operations')->name('work-orders.update');
    Route::patch('work-orders/{workOrder}/details', [WorkOrderController::class, 'updateDetails'])->middleware('can:manage-operations')->name('work-orders.details.update');
    Route::patch('work-orders/{workOrder}/schedule', [WorkOrderController::class, 'updateSchedule'])->middleware('can:manage-installations')->name('work-orders.schedule.update');
    Route::post('work-orders/{workOrder}/materials/procure', [WorkOrderMaterialsController::class, 'procure'])->middleware('can:manage-catalog')->name('work-orders.materials.procure');
    Route::post('work-orders/{workOrder}/materials', [WorkOrderMaterialsController::class, 'reserve'])->middleware('can:manage-catalog')->name('work-orders.materials.reserve');
    Route::post('work-orders/{workOrder}/materials/reserve-kit', [WorkOrderMaterialsController::class, 'reserveKit'])->middleware('can:manage-catalog')->name('work-orders.materials.reserve-kit');
    Route::post('work-orders/{workOrder}/materials/{reservation}/consume', [WorkOrderMaterialsController::class, 'consume'])->middleware('can:manage-operations')->name('work-orders.materials.consume');
    Route::delete('work-orders/{workOrder}/materials/{reservation}', [WorkOrderMaterialsController::class, 'release'])->middleware('can:manage-catalog')->name('work-orders.materials.release');
    Route::post('work-orders/{workOrder}/timer', [WorkOrderController::class, 'start'])->middleware('can:manage-operations')->name('work-orders.timer.start');
    Route::patch('time-entries/{timeEntry}/stop', [WorkOrderController::class, 'stop'])->middleware('can:manage-operations')->name('time-entries.stop');
    Route::post('work-orders/{workOrder}/time-entries', [WorkOrderController::class, 'storeTime'])->middleware('can:manage-installations')->name('work-orders.time-entries.store');
    Route::delete('time-entries/{timeEntry}', [WorkOrderController::class, 'destroyTime'])->middleware('can:manage-installations')->name('time-entries.destroy');
    Route::patch('work-orders/{workOrder}/checklist/{item}', [WorkOrderController::class, 'toggleChecklist'])->middleware('can:manage-operations')->name('work-orders.checklist.update');
    Route::post('work-orders/{workOrder}/attachments', [WorkOrderController::class, 'storeAttachments'])->middleware('can:manage-operations')->name('work-orders.attachments.store');
    Route::patch('work-orders/{workOrder}/attachments/{attachment}/publication', [WorkOrderController::class, 'updateAttachmentPublication'])->middleware('can:manage-operations')->name('work-orders.attachments.publication');
    Route::get('work-orders/{workOrder}/attachments/{attachment}', [WorkOrderController::class, 'downloadAttachment'])->middleware('can:manage-operations')->name('work-orders.attachments.download');
    Route::delete('work-orders/{workOrder}/attachments/{attachment}', [WorkOrderController::class, 'destroyAttachment'])->middleware('can:manage-operations')->name('work-orders.attachments.destroy');
    Route::post('work-orders/{workOrder}/signature', [WorkOrderController::class, 'sign'])->middleware('can:manage-operations')->name('work-orders.signature.store');
    Route::get('work-orders/{workOrder}/signature', [WorkOrderController::class, 'signature'])->middleware('can:manage-operations')->name('work-orders.signature.show');
    Route::get('catalog', [CatalogController::class, 'index'])->middleware('can:manage-catalog')->name('catalog.index');
    Route::get('catalog/settings', [CatalogController::class, 'settings'])->middleware('can:manage-catalog')->name('catalog.settings');
    Route::get('inventory/reservations', [InventoryController::class, 'index'])->middleware('can:manage-catalog')->name('inventory.reservations.index');
    Route::get('inventory/movements', [InventoryController::class, 'index'])->middleware('can:manage-catalog')->name('inventory.movements.index');
    Route::get('inventory/warehouses', [InventoryController::class, 'index'])->middleware('can:manage-catalog')->name('inventory.warehouses.index');
    Route::get('suppliers', [CatalogController::class, 'suppliers'])->middleware('can:manage-catalog')->name('suppliers.index');
    Route::get('inventory', [InventoryController::class, 'index'])->middleware('can:manage-catalog')->name('inventory.index');
    Route::get('inventory/serials', [SerialTraceabilityController::class, 'index'])->middleware('can:view-purchases')->name('inventory.serials.index');
    Route::get('inventory/counts', [StockCountController::class, 'index'])->middleware('can:manage-catalog')->name('stock-counts.index');
    Route::post('inventory/counts', [StockCountController::class, 'store'])->middleware('can:manage-catalog')->name('stock-counts.store');
    Route::get('inventory/counts/{stockCount}', [StockCountController::class, 'show'])->middleware('can:manage-catalog')->name('stock-counts.show');
    Route::patch('inventory/counts/{stockCount}', [StockCountController::class, 'update'])->middleware('can:manage-catalog')->name('stock-counts.update');
    Route::post('inventory/counts/{stockCount}/post', [StockCountController::class, 'post'])->middleware('can:manage-catalog')->name('stock-counts.post');
    Route::delete('inventory/counts/{stockCount}', [StockCountController::class, 'destroy'])->middleware('can:manage-catalog')->name('stock-counts.destroy');
    Route::get('purchases', [PurchaseController::class, 'index'])->middleware('can:view-purchases')->name('purchases.index');
    Route::get('purchase-inbox', [PurchaseInboxController::class, 'index'])->middleware('can:manage-purchases')->name('purchase-inbox.index');
    Route::post('purchase-inbox', [PurchaseInboxController::class, 'store'])->middleware('can:manage-purchases')->name('purchase-inbox.store');
    Route::put('purchase-inbox/mailbox', [PurchaseMailboxController::class, 'update'])->middleware('can:manage-staff')->name('purchase-inbox.mailbox.update');
    Route::post('purchase-inbox/mailbox/sync', [PurchaseMailboxController::class, 'sync'])->middleware('can:manage-purchases')->name('purchase-inbox.mailbox.sync');
    Route::get('purchase-inbox/{inboxDocument}', [PurchaseInboxController::class, 'show'])->middleware('can:manage-purchases')->name('purchase-inbox.show');
    Route::get('purchase-inbox/{inboxDocument}/download', [PurchaseInboxController::class, 'download'])->middleware('can:manage-purchases')->name('purchase-inbox.download');
    Route::post('purchase-inbox/{inboxDocument}/extract', [PurchaseInboxController::class, 'extract'])->middleware('can:manage-purchases')->name('purchase-inbox.extract');
    Route::patch('purchase-inbox/{inboxDocument}', [PurchaseInboxController::class, 'update'])->middleware('can:manage-purchases')->name('purchase-inbox.update');
    Route::post('purchase-inbox/{inboxDocument}/convert', [PurchaseInboxController::class, 'convert'])->middleware('can:manage-purchases')->name('purchase-inbox.convert');
    Route::post('purchase-inbox/{inboxDocument}/reject', [PurchaseInboxController::class, 'reject'])->middleware('can:manage-purchases')->name('purchase-inbox.reject');
    Route::get('expenses', [ExpenseController::class, 'index'])->middleware('can:view-finance')->name('expenses.index');
    Route::get('finance', [FinanceController::class, 'index'])->middleware('can:view-finance')->name('finance.index');
    Route::post('finance/forecast-items', [FinanceController::class, 'storeForecast'])->middleware('can:manage-treasury')->name('finance.forecast-items.store');
    Route::post('finance/categories', [FinanceController::class, 'storeFinancialCategory'])->middleware('can:manage-accounting')->name('finance.categories.store');
    Route::patch('finance/categories/{financialCategory}', [FinanceController::class, 'updateFinancialCategory'])->middleware('can:manage-accounting')->name('finance.categories.update');
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
    Route::post('finance/payment-batches/{paymentBatch}/sepa', [FinanceController::class, 'exportPaymentBatch'])->middleware('can:approve-payments')->name('finance.payment-batches.export');
    Route::get('finance/payment-batches/{paymentBatch}/sepa', [FinanceController::class, 'downloadPaymentBatch'])->middleware('can:approve-payments')->name('finance.payment-batches.sepa');
    Route::post('finance/payment-batches/{paymentBatch}/execute', [FinanceController::class, 'executePaymentBatch'])->middleware('can:approve-payments')->name('finance.payment-batches.execute');
    Route::get('accounting', [AccountingController::class, 'index'])->middleware('can:view-finance')->name('accounting.index');
    Route::post('accounting/sync', [AccountingController::class, 'sync'])->middleware('can:manage-accounting')->name('accounting.sync');
    Route::post('accounting/entries', [AccountingController::class, 'store'])->middleware('can:manage-accounting')->name('accounting.entries.store');
    Route::post('accounting/entries/{entry}/reverse', [AccountingController::class, 'reverse'])->middleware('can:manage-accounting')->name('accounting.entries.reverse');
    Route::post('accounting/years/{fiscalYear}/close', [AccountingController::class, 'close'])->middleware('can:manage-staff')->name('accounting.years.close');
    Route::post('accounting/years/{fiscalYear}/opening', [AccountingController::class, 'opening'])->middleware('can:manage-staff')->name('accounting.years.opening');
    Route::post('accounting/periods/{year}/{month}/close', [AccountingController::class, 'closePeriod'])->middleware('can:manage-staff')->name('accounting.periods.close');
    Route::post('accounting/periods/{year}/{month}/reopen', [AccountingController::class, 'reopenPeriod'])->middleware('can:manage-staff')->name('accounting.periods.reopen');
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
    Route::patch('accounting/templates/{template}/schedule', [AccountingTemplateController::class, 'schedule'])->middleware('can:manage-accounting')->name('accounting.templates.schedule');
    Route::post('accounting/recurring/run', [AccountingTemplateController::class, 'runRecurring'])->middleware('can:manage-accounting')->name('accounting.recurring.run');
    Route::get('corporate-tax', [CorporateTaxController::class, 'index'])->middleware('can:view-finance')->name('corporate-tax.index');
    Route::post('corporate-tax/returns', [CorporateTaxController::class, 'prepare'])->middleware('can:manage-tax')->name('corporate-tax.prepare');
    Route::post('corporate-tax/returns/{corporateTaxReturn}/file', [CorporateTaxController::class, 'file'])->middleware('can:manage-tax')->name('corporate-tax.file');
    Route::get('corporate-tax/returns/{corporateTaxReturn}/receipt', [CorporateTaxController::class, 'receipt'])->middleware('can:view-finance')->name('corporate-tax.receipt');
    Route::get('tax', [TaxController::class, 'index'])->middleware('can:view-finance')->name('tax.index');
    Route::get('tax/issued', [TaxController::class, 'index'])->middleware('can:view-finance')->name('tax.issued');
    Route::get('tax/received', [TaxController::class, 'index'])->middleware('can:view-finance')->name('tax.received');
    Route::get('tax/export', [TaxController::class, 'export'])->middleware('can:view-finance')->name('tax.export');
    Route::post('tax/returns', [TaxController::class, 'prepare'])->middleware('can:manage-tax')->name('tax.returns.prepare');
    Route::post('tax/returns/{taxReturn}/file', [TaxController::class, 'file'])->middleware('can:manage-tax')->name('tax.returns.file');
    Route::get('tax/returns/{taxReturn}/receipt', [TaxController::class, 'receipt'])->middleware('can:view-finance')->name('tax.returns.receipt');
    Route::post('tax/returns/{taxReturn}/payments', [TaxController::class, 'pay'])->middleware('can:manage-tax')->name('tax.returns.payments.store');
    Route::get('banking', [BankingController::class, 'index'])->middleware('can:view-finance')->name('banking.index');
    Route::post('banking/accounts', [BankingController::class, 'storeAccount'])->middleware('can:manage-treasury')->name('banking.accounts.store');
    Route::post('banking/products', [BankingController::class, 'storeProduct'])->middleware('can:manage-treasury')->name('banking.products.store');
    Route::patch('banking/products/{product}', [BankingController::class, 'updateProduct'])->middleware('can:manage-treasury')->name('banking.products.update');
    Route::post('banking/shared-credit-facilities', [BankingController::class, 'storeSharedFacility'])->middleware('can:manage-treasury')->name('banking.shared-credit-facilities.store');
    Route::post('banking/accounts/{account}/import/preview', [BankingController::class, 'previewImport'])->middleware('can:manage-treasury')->name('banking.import.preview');
    Route::post('banking/accounts/{account}/import', [BankingController::class, 'import'])->middleware('can:manage-treasury')->name('banking.import');
    Route::delete('banking/imports/{statementImport}', [BankingController::class, 'rollbackImport'])->middleware('can:manage-treasury')->name('banking.imports.rollback');
    Route::post('banking/accounts/{account}/auto-reconcile', [BankingController::class, 'auto'])->middleware('can:manage-treasury')->name('banking.auto');
    Route::post('banking/accounts/{account}/rules', [BankingController::class, 'storeRule'])->middleware('can:manage-treasury')->name('banking.rules.store');
    Route::patch('banking/rules/{rule}', [BankingController::class, 'updateRule'])->middleware('can:manage-treasury')->name('banking.rules.update');
    Route::delete('banking/rules/{rule}', [BankingController::class, 'destroyRule'])->middleware('can:manage-treasury')->name('banking.rules.destroy');
    Route::post('banking/accounts/{account}/apply-rules', [BankingController::class, 'applyRules'])->middleware(['can:manage-treasury', 'can:manage-accounting'])->name('banking.rules.apply');
    Route::post('banking/rule-runs/{run}/reverse', [BankingController::class, 'reverseRuleRun'])->middleware(['can:manage-treasury', 'can:manage-accounting'])->name('banking.rule-runs.reverse');
    Route::post('banking/transactions/{transaction}/reconcile', [BankingController::class, 'reconcile'])->middleware('can:manage-treasury')->name('banking.reconcile');
    Route::delete('banking/reconciliations/{reconciliation}', [BankingController::class, 'undo'])->middleware('can:manage-treasury')->name('banking.reconciliations.undo');
    Route::post('banking/transactions/{transaction}/post', [BankingController::class, 'postTransaction'])->middleware('can:manage-accounting')->name('banking.transactions.post');
    Route::post('banking/accounts/{account}/transactions/bulk-post', [BankingController::class, 'bulkPostTransactions'])->middleware('can:manage-accounting')->name('banking.transactions.bulk-post');
    Route::post('banking/postings/{posting}/reverse', [BankingController::class, 'reversePosting'])->middleware('can:manage-accounting')->name('banking.postings.reverse');
    Route::post('banking/transfers/{transfer}/reverse', [BankingController::class, 'reverseTransfer'])->middleware('can:manage-treasury')->name('banking.transfers.reverse');
    Route::get('company-fiscal', [FiscalSettingsController::class, 'index'])->middleware('can:manage-tax')->name('fiscal-settings.index');
    Route::post('company-fiscal/company', [FiscalSettingsController::class, 'updateCompany'])->middleware('can:manage-tax')->name('fiscal-settings.company.update');
    Route::get('company-fiscal/logo', [FiscalSettingsController::class, 'logo'])->middleware('can:manage-tax')->name('fiscal-settings.logo');
    Route::post('company-fiscal/years', [FiscalSettingsController::class, 'storeYear'])->middleware('can:manage-tax')->name('fiscal-settings.years.store');
    Route::post('company-fiscal/years/{fiscalYear}/close', [FiscalSettingsController::class, 'closeYear'])->middleware('can:manage-tax')->name('fiscal-settings.years.close');
    Route::post('company-fiscal/years/{fiscalYear}/reopen', [FiscalSettingsController::class, 'reopenYear'])->middleware('can:manage-tax')->name('fiscal-settings.years.reopen');
    Route::post('company-fiscal/years/{fiscalYear}/opening', [FiscalSettingsController::class, 'openNextYear'])->middleware('can:manage-tax')->name('fiscal-settings.years.opening');
    Route::patch('company-fiscal/series/{series}', [FiscalSettingsController::class, 'updateSeries'])->middleware('can:manage-tax')->name('fiscal-settings.series.update');
    Route::get('finance/export', [FinanceController::class, 'export'])->middleware('can:view-finance')->name('finance.export');
    Route::post('expenses', [ExpenseController::class, 'store'])->middleware('can:manage-catalog')->name('expenses.store');
    Route::get('expenses/documentary-review', [ExpenseController::class, 'documentaryReview'])->middleware(['can:manage-catalog', 'can:view-finance'])->name('expenses.documentary-review');
    Route::post('expenses/{expense}/documentary-evidence', [ExpenseController::class, 'adoptDocumentaryEvidence'])->middleware(['can:manage-catalog', 'can:view-finance', 'throttle:30,1'])->name('expenses.documentary-evidence.adopt');
    Route::post('expenses/calculation-preview', [ExpenseController::class, 'previewCalculation'])->middleware(['can:manage-catalog', 'can:view-finance', 'throttle:60,1'])->name('expenses.calculation.preview');
    Route::get('expenses/issuer-lookup', ExpenseIssuerLookupController::class)->middleware(['can:manage-catalog', 'can:view-finance', 'throttle:60,1'])->name('expenses.issuer.lookup');
    Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->middleware('can:view-finance')->name('expenses.show');
    Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])->middleware(['can:view-finance', 'can:manage-catalog'])->name('expenses.edit');
    Route::patch('expenses/{expense}', [ExpenseController::class, 'update'])->middleware(['can:view-finance', 'can:manage-catalog'])->name('expenses.update');
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware('can:manage-catalog')->name('expenses.destroy');
    Route::get('expenses/{expense}/attachments/{attachment}', [ExpenseController::class, 'download'])->middleware('can:view-finance')->name('expenses.attachments.download');
    Route::post('expenses/{expense}/attachments', [ExpenseController::class, 'storeAttachments'])->middleware('can:manage-catalog')->name('expenses.attachments.store');
    Route::delete('expenses/{expense}/attachments/{attachment}', [ExpenseController::class, 'destroyAttachment'])->middleware('can:manage-catalog')->name('expenses.attachments.destroy');
    Route::post('expenses/{expense}/payments', [ExpenseController::class, 'payExpense'])->middleware('can:manage-purchase-payments')->name('expenses.payments.store');
    Route::post('supplier-invoices/{purchaseDocument}/payments', [ExpenseController::class, 'paySupplierInvoice'])->middleware('can:manage-purchase-payments')->name('supplier-invoices.payments.store');
    Route::delete('supplier-payments/{payment}', [ExpenseController::class, 'destroyPayment'])->middleware('can:manage-purchase-payments')->name('supplier-payments.destroy');
    Route::post('expenses/{expense}/payments/{payment}/cancel', [ExpenseController::class, 'cancelPayment'])->middleware('can:manage-purchase-payments')->name('expenses.payments.cancel');
    Route::post('purchases', [PurchaseController::class, 'store'])->middleware('can:manage-purchases')->name('purchases.store');
    Route::get('purchases/{purchaseDocument}', [PurchaseController::class, 'show'])->middleware('can:view-purchases')->name('purchases.show');
    Route::patch('purchases/{purchaseDocument}/dates', [PurchaseController::class, 'correctDates'])->middleware('can:manage-purchases')->name('purchases.dates.update');
    Route::post('purchases/{purchaseDocument}/convert', [PurchaseController::class, 'convert'])->middleware('can:manage-purchases')->name('purchases.convert');
    Route::post('purchases/{purchaseDocument}/receive', [PurchaseController::class, 'receive'])->middleware('can:receive-purchases')->name('purchases.receive');
    Route::post('purchases/{purchaseDocument}/approve', [PurchaseController::class, 'approveOrder'])->middleware('can:approve-purchase-orders')->name('purchases.approve');
    Route::post('purchases/{purchaseDocument}/return', [PurchaseController::class, 'returnGoods'])->middleware('can:receive-purchases')->name('purchases.return');
    Route::post('purchases/{purchaseDocument}/invoice', [PurchaseController::class, 'invoice'])->middleware('can:manage-purchases')->name('purchases.invoice');
    Route::get('purchases/{purchaseDocument}/pdf', [PurchaseDeliveryController::class, 'pdf'])->middleware('can:view-purchases')->name('purchases.pdf');
    Route::post('purchases/{purchaseDocument}/send', [PurchaseDeliveryController::class, 'send'])->middleware('can:manage-purchases')->name('purchases.send');
    Route::patch('purchases/{purchaseDocument}/schedule', [PurchaseWorkflowController::class, 'schedule'])->middleware('can:manage-purchases')->name('purchases.schedule');
    Route::post('purchases/{purchaseDocument}/cancel', [PurchaseWorkflowController::class, 'cancel'])->middleware('can:manage-purchases')->name('purchases.cancel');
    Route::post('purchases/{purchaseDocument}/close', [PurchaseWorkflowController::class, 'close'])->middleware('can:manage-purchases')->name('purchases.close');
    Route::post('purchases/{purchaseDocument}/reopen', [PurchaseWorkflowController::class, 'reopen'])->middleware('can:manage-purchases')->name('purchases.reopen');
    Route::patch('purchase-settings', [PurchaseSettingsController::class, 'update'])->middleware('can:manage-staff')->name('purchase-settings.update');
    Route::post('purchase-approval-rules', [PurchaseApprovalRuleController::class, 'store'])->middleware('can:manage-staff')->name('purchase-approval-rules.store');
    Route::patch('purchase-approval-rules/{purchaseApprovalRule}', [PurchaseApprovalRuleController::class, 'update'])->middleware('can:manage-staff')->name('purchase-approval-rules.update');
    Route::delete('purchase-approval-rules/{purchaseApprovalRule}', [PurchaseApprovalRuleController::class, 'destroy'])->middleware('can:manage-staff')->name('purchase-approval-rules.destroy');
    Route::get('supplier-intelligence', [SupplierIntelligenceController::class, 'index'])->middleware('can:manage-purchases')->name('supplier-intelligence.index');
    Route::get('supplier-intelligence/{supplier}', [SupplierIntelligenceController::class, 'show'])->middleware('can:manage-purchases')->name('supplier-intelligence.show');
    Route::post('supplier-intelligence/{supplier}/products', [SupplierIntelligenceController::class, 'storeProduct'])->middleware('can:manage-purchases')->name('supplier-intelligence.products.store');
    Route::patch('supplier-products/{supplierProduct}', [SupplierIntelligenceController::class, 'updateProduct'])->middleware('can:manage-purchases')->name('supplier-intelligence.products.update');
    Route::delete('supplier-products/{supplierProduct}', [SupplierIntelligenceController::class, 'destroyProduct'])->middleware('can:manage-purchases')->name('supplier-intelligence.products.destroy');
    Route::post('supplier-intelligence/{supplier}/contacts', [SupplierContactController::class, 'store'])->middleware('can:manage-purchases')->name('supplier-intelligence.contacts.store');
    Route::patch('supplier-intelligence/{supplier}/contacts/{contact}', [SupplierContactController::class, 'update'])->middleware('can:manage-purchases')->name('supplier-intelligence.contacts.update');
    Route::delete('supplier-intelligence/{supplier}/contacts/{contact}', [SupplierContactController::class, 'destroy'])->middleware('can:manage-purchases')->name('supplier-intelligence.contacts.destroy');
    Route::get('replenishment', [ReplenishmentController::class, 'index'])->middleware('can:manage-purchases')->name('replenishment.index');
    Route::post('replenishment/policies', [ReplenishmentController::class, 'store'])->middleware('can:manage-purchases')->name('replenishment.policies.store');
    Route::patch('replenishment/policies/{policy}', [ReplenishmentController::class, 'update'])->middleware('can:manage-purchases')->name('replenishment.policies.update');
    Route::delete('replenishment/policies/{policy}', [ReplenishmentController::class, 'destroy'])->middleware('can:manage-purchases')->name('replenishment.policies.destroy');
    Route::post('replenishment/procure', [ReplenishmentController::class, 'procure'])->middleware('can:manage-purchases')->name('replenishment.procure');
    Route::get('supplier-quality', [SupplierQualityController::class, 'index'])->middleware('can:view-purchases')->name('supplier-quality.index');
    Route::post('supplier-quality/{incident}/resolve', [SupplierQualityController::class, 'resolve'])->middleware('can:manage-purchases')->name('supplier-quality.resolve');
    Route::post('supplier-quality/{incident}/reopen', [SupplierQualityController::class, 'reopen'])->middleware('can:manage-purchases')->name('supplier-quality.reopen');
    Route::get('supply-control', [SupplyControlTowerController::class, 'index'])->middleware('can:view-purchases')->name('supply-control.index');
    Route::patch('supply-control/{purchaseDocument}/assignment', [SupplyControlTowerController::class, 'assign'])->middleware('can:manage-purchases')->name('supply-control.assign');
    Route::post('supply-control/{purchaseDocument}/confirm', [SupplyControlTowerController::class, 'confirm'])->middleware('can:manage-purchases')->name('supply-control.confirm');
    Route::post('supply-control/{purchaseDocument}/follow-up', [SupplyControlTowerController::class, 'followUp'])->middleware('can:manage-purchases')->name('supply-control.follow-up');
    Route::get('supply-analytics', SupplyAnalyticsController::class)->middleware('can:manage-purchases')->name('supply-analytics');
    Route::post('supplier-invoices/{purchaseDocument}/approve', [PurchaseController::class, 'approveInvoice'])->middleware('can:approve-purchase-invoices')->name('supplier-invoices.approve');
    Route::post('supplier-invoices/{purchaseDocument}/credit-note', [PurchaseController::class, 'creditNote'])->middleware('can:manage-purchases')->name('supplier-invoices.credit-note');
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
    Route::post('catalog/products/{product}/replenishment-targets', [ReplenishmentTargetController::class, 'store'])->middleware('can:manage-catalog')->name('replenishment-targets.store');
    Route::patch('catalog/products/{product}', [CatalogController::class, 'updateProduct'])->middleware('can:manage-catalog')->name('catalog.products.update');
    Route::delete('catalog/products/{product}', [CatalogController::class, 'destroyProduct'])->middleware('can:manage-catalog')->name('catalog.products.destroy');
    Route::post('catalog/suppliers', [CatalogController::class, 'storeSupplier'])->middleware('can:manage-catalog')->name('catalog.suppliers.store');
    Route::patch('catalog/suppliers/{supplier}', [CatalogController::class, 'updateSupplier'])->middleware('can:manage-catalog')->name('catalog.suppliers.update');
    Route::delete('catalog/suppliers/{supplier}', [CatalogController::class, 'destroySupplier'])->middleware('can:manage-catalog')->name('catalog.suppliers.destroy');
    Route::post('operations/activities', [OperationsController::class, 'store'])->middleware('can:manage-operations')->name('activities.store');
    Route::patch('operations/activities/{activity}', [OperationsController::class, 'update'])->middleware('can:manage-operations')->name('activities.update');
    Route::delete('operations/activities/{activity}', [OperationsController::class, 'destroy'])->middleware('can:manage-operations')->name('activities.destroy');
    Route::post('operations/activities/{activity}/work-order', [OperationsController::class, 'convertToWorkOrder'])->middleware('can:manage-installations')->name('activities.work-order.store');
    Route::get('operations/field-service', [FieldServiceSettingsController::class, 'index'])->middleware('can:manage-installations')->name('field-service.index');
    Route::put('operations/field-service/users/{user}', [FieldServiceSettingsController::class, 'updateProfile'])->middleware('can:manage-installations')->name('field-service.profiles.update');
    Route::post('operations/work-order-templates', [FieldServiceSettingsController::class, 'storeTemplate'])->middleware('can:manage-installations')->name('work-order-templates.store');
    Route::patch('operations/work-order-templates/{template}', [FieldServiceSettingsController::class, 'updateTemplate'])->middleware('can:manage-installations')->name('work-order-templates.update');
    Route::get('quotes', [QuoteController::class, 'index'])->middleware('can:manage-quotes')->name('quotes.index');
    Route::get('planning-scenarios', [PlanningScenarioController::class, 'index'])->middleware('can:manage-admin')->name('planning-scenarios.index');
    Route::post('planning-scenarios', [PlanningScenarioController::class, 'store'])->middleware('can:manage-admin')->name('planning-scenarios.store');
    Route::post('planning-scenarios/calibrations', [PlanningScenarioController::class, 'generateCalibrations'])->middleware('can:manage-admin')->name('planning-scenarios.calibrations.store');
    Route::post('planning-scenarios/calibration-evaluations', [PlanningScenarioController::class, 'generateCalibrationEvaluations'])->middleware('can:manage-admin')->name('planning-scenarios.calibration-evaluations.store');
    Route::post('planning-scenarios/calibration-governance', [PlanningScenarioController::class, 'generateCalibrationGovernance'])->middleware('can:manage-admin')->name('planning-scenarios.calibration-governance.store');
    Route::post('planning-calibrations/{calibration}/decision', [PlanningScenarioController::class, 'decideCalibration'])->middleware('can:manage-admin')->name('planning-calibrations.decision.store');
    Route::post('planning-calibration-evaluations/{evaluation}/decision', [PlanningScenarioController::class, 'decideCalibrationEvaluation'])->middleware('can:manage-admin')->name('planning-calibration-evaluations.decision.store');
    Route::post('planning-calibration-governance/{review}/decision', [PlanningScenarioController::class, 'decideCalibrationGovernance'])->middleware('can:manage-admin')->name('planning-calibration-governance.decision.store');
    Route::post('planning-scenarios/{scenario}/approval', [PlanningScenarioController::class, 'approve'])->middleware('can:manage-admin')->name('planning-scenarios.approval.store');
    Route::post('planning-scenarios/{scenario}/reviews', [PlanningScenarioController::class, 'review'])->middleware('can:manage-admin')->name('planning-scenarios.reviews.store');
    Route::post('planning-scenarios/{scenario}/closure', [PlanningScenarioController::class, 'close'])->middleware('can:manage-admin')->name('planning-scenarios.closure.store');
    Route::post('planning-scenarios/{scenario}/requests', [PlanningScenarioRequestController::class, 'generate'])->middleware('can:manage-admin')->name('planning-scenarios.requests.store');
    Route::get('planning-requests', [PlanningScenarioRequestController::class, 'index'])->name('planning-scenario-requests.index');
    Route::post('planning-requests/{scenarioRequest}/confirmation', [PlanningScenarioRequestController::class, 'confirm'])->name('planning-scenario-requests.confirmation.store');
    Route::post('planning-requests/{scenarioRequest}/materialization', [PlanningScenarioRequestController::class, 'prepare'])->name('planning-scenario-requests.materialization.store');
    Route::post('planning-requests/{scenarioRequest}/materialization/authorization', [PlanningScenarioRequestController::class, 'authorizeMaterialization'])->name('planning-scenario-requests.materialization.authorization.store');
    Route::post('planning-requests/{scenarioRequest}/execution', [PlanningScenarioRequestController::class, 'execute'])->name('planning-scenario-requests.execution.store');
    Route::post('planning-requests/{scenarioRequest}/promotion', [PlanningScenarioRequestController::class, 'promote'])->name('planning-scenario-requests.promotion.store');
    Route::post('planning-requests/{scenarioRequest}/exceptions/tasks/assignment', [PlanningScenarioRequestController::class, 'assignTasks'])->name('planning-scenario-requests.exceptions.tasks.assignment.store');
    Route::post('planning-requests/{scenarioRequest}/exceptions/reservations', [PlanningScenarioRequestController::class, 'resolveReservations'])->name('planning-scenario-requests.exceptions.reservations.store');
    Route::get('quotes/create', [QuoteController::class, 'create'])->middleware('can:manage-quotes')->name('quotes.create');
    Route::post('quotes', [QuoteController::class, 'store'])->middleware('can:manage-quotes')->name('quotes.store');
    Route::get('quotes/{quote}', [QuoteController::class, 'show'])->middleware('can:manage-quotes')->name('quotes.show');
    Route::get('quotes/{quote}/edit', [QuoteController::class, 'edit'])->middleware('can:manage-quotes')->name('quotes.edit');
    Route::put('quotes/{quote}', [QuoteController::class, 'updateDocument'])->middleware('can:manage-quotes')->name('quotes.document.update');
    Route::patch('quotes/{quote}/status', [QuoteController::class, 'updateStatus'])->middleware('can:manage-quotes')->name('quotes.status.update');
    Route::post('quotes/{quote}/approval', [QuoteController::class, 'approve'])->middleware('can:approve-quotes')->name('quotes.approval.store');
    Route::post('quotes/{quote}/project', [QuoteController::class, 'startProject'])->middleware('can:manage-installations')->name('quotes.project.store');
    Route::delete('quotes/{quote}', [QuoteController::class, 'destroy'])->middleware('can:manage-quotes')->name('quotes.destroy');
    Route::post('quotes/{quote}/send', [DocumentDeliveryController::class, 'sendQuote'])->middleware('can:manage-quotes')->name('quotes.send');
    Route::get('quotes/{quote}/pdf', [SalesDocumentController::class, 'quotePdf'])->middleware('can:manage-quotes')->name('quotes.pdf');
    Route::get('quotes/{quote}/pdf/without-watermark', [SalesDocumentController::class, 'quotePdfWithoutWatermark'])->middleware('can:manage-admin')->name('quotes.pdf.without-watermark');
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
    Route::post('sales/{salesDocument}/issue', [SalesDocumentController::class, 'issue'])->middleware('can:issue-invoices')->name('sales-documents.issue');
    Route::post('sales/{salesDocument}/convert', [SalesDocumentController::class, 'convertDocument'])->middleware('can:manage-installations')->name('sales-documents.convert');
    Route::post('sales/{salesDocument}/payments', [SalesDocumentController::class, 'storePayment'])->middleware('can:manage-installations')->name('sales-documents.payments.store');
    Route::delete('sales/{salesDocument}/payments/{payment}', [SalesDocumentController::class, 'destroyPayment'])->middleware('can:manage-installations')->name('sales-documents.payments.destroy');
    Route::post('sales/{salesDocument}/credit-note', [SalesDocumentController::class, 'creditNote'])->middleware('can:prepare-credit-notes')->name('sales-documents.credit-note');
    Route::post('sales/{salesDocument}/dispatch', [SalesDocumentController::class, 'dispatch'])->middleware('can:manage-installations')->name('sales-documents.dispatch');
    Route::get('admin', [AdminController::class, 'index'])->middleware('can:manage-admin')->name('admin.index');
    Route::get('admin/users', [AdminController::class, 'users'])->middleware('can:manage-access')->name('admin.users.index');
    Route::get('admin/access', [AdminController::class, 'access'])->middleware('can:manage-access')->name('admin.access.index');
    Route::patch('admin/users/{user}', [AdminController::class, 'updateUser'])->middleware('can:manage-access')->name('admin.users.update');
    Route::post('admin/users/{user}/review', [AdminController::class, 'reviewAccess'])->middleware('can:manage-access')->name('admin.users.review');
    Route::patch('admin/profiles/{profile}', [AdminController::class, 'updateProfile'])->middleware('can:manage-access')->name('admin.profiles.update');
    Route::get('admin/backups', [AdminController::class, 'backups'])->middleware('can:manage-admin')->name('admin.backups.index');
    Route::post('admin/backups', [AdminController::class, 'backup'])->middleware('can:manage-admin')->name('admin.backups.store');
    Route::get('admin/backups/{backup}', [AdminController::class, 'downloadBackup'])->middleware('can:manage-admin')->name('admin.backups.download');
    Route::get('admin/audit', [AdminController::class, 'audit'])->middleware('can:export-audit')->name('admin.audit.index');
    Route::get('admin/audit/export', [AdminController::class, 'exportAudit'])->middleware('can:export-audit')->name('admin.audit.export');
    Route::get('admin/health', [AdminController::class, 'health'])->middleware('can:view-system-health')->name('admin.health.index');
    Route::post('installations/{installation}/ai/briefing', [InstallationAiController::class, 'briefing'])
        ->middleware(['can:use-ai', 'can:view-installations', 'throttle:arevia-briefings'])
        ->name('installations.ai.briefing');
    Route::get('installations/{installation}/ai/briefings/{aiGeneration:uuid}', [InstallationAiController::class, 'status'])
        ->middleware(['can:use-ai', 'can:view-installations'])
        ->name('installations.ai.briefing.status');
    Route::post('installations/{installation}/ai/briefings/{aiGeneration:uuid}/activities', [InstallationAiController::class, 'createActivities'])
        ->middleware(['can:manage-operations', 'can:view-installations', 'throttle:arevia-actions'])
        ->name('installations.ai.briefing.activities');
    Route::post('installations/{installation}/ai/briefings/{aiGeneration:uuid}/feedback', [AiFeedbackController::class, 'store'])
        ->middleware(['can:use-ai', 'can:view-installations'])
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
    Route::post('installations/{installation}/assets', [InstallationAssetController::class, 'store'])->middleware('can:manage-installations')->name('installations.assets.store');
    Route::patch('installations/{installation}/assets/{asset}', [InstallationAssetController::class, 'update'])->middleware('can:manage-installations')->name('installations.assets.update');
    Route::post('installations/{installation}/assets/{asset}/events', [InstallationAssetController::class, 'storeEvent'])->middleware('can:manage-operations')->name('installations.assets.events.store');
    Route::post('installations/{installation}/assets/{asset}/rmas', [AssetRmaController::class, 'store'])->middleware('can:manage-installations')->name('installations.assets.rmas.store');
    Route::patch('installations/{installation}/assets/{asset}/rmas/{rma}', [AssetRmaController::class, 'update'])->middleware('can:manage-installations')->name('installations.assets.rmas.update');
    Route::post('installations/{installation}/assets/{asset}/replacement', [InstallationAssetController::class, 'replace'])->middleware('can:manage-installations')->name('installations.assets.replace');
    Route::resource('installations', InstallationController::class)
        ->except(['index', 'show'])
        ->middleware('can:manage-installations');
    Route::post('installations/{installation}/people', [InstallationPersonController::class, 'store'])
        ->middleware('can:manage-installations')
        ->name('installations.people.store');
    Route::delete('installations/{installation}/people/{person}', [InstallationPersonController::class, 'destroy'])
        ->middleware('can:manage-installations')
        ->name('installations.people.destroy');
    Route::resource('installations', InstallationController::class)
        ->only(['index', 'show'])
        ->middleware('can:view-installations');
});

require __DIR__.'/settings.php';
