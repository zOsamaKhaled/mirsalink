<?php

namespace App\Actions\Team;

use App\Models\User;
use App\Notifications\TeamInvitation;
use Illuminate\Support\Facades\URL;

/**
 * Emails a pending member the link that lets them in, and returns it so the
 * supervisor can also pass it on by hand.
 *
 * The link is signed and lasts seven days. It carries the time it was sent:
 * sending a new one moves invited_at on, which retires every earlier link.
 */
class SendInvitation
{
    public const int DAYS_VALID = 7;

    public function handle(User $member): string
    {
        $member->forceFill(['invited_at' => now()])->save();

        $link = URL::temporarySignedRoute(
            'invitations.show',
            now()->addDays(self::DAYS_VALID),
            ['user' => $member->id, 'sent' => $member->invited_at?->timestamp],
        );

        $member->notify(new TeamInvitation($link, $member->inviter?->name));

        return $link;
    }
}
