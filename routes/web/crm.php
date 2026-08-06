<?php

use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\DocumentDeliveryController;
use App\Http\Controllers\InstallationController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\SalesDocumentController;
use App\Http\Controllers\SalesOperationsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::post('people/{person}/restore', [PersonController::class, 'restore'])
        ->middleware('can:manage-customers')
        ->name('people.restore');
    Route::resource('people', PersonController::class)->middleware('can:manage-customers');
    Route::post('opportunities/{opportunity}/activities', [OpportunityController::class, 'storeActivity'])
        ->middleware('can:manage-customers')
        ->name('opportunities.activities.store');
    Route::post('opportunities/{opportunity}/restore', [OpportunityController::class, 'restore'])
        ->middleware('can:manage-customers')
        ->name('opportunities.restore');
    Route::resource('opportunities', OpportunityController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:manage-customers');
    Route::post('people/{person}/portal', [CustomerPortalController::class, 'invite'])->middleware('can:manage-customers')->name('people.portal.invite');
    Route::delete('people/{person}/portal/{access}', [CustomerPortalController::class, 'revoke'])->middleware('can:manage-customers')->name('people.portal.revoke');

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

    Route::resource('installations', InstallationController::class)
        ->except(['index', 'show'])
        ->middleware('can:manage-installations');
    Route::resource('installations', InstallationController::class)
        ->only(['index', 'show'])
        ->middleware('can:view-installations');
});
