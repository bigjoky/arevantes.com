<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PurchaseComparisonController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseDeliveryController;
use App\Http\Controllers\PurchaseInboxController;
use App\Http\Controllers\PurchaseMailboxController;
use App\Http\Controllers\PurchaseSettingsController;
use App\Http\Controllers\PurchaseWorkflowController;
use App\Http\Controllers\ReplenishmentController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\SupplierIntelligenceController;
use App\Http\Controllers\SupplierInvoiceDocumentController;
use App\Http\Controllers\SupplierQualityController;
use App\Http\Controllers\SupplyAnalyticsController;
use App\Http\Controllers\SupplyControlTowerController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('catalog', [CatalogController::class, 'index'])->middleware('can:manage-catalog')->name('catalog.index');
    Route::post('catalog/products', [CatalogController::class, 'storeProduct'])->middleware('can:manage-catalog')->name('catalog.products.store');
    Route::patch('catalog/products/{product}', [CatalogController::class, 'updateProduct'])->middleware('can:manage-catalog')->name('catalog.products.update');
    Route::delete('catalog/products/{product}', [CatalogController::class, 'destroyProduct'])->middleware('can:manage-catalog')->name('catalog.products.destroy');
    Route::post('catalog/suppliers', [CatalogController::class, 'storeSupplier'])->middleware('can:manage-catalog')->name('catalog.suppliers.store');
    Route::patch('catalog/suppliers/{supplier}', [CatalogController::class, 'updateSupplier'])->middleware('can:manage-catalog')->name('catalog.suppliers.update');
    Route::delete('catalog/suppliers/{supplier}', [CatalogController::class, 'destroySupplier'])->middleware('can:manage-catalog')->name('catalog.suppliers.destroy');

    Route::get('inventory', [InventoryController::class, 'index'])->middleware('can:manage-catalog')->name('inventory.index');
    Route::post('inventory/warehouses', [InventoryController::class, 'storeWarehouse'])->middleware('can:manage-catalog')->name('inventory.warehouses.store');
    Route::patch('inventory/warehouses/{warehouse}', [InventoryController::class, 'updateWarehouse'])->middleware('can:manage-catalog')->name('inventory.warehouses.update');
    Route::post('inventory/movements', [InventoryController::class, 'storeMovement'])->middleware('can:manage-catalog')->name('inventory.movements.store');
    Route::post('inventory/reservations', [InventoryController::class, 'storeReservation'])->middleware('can:manage-catalog')->name('inventory.reservations.store');
    Route::delete('inventory/reservations/{reservation}', [InventoryController::class, 'releaseReservation'])->middleware('can:manage-catalog')->name('inventory.reservations.release');
    Route::get('inventory/counts', [StockCountController::class, 'index'])->middleware('can:manage-catalog')->name('stock-counts.index');
    Route::post('inventory/counts', [StockCountController::class, 'store'])->middleware('can:manage-catalog')->name('stock-counts.store');
    Route::get('inventory/counts/{stockCount}', [StockCountController::class, 'show'])->middleware('can:manage-catalog')->name('stock-counts.show');
    Route::patch('inventory/counts/{stockCount}', [StockCountController::class, 'update'])->middleware('can:manage-catalog')->name('stock-counts.update');
    Route::post('inventory/counts/{stockCount}/post', [StockCountController::class, 'post'])->middleware('can:manage-catalog')->name('stock-counts.post');
    Route::delete('inventory/counts/{stockCount}', [StockCountController::class, 'destroy'])->middleware('can:manage-catalog')->name('stock-counts.destroy');

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

    Route::get('purchases', [PurchaseController::class, 'index'])->middleware('can:view-purchases')->name('purchases.index');
    Route::post('purchases', [PurchaseController::class, 'store'])->middleware('can:manage-purchases')->name('purchases.store');
    Route::get('purchases/{purchaseDocument}', [PurchaseController::class, 'show'])->middleware('can:view-purchases')->name('purchases.show');
    Route::post('purchases/{purchaseDocument}/convert', [PurchaseController::class, 'convert'])->middleware('can:manage-purchases')->name('purchases.convert');
    Route::post('purchases/{purchaseDocument}/receive', [PurchaseController::class, 'receive'])->middleware('can:receive-purchases')->name('purchases.receive');
    Route::post('purchases/{purchaseDocument}/return', [PurchaseController::class, 'returnGoods'])->middleware('can:receive-purchases')->name('purchases.return');
    Route::post('purchases/{purchaseDocument}/invoice', [PurchaseController::class, 'invoice'])->middleware('can:manage-purchases')->name('purchases.invoice');
    Route::get('purchases/{purchaseDocument}/pdf', [PurchaseDeliveryController::class, 'pdf'])->middleware('can:view-purchases')->name('purchases.pdf');
    Route::post('purchases/{purchaseDocument}/send', [PurchaseDeliveryController::class, 'send'])->middleware('can:manage-purchases')->name('purchases.send');
    Route::patch('purchases/{purchaseDocument}/schedule', [PurchaseWorkflowController::class, 'schedule'])->middleware('can:manage-purchases')->name('purchases.schedule');
    Route::post('purchases/{purchaseDocument}/cancel', [PurchaseWorkflowController::class, 'cancel'])->middleware('can:manage-purchases')->name('purchases.cancel');
    Route::post('purchases/{purchaseDocument}/close', [PurchaseWorkflowController::class, 'close'])->middleware('can:manage-purchases')->name('purchases.close');
    Route::post('purchases/{purchaseDocument}/reopen', [PurchaseWorkflowController::class, 'reopen'])->middleware('can:manage-purchases')->name('purchases.reopen');
    Route::patch('purchase-settings', [PurchaseSettingsController::class, 'update'])->middleware('can:manage-staff')->name('purchase-settings.update');

    Route::get('supplier-intelligence', [SupplierIntelligenceController::class, 'index'])->middleware('can:manage-purchases')->name('supplier-intelligence.index');
    Route::get('supplier-intelligence/{supplier}', [SupplierIntelligenceController::class, 'show'])->middleware('can:manage-purchases')->name('supplier-intelligence.show');
    Route::post('supplier-intelligence/{supplier}/products', [SupplierIntelligenceController::class, 'storeProduct'])->middleware('can:manage-purchases')->name('supplier-intelligence.products.store');
    Route::patch('supplier-products/{supplierProduct}', [SupplierIntelligenceController::class, 'updateProduct'])->middleware('can:manage-purchases')->name('supplier-intelligence.products.update');
    Route::delete('supplier-products/{supplierProduct}', [SupplierIntelligenceController::class, 'destroyProduct'])->middleware('can:manage-purchases')->name('supplier-intelligence.products.destroy');
    Route::get('replenishment', [ReplenishmentController::class, 'index'])->middleware('can:manage-purchases')->name('replenishment.index');
    Route::post('replenishment/policies', [ReplenishmentController::class, 'store'])->middleware('can:manage-purchases')->name('replenishment.policies.store');
    Route::patch('replenishment/policies/{policy}', [ReplenishmentController::class, 'update'])->middleware('can:manage-purchases')->name('replenishment.policies.update');
    Route::delete('replenishment/policies/{policy}', [ReplenishmentController::class, 'destroy'])->middleware('can:manage-purchases')->name('replenishment.policies.destroy');
    Route::post('replenishment/procure', [ReplenishmentController::class, 'procure'])->middleware('can:manage-purchases')->name('replenishment.procure');
    Route::get('supplier-quality', [SupplierQualityController::class, 'index'])->middleware('can:view-purchases')->name('supplier-quality.index');
    Route::post('supplier-quality/{incident}/resolve', [SupplierQualityController::class, 'resolve'])->middleware('can:manage-purchases')->name('supplier-quality.resolve');
    Route::post('supplier-quality/{incident}/reopen', [SupplierQualityController::class, 'reopen'])->middleware('can:manage-purchases')->name('supplier-quality.reopen');
    Route::get('supply-control', [SupplyControlTowerController::class, 'index'])->middleware('can:view-purchases')->name('supply-control.index');
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
});
