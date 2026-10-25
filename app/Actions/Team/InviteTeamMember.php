<?php

namespace App\Actions\Team;

use App\Enums\Availability;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds someone to the team and sends them their invitation. They get a
 * password no one knows until they choose their own from the link.
 */
class InviteTeamMember
{
    public function __construct(private SendInvitation $sendInvitation) {}

    /**
     * @return array{member: User, link: string}
     */
    public function handle(User $inviter, string $name, string $email, Role $role): array
    {
        $member = DB::transaction(function () use ($inviter, $name, $email, $role): User {
            $member = new User([
                'name' => $name,
                'email' => $email,
                'password' => Str::random(40),
                'role' => $role,
                'availability' => Availability::Away,
            ]);
            $member->invited_by = $inviter->id;
            $member->save();

            return $member;
        });

        return ['member' => $member, 'link' => $this->sendInvitation->handle($member)];
    }
}
