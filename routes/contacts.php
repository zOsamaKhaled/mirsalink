<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactImportController;
use App\Http\Controllers\Routing\RoutingController;
use App\Http\Controllers\Routing\RoutingRuleController;
use App\Http\Controllers\Routing\TagController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Every member works with contacts; supervisors also delete and import.
    Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::put('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');

    Route::middleware('can:manage-team')->group(function () {
        Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');
        Route::get('contacts/import', [ContactImportController::class, 'create'])->name('contacts.import.create');
        Route::post('contacts/import', [ContactImportController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('contacts.import.store');

        Route::get('routing', [RoutingController::class, 'index'])->name('routing.index');
        Route::post('routing/tags', [TagController::class, 'store'])->name('routing.tags.store');
        Route::put('routing/tags/{tag}', [TagController::class, 'update'])->name('routing.tags.update');
        Route::delete('routing/tags/{tag}', [TagController::class, 'destroy'])->name('routing.tags.destroy');
        Route::put('routing/rules', [RoutingRuleController::class, 'update'])->name('routing.rules.update');
    });
});
