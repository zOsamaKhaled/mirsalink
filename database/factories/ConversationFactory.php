<?php

namespace Database\Factories;

use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'status' => ConversationStatus::Open,
            'last_message_at' => now(),
            'last_inbound_at' => now(),
        ];
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => ['assigned_to' => $user->id]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ConversationStatus::Closed,
            'closed_at' => now(),
        ]);
    }

    /** The customer's last message was longer ago than the reply window. */
    public function windowClosed(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_inbound_at' => now()->subHours(Conversation::WINDOW_HOURS + 1),
        ]);
    }
}
