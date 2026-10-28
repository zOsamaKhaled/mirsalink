<?php

namespace Database\Factories;

use App\Enums\MessageStatus;
use App\Models\CodeDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CodeDelivery>
 */
class CodeDeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => Str::uuid()->toString(),
            'phone' => '+9665'.fake()->numerify('########'),
            'language' => 'ar',
            'purpose' => 'login',
            'status' => MessageStatus::Sent,
            'external_id' => 'wamid.sim.'.Str::uuid()->toString(),
        ];
    }
}
