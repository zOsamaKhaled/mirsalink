<?php

namespace App\Actions\Inbox;

use App\Enums\ConversationEventType;
use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\User;

/**
 * Closing ends a piece of work; the customer's next message opens the
 * conversation again. Reopening by hand keeps whoever had it.
 */
class CloseConversation
{
    public function close(Conversation $conversation, User $actor): void
    {
        if (! $conversation->isOpen()) {
            return;
        }

        $conversation->forceFill([
            'status' => ConversationStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $actor->id,
            'unread_count' => 0,
            'awaiting_reply_since' => null,
        ])->save();

        ConversationEvent::record($conversation, ConversationEventType::Closed, $actor);
    }

    public function reopen(Conversation $conversation, User $actor): void
    {
        if ($conversation->isOpen()) {
            return;
        }

        $conversation->forceFill([
            'status' => ConversationStatus::Open,
            'closed_at' => null,
            'closed_by' => null,
        ])->save();

        ConversationEvent::record($conversation, ConversationEventType::Reopened, $actor);
    }
}
