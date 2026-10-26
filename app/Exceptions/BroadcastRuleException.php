<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A campaign that can't start as asked, with the reason already in the
 * member's language: its template is no longer approved, no one is left
 * to send to, or its time has passed.
 */
class BroadcastRuleException extends RuntimeException
{
    public static function because(string $key): self
    {
        return new self(__("app.broadcasts.rules.{$key}"));
    }
}
