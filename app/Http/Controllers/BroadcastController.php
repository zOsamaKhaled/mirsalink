<?php

namespace App\Http\Controllers;

use App\Actions\Broadcasts\LaunchBroadcast;
use App\Actions\Broadcasts\PrepareRecipients;
use App\Actions\Broadcasts\RunBroadcasts;
use App\Enums\AudienceType;
use App\Enums\BroadcastStatus;
use App\Enums\RecipientStatus;
use App\Enums\TemplateStatus;
use App\Exceptions\BroadcastRuleException;
use App\Http\Requests\Broadcasts\SaveBroadcastRequest;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\Template;
use App\Support\BroadcastQuota;
use App\Support\TemplatePresenter;
use App\WhatsApp\Gateway;
use App\WhatsApp\SimulatorGateway;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Campaigns, for supervisors: write one, see who it will reach and who is
 * left out before it goes, send it now or later, and follow its results.
 */
class BroadcastController extends Controller
{
    private const array OUTCOMES = ['all', 'queued', 'sent', 'delivered', 'read', 'replied', 'failed', 'skipped'];

    public function index(): Response
    {
        $broadcasts = Broadcast::query()
            ->with('template:id,name')
            ->latest('id')
            ->paginate(20)
            ->through(fn (Broadcast $broadcast): array => [
                'id' => $broadcast->id,
                'name' => $broadcast->name,
                'status' => $broadcast->status->value,
                'template' => $broadcast->template?->name,
                'audience_type' => $broadcast->audience_type->value,
                'scheduled_at' => $broadcast->scheduled_at?->toIso8601String(),
                'started_at' => $broadcast->started_at?->toIso8601String(),
                'completed_at' => $broadcast->completed_at?->toIso8601String(),
                'created_at' => $broadcast->created_at?->toIso8601String(),
                'stats' => $broadcast->stats(),
            ]);

        return Inertia::render('broadcasts/index', [
            'broadcasts' => $broadcasts,
            'quota' => $this->quota(),
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(SaveBroadcastRequest $request, PrepareRecipients $prepare): RedirectResponse
    {
        $broadcast = new Broadcast(['created_by' => $request->user()->id, 'status' => BroadcastStatus::Draft]);
        $this->fill($broadcast, $request);
        $prepare->handle($broadcast);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.broadcasts.flash.prepared')]);

        return to_route('broadcasts.show', $broadcast);
    }

    public function show(Request $request, Broadcast $broadcast): Response
    {
        $broadcast->load(['template', 'creator:id,name']);
        $outcome = in_array($request->query('outcome'), self::OUTCOMES, true) ? (string) $request->query('outcome') : 'all';

        $recipients = $broadcast->recipients()
            ->with('message:id,status,error_message')
            ->tap(fn ($query) => $this->outcome($query, $outcome))
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (BroadcastRecipient $recipient): array => [
                'id' => $recipient->id,
                'name' => $recipient->name,
                'phone' => $recipient->phone,
                'status' => $recipient->status->value,
                'message_status' => $recipient->message?->status?->value,
                'skip_reason' => $recipient->skip_reason,
                'error' => $recipient->error ?? $recipient->message?->error_message,
                'replied' => $recipient->replied_at !== null,
                'sent_at' => $recipient->sent_at?->toIso8601String(),
            ]);

        $sample = $broadcast->recipients()->where('status', RecipientStatus::Queued)->orderBy('id')->first()
            ?? $broadcast->recipients()->whereNotNull('params')->orderBy('id')->first();

        return Inertia::render('broadcasts/show', [
            'broadcast' => [
                'id' => $broadcast->id,
                'name' => $broadcast->name,
                'status' => $broadcast->status->value,
                'audience_type' => $broadcast->audience_type->value,
                'audience_tags' => Tag::query()->whereKey($broadcast->audience_tags ?? [])->orderBy('name')->get(['id', 'name', 'color']),
                'scheduled_at' => $broadcast->scheduled_at?->toIso8601String(),
                'started_at' => $broadcast->started_at?->toIso8601String(),
                'completed_at' => $broadcast->completed_at?->toIso8601String(),
                'created_by' => $broadcast->creator?->name,
                'template' => $broadcast->template === null ? null : TemplatePresenter::present($broadcast->template),
            ],
            'sample' => $sample?->params,
            'stats' => $broadcast->stats(),
            'skipReasons' => $broadcast->recipients()
                ->where('status', RecipientStatus::Skipped)
                ->toBase()
                ->selectRaw('skip_reason, count(*) as total')
                ->groupBy('skip_reason')
                ->pluck('total', 'skip_reason'),
            'recipients' => $recipients,
            'outcome' => $outcome,
            'quota' => $this->quota(),
            'simulator' => app(Gateway::class) instanceof SimulatorGateway,
        ]);
    }

    public function edit(Broadcast $broadcast): Response|RedirectResponse
    {
        if (! $broadcast->status->isEditable()) {
            return $this->refuse('locked');
        }

        return $this->form($broadcast);
    }

    /**
     * Changing a campaign prepares its list again and returns it to draft:
     * the new count deserves a look before it goes.
     */
    public function update(SaveBroadcastRequest $request, Broadcast $broadcast, PrepareRecipients $prepare): RedirectResponse
    {
        if (! $broadcast->status->isEditable()) {
            return $this->refuse('locked');
        }

        $broadcast->forceFill(['status' => BroadcastStatus::Draft, 'scheduled_at' => null]);
        $this->fill($broadcast, $request);
        $prepare->handle($broadcast);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.broadcasts.flash.prepared')]);

        return to_route('broadcasts.show', $broadcast);
    }

    public function launch(Request $request, Broadcast $broadcast, LaunchBroadcast $launch): RedirectResponse
    {
        $validated = $request->validate(['at' => ['nullable', 'date']]);
        $at = isset($validated['at']) ? Carbon::parse($validated['at']) : null;

        try {
            $launch->handle($broadcast, $at);
        } catch (BroadcastRuleException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __($at === null ? 'app.broadcasts.flash.started' : 'app.broadcasts.flash.scheduled')]);

        return back();
    }

    public function pause(Broadcast $broadcast): RedirectResponse
    {
        if (in_array($broadcast->status, [BroadcastStatus::Sending, BroadcastStatus::Scheduled], true)) {
            $broadcast->forceFill(['status' => BroadcastStatus::Paused])->save();
        }

        return back();
    }

    public function resume(Broadcast $broadcast): RedirectResponse
    {
        if ($broadcast->status === BroadcastStatus::Paused) {
            $broadcast->forceFill([
                'status' => BroadcastStatus::Sending,
                'started_at' => $broadcast->started_at ?? now(),
            ])->save();
        }

        return back();
    }

    public function cancel(Broadcast $broadcast): RedirectResponse
    {
        if (! in_array($broadcast->status, [BroadcastStatus::Completed, BroadcastStatus::Cancelled], true)) {
            $broadcast->recipients()->where('status', RecipientStatus::Queued)->update(['status' => RecipientStatus::Cancelled->value]);
            $broadcast->forceFill(['status' => BroadcastStatus::Cancelled, 'completed_at' => now()])->save();
        }

        return back();
    }

    public function destroy(Broadcast $broadcast): RedirectResponse
    {
        if (in_array($broadcast->status, [BroadcastStatus::Sending, BroadcastStatus::Paused], true)) {
            return $this->refuse('delete_running');
        }

        if ($broadcast->audience_file !== null) {
            Storage::disk('local')->delete($broadcast->audience_file);
        }

        $broadcast->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.broadcasts.flash.deleted')]);

        return to_route('broadcasts.index');
    }

    /**
     * The simulator's stand-in for the scheduler: this minute's batch, now.
     */
    public function tick(RunBroadcasts $run): RedirectResponse
    {
        abort_unless(app(Gateway::class) instanceof SimulatorGateway, 404);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('app.broadcasts.flash.tick', ['count' => $run->handle()])]);

        return back();
    }

    private function form(?Broadcast $broadcast): Response
    {
        return Inertia::render('broadcasts/form', [
            'broadcast' => $broadcast === null ? null : [
                'id' => $broadcast->id,
                'name' => $broadcast->name,
                'template_id' => $broadcast->template_id,
                'audience_type' => $broadcast->audience_type->value,
                'audience_tags' => $broadcast->audience_tags ?? [],
                'has_file' => $broadcast->audience_file !== null,
                'file_columns' => $broadcast->audience_file !== null && Storage::disk('local')->exists($broadcast->audience_file)
                    ? PrepareRecipients::headingsOf(Storage::disk('local')->path($broadcast->audience_file))
                    : [],
                'variables' => $broadcast->variables ?? ['body' => []],
            ],
            'templates' => Template::query()
                ->where('status', TemplateStatus::Approved)
                ->orderBy('name')
                ->get()
                ->map(fn (Template $template): array => TemplatePresenter::present($template)),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name', 'color']),
            'reachable' => Contact::query()->whereNull('opted_out_at')->count(),
        ]);
    }

    private function fill(Broadcast $broadcast, SaveBroadcastRequest $request): void
    {
        $audience = AudienceType::from($request->string('audience_type')->toString());
        $file = $request->file('file');

        if ($file !== null) {
            if ($broadcast->audience_file !== null) {
                Storage::disk('local')->delete($broadcast->audience_file);
            }

            $broadcast->audience_file = $file->storeAs('broadcasts', Str::uuid()->toString().'.csv', 'local');
        }

        $variables = (array) $request->validated('variables', []);

        $broadcast->fill([
            'name' => $request->validated('name'),
            'template_id' => $request->validated('template_id'),
            'audience_type' => $audience,
            'audience_tags' => $audience === AudienceType::Tags ? array_map('intval', (array) $request->validated('audience_tags')) : null,
            'variables' => [
                'body' => array_values((array) ($variables['body'] ?? [])),
                'header' => $variables['header'] ?? null,
                'button' => $variables['button'] ?? null,
            ],
        ])->save();
    }

    /**
     * @param  Builder<BroadcastRecipient>  $query
     */
    private function outcome(Builder $query, string $outcome): void
    {
        match ($outcome) {
            'queued' => $query->where('status', RecipientStatus::Queued),
            'sent' => $query->where('status', RecipientStatus::Sent),
            'delivered' => $query->whereHas('message', fn ($message) => $message->whereIn('status', ['delivered', 'read'])),
            'read' => $query->whereHas('message', fn ($message) => $message->where('status', 'read')),
            'replied' => $query->whereNotNull('replied_at'),
            'failed' => $query->where(fn ($failed) => $failed
                ->where('status', RecipientStatus::Failed)
                ->orWhereHas('message', fn ($message) => $message->where('status', 'failed'))),
            'skipped' => $query->whereIn('status', [RecipientStatus::Skipped, RecipientStatus::Cancelled]),
            default => null,
        };
    }

    /**
     * @return array{per_minute: int, daily_limit: int, remaining_today: int}
     */
    private function quota(): array
    {
        return [
            'per_minute' => (int) config('broadcasts.per_minute'),
            'daily_limit' => min(BroadcastQuota::dailyLimit(), 9_999_999),
            'remaining_today' => BroadcastQuota::remainingToday(),
        ];
    }

    private function refuse(string $reason): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'info', 'message' => __("app.broadcasts.rules.{$reason}")]);

        return back();
    }
}
