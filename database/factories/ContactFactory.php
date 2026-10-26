<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Saudi mobile numbers, +9665XXXXXXXX, as contacts are stored.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake('ar_SA')->name(),
            'phone' => '+9665'.fake()->unique()->numerify('########'),
            'email' => fake()->boolean(40) ? fake()->unique()->safeEmail() : null,
            'company' => fake()->boolean(30) ? fake('ar_SA')->company() : null,
            'source' => Contact::SOURCE_MANUAL,
        ];
    }

    public function optedOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'opted_out_at' => now(),
        ]);
    }
}
