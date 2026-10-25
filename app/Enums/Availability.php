<?php

namespace App\Enums;

/**
 * Whether a team member takes new conversations right now. Only the
 * available are given new conversations by the routing rules (phase 4).
 */
enum Availability: string
{
    case Available = 'available';
    case Busy = 'busy';
    case Away = 'away';

    public function takesNewConversations(): bool
    {
        return $this === self::Available;
    }
}
