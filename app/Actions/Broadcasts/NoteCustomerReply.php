<?php

namespace App\Actions\Broadcasts;

use App\Enums\ConversationEventType;
use App\Enums\RecipientStatus;
use App\Models\BroadcastRecipient;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\Message;
use App\Support\StopWords;

/**
 * What a customer's message means for campaigns: a reply to the ones that
 * reached them lately, and, when it is only a stop word or the stop
 * button, the end of their broadcasts.
 */
class NoteCustomerReply
{
    public function handle(Conversation $conversation, Message $message): void
    {
        BroadcastRecipient::query()
            ->where('contact_id', $conversation->contact_id)
            ->where('status', RecipientStatus::Sent)
            ->whereNull('replied_at')
            ->where('sent_at', '>=', now()->subHours((int) config('broadcasts.reply_window_hours')))
            ->update(['replied_at' => now()]);

        $contact = $conversation->contact;

        if ($contact->acceptsBroadcasts() && StopWords::matches($message->body)) {
            $contact->forceFill(['opted_out_at' => now()])->save();

            ConversationEvent::record($conversation, ConversationEventType::OptedOut);
        }
    }
}
