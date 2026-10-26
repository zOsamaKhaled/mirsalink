<?php

namespace Database\Factories;

use App\Enums\MessageDirection;
use App\Enums\MessageSender;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * A customer's text, by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'direction' => MessageDirection::Inbound,
            'sender' => MessageSender::Contact,
            'type' => MessageType::Text,
            'body' => fake('ar_SA')->realText(60),
            'external_id' => 'wamid.'.fake()->unique()->uuid(),
        ];
    }

    public function fromAgent(User $agent): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => MessageDirection::Outbound,
            'sender' => MessageSender::Agent,
            'user_id' => $agent->id,
            'status' => MessageStatus::Sent,
            'sent_at' => now(),
        ]);
    }
}
