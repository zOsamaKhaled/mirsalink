<?php

use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\Team\MemberController;
use App\Http\Controllers\Team\WorkingHoursController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::patch('availability', [AvailabilityController::class, 'update'])->name('availability.update');

    Route::middleware('can:manage-team')->prefix('team')->name('team.')->group(function () {
        Route::redirect('/', '/team/members')->name('index');

        Route::get('members', [MemberController::class, 'index'])->name('members.index');
        Route::post('members', [MemberController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('members.store');
        Route::patch('members/{member}/role', [MemberController::class, 'update'])->name('members.role');
        Route::post('members/{member}/deactivate', [MemberController::class, 'deactivate'])->name('members.deactivate');
        Route::post('members/{member}/reactivate', [MemberController::class, 'reactivate'])->name('members.reactivate');
        Route::post('members/{member}/invitation', [MemberController::class, 'resendInvitation'])
            ->middleware('throttle:10,1')
            ->name('members.invitation');

        Route::get('hours', [WorkingHoursController::class, 'edit'])->name('hours.edit');
        Route::put('hours', [WorkingHoursController::class, 'update'])->name('hours.update');
    });
});

Route::middleware(['signed', 'throttle:20,1'])->group(function () {
    Route::get('invitations/{user}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{user}', [InvitationController::class, 'store'])->name('invitations.store');
});
