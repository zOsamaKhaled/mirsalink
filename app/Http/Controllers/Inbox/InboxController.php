<?php

namespace App\Http\Controllers\Inbox;

use App\Assistant\AssistantSettings;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\TemplateStatus;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\QuickReply;
use App\Models\Tag;
use App\Models\Template;
use App\Models\User;
use App\Rizq\ClientCard;
use App\Support\InboxPresenter;
use App\Support\TemplatePresenter;
use App\WhatsApp\Gateway;
use App\WhatsApp\SimulatorGateway;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The inbox: the list of conversations in tabs on one side, the open
 * thread beside it. An agent sees their own conversations and the
 * unassigned; a supervisor sees everyone's, and those waiting too long.
 */
class InboxController extends Controller
{
    private const array AGENT_TABS = ['mine', 'unassigned', 'closed'];

    private const array SUPERVISOR_TABS = ['mine', 'unassigned', 'waiting', 'all', 'closed'];

    public function index(Request $request): Response
    {
        return $this->render($request);
    }

    public function show(Request $request, Conversation $conversation): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $conversation->isVisibleTo($user)) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('app.inbox.flash.taken', [
                'name' => $conversation->assignee?->name ?? __('app.inbox.events.someone'),
            ])]);

            return to_route('inbox.index', $request->only(['tab', 'q', 'tag']));
        }

        if ($conversation->assigned_to === $user->id && $conversation->unread_count > 0) {
            $conversation->forceFill(['unread_count' => 0])->save();
        }

        return $this->render($request, $conversation);
    }

    private function render(Request $request, ?Conversation $selected = null): Response
    {
        $user = $request->user();
        $tabs = $user->isSupervisor() ? self::SUPERVISOR_TABS : self::AGENT_TABS;
        $tab = in_array($request->query('tab'), $tabs, true) ? (string) $request->query('tab') : 'mine';
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'tag' => $request->integer('tag') ?: null,
            // Supervisors can look at one member's conversations.
            'member' => $user->isSupervisor() ? ($request->integer('member') ?: null) : null,
        ];

        $conversations = $this->tab(Conversation::query()->visibleTo($user), $tab, $user)
            ->with(['contact', 'latestMessage', 'tags:id,name,color', 'assignee:id,name'])
            ->when($filters['q'] !== '', fn (Builder $query) => $query
                ->whereHas('contact', fn (Builder $contacts) => $contacts->search($filters['q'])))
            ->when($filters['tag'], fn (Builder $query, int $tag) => $query
                ->whereHas('tags', fn (Builder $tags) => $tags->whereKey($tag)))
            ->when($filters['member'], fn (Builder $query, int $member) => $query->where('assigned_to', $member))
            ->limit((int) config('inbox.list_size'))
            ->get()
            ->map(fn (Conversation $conversation): array => InboxPresenter::listItem($conversation));

        $selected?->load(['contact', 'latestMessage', 'tags', 'assignee:id,name']);

        return Inertia::render('inbox/index', [
            'tab' => $tab,
            'tabs' => $tabs,
            'filters' => $filters,
            'counts' => $this->counts($user),
            'conversations' => $conversations,
            'selected' => $selected === null ? null : [
                ...InboxPresenter::conversation($selected),
                'contact_tags' => InboxPresenter::tags($selected->contact->tags),
            ],
            'thread' => $selected === null ? [] : InboxPresenter::thread($selected),
            // Asked of Rizq after the page shows, and left out of the poll.
            'rizqCard' => $selected === null || $selected->contact->trashed()
                ? null
                : Inertia::defer(fn (): array => app(ClientCard::class)->forPhone((string) $selected->contact->phone)),
            'incoming' => $this->incoming($user),
            'members' => User::query()
                ->active()
                ->whereNotNull('email_verified_at')
                ->orderBy('name')
                ->get(['id', 'name', 'availability'])
                ->map(fn (User $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'availability' => $member->availability->value,
                ]),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name', 'color']),
            // Left out of the poll: neither changes while a member is replying.
            'quickReplies' => QuickReply::query()
                ->availableTo($user)
                ->orderBy('shortcut')
                ->get(['id', 'user_id', 'shortcut', 'title', 'body'])
                ->map(fn (QuickReply $reply): array => [
                    'id' => $reply->id,
                    'shortcut' => $reply->shortcut,
                    'title' => $reply->title,
                    'body' => $reply->body,
                    'shared' => $reply->isShared(),
                ]),
            'templates' => Template::query()
                ->where('status', TemplateStatus::Approved)
                ->orderBy('name')
                ->get()
                ->map(fn (Template $template): array => TemplatePresenter::present($template)),
            'pollSeconds' => (int) config('inbox.poll_seconds'),
            'assistantLive' => AssistantSettings::current()->isLive(),
            'longWaitMinutes' => (int) config('inbox.long_wait_minutes'),
            'simulator' => $user->can('manage-team') && app(Gateway::class) instanceof SimulatorGateway,
        ]);
    }

    /**
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    private function tab(Builder $query, string $tab, User $user): Builder
    {
        return match ($tab) {
            'mine' => $query->where('status', ConversationStatus::Open)->where('assigned_to', $user->id)->orderByDesc('last_message_at'),
            'unassigned' => $query->where('status', ConversationStatus::Open)->whereNull('assigned_to')->orderBy('awaiting_reply_since')->orderByDesc('last_message_at'),
            'waiting' => $this->waiting($query)->orderBy('awaiting_reply_since'),
            'all' => $query->where('status', ConversationStatus::Open)->orderByDesc('last_message_at'),
            'closed' => $query->where('status', ConversationStatus::Closed)->orderByDesc('closed_at'),
        };
    }

    /**
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    private function waiting(Builder $query): Builder
    {
        return $query->where('status', ConversationStatus::Open)
            ->whereNotNull('awaiting_reply_since')
            ->where('awaiting_reply_since', '<=', now()->subMinutes((int) config('inbox.long_wait_minutes')));
    }

    /**
     * @return array<string, int>
     */
    private function counts(User $user): array
    {
        $open = fn (): Builder => Conversation::query()->visibleTo($user)->where('status', ConversationStatus::Open);

        $counts = [
            'mine' => $open()->where('assigned_to', $user->id)->count(),
            'unassigned' => $open()->whereNull('assigned_to')->count(),
            'mine_unread' => $open()->where('assigned_to', $user->id)->where('unread_count', '>', 0)->count(),
        ];

        if ($user->isSupervisor()) {
            $counts['waiting'] = $this->waiting(Conversation::query())->count();
            $counts['all'] = $open()->count();
        }

        return $counts;
    }

    /**
     * The newest customer messages this member should hear about, for the
     * browser notification: in their own conversations and the unassigned.
     *
     * @return list<array<string, mixed>>
     */
    private function incoming(User $user): array
    {
        return Message::query()
            ->where('direction', MessageDirection::Inbound)
            ->whereHas('conversation', fn (Builder $query) => $query
                ->where('status', ConversationStatus::Open)
                ->where(fn (Builder $query) => $query->where('assigned_to', $user->id)->orWhereNull('assigned_to')))
            ->with('conversation.contact')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Message $message): array => [
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'name' => $message->conversation->contact?->name ?? $message->conversation->contact?->phone,
                'preview' => $message->preview(),
            ])
            ->all();
    }
}
