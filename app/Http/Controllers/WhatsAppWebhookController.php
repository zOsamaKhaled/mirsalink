<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebhookEventJob;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Where Meta knocks. The GET answers Meta's one-time check of the address
 * with the verify token; the POST takes a notification, keeps it only if
 * Meta signed it with the app secret, answers at once (Meta resends
 * anything not answered quickly), and reads it after the response.
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $token = (string) config('whatsapp.cloud.verify_token');

        abort_unless(
            $token !== ''
                && $request->query('hub_mode') === 'subscribe'
                && hash_equals($token, (string) $request->query('hub_verify_token')),
            403,
        );

        return response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
    }

    public function receive(Request $request): Response
    {
        $secret = (string) config('whatsapp.cloud.app_secret');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        abort_unless($secret !== '' && hash_equals($expected, (string) $request->header('X-Hub-Signature-256')), 403);

        $payload = json_decode($request->getContent(), true);
        abort_unless(is_array($payload), 400);

        $event = WebhookEvent::query()->create(['payload' => $payload]);
        ProcessWebhookEventJob::dispatchAfterResponse($event->id);

        return response('EVENT_RECEIVED', 200, ['Content-Type' => 'text/plain']);
    }
}
