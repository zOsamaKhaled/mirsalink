<?php

namespace App\Http\Controllers\Team;

use App\Actions\Team\ChangeMemberRole;
use App\Actions\Team\DeactivateMember;
use App\Actions\Team\InviteTeamMember;
use App\Actions\Team\ReactivateMember;
use App\Actions\Team\SendInvitation;
use App\Enums\Role;
use App\Exceptions\TeamRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\InviteMemberRequest;
use App\Http\Requests\Team\UpdateMemberRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Team page: who is on the team, inviting new members, and changing
 * their role or access. Supervisors only (`can:manage-team` on the routes).
 */
class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        $members = User::query()
            ->with('inviter:id,name')
            ->get()
            ->sortBy(fn (User $member): string => implode('|', [
                match (true) {
                    ! $member->isActive() => 3,
                    $member->hasPendingInvitation() => 2,
                    $member->isSupervisor() => 0,
                    default => 1,
                },
                mb_strtolower($member->name),
            ]))
            ->values()
            ->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role->value,
                'availability' => $member->availability->value,
                'status' => match (true) {
                    ! $member->isActive() => 'deactivated',
                    $member->hasPendingInvitation() => 'invited',
                    default => 'active',
                },
                'invited_at' => $member->invited_at?->toIso8601String(),
                'invited_by' => $member->inviter?->name,
                'is_me' => $member->is($request->user()),
            ]);

        return Inertia::render('team/members', [
            'members' => $members,
        ]);
    }

    public function store(InviteMemberRequest $request, InviteTeamMember $invite): RedirectResponse
    {
        ['member' => $member, 'link' => $link] = $invite->handle(
            $request->user(),
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->enum('role', Role::class),
        );

        Inertia::flash('invitation', ['name' => $member->name, 'email' => $member->email, 'link' => $link]);

        return to_route('team.members.index');
    }

    public function update(UpdateMemberRoleRequest $request, User $member, ChangeMemberRole $changeRole): RedirectResponse
    {
        return $this->guarded(
            fn () => $changeRole->handle($request->user(), $member, $request->enum('role', Role::class)),
            __('app.team.flash.role_changed', ['name' => $member->name]),
        );
    }

    public function deactivate(Request $request, User $member, DeactivateMember $deactivate): RedirectResponse
    {
        return $this->guarded(
            fn () => $deactivate->handle($request->user(), $member),
            __('app.team.flash.deactivated', ['name' => $member->name]),
        );
    }

    public function reactivate(User $member, ReactivateMember $reactivate): RedirectResponse
    {
        $reactivate->handle($member);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.team.flash.reactivated', ['name' => $member->name])]);

        return to_route('team.members.index');
    }

    public function resendInvitation(User $member, SendInvitation $sendInvitation): RedirectResponse
    {
        abort_unless($member->hasPendingInvitation() && $member->isActive(), 404);

        $link = $sendInvitation->handle($member);

        Inertia::flash('invitation', ['name' => $member->name, 'email' => $member->email, 'link' => $link]);

        return to_route('team.members.index');
    }

    /**
     * Runs a team change, turning a broken team rule into an error toast
     * instead of a failed request.
     */
    private function guarded(callable $change, string $success): RedirectResponse
    {
        try {
            $change();
        } catch (TeamRuleException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return to_route('team.members.index');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $success]);

        return to_route('team.members.index');
    }
}
