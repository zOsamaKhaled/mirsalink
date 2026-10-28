<?php

namespace App\WhatsApp;

use App\Models\Message;
use App\Models\Template;

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

    /**
     * Hands a template to Meta for review. The verdict comes back later
     * (RecordTemplateDecision), not from here.
     */
    public function submitTemplate(Template $template): SendResult;

    /**
     * Sends a verification code with Meta's authentication template, in
     * the given language. Like any message, its fate comes back later as
     * status updates.
     */
    public function sendCode(string $phone, string $code, string $language): SendResult;

    /**
     * Hands Meta the authentication template for one language. Its wording
     * is Meta's own; only the expiry and the copy button are ours.
     */
    public function submitCodeTemplate(string $language): SendResult;
}
