<?php

namespace App\Actions\WhatsApp;

use App\Actions\Codes\RecordCodeStatus;
use App\Actions\Inbox\ReceiveMessage;
use App\Actions\Inbox\UpdateMessageStatus;
use App\Actions\Templates\RecordTemplateDecision;
use App\Codes\CodeSettings;
use App\Enums\MessageStatus;
use App\Enums\TemplateStatus;
use App\Models\Template;
use App\Models\WebhookEvent;
use App\WhatsApp\CloudApiException;
use App\WhatsApp\InboundFromMeta;
use App\WhatsApp\NumberHealth;
use Throwable;

/**
 * Reads one stored notification from Meta: customer messages into the
 * inbox, delivery and reading back onto what the team sent, Meta's verdict
 * on templates, and the number's quality and tier. Reading twice changes
 * nothing (messages by their WhatsApp id, statuses only forward), so an
 * event that failed halfway is simply read again.
 */
class ProcessWebhookEvent
{
    public function __construct(
        private InboundFromMeta $inbound,
        private ReceiveMessage $receive,
        private UpdateMessageStatus $status,
        private RecordTemplateDecision $decision,
        private RecordCodeStatus $codeStatus,
    ) {}

    public function handle(WebhookEvent $event): bool
    {
        $event->increment('attempts');

        try {
            foreach ((array) ($event->payload['entry'] ?? []) as $entry) {
                foreach ((array) ($entry['changes'] ?? []) as $change) {
                    $this->change((string) ($change['field'] ?? ''), (array) ($change['value'] ?? []));
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            $event->forceFill(['error' => mb_substr($exception->getMessage(), 0, 250)])->save();

            return false;
        }

        $event->forceFill(['processed_at' => now(), 'error' => null])->save();

        return true;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function change(string $field, array $value): void
    {
        match ($field) {
            'messages' => $this->messages($value),
            'message_template_status_update' => $this->template($value),
            'phone_number_quality_update' => NumberHealth::record([
                'event' => $value['event'] ?? null,
                'tier' => $value['current_limit'] ?? null,
            ]),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function messages(array $value): void
    {
        foreach ((array) ($value['messages'] ?? []) as $message) {
            $inbound = $this->inbound->convert((array) $message, (array) ($value['contacts'] ?? []));

            if ($inbound !== null) {
                $this->receive->handle($inbound);
            }
        }

        foreach ((array) ($value['statuses'] ?? []) as $status) {
            $kind = MessageStatus::tryFrom((string) ($status['status'] ?? ''));

            if ($kind === null) {
                continue;
            }

            $error = $status['errors'][0] ?? null;
            $reason = $error === null ? null : (new CloudApiException(
                (string) ($error['code'] ?? ''),
                trim(($error['message'] ?? $error['title'] ?? '').(isset($error['error_data']['details']) ? " ({$error['error_data']['details']})" : '')),
            ))->forPeople();

            $errorCode = $error === null ? null : (string) ($error['code'] ?? '');

            // Not a message the team sent: then perhaps a verification code.
            $this->status->handle((string) $status['id'], $kind, $errorCode, $reason)
                ?? $this->codeStatus->handle((string) $status['id'], $kind, $errorCode, $reason);
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function template(array $value): void
    {
        $status = TemplateStatus::fromMeta((string) ($value['event'] ?? ''));
        $template = Template::query()->where('external_id', (string) ($value['message_template_id'] ?? ''))->first()
            ?? Template::query()
                ->where('name', (string) ($value['message_template_name'] ?? ''))
                ->where('language', (string) ($value['message_template_language'] ?? ''))
                ->first();

        $reason = $value['reason'] ?? null;
        $reason = $reason === 'NONE' ? null : $reason;

        if ($status !== null && $template !== null) {
            $this->decision->handle($template, $status, $reason);
        } elseif ($status !== null && ($value['message_template_name'] ?? null) === CodeSettings::templateName()) {
            CodeSettings::recordDecision((string) ($value['message_template_language'] ?? ''), $status, $reason);
        }
    }
}
