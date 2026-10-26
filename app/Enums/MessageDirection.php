<?php

namespace App\Enums;

enum MessageDirection: string
{
    /** From the customer. */
    case Inbound = 'inbound';

    /** From the team, the assistant or the platform, or an internal note. */
    case Outbound = 'outbound';
}
