<?php

use App\Http\Controllers\AiFeedbackController;
use App\Http\Controllers\AreviaChatController;
use App\Http\Controllers\InstallationAiController;
use App\Http\Controllers\InstallationDeyeController;
use App\Http\Controllers\InstallationHuaweiController;
use App\Http\Controllers\RemoteStationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('arevia/conversation', [AreviaChatController::class, 'show'])->middleware('can:use-ai')->name('arevia.conversation');
    Route::post('arevia/messages', [AreviaChatController::class, 'store'])->middleware('can:use-ai')->name('arevia.messages.store');
    Route::post('arevia/messages/{aiMessage}/feedback', [AreviaChatController::class, 'feedback'])->middleware('can:use-ai')->name('arevia.messages.feedback');

    Route::post('remote-stations/refresh', [RemoteStationController::class, 'refresh'])
        ->middleware(['can:manage-installations', 'throttle:10,1'])
        ->name('remote-stations.refresh');
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
});
