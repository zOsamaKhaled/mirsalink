<?php

namespace App\Actions\Inbox;

use App\Enums\MessageStatus;
use App\Models\Message;

/**
 * WhatsApp telling us what happened to a message we sent: delivered, read
 * or failed. Statuses arrive out of order, so one never moves a message
 * backwards (a late "delivered" after "read" is ignored), and a failure
 * keeps its reason.
 */
class UpdateMessageStatus
{
    public function handle(string $externalId, MessageStatus $status, ?string $errorCode = null, ?string $errorMessage = null): ?Message
    {
        $message = Message::query()->where('external_id', $externalId)->first();

        if ($message === null || ($message->status !== null && $status->rank() <= $message->status->rank())) {
            return $message;
        }

        $message->status = $status;

        match ($status) {
            MessageStatus::Delivered => $message->delivered_at ??= now(),
            MessageStatus::Read => [$message->delivered_at ??= now(), $message->read_at ??= now()],
            MessageStatus::Failed => $message->forceFill([
                'failed_at' => now(),
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ]),
            default => null,
        };

        $message->save();

        return $message;
    }
}
