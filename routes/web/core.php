<?php

use App\Http\Controllers\ActionCenterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GeocodingController;
use App\Http\Controllers\GlobalSearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('search', GlobalSearchController::class)->middleware('throttle:60,1')->name('global-search');
    Route::get('action-center', ActionCenterController::class)->middleware('throttle:60,1')->name('action-center');
    Route::post('notifications/read', function (Request $request) {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    })->name('notifications.read');
    Route::post('geocoding/search', [GeocodingController::class, 'search'])
        ->middleware('throttle:30,1')
        ->name('geocoding.search');
});
