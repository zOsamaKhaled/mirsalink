<?php

use App\Http\Controllers\AssistantController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\Templates\QuickReplyController;
use App\Http\Controllers\Templates\TemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Every member reads templates and the knowledge base, and keeps their own quick replies.
    Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::post('quick-replies', [QuickReplyController::class, 'store'])->name('quick-replies.store');
    Route::put('quick-replies/{quickReply}', [QuickReplyController::class, 'update'])->name('quick-replies.update');
    Route::delete('quick-replies/{quickReply}', [QuickReplyController::class, 'destroy'])->name('quick-replies.destroy');

    Route::get('knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');

    Route::middleware('can:manage-team')->group(function () {
        Route::get('templates/create', [TemplateController::class, 'create'])->name('templates.create');
        Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::get('templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
        Route::put('templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
        Route::post('templates/{template}/submit', [TemplateController::class, 'submit'])->name('templates.submit');
        Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');

        Route::post('knowledge', [KnowledgeController::class, 'store'])->name('knowledge.store');
        Route::put('knowledge/{entry}', [KnowledgeController::class, 'update'])->name('knowledge.update');
        Route::post('knowledge/{entry}/review', [KnowledgeController::class, 'review'])->name('knowledge.review');
        Route::delete('knowledge/{entry}', [KnowledgeController::class, 'destroy'])->name('knowledge.destroy');

        Route::get('assistant', [AssistantController::class, 'index'])->name('assistant.index');
        Route::put('assistant', [AssistantController::class, 'update'])->name('assistant.update');
        Route::post('assistant/trial', [AssistantController::class, 'trial'])
            ->middleware('throttle:30,1')
            ->name('assistant.trial');
    });
});
