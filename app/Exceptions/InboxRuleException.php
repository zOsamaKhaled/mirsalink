<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Something the inbox will not do, with the reason already translated for
 * the member: a free-form reply after the 24-hour window, or taking a
 * conversation that belongs to someone else.
 */
class InboxRuleException extends RuntimeException
{
    public static function because(string $key): self
    {
        return new self(__("app.inbox.rules.{$key}"));
    }
}
