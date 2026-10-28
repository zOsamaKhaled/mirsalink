<?php

use App\Http\Controllers\Internal\CodeController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Middleware\VerifyRizqSignature;
use Illuminate\Support\Facades\Route;

// Meta's webhook: no session or CSRF; every POST must carry Meta's signature.
Route::get('webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
Route::post('webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->name('webhooks.whatsapp.receive');

// Rizq's private door: every request signed with the shared secret.
Route::middleware(VerifyRizqSignature::class)->prefix('internal')->name('internal.')->group(function () {
    Route::post('codes', [CodeController::class, 'store'])->name('codes.store');
});
