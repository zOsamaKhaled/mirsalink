<?php

namespace Tests\Feature\Team;

use App\Actions\Team\SendInvitation;
use App\Enums\Availability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private function invite(): array
    {
        Notification::fake();
        $supervisor = User::factory()->supervisor()->create(['name' => 'مشرف']);
        $member = User::factory()->invited($supervisor)->create(['name' => 'سارة']);
        $link = app(SendInvitation::class)->handle($member);

        return [$member, $link];
    }

    public function test_the_link_opens_the_page_to_choose_a_password(): void
    {
        [$member, $link] = $this->invite();

        $this->get($link)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('auth/accept-invitation')
                ->where('email', $member->email)
                ->where('inviter', 'مشرف'));
    }

    public function test_accepting_sets_the_password_confirms_the_email_and_logs_in(): void
    {
        [$member, $link] = $this->invite();

        $this->post($link, [
            'name' => 'سارة العتيبي',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ])->assertRedirect(route('dashboard'));

        $member->refresh();
        $this->assertAuthenticatedAs($member);
        $this->assertSame('سارة العتيبي', $member->name);
        $this->assertTrue(Hash::check('Str0ng-Passw0rd!', $member->password));
        $this->assertFalse($member->hasPendingInvitation());
        $this->assertSame(Availability::Available, $member->availability);
    }

    public function test_a_used_link_cannot_be_used_again(): void
    {
        [, $link] = $this->invite();
        $password = ['name' => 'سارة', 'password' => 'Str0ng-Passw0rd!', 'password_confirmation' => 'Str0ng-Passw0rd!'];

        $this->post($link, $password);
        $this->post(route('logout'));

        $this->get($link)->assertForbidden();
    }

    public function test_a_tampered_link_is_refused(): void
    {
        [, $link] = $this->invite();

        $this->get($link.'x')->assertForbidden();
    }

    public function test_the_link_runs_out_after_seven_days(): void
    {
        [, $link] = $this->invite();

        $this->travel(SendInvitation::DAYS_VALID + 1)->days();

        $this->get($link)->assertForbidden();
    }

    public function test_a_link_from_before_a_resend_is_refused(): void
    {
        [$member, $firstLink] = $this->invite();

        $this->travel(1)->minutes();
        $secondLink = app(SendInvitation::class)->handle($member->fresh());

        $this->get($firstLink)->assertForbidden();
        $this->get($secondLink)->assertOk();
    }

    public function test_a_deactivated_member_cannot_accept(): void
    {
        [$member, $link] = $this->invite();
        $member->forceFill(['deactivated_at' => now()])->save();

        $this->get($link)->assertForbidden();
    }
}
