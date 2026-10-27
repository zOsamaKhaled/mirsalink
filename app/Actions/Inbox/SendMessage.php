<?php

namespace App\Actions\Inbox;

use App\Enums\MessageDirection;
use App\Enums\MessageSender;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Exceptions\InboxRuleException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\WhatsApp\Gateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * A team member's reply: text, a file, or both (the text becomes the
 * file's caption).
 *
 * WhatsApp only takes free-form replies within 24 hours of the customer's
 * last message; after that it needs an approved template (phase 5). The
 * first reply also gives an unassigned conversation to whoever wrote it.
 */
class SendMessage
{
    public function __construct(
        private Gateway $gateway,
        private AssignConversation $assign,
    ) {}

    /**
     * @throws InboxRuleException
     */
    public function handle(Conversation $conversation, User $author, ?string $body, ?UploadedFile $file = null): Message
    {
        if (! $conversation->isOpen()) {
            throw InboxRuleException::because('closed');
        }

        if (! $conversation->windowIsOpen()) {
            throw InboxRuleException::because('window_closed');
        }

        $media = $file === null ? [] : $this->store($conversation, $file);

        $message = $conversation->messages()->create([
            'direction' => MessageDirection::Outbound,
            'sender' => MessageSender::Agent,
            'user_id' => $author->id,
            'type' => $file === null ? MessageType::Text : MessageType::forMime((string) $file->getMimeType()),
            'body' => filled($body) ? $body : null,
            'status' => MessageStatus::Pending,
            ...$media,
        ]);

        $result = $this->gateway->send($message);

        $message->forceFill($result->accepted
            ? ['external_id' => $result->externalId, 'status' => MessageStatus::Sent, 'sent_at' => now()]
            : ['status' => MessageStatus::Failed, 'failed_at' => now(), 'error_code' => $result->errorCode, 'error_message' => $result->errorMessage]
        )->save();

        $conversation->forceFill([
            'last_message_at' => now(),
            'unread_count' => 0,
            'awaiting_reply_since' => null,
            'first_response_at' => $conversation->first_response_at ?? now(),
            'bot_active' => false,
        ])->save();

        if ($conversation->assigned_to === null) {
            $this->assign->handle($conversation, $author, $author);
        }

        return $message;
    }

    /**
     * @return array{media_path: string, media_mime: string, media_name: string, media_size: int}
     */
    private function store(Conversation $conversation, UploadedFile $file): array
    {
        $name = Str::uuid()->toString().'.'.($file->guessExtension() ?? $file->getClientOriginalExtension());

        return [
            'media_path' => $file->storeAs("media/{$conversation->id}", $name, config('whatsapp.media_disk')),
            'media_mime' => (string) $file->getMimeType(),
            'media_name' => $file->getClientOriginalName(),
            'media_size' => (int) $file->getSize(),
        ];
    }
}
