<?php

use App\Http\Controllers\HrDepartmentController;
use App\Http\Controllers\HrEmploymentController;
use App\Http\Controllers\HrLeaveController;
use App\Http\Controllers\HrTaskController;
use App\Http\Controllers\HumanResourcesController;
use App\Http\Controllers\MyHumanResourcesController;
use App\Http\Controllers\StaffAccessController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::resource('staff', StaffController::class)->middleware('can:manage-staff');
    Route::post('staff/{staff}/access', [StaffAccessController::class, 'store'])->middleware('can:manage-staff')->name('staff.access.store');
    Route::patch('staff/{staff}/access', [StaffAccessController::class, 'update'])->middleware('can:manage-staff')->name('staff.access.update');
    Route::delete('staff/{staff}/access', [StaffAccessController::class, 'destroy'])->middleware('can:manage-staff')->name('staff.access.destroy');

    Route::get('human-resources', HumanResourcesController::class)->middleware('can:manage-staff')->name('human-resources.index');
    Route::get('human-resources/employees', [HrEmploymentController::class, 'index'])->middleware('can:manage-staff')->name('human-resources.employees.index');
    Route::put('human-resources/employees/{staff}', [HrEmploymentController::class, 'update'])->middleware('can:manage-staff')->name('human-resources.employees.update');
    Route::resource('human-resources/departments', HrDepartmentController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:manage-staff')->names('human-resources.departments');
    Route::resource('human-resources/leave', HrLeaveController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:manage-staff')->names('human-resources.leave');
    Route::resource('human-resources/tasks', HrTaskController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:manage-staff')->names('human-resources.tasks');

    Route::get('my-human-resources', [MyHumanResourcesController::class, 'index'])->name('my-human-resources.index');
    Route::post('my-human-resources/leave', [MyHumanResourcesController::class, 'store'])->name('my-human-resources.leave.store');
    Route::patch('my-human-resources/leave/{leave}/cancel', [MyHumanResourcesController::class, 'cancel'])->name('my-human-resources.leave.cancel');
    Route::patch('my-human-resources/tasks/{task}/complete', [MyHumanResourcesController::class, 'completeTask'])->name('my-human-resources.tasks.complete');
});
