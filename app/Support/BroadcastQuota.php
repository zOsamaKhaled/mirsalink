<?php

namespace App\Support;

use App\Enums\RecipientStatus;
use App\Models\BroadcastRecipient;
use App\WhatsApp\NumberHealth;

/**
 * How many campaign messages may still go out: the number's daily limit
 * counts every campaign message of the last 24 hours, as Meta counts the
 * conversations a business starts. The limit is the tier Meta last
 * reported for the number, or BROADCASTS_DAILY_LIMIT until it has.
 */
class BroadcastQuota
{
    public static function sentInLastDay(): int
    {
        return BroadcastRecipient::query()
            ->whereIn('status', [RecipientStatus::Sent, RecipientStatus::Failed])
            ->where('sent_at', '>=', now()->subDay())
            ->count();
    }

    public static function dailyLimit(): int
    {
        return NumberHealth::dailyLimit() ?? (int) config('broadcasts.daily_limit');
    }

    public static function remainingToday(): int
    {
        return max(0, self::dailyLimit() - self::sentInLastDay());
    }

    /** What one minute's batch may send, across all campaigns. */
    public static function forThisMinute(): int
    {
        return min((int) config('broadcasts.per_minute'), self::remainingToday());
    }
}
