<?php

namespace Database\Factories;

use App\Enums\Availability;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function supervisor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Supervisor,
        ]);
    }

    public function availability(Availability $availability): static
    {
        return $this->state(fn (array $attributes) => [
            'availability' => $availability,
        ]);
    }

    /**
     * Invited by a supervisor and not yet in: no confirmed email, and a
     * password no one knows until the invitation is accepted.
     */
    public function invited(?User $by = null): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'password' => Hash::make(Str::random(40)),
            'invited_at' => now(),
            'invited_by' => $by?->id,
        ]);
    }

    public function deactivated(): static
    {
        return $this->state(fn (array $attributes) => [
            'deactivated_at' => now(),
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
