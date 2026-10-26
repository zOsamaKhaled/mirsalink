<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The knowledge base: what the team knows, as questions and answers. Every
 * member reads it; supervisors write it and confirm entries are still true.
 * The assistant (phase 8) answers from the active entries.
 */
class KnowledgeController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'show' => in_array($request->query('show'), ['review', 'inactive'], true) ? (string) $request->query('show') : 'all',
        ];

        $entries = KnowledgeEntry::query()
            ->with('reviewer:id,name')
            ->search($filters['q'])
            ->when($filters['show'] === 'review', fn ($query) => $query->where('active', true)->needingReview())
            ->when($filters['show'] === 'inactive', fn ($query) => $query->where('active', false))
            ->orderByDesc('active')
            ->orderByDesc('updated_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (KnowledgeEntry $entry): array => [
                'id' => $entry->id,
                'question' => $entry->question,
                'answer' => $entry->answer,
                'source' => $entry->source,
                'active' => $entry->active,
                'needs_review' => $entry->needsReview(),
                'reviewed_at' => $entry->reviewed_at?->toIso8601String(),
                'reviewed_by' => $entry->reviewer?->name,
            ]);

        return Inertia::render('knowledge/index', [
            'entries' => $entries,
            'filters' => $filters,
            'counts' => [
                'all' => KnowledgeEntry::query()->count(),
                'review' => KnowledgeEntry::query()->where('active', true)->needingReview()->count(),
                'inactive' => KnowledgeEntry::query()->where('active', false)->count(),
            ],
            'reviewDays' => KnowledgeEntry::REVIEW_DAYS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $entry = new KnowledgeEntry($this->validated($request));
        $entry->forceFill([
            'created_by' => $request->user()->id,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.knowledge.flash.saved')]);

        return back();
    }

    /**
     * Saving an entry counts as reviewing it: whoever edits it has read it.
     */
    public function update(Request $request, KnowledgeEntry $entry): RedirectResponse
    {
        $entry->fill($this->validated($request))->forceFill([
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.knowledge.flash.saved')]);

        return back();
    }

    public function review(Request $request, KnowledgeEntry $entry): RedirectResponse
    {
        $entry->forceFill(['reviewed_at' => now(), 'reviewed_by' => $request->user()->id])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.knowledge.flash.reviewed')]);

        return back();
    }

    public function destroy(KnowledgeEntry $entry): RedirectResponse
    {
        $entry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.knowledge.flash.deleted')]);

        return back();
    }

    /**
     * @return array{question: string, answer: string, source: string|null, active: bool}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:5000'],
            'source' => ['nullable', 'string', 'max:500'],
            'active' => ['boolean'],
        ], [], [
            'question' => __('app.knowledge.form.question'),
            'answer' => __('app.knowledge.form.answer'),
            'source' => __('app.knowledge.form.source'),
        ]);

        return [
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'source' => $validated['source'] ?? null,
            'active' => (bool) ($validated['active'] ?? true),
        ];
    }
}
