<?php

namespace App\Actions\Inbox;

use App\Enums\ConversationEventType;
use App\Exceptions\InboxRuleException;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\User;

/**
 * Gives a conversation to a member, moves it to another, or puts it back
 * for anyone to take. Supervisors may do any of these; an agent may take an
 * unassigned conversation, and pass on or put back one of their own.
 */
class AssignConversation
{
    /**
     * @throws InboxRuleException
     */
    public function handle(Conversation $conversation, ?User $to, ?User $actor = null): void
    {
        $from = $conversation->assigned_to;

        if ($from === $to?->id) {
            return;
        }

        if ($actor !== null && ! $actor->isSupervisor() && $from !== null && $from !== $actor->id) {
            throw InboxRuleException::because('not_yours');
        }

        if ($to !== null && (! $to->isActive() || $to->hasPendingInvitation())) {
            throw InboxRuleException::because('member_unavailable');
        }

        $conversation->forceFill(['assigned_to' => $to?->id])->save();

        match (true) {
            $to === null => ConversationEvent::record($conversation, ConversationEventType::Unassigned, $actor, ['from' => $from]),
            $from === null => ConversationEvent::record($conversation, ConversationEventType::Assigned, $actor, ['to' => $to->id]),
            default => ConversationEvent::record($conversation, ConversationEventType::Transferred, $actor, ['from' => $from, 'to' => $to->id]),
        };
    }
}
