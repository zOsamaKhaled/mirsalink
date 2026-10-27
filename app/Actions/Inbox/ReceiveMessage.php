<?php

namespace App\Actions\Inbox;

use App\Actions\Broadcasts\NoteCustomerReply;
use App\Assistant\AssistantSettings;
use App\Enums\ConversationEventType;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageSender;
use App\Jobs\RunAssistantJob;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\Message;
use App\Support\PhoneNumber;
use App\WhatsApp\InboundMessage;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A customer's message arriving, from the simulator now and from Meta's
 * webhook in phase 9.
 *
 * It finds the contact by number (or makes one), opens their conversation
 * again if it was closed, and records the message. A conversation that has
 * no one, or whose member is gone or no longer taking conversations when it
 * reopens, is routed. Outside working hours the customer gets the
 * out-of-hours reply once. A message already seen (same WhatsApp id) is
 * ignored, since Meta delivers again when unsure.
 */
class ReceiveMessage
{
    public function __construct(
        private RouteConversation $route,
        private SendAutoReply $autoReply,
        private NoteCustomerReply $noteReply,
    ) {}

    public function handle(InboundMessage $inbound): Message
    {
        if ($inbound->externalId !== null) {
            $seen = Message::query()->where('external_id', $inbound->externalId)->first();

            if ($seen !== null) {
                return $seen;
            }
        }

        $phone = PhoneNumber::normalize($inbound->phone)
            ?? throw new InvalidArgumentException("Unreadable phone number: {$inbound->phone}");

        $settings = AssistantSettings::current();

        [$conversation, $message, $needsRouting] = DB::transaction(function () use ($inbound, $phone, $settings): array {
            $contact = $this->contact($phone, $inbound->profileName);
            $conversation = Conversation::query()->firstOrNew(['contact_id' => $contact->id]);
            $isNew = ! $conversation->exists;
            $wasClosed = $conversation->exists && ! $conversation->isOpen();

            $conversation->fill([
                'status' => ConversationStatus::Open,
                'closed_at' => null,
                'closed_by' => null,
                'last_message_at' => now(),
                'last_inbound_at' => now(),
            ]);
            $conversation->unread_count = ($conversation->unread_count ?? 0) + 1;
            $conversation->awaiting_reply_since ??= now();

            // Each time it opens, the assistant (when answering) has the first word.
            if ($isNew || $wasClosed) {
                $conversation->bot_active = $settings->isLive();
            }

            $conversation->save();

            if ($wasClosed) {
                ConversationEvent::record($conversation, ConversationEventType::Reopened);
            }

            $message = $conversation->messages()->create([
                'direction' => MessageDirection::Inbound,
                'sender' => MessageSender::Contact,
                'type' => $inbound->type,
                'body' => $inbound->body,
                'external_id' => $inbound->externalId,
                'media_path' => $inbound->mediaPath,
                'media_mime' => $inbound->mediaMime,
                'media_name' => $inbound->mediaName,
                'media_size' => $inbound->mediaSize,
            ]);

            $contact->forceFill(['last_message_at' => now()])->save();

            return [$conversation, $message, $isNew || $wasClosed || $conversation->assigned_to === null];
        });

        $this->noteReply->handle($conversation, $message);

        // While the assistant has the conversation, it routes on handover,
        // by the tags it gives, and the out-of-hours reply waits for that.
        if (! $conversation->bot_active) {
            if ($needsRouting) {
                $this->assignOnArrival($conversation);
            }

            $this->autoReply->handle($conversation->fresh());
        }

        if (! $settings->isOff()) {
            RunAssistantJob::dispatchAfterResponse($message->id);
        }

        return $message;
    }

    /**
     * The customer who comes back goes to the member who helped them last,
     * if that member is still here and taking conversations.
     */
    private function assignOnArrival(Conversation $conversation): void
    {
        $previous = $conversation->assignee;

        if ($previous !== null && $previous->isActive() && $previous->availability->takesNewConversations()) {
            return;
        }

        if ($previous !== null) {
            $conversation->forceFill(['assigned_to' => null])->save();
            ConversationEvent::record($conversation, ConversationEventType::Unassigned, null, ['from' => $previous->id]);
        }

        $this->route->handle($conversation);
    }

    private function contact(string $phone, ?string $profileName): Contact
    {
        $contact = Contact::withTrashed()->where('phone', $phone)->first();

        if ($contact === null) {
            return Contact::query()->create([
                'phone' => $phone,
                'name' => $profileName,
                'source' => Contact::SOURCE_WHATSAPP,
            ]);
        }

        if ($contact->trashed()) {
            $contact->restore();
        }

        if (blank($contact->name) && filled($profileName)) {
            $contact->forceFill(['name' => $profileName])->save();
        }

        return $contact;
    }
}
