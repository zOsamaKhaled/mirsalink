<?php

namespace App\Actions\Inbox;

use App\Enums\MessageDirection;
use App\Enums\MessageSender;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

/**
 * An internal note: written in the thread for the team, never sent to the
 * customer, and allowed whatever the 24-hour window says.
 */
class AddNote
{
    public function handle(Conversation $conversation, User $author, string $body): Message
    {
        return $conversation->messages()->create([
            'direction' => MessageDirection::Outbound,
            'sender' => MessageSender::Agent,
            'user_id' => $author->id,
            'type' => MessageType::Note,
            'body' => $body,
        ]);
    }
}
