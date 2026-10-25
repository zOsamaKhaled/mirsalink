<?php

namespace App\Actions\Team;

use App\Enums\Availability;
use App\Exceptions\TeamRuleException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Stops a member from logging in without deleting them, so the
 * conversations and notes they wrote keep their name. Their open sessions
 * end at once; EnsureAccountIsActive catches any the database doesn't hold.
 */
class DeactivateMember
{
    /**
     * @throws TeamRuleException
     */
    public function handle(User $actor, User $member): void
    {
        if (! $member->isActive()) {
            return;
        }

        if ($actor->is($member)) {
            throw TeamRuleException::because('deactivate_self');
        }

        if ($member->isSupervisor() && ChangeMemberRole::activeSupervisorCount() === 1) {
            throw TeamRuleException::because('last_supervisor');
        }

        DB::transaction(function () use ($member): void {
            $member->forceFill([
                'deactivated_at' => now(),
                'availability' => Availability::Away,
                'remember_token' => Str::random(60),
            ])->save();

            $sessions = config('session.table', 'sessions');

            if (Schema::hasTable($sessions)) {
                DB::table($sessions)->where('user_id', $member->id)->delete();
            }
        });
    }
}
