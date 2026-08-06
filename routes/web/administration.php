<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\KnowledgeDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('knowledge', [KnowledgeDocumentController::class, 'index'])->middleware('can:manage-staff')->name('knowledge.index');
    Route::post('knowledge', [KnowledgeDocumentController::class, 'store'])->middleware('can:manage-staff')->name('knowledge.store');
    Route::post('knowledge/{knowledgeDocument}/reindex', [KnowledgeDocumentController::class, 'reindex'])->middleware('can:manage-staff')->name('knowledge.reindex');
    Route::delete('knowledge/{knowledgeDocument}', [KnowledgeDocumentController::class, 'destroy'])->middleware('can:manage-staff')->name('knowledge.destroy');

    Route::get('admin', [AdminController::class, 'index'])->middleware('can:manage-staff')->name('admin.index');
    Route::get('admin/users', [AdminController::class, 'users'])->middleware('can:manage-staff')->name('admin.users.index');
    Route::get('admin/backups', [AdminController::class, 'backups'])->middleware('can:manage-staff')->name('admin.backups.index');
    Route::get('admin/audit', [AdminController::class, 'audit'])->middleware('can:manage-staff')->name('admin.audit.index');
    Route::patch('admin/users/{user}', [AdminController::class, 'updateUser'])->middleware('can:manage-staff')->name('admin.users.update');
    Route::post('admin/backups', [AdminController::class, 'backup'])->middleware('can:manage-staff')->name('admin.backups.store');
    Route::get('admin/backups/{backup}', [AdminController::class, 'downloadBackup'])->middleware('can:manage-staff')->name('admin.backups.download');
});
