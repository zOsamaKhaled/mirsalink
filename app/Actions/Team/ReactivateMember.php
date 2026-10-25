<?php

namespace App\Actions\Team;

use App\Models\User;

/**
 * Lets a deactivated member log in again with the password they had.
 */
class ReactivateMember
{
    public function handle(User $member): void
    {
        $member->forceFill(['deactivated_at' => null])->save();
    }
}
