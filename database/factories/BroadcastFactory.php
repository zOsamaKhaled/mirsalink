<?php

namespace Database\Factories;

use App\Enums\AudienceType;
use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Broadcast>
 */
class BroadcastFactory extends Factory
{
    /**
     * A draft to every contact from an approved two-variable template: the
     * customer's name, then a fixed order number.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'متابعة الطلبات',
            'template_id' => Template::factory()->approved(),
            'audience_type' => AudienceType::All,
            'variables' => [
                'body' => [
                    ['source' => 'name', 'fallback' => 'عميلنا'],
                    ['source' => 'text', 'value' => '1024'],
                ],
            ],
            'status' => BroadcastStatus::Draft,
        ];
    }
}
