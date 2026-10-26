<?php

namespace App\Enums;

/**
 * Who a campaign is for. Rizq's own segments join in once Rizq's internal
 * API is ready.
 */
enum AudienceType: string
{
    /** Every contact who has not stopped broadcasts. */
    case All = 'all';

    /** Contacts carrying any of the chosen tags, on them or on their conversation. */
    case Tags = 'tags';

    /** The numbers in a CSV file, whose other columns can fill variables. */
    case Csv = 'csv';
}
