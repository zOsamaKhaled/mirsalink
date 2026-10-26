<?php

namespace App\Actions\Broadcasts;

use App\Enums\BroadcastStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageSender;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\RecipientStatus;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Conversation;
use App\Models\Template;
use App\WhatsApp\Gateway;

/**
 * Sends the next recipients of a campaign, at most $limit of them. Each
 * message lands in the customer's thread; a customer the team has never
 * talked to gets a closed conversation, which their reply opens and routes
 * like any other. The campaign completes when no one is left waiting, and
 * pauses if its template is no longer approved.
 */
class SendBroadcastBatch
{
    public function __construct(private Gateway $gateway) {}

    /**
     * @return int How many were sent or refused.
     */
    public function handle(Broadcast $broadcast, int $limit): int
    {
        $template = $broadcast->template;

        if ($template === null || ! $template->isApproved()) {
            $broadcast->forceFill(['status' => BroadcastStatus::Paused])->save();

            return 0;
        }

        $recipients = $broadcast->recipients()
            ->where('status', RecipientStatus::Queued)
            ->with('contact')
            ->orderBy('id')
            ->limit(max(0, $limit))
            ->get();

        $attempted = 0;

        foreach ($recipients as $recipient) {
            if ($recipient->contact === null || $recipient->contact->trashed() || ! $recipient->contact->acceptsBroadcasts()) {
                $recipient->forceFill(['status' => RecipientStatus::Skipped, 'skip_reason' => BroadcastRecipient::SKIP_OPTED_OUT])->save();

                continue;
            }

            $this->send($broadcast, $template, $recipient);
            $attempted++;
        }

        if (! $broadcast->recipients()->where('status', RecipientStatus::Queued)->exists()) {
            $broadcast->forceFill(['status' => BroadcastStatus::Completed, 'completed_at' => now()])->save();
        }

        return $attempted;
    }

    private function send(Broadcast $broadcast, Template $template, BroadcastRecipient $recipient): void
    {
        $conversation = Conversation::query()->firstOrCreate(
            ['contact_id' => $recipient->contact_id],
            ['status' => ConversationStatus::Closed, 'closed_at' => now()],
        );

        $params = $recipient->params ?? [];

        $message = $conversation->messages()->create([
            'direction' => MessageDirection::Outbound,
            'sender' => MessageSender::System,
            'user_id' => $broadcast->created_by,
            'type' => MessageType::Template,
            'template_id' => $template->id,
            'template_params' => $params,
            'broadcast_id' => $broadcast->id,
            'body' => Template::withValues($template->body, $params['body'] ?? []),
            'status' => MessageStatus::Pending,
        ]);

        $result = $this->gateway->send($message);

        $message->forceFill($result->accepted
            ? ['external_id' => $result->externalId, 'status' => MessageStatus::Sent, 'sent_at' => now()]
            : ['status' => MessageStatus::Failed, 'failed_at' => now(), 'error_code' => $result->errorCode, 'error_message' => $result->errorMessage]
        )->save();

        $conversation->forceFill(['last_message_at' => now()])->save();

        $recipient->forceFill([
            'status' => $result->accepted ? RecipientStatus::Sent : RecipientStatus::Failed,
            'message_id' => $message->id,
            'error' => $result->accepted ? null : $result->errorMessage,
            'sent_at' => now(),
        ])->save();
    }
}
