<?php

namespace App\Codes;

use RuntimeException;

/**
 * Why a code Rizq asked for was not handed to WhatsApp. Rizq reads the
 * reason and sends the code by email instead.
 */
class CodeNotSent extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly int $status,
        string $message = '',
    ) {
        parent::__construct($message === '' ? $reason : $message);
    }

    /** Codes are switched off here, or Meta approved no template yet. */
    public static function unavailable(): self
    {
        return new self('unavailable', 503);
    }

    public static function tooMany(): self
    {
        return new self('too_many', 429);
    }

    public static function refused(string $message): self
    {
        return new self('refused', 502, $message);
    }
}
