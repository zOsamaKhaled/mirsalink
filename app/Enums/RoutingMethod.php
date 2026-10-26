<?php

namespace App\Enums;

/**
 * How a rule chooses among its members when a conversation comes in.
 */
enum RoutingMethod: string
{
    /** Each available member in turn. */
    case RoundRobin = 'round_robin';

    /** The available member with the fewest open conversations. */
    case LeastBusy = 'least_busy';
}
