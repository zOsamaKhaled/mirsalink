<?php

namespace App\Actions\Broadcasts;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Support\BroadcastQuota;

/**
 * One minute's work for all campaigns: those whose time has come start,
 * then the minute's allowance (the pace, within what is left of the day)
 * goes to the campaigns sending, oldest first.
 */
class RunBroadcasts
{
    public function __construct(private SendBroadcastBatch $batch) {}

    /**
     * @return int How many messages went out this minute.
     */
    public function handle(): int
    {
        Broadcast::query()
            ->where('status', BroadcastStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->get()
            ->each(fn (Broadcast $broadcast) => $broadcast->forceFill([
                'status' => BroadcastStatus::Sending,
                'started_at' => now(),
            ])->save());

        $allowance = BroadcastQuota::forThisMinute();
        $sent = 0;

        $sending = Broadcast::query()
            ->with('template')
            ->where('status', BroadcastStatus::Sending)
            ->orderBy('started_at')
            ->get();

        foreach ($sending as $broadcast) {
            if ($sent >= $allowance) {
                break;
            }

            $sent += $this->batch->handle($broadcast, $allowance - $sent);
        }

        return $sent;
    }
}
