<?php

namespace App\WhatsApp;

use App\Models\Message;

/**
 * The one door messages leave the platform through.
 *
 * The simulator stands behind it until phase 9, when the WhatsApp Cloud API
 * takes its place from config('whatsapp.gateway'). Nothing else in the
 * platform knows which one is answering, the way payment gateways are
 * swapped in Rizq.
 */
interface Gateway
{
    /**
     * Hands the message to WhatsApp. Delivery and reading come back later
     * as status updates (UpdateMessageStatus), not from here.
     */
    public function send(Message $message): SendResult;
}
