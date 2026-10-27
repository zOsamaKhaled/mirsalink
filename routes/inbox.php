<?php

use App\Http\Controllers\Inbox\ConversationController;
use App\Http\Controllers\Inbox\InboxController;
use App\Http\Controllers\Inbox\SimulatorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('inbox/{conversation}', [InboxController::class, 'show'])->name('inbox.show');

    Route::post('inbox/{conversation}/messages', [ConversationController::class, 'message'])
        ->middleware('throttle:120,1')
        ->name('inbox.messages.store');
    Route::post('inbox/{conversation}/template', [ConversationController::class, 'template'])
        ->middleware('throttle:60,1')
        ->name('inbox.template.store');
    Route::put('inbox/{conversation}/assistant', [ConversationController::class, 'assistant'])->name('inbox.assistant.update');
    Route::put('inbox/{conversation}/assignee', [ConversationController::class, 'assign'])->name('inbox.assignee.update');
    Route::post('inbox/{conversation}/tags', [ConversationController::class, 'tag'])->name('inbox.tags.store');
    Route::delete('inbox/{conversation}/tags/{tag}', [ConversationController::class, 'untag'])->name('inbox.tags.destroy');
    Route::post('inbox/{conversation}/close', [ConversationController::class, 'close'])->name('inbox.close');
    Route::post('inbox/{conversation}/reopen', [ConversationController::class, 'reopen'])->name('inbox.reopen');
    Route::get('media/{message}', [ConversationController::class, 'media'])->name('inbox.media');

    // Only while WhatsApp is simulated (the controller checks the gateway).
    Route::middleware('can:manage-team')->group(function () {
        Route::get('simulator', [SimulatorController::class, 'index'])->name('simulator.index');
        Route::post('simulator/messages', [SimulatorController::class, 'store'])->name('simulator.messages.store');
        Route::put('simulator/messages/{message}/status', [SimulatorController::class, 'status'])->name('simulator.messages.status');
        Route::put('simulator/templates/{template}', [SimulatorController::class, 'decide'])->name('simulator.templates.decide');
    });
});
