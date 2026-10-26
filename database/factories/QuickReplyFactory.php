<?php

namespace Database\Factories;

use App\Models\QuickReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuickReply>
 */
class QuickReplyFactory extends Factory
{
    /**
     * A team reply unless given an owner.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shortcut' => fake()->unique()->lexify('reply????'),
            'title' => 'ترحيب',
            'body' => 'أهلًا {name}، معك {agent}. كيف أساعدك؟',
        ];
    }

    public function ownedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $user->id]);
    }
}
