<?php

use App\Http\Controllers\WhatsAppController;
use Illuminate\Support\Facades\Route;

// The connection to Meta, for supervisors. The webhook itself is in routes/api.php.
Route::middleware(['auth', 'verified', 'can:manage-team'])->group(function () {
    Route::get('whatsapp', [WhatsAppController::class, 'index'])->name('whatsapp.index');
    Route::post('whatsapp/retry', [WhatsAppController::class, 'retry'])->name('whatsapp.retry');
    Route::post('whatsapp/templates/sync', [WhatsAppController::class, 'syncTemplates'])->name('whatsapp.templates.sync');
});
