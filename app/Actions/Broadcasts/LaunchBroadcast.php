<?php

namespace App\Actions\Broadcasts;

use App\Enums\BroadcastStatus;
use App\Enums\RecipientStatus;
use App\Exceptions\BroadcastRuleException;
use App\Models\Broadcast;
use Carbon\CarbonInterface;

/**
 * Starts a campaign now, or sets it for later. The scheduler sends it a
 * batch each minute from then on.
 */
class LaunchBroadcast
{
    /**
     * @throws BroadcastRuleException
     */
    public function handle(Broadcast $broadcast, ?CarbonInterface $at = null): void
    {
        if (! $broadcast->status->isEditable()) {
            return;
        }

        if (! $broadcast->template?->isApproved()) {
            throw BroadcastRuleException::because('template_not_approved');
        }

        if (! $broadcast->recipients()->where('status', RecipientStatus::Queued)->exists()) {
            throw BroadcastRuleException::because('no_recipients');
        }

        if ($at !== null && $at->isPast()) {
            throw BroadcastRuleException::because('past');
        }

        $broadcast->forceFill($at === null
            ? ['status' => BroadcastStatus::Sending, 'started_at' => now(), 'scheduled_at' => null]
            : ['status' => BroadcastStatus::Scheduled, 'scheduled_at' => $at]
        )->save();
    }
}
