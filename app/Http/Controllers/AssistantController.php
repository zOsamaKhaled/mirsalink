<?php

namespace App\Http\Controllers;

use App\Actions\Assistant\RunAssistant;
use App\Assistant\AssistantReport;
use App\Assistant\AssistantSettings;
use App\Assistant\Brain;
use App\Models\KnowledgeEntry;
use App\Support\Migration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The assistant's page, for supervisors: the switch (off, silent, live),
 * its three fixed replies, a box to try a message on it without sending
 * anything, and its latest turns beside what the team decided.
 */
class AssistantController extends Controller
{
    public function index(Brain $brain): Response
    {
        $settings = AssistantSettings::current();

        return Inertia::render('assistant/index', [
            'settings' => [
                'mode' => $settings->mode,
                'greeting' => $settings->greeting,
                'out_of_scope_reply' => $settings->outOfScopeReply,
                'handover_reply' => $settings->handoverReply,
            ],
            'brain' => [
                'driver' => config('assistant.driver'),
                'model' => $brain->model(),
                'embedding_model' => $brain->embeddingModel(),
                'healthy' => $brain->healthy(),
                'threshold' => (float) config('assistant.confidence_threshold'),
                'timeout' => (int) config('assistant.timeout_seconds'),
            ],
            'knowledge' => [
                'active' => KnowledgeEntry::query()->where('active', true)->count(),
                'embedded' => $brain->embeddingModel() === null ? null : KnowledgeEntry::query()
                    ->where('active', true)
                    ->where('embedding_model', $brain->embeddingModel())
                    ->count(),
            ],
            'summary' => [
                'live' => AssistantReport::summary('live', now()->subDays(7), now()),
                'shadow' => AssistantReport::summary('shadow', now()->subDays(7), now()),
            ],
            'runs' => AssistantReport::recent(),
            'respondIoBotActive' => Migration::current()->respondIoBotActive,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => [
                'required',
                Rule::in([AssistantSettings::OFF, AssistantSettings::SHADOW, AssistantSettings::LIVE]),
                Rule::notIn(Migration::current()->respondIoBotActive ? [AssistantSettings::LIVE] : []),
            ],
            'greeting' => ['required', 'string', 'max:1000'],
            'out_of_scope_reply' => ['required', 'string', 'max:1000'],
            'handover_reply' => ['nullable', 'string', 'max:1000'],
        ], [
            // Two assistants on one number would answer the same customer twice.
            'mode.not_in' => __('app.migration.rules.two_assistants'),
        ], [
            'greeting' => __('app.assistant.replies.greeting'),
            'out_of_scope_reply' => __('app.assistant.replies.out_of_scope'),
            'handover_reply' => __('app.assistant.replies.handover'),
        ]);

        (new AssistantSettings(
            mode: $validated['mode'],
            greeting: $validated['greeting'],
            outOfScopeReply: $validated['out_of_scope_reply'],
            handoverReply: (string) ($validated['handover_reply'] ?? ''),
        ))->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __("app.assistant.flash.{$validated['mode']}")]);

        return back();
    }

    /**
     * Reads a typed message as it would a customer's and shows what it would
     * do. Nothing is sent and no conversation is touched.
     */
    public function trial(Request $request, RunAssistant $assistant): RedirectResponse
    {
        $validated = $request->validate(['message' => ['required', 'string', 'max:1000']]);
        $run = $assistant->trial($validated['message']);

        Inertia::flash('trial', [
            'message' => $validated['message'],
            'status' => $run->status,
            'intent' => $run->intent,
            'tags' => $run->tags ?? [],
            'confidence' => $run->confidence,
            'decision' => $run->decision,
            'reply' => $run->reply,
            'sources' => KnowledgeEntry::query()->whereKey($run->sources ?? [])->pluck('question')->all(),
            'latency_ms' => $run->latency_ms,
            'error' => $run->error,
        ]);

        return back();
    }
}
