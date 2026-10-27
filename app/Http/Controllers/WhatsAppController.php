<?php

namespace App\Http\Controllers;

use App\Actions\WhatsApp\ProcessWebhookEvent;
use App\Actions\WhatsApp\SyncTemplates;
use App\Models\WebhookEvent;
use App\WhatsApp\CloudApi;
use App\WhatsApp\CloudApiException;
use App\WhatsApp\NumberHealth;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The connection to WhatsApp, for supervisors: which keys are in place
 * (never their values), the webhook address to give Meta, the number as
 * Meta sees it (name, quality, tier), and how notifications are flowing.
 */
class WhatsAppController extends Controller
{
    private const array KEYS = ['token', 'phone_number_id', 'business_account_id', 'app_secret', 'verify_token'];

    public function index(CloudApi $api): Response
    {
        $cloud = config('whatsapp.gateway') === 'cloud';

        return Inertia::render('whatsapp/index', [
            'gateway' => $cloud ? 'cloud' : 'simulator',
            'keys' => collect(self::KEYS)->mapWithKeys(fn (string $key): array => [$key => filled(config("whatsapp.cloud.{$key}"))]),
            'webhookUrl' => route('webhooks.whatsapp.receive'),
            'graphVersion' => config('whatsapp.cloud.graph_version'),
            'number' => $cloud && $api->configured() ? $this->number($api) : null,
            'health' => NumberHealth::current(),
            'events' => [
                'last' => WebhookEvent::query()->latest('id')->value('created_at')?->toIso8601String(),
                'today' => WebhookEvent::query()->where('created_at', '>=', now()->subDay())->count(),
                'waiting' => WebhookEvent::query()->whereNull('processed_at')->where('attempts', '<', WebhookEvent::MAX_ATTEMPTS)->count(),
                'stuck' => WebhookEvent::query()->whereNull('processed_at')->where('attempts', '>=', WebhookEvent::MAX_ATTEMPTS)->count(),
                'error' => WebhookEvent::query()->whereNull('processed_at')->whereNotNull('error')->latest('id')->value('error'),
            ],
        ]);
    }

    /**
     * Reads again every notification not read yet, the stuck ones too.
     */
    public function retry(ProcessWebhookEvent $process): RedirectResponse
    {
        $events = WebhookEvent::query()->whereNull('processed_at')->orderBy('id')->limit(500)->get();
        $read = $events->filter(fn (WebhookEvent $event): bool => $process->handle($event))->count();

        Inertia::flash('toast', ['type' => $read === $events->count() ? 'success' : 'warning', 'message' => __('app.whatsapp.flash.retried', [
            'read' => $read,
            'total' => $events->count(),
        ])]);

        return back();
    }

    public function syncTemplates(SyncTemplates $sync): RedirectResponse
    {
        abort_unless(config('whatsapp.gateway') === 'cloud', 404);

        try {
            $changed = $sync->handle();
        } catch (CloudApiException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->forPeople()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.whatsapp.flash.synced', ['count' => $changed])]);

        return back();
    }

    /**
     * The number as Meta sees it now; its quality and tier are kept for
     * the campaigns' daily limit.
     *
     * @return array<string, mixed>
     */
    private function number(CloudApi $api): array
    {
        try {
            $number = $api->get($api->phoneNumberId(), [
                'fields' => 'display_phone_number,verified_name,quality_rating,messaging_limit_tier,name_status',
            ]);
        } catch (CloudApiException $exception) {
            return ['error' => $exception->forPeople()];
        }

        NumberHealth::record([
            'quality' => $number['quality_rating'] ?? null,
            'tier' => $number['messaging_limit_tier'] ?? null,
        ]);

        return [
            'display' => $number['display_phone_number'] ?? null,
            'name' => $number['verified_name'] ?? null,
            'name_status' => $number['name_status'] ?? null,
            'quality' => $number['quality_rating'] ?? null,
            'tier' => $number['messaging_limit_tier'] ?? null,
        ];
    }
}
