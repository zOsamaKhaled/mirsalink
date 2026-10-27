<?php

namespace App\Http\Controllers;

use App\Assistant\AssistantSettings;
use App\Support\Migration;
use App\Support\MigrationReadiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The move from Respond.io, for supervisors: the plan's steps ticked as
 * they are done, the six conditions for cancelling measured over the last
 * two weeks, and the switch saying Respond.io's bot still answers on the
 * number, which keeps ours silent.
 */
class MigrationController extends Controller
{
    public function index(): Response
    {
        $migration = Migration::current();

        return Inertia::render('migration/index', [
            'migration' => [
                'respond_io_bot_active' => $migration->respondIoBotActive,
                'baseline_reply_minutes' => $migration->baselineReplyMinutes,
                'prices_checked_at' => $migration->pricesCheckedAt,
                'steps' => $migration->steps,
            ],
            'steps' => Migration::STEPS,
            'criteria' => MigrationReadiness::criteria(),
            'assistantMode' => AssistantSettings::current()->mode,
            'gateway' => config('whatsapp.gateway'),
            'targets' => [
                'days' => MigrationReadiness::DAYS,
                'agreement' => MigrationReadiness::AGREEMENT_TARGET,
                'min_judged' => MigrationReadiness::MIN_JUDGED,
                'delay_minutes' => intdiv(MigrationReadiness::MAX_DELAY_SECONDS, 60),
            ],
        ]);
    }

    /**
     * Saying Respond.io's bot still answers puts ours back to silent: two
     * assistants answering one customer would contradict each other.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'respond_io_bot_active' => ['required', 'boolean'],
            'baseline_reply_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ], [], ['baseline_reply_minutes' => __('app.migration.baseline.label')]);

        Migration::update([
            'respond_io_bot_active' => (bool) $validated['respond_io_bot_active'],
            'baseline_reply_minutes' => $validated['baseline_reply_minutes'] ?? null,
        ]);

        $assistant = AssistantSettings::current();

        if ($validated['respond_io_bot_active'] && $assistant->isLive()) {
            (new AssistantSettings(AssistantSettings::SHADOW, $assistant->greeting, $assistant->outOfScopeReply, $assistant->handoverReply))->save();
            Inertia::flash('toast', ['type' => 'info', 'message' => __('app.migration.flash.assistant_silenced')]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.migration.flash.saved')]);

        return back();
    }

    public function step(Request $request, string $step): RedirectResponse
    {
        abort_unless(in_array($step, Migration::STEPS, true), 404);

        $done = $request->validate(['done' => ['required', 'boolean']])['done'];
        $steps = Migration::current()->steps;

        if ($done) {
            $steps[$step] = now()->toIso8601String();
        } else {
            unset($steps[$step]);
        }

        Migration::update(['steps' => $steps]);

        return back();
    }

    /**
     * A supervisor read the assistant's recent answers and found no wrong
     * price or condition: the one condition the platform can't check alone.
     */
    public function pricesChecked(): RedirectResponse
    {
        Migration::update(['prices_checked_at' => now()->toIso8601String()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.migration.flash.prices_checked')]);

        return back();
    }
}
