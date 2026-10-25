<?php

namespace Tests\Feature\Team;

use App\Models\User;
use App\Support\WorkingHours;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class WorkingHoursTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $days = [];

        foreach (range(0, 6) as $day) {
            $days[$day] = ['open' => $day <= 4, 'from' => '08:00', 'to' => '16:00'];
        }

        return array_replace([
            'timezone' => 'Asia/Riyadh',
            'days' => $days,
            'auto_reply_enabled' => true,
            'auto_reply_message' => 'نحن خارج الدوام.',
        ], $overrides);
    }

    public function test_the_page_starts_from_a_saudi_working_week(): void
    {
        $this->actingAs(User::factory()->supervisor()->create())
            ->get(route('team.hours.edit'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('team/hours')
                ->where('hours.timezone', 'Asia/Riyadh')
                ->where('hours.days.0.open', true)
                ->where('hours.days.5.open', false)
                ->where('hours.auto_reply_enabled', false));
    }

    public function test_a_supervisor_saves_the_hours_and_the_reply(): void
    {
        $this->actingAs(User::factory()->supervisor()->create())
            ->put(route('team.hours.update'), $this->payload())
            ->assertRedirect(route('team.hours.edit'));

        $hours = WorkingHours::current();
        $this->assertSame('08:00', $hours->days[0]['from']);
        $this->assertTrue($hours->autoReplyEnabled);
        $this->assertSame('نحن خارج الدوام.', $hours->autoReplyMessage);
    }

    public function test_an_open_day_has_to_end_after_it_starts(): void
    {
        $payload = $this->payload();
        $payload['days'][1] = ['open' => true, 'from' => '17:00', 'to' => '09:00'];

        $this->actingAs(User::factory()->supervisor()->create())
            ->put(route('team.hours.update'), $payload)
            ->assertSessionHasErrors(['days.1.to' => __('app.team.hours.end_after_start')]);
    }

    public function test_a_closed_day_keeps_its_times_whatever_they_are(): void
    {
        $payload = $this->payload();
        $payload['days'][5] = ['open' => false, 'from' => '17:00', 'to' => '09:00'];

        $this->actingAs(User::factory()->supervisor()->create())
            ->put(route('team.hours.update'), $payload)
            ->assertSessionHasNoErrors();
    }

    public function test_the_reply_needs_text_when_it_is_on(): void
    {
        $this->actingAs(User::factory()->supervisor()->create())
            ->put(route('team.hours.update'), $this->payload(['auto_reply_message' => '']))
            ->assertSessionHasErrors(['auto_reply_message' => __('app.team.hours.reply_required')]);
    }

    public function test_agents_cannot_change_the_hours(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('team.hours.update'), $this->payload())
            ->assertForbidden();
    }

    public function test_open_and_closed_are_read_in_the_teams_time_zone(): void
    {
        $hours = WorkingHours::defaults();

        // Sunday 06:30 UTC is 09:30 in Riyadh: open.
        $this->assertTrue($hours->isOpenAt(CarbonImmutable::parse('2026-10-25 06:30', 'UTC')));
        // Sunday 05:30 UTC is 08:30 in Riyadh: not yet.
        $this->assertFalse($hours->isOpenAt(CarbonImmutable::parse('2026-10-25 05:30', 'UTC')));
        // Sunday 14:00 UTC is 17:00 in Riyadh: just closed.
        $this->assertFalse($hours->isOpenAt(CarbonImmutable::parse('2026-10-25 14:00', 'UTC')));
        // Friday is closed all day.
        $this->assertFalse($hours->isOpenAt(CarbonImmutable::parse('2026-10-30 09:00', 'Asia/Riyadh')));
    }
}
