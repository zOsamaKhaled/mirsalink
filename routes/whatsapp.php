<?php

use App\Http\Controllers\MigrationController;
use App\Http\Controllers\WhatsAppController;
use Illuminate\Support\Facades\Route;

// The connection to Meta, for supervisors. The webhook itself is in routes/api.php.
Route::middleware(['auth', 'verified', 'can:manage-team'])->group(function () {
    Route::get('whatsapp', [WhatsAppController::class, 'index'])->name('whatsapp.index');
    Route::post('whatsapp/retry', [WhatsAppController::class, 'retry'])->name('whatsapp.retry');
    Route::post('whatsapp/templates/sync', [WhatsAppController::class, 'syncTemplates'])->name('whatsapp.templates.sync');

    // The move from Respond.io.
    Route::get('migration', [MigrationController::class, 'index'])->name('migration.index');
    Route::put('migration', [MigrationController::class, 'update'])->name('migration.update');
    Route::put('migration/steps/{step}', [MigrationController::class, 'step'])->name('migration.steps.update');
    Route::post('migration/prices-checked', [MigrationController::class, 'pricesChecked'])->name('migration.prices');
});
