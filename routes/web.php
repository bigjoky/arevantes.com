<?php

use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\DocumentDeliveryController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\WebsiteContactController;
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

foreach (['core', 'crm', 'human-resources', 'operations', 'supply', 'finance', 'administration', 'integrations'] as $domain) {
    require __DIR__."/web/{$domain}.php";
}

require __DIR__.'/settings.php';
