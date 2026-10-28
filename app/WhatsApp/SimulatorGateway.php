<?php

namespace App\WhatsApp;

use App\Models\Message;
use App\Models\Template;
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

    /**
     * Takes every template for review; the simulator page approves or
     * refuses it.
     */
    public function submitTemplate(Template $template): SendResult
    {
        return SendResult::accepted('tpl.sim.'.Str::uuid()->toString());
    }

    /**
     * Takes every code; the codes page marks it delivered or failed.
     */
    public function sendCode(string $phone, string $code, string $language): SendResult
    {
        return SendResult::accepted('wamid.sim.code.'.Str::uuid()->toString());
    }

    public function submitCodeTemplate(string $language): SendResult
    {
        return SendResult::accepted('tpl.sim.code.'.Str::uuid()->toString());
    }
}
