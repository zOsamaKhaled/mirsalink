<?php

namespace Tests\Feature\Team;

use App\Enums\Availability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_set_their_own_availability(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->from(route('dashboard'))
            ->patch(route('availability.update'), ['availability' => 'busy'])
            ->assertRedirect(route('dashboard'));

        $this->assertSame(Availability::Busy, $agent->fresh()->availability);
        $this->assertFalse($agent->fresh()->availability->takesNewConversations());
    }

    public function test_an_unknown_availability_is_refused(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->patch(route('availability.update'), ['availability' => 'sleeping'])
            ->assertSessionHasErrors('availability');

        $this->assertSame(Availability::Available, $agent->fresh()->availability);
    }
}
