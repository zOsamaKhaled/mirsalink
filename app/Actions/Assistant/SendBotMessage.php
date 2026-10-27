<?php

namespace App\Actions\Assistant;

use App\Enums\MessageDirection;
use App\Enums\MessageSender;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\WhatsApp\Gateway;

/**
 * A message from the assistant to the customer, through the same gateway
 * as the team's. An answer means the customer is no longer waiting; a
 * handover line leaves them waiting for the person it promises.
 */
class SendBotMessage
{
    public function __construct(private Gateway $gateway) {}

    public function handle(Conversation $conversation, string $body, bool $answers = true): Message
    {
        $message = $conversation->messages()->create([
            'direction' => MessageDirection::Outbound,
            'sender' => MessageSender::Bot,
            'type' => MessageType::Text,
            'body' => $body,
            'status' => MessageStatus::Pending,
        ]);

        $result = $this->gateway->send($message);

        $message->forceFill($result->accepted
            ? ['external_id' => $result->externalId, 'status' => MessageStatus::Sent, 'sent_at' => now()]
            : ['status' => MessageStatus::Failed, 'failed_at' => now(), 'error_code' => $result->errorCode, 'error_message' => $result->errorMessage]
        )->save();

        $conversation->forceFill([
            'last_message_at' => now(),
            ...($answers ? ['awaiting_reply_since' => null, 'unread_count' => 0] : []),
        ])->save();

        return $message;
    }
}
