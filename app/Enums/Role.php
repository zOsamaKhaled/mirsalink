<?php

namespace App\Enums;

/**
 * What a team member may do. Supervisors manage the team, its hours and
 * (later) the routing rules, and see every conversation; agents answer the
 * conversations given to them.
 */
enum Role: string
{
    case Supervisor = 'supervisor';
    case Agent = 'agent';
}
