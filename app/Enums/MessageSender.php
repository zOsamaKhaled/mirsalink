<?php

namespace App\Enums;

/**
 * Who wrote a message: the customer, a team member, the assistant, or the
 * platform itself (the out-of-hours reply).
 */
enum MessageSender: string
{
    case Contact = 'contact';
    case Agent = 'agent';
    case Bot = 'bot';
    case System = 'system';
}
