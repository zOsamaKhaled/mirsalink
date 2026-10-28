<?php

namespace App\Http\Controllers;

use App\Actions\Codes\RecordCodeStatus;
use App\Actions\Codes\SubmitCodeTemplates;
use App\Codes\CodeSettings;
use App\Enums\MessageStatus;
use App\Enums\TemplateStatus;
use App\Models\CodeDelivery;
use App\Models\Template;
use App\WhatsApp\Gateway;
use App\WhatsApp\SimulatorGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rizq's verification codes on WhatsApp, for supervisors: the link with
 * Rizq (whether it is set, never the secret), Meta's authentication
 * template in each language, the switch that sends every code back to
 * email, and the last codes with what became of them. Never the codes.
 */
class CodeController extends Controller
{
    public function index(): Response
    {
        $settings = CodeSettings::current();
        $week = CodeDelivery::query()->where('created_at', '>=', now()->subDays(7));

        return Inertia::render('codes/index', [
            'enabled' => $settings->enabled,
            'simulator' => $this->isSimulator(),
            'rizq' => [
                'url' => config('rizq.url'),
                'secret' => filled(config('rizq.secret')),
            ],
            'templateName' => CodeSettings::templateName(),
            'expiryMinutes' => (int) config('rizq.codes.expiry_minutes'),
            'templates' => collect(Template::LANGUAGES)->map(fn (string $language): array => [
                'language' => $language,
                'status' => $settings->status($language)->value,
                'reason' => $settings->templates[$language]['reason'] ?? null,
                'submitted_at' => $settings->templates[$language]['submitted_at'] ?? null,
            ])->all(),
            'week' => [
                'sent' => (clone $week)->count(),
                'delivered' => (clone $week)->whereIn('status', [MessageStatus::Delivered, MessageStatus::Read])->count(),
                'failed' => (clone $week)->where('status', MessageStatus::Failed)->count(),
                'to_email' => (clone $week)->where('status', MessageStatus::Failed)->whereNotNull('rizq_notified_at')->count(),
            ],
            'deliveries' => CodeDelivery::query()
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(fn (CodeDelivery $delivery): array => [
                    'id' => $delivery->id,
                    'phone' => $delivery->maskedPhone(),
                    'language' => $delivery->language,
                    'purpose' => $delivery->purpose,
                    'status' => $delivery->status->value,
                    'error' => $delivery->error_message,
                    'to_email' => $delivery->status === MessageStatus::Failed && $delivery->rizq_notified_at !== null,
                    'at' => $delivery->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Switching codes off sends every one to email from Rizq's side, with
     * nothing to change there.
     */
    public function update(Request $request): RedirectResponse
    {
        CodeSettings::setEnabled($request->validate(['enabled' => ['required', 'boolean']])['enabled']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.codes.flash.saved')]);

        return back();
    }

    public function submitTemplates(SubmitCodeTemplates $submit): RedirectResponse
    {
        $result = $submit->handle();

        Inertia::flash('toast', $result['refused'] === []
            ? ['type' => 'success', 'message' => __('app.codes.flash.submitted')]
            : ['type' => 'error', 'message' => implode(' ', $result['refused'])]);

        return back();
    }

    /**
     * Meta's review of the template, played on the simulator.
     */
    public function decide(Request $request, string $language): RedirectResponse
    {
        abort_unless($this->isSimulator() && in_array($language, Template::LANGUAGES, true), 404);
        abort_unless(CodeSettings::current()->status($language) === TemplateStatus::Pending, 409);

        $decision = $request->validate(['decision' => ['required', 'in:approved,rejected']])['decision'];

        CodeSettings::recordDecision($language, TemplateStatus::from($decision), __('app.codes.simulator.reason'));

        return back();
    }

    /**
     * A code's fate, played on the simulator: a failure goes back to Rizq
     * as Meta's would.
     */
    public function status(Request $request, CodeDelivery $delivery, RecordCodeStatus $record): RedirectResponse
    {
        abort_unless($this->isSimulator(), 404);

        $status = MessageStatus::from($request->validate(['status' => ['required', 'in:delivered,read,failed']])['status']);

        $record->handle(
            (string) $delivery->external_id,
            $status,
            $status === MessageStatus::Failed ? '131026' : null,
            $status === MessageStatus::Failed ? __('app.simulator.failure') : null,
        );

        return back();
    }

    private function isSimulator(): bool
    {
        return app(Gateway::class) instanceof SimulatorGateway;
    }
}
