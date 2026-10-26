<?php

namespace App\Actions\Inbox;

use App\Enums\ConversationEventType;
use App\Enums\MessageDirection;
use App\Enums\MessageSender;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Support\WorkingHours;
use App\WhatsApp\Gateway;

/**
 * The out-of-hours reply, sent once to a customer who writes while the team
 * is not working. "Once" means once while their message waits: after the
 * team answers, a later message outside hours gets it again.
 */
class SendAutoReply
{
    public function __construct(private Gateway $gateway) {}

    public function handle(Conversation $conversation): void
    {
        $hours = WorkingHours::current();

        if (! $hours->autoReplyEnabled || $hours->isOpenAt(now())) {
            return;
        }

        $alreadyToldSince = $conversation->events()
            ->where('type', ConversationEventType::AutoReplied)
            ->where('created_at', '>=', $conversation->awaiting_reply_since ?? now())
            ->exists();

        if ($alreadyToldSince) {
            return;
        }

        $message = $conversation->messages()->create([
            'direction' => MessageDirection::Outbound,
            'sender' => MessageSender::System,
            'type' => MessageType::Text,
            'body' => $hours->autoReplyMessage,
            'status' => MessageStatus::Pending,
        ]);

        $result = $this->gateway->send($message);

        $message->forceFill($result->accepted
            ? ['external_id' => $result->externalId, 'status' => MessageStatus::Sent, 'sent_at' => now()]
            : ['status' => MessageStatus::Failed, 'failed_at' => now(), 'error_code' => $result->errorCode, 'error_message' => $result->errorMessage]
        )->save();

        ConversationEvent::record($conversation, ConversationEventType::AutoReplied);
    }
}
