<?php

namespace App\WhatsApp;

/**
 * What WhatsApp said when a message was handed to it: its id, or why it
 * refused the message.
 */
final readonly class SendResult
{
    private function __construct(
        public bool $accepted,
        public ?string $externalId,
        public ?string $errorCode,
        public ?string $errorMessage,
    ) {}

    public static function accepted(string $externalId): self
    {
        return new self(true, $externalId, null, null);
    }

    public static function refused(string $errorCode, string $errorMessage): self
    {
        return new self(false, null, $errorCode, $errorMessage);
    }
}
