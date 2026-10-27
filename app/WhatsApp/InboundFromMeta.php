<?php

namespace App\WhatsApp;

use App\Enums\MessageType;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

/**
 * A customer message as Meta's webhook carries it, made into the
 * InboundMessage the inbox reads. Files are fetched from Meta at once (its
 * links expire) onto the private disk. Reactions are not messages and are
 * skipped; a kind the inbox can't show arrives as a line saying so.
 */
class InboundFromMeta
{
    public function __construct(private CloudApi $api) {}

    /**
     * @param  array<string, mixed>  $message  one entry of value.messages
     * @param  list<array<string, mixed>>  $contacts  value.contacts, for the sender's profile name
     */
    public function convert(array $message, array $contacts): ?InboundMessage
    {
        $type = (string) ($message['type'] ?? 'unsupported');

        if ($type === 'reaction') {
            return null;
        }

        $from = (string) ($message['from'] ?? '');
        $profile = collect($contacts)->firstWhere('wa_id', $from)['profile']['name'] ?? null;
        $common = ['phone' => '+'.ltrim($from, '+'), 'profileName' => $profile, 'externalId' => (string) ($message['id'] ?? '')];

        return match ($type) {
            'text' => new InboundMessage(...$common, body: (string) ($message['text']['body'] ?? '')),
            'image', 'document', 'audio', 'video', 'sticker' => $this->withFile($common, $type, (array) $message[$type]),
            'location' => new InboundMessage(...$common, type: MessageType::Location, body: $this->place((array) $message['location'])),
            'button' => new InboundMessage(...$common, body: (string) ($message['button']['text'] ?? '')),
            'interactive' => new InboundMessage(...$common, body: (string) (
                $message['interactive']['button_reply']['title'] ?? $message['interactive']['list_reply']['title'] ?? ''
            )),
            default => new InboundMessage(...$common, body: __('app.whatsapp.unsupported', ['type' => $type])),
        };
    }

    /**
     * @param  array{phone: string, profileName: string|null, externalId: string}  $common
     * @param  array<string, mixed>  $file
     */
    private function withFile(array $common, string $type, array $file): InboundMessage
    {
        $download = $this->api->downloadMedia((string) $file['id']);
        $mime = (string) ($file['mime_type'] ?? $download['mime']);
        $extension = MimeTypes::getDefault()->getExtensions(strtok($mime, ';') ?: $mime)[0] ?? 'bin';
        $path = 'media/inbound/'.Str::uuid()->toString().'.'.$extension;

        Storage::disk(config('whatsapp.media_disk'))->put($path, $download['contents']);

        return new InboundMessage(
            ...$common,
            type: $type === 'sticker' ? MessageType::Image : MessageType::from($type),
            body: isset($file['caption']) ? (string) $file['caption'] : null,
            mediaPath: $path,
            mediaMime: $mime,
            mediaName: (string) ($file['filename'] ?? basename($path)),
            mediaSize: $download['size'],
        );
    }

    /**
     * A shared location as a line the team can open on a map.
     *
     * @param  array<string, mixed>  $location
     */
    private function place(array $location): string
    {
        $where = trim(implode(' · ', array_filter([$location['name'] ?? null, $location['address'] ?? null])));
        $map = "https://maps.google.com/?q={$location['latitude']},{$location['longitude']}";

        return $where === '' ? $map : "{$where}\n{$map}";
    }
}
