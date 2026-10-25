<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A team change that would leave the team unable to run itself: no active
 * supervisor, or a supervisor locking themselves out. The message is
 * already translated and is shown to the supervisor as it is.
 */
class TeamRuleException extends RuntimeException
{
    public static function because(string $key): self
    {
        return new self(__("app.team.rules.{$key}"));
    }
}
