<?php

use App\Http\Controllers\AfterSalesController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\WorkOrderMaterialsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('operations', [OperationsController::class, 'index'])->middleware('can:manage-operations')->name('operations.index');
    Route::post('operations/activities', [OperationsController::class, 'store'])->middleware('can:manage-operations')->name('activities.store');
    Route::patch('operations/activities/{activity}', [OperationsController::class, 'update'])->middleware('can:manage-operations')->name('activities.update');
    Route::delete('operations/activities/{activity}', [OperationsController::class, 'destroy'])->middleware('can:manage-operations')->name('activities.destroy');
    Route::post('operations/activities/{activity}/work-order', [OperationsController::class, 'convertToWorkOrder'])->middleware('can:manage-installations')->name('activities.work-order.store');

    Route::get('work-orders', [WorkOrderController::class, 'index'])->middleware('can:manage-operations')->name('work-orders.index');
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

    Route::get('after-sales', [AfterSalesController::class, 'index'])->middleware('can:manage-operations')->name('after-sales.index');
    Route::post('after-sales/tickets', [AfterSalesController::class, 'storeTicket'])->middleware('can:manage-operations')->name('after-sales.tickets.store');
    Route::patch('after-sales/tickets/{ticket}', [AfterSalesController::class, 'updateTicket'])->middleware('can:manage-operations')->name('after-sales.tickets.update');
    Route::post('after-sales/tickets/{ticket}/work-order', [AfterSalesController::class, 'createWorkOrder'])->middleware('can:manage-operations')->name('after-sales.tickets.work-order');
    Route::post('after-sales/plans', [AfterSalesController::class, 'storePlan'])->middleware('can:manage-installations')->name('after-sales.plans.store');
    Route::patch('after-sales/plans/{plan}', [AfterSalesController::class, 'updatePlan'])->middleware('can:manage-installations')->name('after-sales.plans.update');
    Route::patch('after-sales/warranties/{serial}', [AfterSalesController::class, 'updateWarranty'])->middleware('can:manage-installations')->name('after-sales.warranties.update');
});
