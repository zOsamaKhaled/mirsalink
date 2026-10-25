<?php

namespace App\Actions\Team;

use App\Enums\Role;
use App\Exceptions\TeamRuleException;
use App\Models\User;

class ChangeMemberRole
{
    /**
     * @throws TeamRuleException
     */
    public function handle(User $actor, User $member, Role $role): void
    {
        if ($member->role === $role) {
            return;
        }

        if ($actor->is($member)) {
            throw TeamRuleException::because('own_role');
        }

        if ($member->isSupervisor() && $member->isActive() && self::activeSupervisorCount() === 1) {
            throw TeamRuleException::because('last_supervisor');
        }

        $member->forceFill(['role' => $role])->save();
    }

    public static function activeSupervisorCount(): int
    {
        return User::query()->active()->where('role', Role::Supervisor)->count();
    }
}
