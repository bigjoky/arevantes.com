<?php

use App\Http\Controllers\Api\Mobile\V1\ActivityController as MobileActivityController;
use App\Http\Controllers\Api\Mobile\V1\AuthController as MobileAuthController;
use App\Http\Controllers\Api\Mobile\V1\DeviceController as MobileDeviceController;
use App\Http\Controllers\Api\Mobile\V1\WorkOrderController as MobileWorkOrderController;
use App\Http\Controllers\LocationController;
use App\Http\Middleware\EnsureMobileAccess;
use App\Http\Middleware\EnsureMobileIdempotency;
use Illuminate\Support\Facades\Route;

Route::prefix('locations')->middleware('throttle:120,1')->group(function () {
    Route::get('countries', [LocationController::class, 'countries']);
    Route::get('provinces', [LocationController::class, 'provinces']);
    Route::get('municipalities', [LocationController::class, 'municipalities']);
    Route::get('localities', [LocationController::class, 'localities']);
    Route::get('postal-codes/{code}', [LocationController::class, 'postalCode']);
});

Route::prefix('mobile/v1')->name('api.mobile.v1.')->group(function () {
    Route::post('auth/login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', EnsureMobileAccess::class])->group(function () {
        Route::get('me', [MobileAuthController::class, 'me'])->name('me');
        Route::delete('auth/token', [MobileAuthController::class, 'logout'])->name('auth.logout');
        Route::put('devices/{deviceId}', [MobileDeviceController::class, 'update'])->whereUuid('deviceId')->name('devices.update');
        Route::delete('devices/{deviceId}', [MobileDeviceController::class, 'destroy'])->whereUuid('deviceId')->name('devices.destroy');
        Route::get('activities', [MobileActivityController::class, 'index'])->name('activities.index');
        Route::get('activities/{activity}', [MobileActivityController::class, 'show'])->name('activities.show');
        Route::patch('activities/{activity}/status', [MobileActivityController::class, 'changeStatus'])->middleware(EnsureMobileIdempotency::class)->name('activities.status.update');
        Route::get('work-orders', [MobileWorkOrderController::class, 'index'])->name('work-orders.index');
        Route::get('work-orders/{workOrder}', [MobileWorkOrderController::class, 'show'])->name('work-orders.show');
        Route::patch('work-orders/{workOrder}/status', [MobileWorkOrderController::class, 'changeStatus'])->middleware(EnsureMobileIdempotency::class)->name('work-orders.status.update');
        Route::patch('work-orders/{workOrder}/complete', [MobileWorkOrderController::class, 'complete'])->middleware(EnsureMobileIdempotency::class)->name('work-orders.complete');
        Route::post('work-orders/{workOrder}/materials/{reservation}/consume', [MobileWorkOrderController::class, 'consumeMaterial'])->middleware(EnsureMobileIdempotency::class)->name('work-orders.materials.consume');
        Route::post('work-orders/{workOrder}/timer', [MobileWorkOrderController::class, 'startTimer'])->middleware(EnsureMobileIdempotency::class)->name('work-orders.timer.start');
        Route::patch('time-entries/{timeEntry}/stop', [MobileWorkOrderController::class, 'stopTimer'])->whereNumber('timeEntry')->middleware(EnsureMobileIdempotency::class)->name('time-entries.stop');
        Route::patch('time-entries/client/{clientUuid}/stop', [MobileWorkOrderController::class, 'stopTimerByClient'])->whereUuid('clientUuid')->middleware(EnsureMobileIdempotency::class)->name('time-entries.stop-by-client');
        Route::patch('work-orders/{workOrder}/checklist/{item}', [MobileWorkOrderController::class, 'updateChecklist'])->middleware(EnsureMobileIdempotency::class)->name('work-orders.checklist.update');
        Route::post('work-orders/{workOrder}/attachments', [MobileWorkOrderController::class, 'uploadAttachment'])->middleware(EnsureMobileIdempotency::class)->name('work-orders.attachments.store');
        Route::post('work-orders/{workOrder}/signature', [MobileWorkOrderController::class, 'uploadSignature'])->middleware(EnsureMobileIdempotency::class)->name('work-orders.signature.store');
        Route::post('work-orders/{workOrder}/arrivals', [MobileWorkOrderController::class, 'recordArrival'])->middleware(EnsureMobileIdempotency::class)->name('work-orders.arrivals.store');
    });
});
