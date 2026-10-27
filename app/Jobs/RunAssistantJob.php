<?php

namespace App\Jobs;

use App\Actions\Assistant\RunAssistant;
use App\Models\Message;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * The assistant's turn on a customer message, run once the response that
 * received it has gone (dispatchAfterResponse): WhatsApp and the simulator
 * are answered at once, and no queue worker is needed.
 */
class RunAssistantJob
{
    use Dispatchable;

    public function __construct(public int $messageId) {}

    public function handle(RunAssistant $assistant): void
    {
        $message = Message::query()->find($this->messageId);

        if ($message !== null) {
            $assistant->handle($message);
        }
    }
}
