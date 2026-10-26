<?php

namespace App\Enums;

enum ConversationEventType: string
{
    /** Routing gave it to someone, or someone took it. data: to */
    case Assigned = 'assigned';

    /** Moved from one member to another. data: from, to */
    case Transferred = 'transferred';

    /** Put back for anyone to take. data: from */
    case Unassigned = 'unassigned';

    /** data: tag, by_bot */
    case Tagged = 'tagged';

    /** data: tag */
    case Untagged = 'untagged';

    case Closed = 'closed';

    case Reopened = 'reopened';

    /** The assistant passed it to a person (phase 8). data: to */
    case HandedOver = 'handed_over';

    /** The out-of-hours reply went out. */
    case AutoReplied = 'auto_replied';

    /** The customer asked to stop broadcasts. */
    case OptedOut = 'opted_out';
}
