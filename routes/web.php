<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

if (app()->environment('local')) {
    Route::inertia('design-system', 'design-system')->name('design-system');
    Route::inertia('design-system/fonts', 'design-system-fonts')->name('design-system.fonts');
}

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
