<?php

namespace App\WhatsApp;

use App\Models\Message;
use Illuminate\Support\Str;

/**
 * Plays WhatsApp's part until the platform is connected to Meta.
 *
 * It accepts every message with an id shaped like Meta's, and leaves it
 * "sent". The simulator page then moves it to delivered, read or failed,
 * through the same UpdateMessageStatus that Meta's webhook will call.
 */
class SimulatorGateway implements Gateway
{
    public function send(Message $message): SendResult
    {
        return SendResult::accepted('wamid.sim.'.Str::uuid()->toString());
    }
}
