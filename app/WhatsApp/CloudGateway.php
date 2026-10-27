<?php

namespace App\WhatsApp;

use App\Enums\MessageType;
use App\Models\Message;
use App\Models\Template;
use Illuminate\Support\Facades\Storage;

/**
 * The real WhatsApp: Meta's Cloud API in place of the simulator, chosen by
 * WHATSAPP_GATEWAY=cloud. A refusal comes back as a failed message with
 * Meta's reason in the team's language, never as an error page.
 */
class CloudGateway implements Gateway
{
    public function __construct(private CloudApi $api) {}

    public function send(Message $message): SendResult
    {
        try {
            $response = $this->api->post("{$this->api->phoneNumberId()}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => ltrim((string) $message->conversation->contact->phone, '+'),
                ...$this->content($message),
            ]);
        } catch (CloudApiException $exception) {
            return SendResult::refused($exception->metaCode, $exception->forPeople());
        }

        $id = $response['messages'][0]['id'] ?? null;

        return $id === null
            ? SendResult::refused('no_message_id', __('app.whatsapp.errors.no_message_id'))
            : SendResult::accepted((string) $id);
    }

    public function submitTemplate(Template $template): SendResult
    {
        // Meta reviews a picture, document or video header only with a sample
        // file uploaded through its resumable upload; not built yet.
        if ($template->header_type->hasMedia()) {
            return SendResult::refused('media_header', __('app.whatsapp.errors.media_header'));
        }

        try {
            $response = $this->api->post("{$this->api->businessAccountId()}/message_templates", TemplatePayload::forReview($template));
        } catch (CloudApiException $exception) {
            return SendResult::refused($exception->metaCode, $exception->forPeople());
        }

        return SendResult::accepted((string) ($response['id'] ?? ''));
    }

    /**
     * The message's part of the request, by its kind.
     *
     * @return array<string, mixed>
     *
     * @throws CloudApiException
     */
    private function content(Message $message): array
    {
        if ($message->type === MessageType::Template) {
            return ['type' => 'template', 'template' => TemplatePayload::forSending($message)];
        }

        if ($message->type->hasMedia() && $message->media_path !== null) {
            $kind = $message->type->value;
            $media = ['id' => $this->api->uploadMedia(
                (string) Storage::disk(config('whatsapp.media_disk'))->get($message->media_path),
                (string) $message->media_name,
                (string) $message->media_mime,
            )];

            if ($message->type !== MessageType::Audio && filled($message->body)) {
                $media['caption'] = $message->body;
            }

            if ($message->type === MessageType::Document) {
                $media['filename'] = $message->media_name;
            }

            return ['type' => $kind, $kind => $media];
        }

        return ['type' => 'text', 'text' => ['body' => (string) $message->body, 'preview_url' => true]];
    }
}
