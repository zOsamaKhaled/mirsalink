<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateSupervisorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_supervisor_who_can_log_in_straight_away(): void
    {
        $this->artisan('mirsalink:supervisor', [
            '--name' => 'سارة العتيبي',
            '--email' => 'sara@mirsalink.test',
        ])
            ->expectsQuestion('Password', 'Str0ng-Passw0rd!')
            ->assertSuccessful();

        $user = User::where('email', 'sara@mirsalink.test')->firstOrFail();

        $this->assertSame('سارة العتيبي', $user->name);
        $this->assertTrue(Hash::check('Str0ng-Passw0rd!', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_it_refuses_an_email_that_is_already_taken(): void
    {
        User::factory()->create(['email' => 'sara@mirsalink.test']);

        $this->artisan('mirsalink:supervisor', [
            '--name' => 'سارة العتيبي',
            '--email' => 'sara@mirsalink.test',
        ])
            ->expectsQuestion('Password', 'Str0ng-Passw0rd!')
            ->assertFailed();

        $this->assertSame(1, User::where('email', 'sara@mirsalink.test')->count());
    }
}
