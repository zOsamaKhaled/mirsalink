<?php

namespace App\Actions\Inbox;

use App\Enums\ConversationEventType;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\Tag;
use App\Models\User;

/**
 * Puts a tag on a conversation or takes it off, remembering who did: a
 * member, or the assistant (phase 8) with $byBot.
 */
class TagConversation
{
    public function attach(Conversation $conversation, Tag $tag, ?User $actor = null, bool $byBot = false): void
    {
        if ($conversation->tags()->whereKey($tag->id)->exists()) {
            return;
        }

        $conversation->tags()->attach($tag->id, ['tagged_by' => $actor?->id, 'by_bot' => $byBot]);

        ConversationEvent::record($conversation, ConversationEventType::Tagged, $actor, ['tag' => $tag->name, 'by_bot' => $byBot]);
    }

    public function detach(Conversation $conversation, Tag $tag, ?User $actor = null): void
    {
        if ($conversation->tags()->detach($tag->id) > 0) {
            ConversationEvent::record($conversation, ConversationEventType::Untagged, $actor, ['tag' => $tag->name]);
        }
    }
}
