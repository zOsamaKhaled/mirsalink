<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

if (app()->environment('local')) {
    Route::inertia('design-system', 'design-system')->name('design-system');
    Route::inertia('design-system/fonts', 'design-system-fonts')->name('design-system.fonts');
}

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/team.php';
