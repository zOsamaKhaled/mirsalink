<?php

namespace App\Enums;

/**
 * One recipient's place in a campaign. Delivery and reading come from the
 * message that was sent; replies from the customer's next message.
 */
enum RecipientStatus: string
{
    case Queued = 'queued';
    case Skipped = 'skipped';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
