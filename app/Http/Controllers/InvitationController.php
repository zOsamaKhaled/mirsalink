<?php

namespace App\Http\Controllers;

use App\Enums\Availability;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where an invited member lands from their email: they choose their
 * password, and are in. The route is signed and expires after seven days;
 * a link from before the latest resend is refused here.
 */
class InvitationController extends Controller
{
    public function show(Request $request, User $user): Response
    {
        $this->ensureStillOpen($request, $user);

        return Inertia::render('auth/accept-invitation', [
            'name' => $user->name,
            'email' => $user->email,
            'inviter' => $user->inviter?->name,
        ]);
    }

    public function store(AcceptInvitationRequest $request, User $user): RedirectResponse
    {
        $this->ensureStillOpen($request, $user);

        $user->forceFill([
            'name' => $request->string('name')->toString(),
            'password' => $request->string('password')->toString(),
            'email_verified_at' => now(),
            'availability' => Availability::Available,
        ])->save();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.invitation.welcome', ['name' => $user->name])]);

        return to_route('dashboard');
    }

    private function ensureStillOpen(Request $request, User $user): void
    {
        $sentAt = $user->invited_at?->timestamp;

        abort_unless(
            $user->hasPendingInvitation()
                && $user->isActive()
                && $sentAt !== null
                && (int) $request->query('sent') === $sentAt,
            403,
            __('app.invitation.expired'),
        );
    }
}
