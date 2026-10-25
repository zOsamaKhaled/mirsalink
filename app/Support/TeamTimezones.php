<?php

namespace App\Support;

/**
 * The time zones a team can set its hours in: the Gulf, the region around
 * it, and UTC. Their names are in lang/{locale}/app.php under
 * team.hours.timezones.
 */
final class TeamTimezones
{
    /**
     * @var list<string>
     */
    public const array ALL = [
        'Asia/Riyadh',
        'Asia/Kuwait',
        'Asia/Bahrain',
        'Asia/Qatar',
        'Asia/Dubai',
        'Asia/Muscat',
        'Africa/Cairo',
        'Asia/Amman',
        'Europe/London',
        'UTC',
    ];
}
