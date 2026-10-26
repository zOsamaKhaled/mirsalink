<?php

namespace App\Enums;

/**
 * Where a campaign stands: written, waiting for its time, going out (a
 * batch each minute), held, finished, or stopped for good.
 */
enum BroadcastStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** Its audience and wording may still change. */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Scheduled;
    }
}
