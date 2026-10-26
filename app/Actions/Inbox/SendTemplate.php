<?php

namespace App\Actions\Inbox;

use App\Enums\MessageDirection;
use App\Enums\MessageSender;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\TemplateCategory;
use App\Exceptions\InboxRuleException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Template;
use App\Models\User;
use App\WhatsApp\Gateway;

/**
 * A reply from an approved template, the one kind WhatsApp takes after the
 * 24-hour window (it goes inside the window too). The member gives a value
 * for each variable; the thread keeps the text as the customer reads it.
 * A marketing template never goes to a contact who stopped broadcasts.
 */
class SendTemplate
{
    public function __construct(
        private Gateway $gateway,
        private AssignConversation $assign,
    ) {}

    /**
     * @param  array{body?: list<string>, header?: string|null, button?: string|null}  $params
     *
     * @throws InboxRuleException
     */
    public function handle(Conversation $conversation, User $author, Template $template, array $params): Message
    {
        if (! $conversation->isOpen()) {
            throw InboxRuleException::because('closed');
        }

        if (! $template->isApproved()) {
            throw InboxRuleException::because('template_not_approved');
        }

        if ($template->category === TemplateCategory::Marketing && ! $conversation->contact->acceptsBroadcasts()) {
            throw InboxRuleException::because('template_opted_out');
        }

        $body = array_values(array_map(fn ($value): string => trim((string) $value), $params['body'] ?? []));
        $header = trim((string) ($params['header'] ?? ''));
        $button = trim((string) ($params['button'] ?? ''));

        $missing = count($body) !== $template->bodyVariableCount()
            || in_array('', $body, true)
            || ($template->headerHasVariable() && $header === '')
            || ($template->linkButtonsWithVariable() !== [] && $button === '');

        if ($missing) {
            throw InboxRuleException::because('template_params');
        }

        $message = $conversation->messages()->create([
            'direction' => MessageDirection::Outbound,
            'sender' => MessageSender::Agent,
            'user_id' => $author->id,
            'type' => MessageType::Template,
            'template_id' => $template->id,
            'template_params' => [
                'body' => $body,
                'header' => $template->headerHasVariable() ? $header : null,
                'button' => $template->linkButtonsWithVariable() !== [] ? $button : null,
            ],
            'body' => Template::withValues($template->body, $body),
            'status' => MessageStatus::Pending,
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
        ])->save();

        if ($conversation->assigned_to === null) {
            $this->assign->handle($conversation, $author, $author);
        }

        return $message;
    }
}
