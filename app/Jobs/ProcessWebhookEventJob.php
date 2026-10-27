<?php

namespace App\Jobs;

use App\Actions\WhatsApp\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Reads a stored notification once Meta has had its answer. One that
 * fails is read again by php artisan mirsalink:webhooks on the scheduler.
 */
class ProcessWebhookEventJob
{
    use Dispatchable;

    public function __construct(public int $eventId) {}

    public function handle(ProcessWebhookEvent $process): void
    {
        $event = WebhookEvent::query()->find($this->eventId);

        if ($event !== null && $event->processed_at === null) {
            $process->handle($event);
        }
    }
}
