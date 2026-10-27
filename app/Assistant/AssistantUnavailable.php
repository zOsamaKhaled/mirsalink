<?php

namespace App\Assistant;

use RuntimeException;

/**
 * The brain could not be used for this message: unreachable, slower than
 * the timeout, or its answer was not the JSON asked for. The customer then
 * goes to a person without an automatic reply.
 */
class AssistantUnavailable extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self(mb_substr($reason, 0, 250));
    }
}
