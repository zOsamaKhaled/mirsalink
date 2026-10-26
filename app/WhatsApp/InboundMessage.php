<?php

namespace App\WhatsApp;

use App\Enums\MessageType;

/**
 * A message from a customer as it reaches the platform, whether the
 * simulator typed it or Meta's webhook delivered it. Media is already saved
 * to the private disk by then.
 */
final readonly class InboundMessage
{
    public function __construct(
        public string $phone,
        public MessageType $type = MessageType::Text,
        public ?string $body = null,
        public ?string $profileName = null,
        public ?string $externalId = null,
        public ?string $mediaPath = null,
        public ?string $mediaMime = null,
        public ?string $mediaName = null,
        public ?int $mediaSize = null,
    ) {}
}
