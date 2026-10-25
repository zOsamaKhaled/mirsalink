<?php

namespace Tests\Feature\Team;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\TeamInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TeamMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisors_see_the_whole_team(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        User::factory()->count(2)->create();
        User::factory()->invited($supervisor)->create();
        User::factory()->deactivated()->create();

        $this->actingAs($supervisor)
            ->get(route('team.members.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('team/members')
                ->has('members', 5)
                ->where('members.0.is_me', true)
                ->where('members.4.status', 'deactivated')
                ->where('auth.can.manageTeam', true));
    }

    public function test_agents_cannot_open_the_team_pages(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)->get(route('team.members.index'))->assertForbidden();
        $this->actingAs($agent)->get(route('team.hours.edit'))->assertForbidden();
        $this->actingAs($agent)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.can.manageTeam', false));
    }

    public function test_a_supervisor_invites_a_member_who_gets_a_signed_link(): void
    {
        Notification::fake();
        $supervisor = User::factory()->supervisor()->create(['name' => 'مشرف']);

        $this->actingAs($supervisor)
            ->post(route('team.members.store'), [
                'name' => 'سارة العتيبي',
                'email' => 'sara@mirsalink.test',
                'role' => 'agent',
            ])
            ->assertRedirect(route('team.members.index'));

        $member = User::where('email', 'sara@mirsalink.test')->firstOrFail();

        $this->assertSame(Role::Agent, $member->role);
        $this->assertTrue($member->hasPendingInvitation());
        $this->assertSame($supervisor->id, $member->invited_by);

        Notification::assertSentTo($member, TeamInvitation::class, function (TeamInvitation $notification): bool {
            return str_contains($notification->link, '/invitations/')
                && str_contains($notification->link, 'signature=')
                && $notification->inviterName === 'مشرف';
        });
    }

    public function test_an_email_already_on_the_team_cannot_be_invited_again(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        User::factory()->create(['email' => 'sara@mirsalink.test']);

        $this->actingAs($supervisor)
            ->post(route('team.members.store'), [
                'name' => 'سارة',
                'email' => 'sara@mirsalink.test',
                'role' => 'agent',
            ])
            ->assertSessionHasErrors(['email' => __('app.team.rules.email_taken')]);
    }

    public function test_a_supervisor_changes_a_members_role(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $agent = User::factory()->create();

        $this->actingAs($supervisor)
            ->patch(route('team.members.role', $agent), ['role' => 'supervisor'])
            ->assertRedirect(route('team.members.index'));

        $this->assertTrue($agent->fresh()->isSupervisor());
    }

    public function test_no_one_changes_their_own_role(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        User::factory()->supervisor()->create();

        $this->actingAs($supervisor)->patch(route('team.members.role', $supervisor), ['role' => 'agent']);

        $this->assertTrue($supervisor->fresh()->isSupervisor());
    }

    public function test_the_last_active_supervisor_cannot_be_made_an_agent(): void
    {
        $actor = User::factory()->supervisor()->create();
        $other = User::factory()->supervisor()->create();
        $actor->forceFill(['deactivated_at' => now()])->save();
        // Only $other is an active supervisor now; a second, deactivated one can't vouch.
        $reviewer = User::factory()->supervisor()->create();

        $this->actingAs($reviewer)->patch(route('team.members.role', $other), ['role' => 'agent']);
        $this->assertFalse($other->fresh()->isSupervisor());

        $this->actingAs($other)->patch(route('team.members.role', $reviewer), ['role' => 'agent']);
        $this->assertTrue($reviewer->fresh()->isSupervisor());
    }

    public function test_deactivating_a_member_ends_their_sessions_and_keeps_them_out(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $agent = User::factory()->create(['email' => 'agent@mirsalink.test']);

        DB::table('sessions')->insert([
            'id' => 'agent-session',
            'user_id' => $agent->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($supervisor)
            ->post(route('team.members.deactivate', $agent))
            ->assertRedirect(route('team.members.index'));

        $this->assertFalse($agent->fresh()->isActive());
        $this->assertDatabaseMissing('sessions', ['id' => 'agent-session']);

        $this->post(route('logout'));

        $this->post(route('login.store'), ['email' => 'agent@mirsalink.test', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.deactivated')]);
        $this->assertGuest();
    }

    public function test_a_deactivated_member_still_logged_in_is_signed_out(): void
    {
        $agent = User::factory()->deactivated()->create();

        $this->actingAs($agent)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_supervisors_cannot_deactivate_themselves(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        User::factory()->supervisor()->create();

        $this->actingAs($supervisor)->post(route('team.members.deactivate', $supervisor));

        $this->assertTrue($supervisor->fresh()->isActive());
    }

    public function test_a_deactivated_member_can_be_reactivated(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $agent = User::factory()->deactivated()->create();

        $this->actingAs($supervisor)->post(route('team.members.reactivate', $agent));

        $this->assertTrue($agent->fresh()->isActive());
    }

    public function test_resending_an_invitation_retires_the_earlier_link(): void
    {
        Notification::fake();
        $supervisor = User::factory()->supervisor()->create();
        $member = User::factory()->invited($supervisor)->create(['invited_at' => now()->subDay()]);

        $this->actingAs($supervisor)
            ->post(route('team.members.invitation', $member))
            ->assertRedirect(route('team.members.index'));

        Notification::assertSentTo($member, TeamInvitation::class);
        $this->assertTrue($member->fresh()->invited_at->isToday());
    }

    public function test_only_pending_invitations_can_be_resent(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $agent = User::factory()->create();

        $this->actingAs($supervisor)
            ->post(route('team.members.invitation', $agent))
            ->assertNotFound();
    }
}
