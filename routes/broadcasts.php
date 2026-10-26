<?php

use App\Http\Controllers\BroadcastController;
use Illuminate\Support\Facades\Route;

// Campaigns are for supervisors.
Route::middleware(['auth', 'verified', 'can:manage-team'])->group(function () {
    Route::get('broadcasts', [BroadcastController::class, 'index'])->name('broadcasts.index');
    Route::get('broadcasts/create', [BroadcastController::class, 'create'])->name('broadcasts.create');
    Route::post('broadcasts', [BroadcastController::class, 'store'])->name('broadcasts.store');
    Route::post('broadcasts/tick', [BroadcastController::class, 'tick'])->name('broadcasts.tick');
    Route::get('broadcasts/{broadcast}', [BroadcastController::class, 'show'])->name('broadcasts.show');
    Route::get('broadcasts/{broadcast}/edit', [BroadcastController::class, 'edit'])->name('broadcasts.edit');
    Route::post('broadcasts/{broadcast}', [BroadcastController::class, 'update'])->name('broadcasts.update');
    Route::post('broadcasts/{broadcast}/launch', [BroadcastController::class, 'launch'])->name('broadcasts.launch');
    Route::post('broadcasts/{broadcast}/pause', [BroadcastController::class, 'pause'])->name('broadcasts.pause');
    Route::post('broadcasts/{broadcast}/resume', [BroadcastController::class, 'resume'])->name('broadcasts.resume');
    Route::post('broadcasts/{broadcast}/cancel', [BroadcastController::class, 'cancel'])->name('broadcasts.cancel');
    Route::delete('broadcasts/{broadcast}', [BroadcastController::class, 'destroy'])->name('broadcasts.destroy');
});
