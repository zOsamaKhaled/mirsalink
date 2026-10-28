<?php

namespace App\Support;

use App\Enums\ConversationEventType;
use App\Enums\MessageDirection;
use App\Enums\TemplateHeader;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\Message;
use App\Models\Tag;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Turns conversations, messages and events into what the inbox screen
 * draws, with the sentences for events already in the member's language.
 */
class InboxPresenter
{
    public static function isWaitingLong(Conversation $conversation): bool
    {
        return $conversation->isOpen()
            && $conversation->awaiting_reply_since !== null
            && $conversation->awaiting_reply_since->lte(now()->subMinutes((int) config('inbox.long_wait_minutes')));
    }

    /**
     * @return array<string, mixed>
     */
    public static function listItem(Conversation $conversation): array
    {
        $latest = $conversation->latestMessage;

        return [
            'id' => $conversation->id,
            'name' => $conversation->contact?->name,
            'phone' => $conversation->contact?->phone,
            'preview' => $latest?->preview() ?? '',
            'preview_from_team' => $latest !== null && $latest->direction === MessageDirection::Outbound && ! $latest->isNote(),
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'unread' => $conversation->unread_count,
            'bot_active' => $conversation->bot_active,
            'closed' => ! $conversation->isOpen(),
            'waiting_since' => $conversation->awaiting_reply_since?->toIso8601String(),
            'waiting_long' => self::isWaitingLong($conversation),
            'tags' => self::tags($conversation->tags),
            'assignee' => $conversation->assignee === null ? null : [
                'id' => $conversation->assignee->id,
                'name' => $conversation->assignee->name,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function conversation(Conversation $conversation): array
    {
        $contact = $conversation->contact;

        return [
            ...self::listItem($conversation),
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'company' => $contact->company,
                'notes' => $contact->notes,
                'opted_out' => $contact->opted_out_at !== null,
                'deleted' => $contact->trashed(),
                'since' => $contact->created_at?->toIso8601String(),
            ],
            'window_closes_at' => $conversation->windowClosesAt()?->toIso8601String(),
            'window_open' => $conversation->windowIsOpen(),
            'first_response_at' => $conversation->first_response_at?->toIso8601String(),
        ];
    }

    /**
     * The newest lines of the thread, messages and events together, oldest
     * first as they are read.
     *
     * @return list<array<string, mixed>>
     */
    public static function thread(Conversation $conversation): array
    {
        $messages = $conversation->messages()
            ->with(['author:id,name', 'template', 'broadcast:id,name'])
            ->latest('id')
            ->limit((int) config('inbox.thread_size'))
            ->get();

        $events = $conversation->events()
            ->with('actor:id,name')
            ->when($messages->count() >= (int) config('inbox.thread_size'), fn ($query) => $query
                ->where('created_at', '>=', $messages->last()?->created_at))
            ->get();

        $names = self::memberNames($events);

        return $messages->map(fn (Message $message): array => self::message($message))
            ->concat($events->map(fn (ConversationEvent $event): array => [
                'kind' => 'event',
                'id' => 'e'.$event->id,
                'at' => $event->created_at?->toIso8601String(),
                'type' => $event->type->value,
                'text' => self::eventText($event, $names),
            ]))
            ->sortBy([['at', 'asc'], ['kind', 'desc']])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function message(Message $message): array
    {
        return [
            'kind' => 'message',
            'id' => $message->id,
            'at' => $message->created_at?->toIso8601String(),
            'direction' => $message->direction->value,
            'sender' => $message->sender->value,
            'type' => $message->type->value,
            'body' => $message->body,
            'author' => $message->author?->name,
            'media' => $message->media_path === null ? null : [
                'url' => route('inbox.media', $message),
                'name' => $message->media_name,
                'mime' => $message->media_mime,
                'size' => $message->media_size,
            ],
            'status' => $message->status?->value,
            'error' => $message->error_message,
            'broadcast' => $message->broadcast?->name,
            'template' => $message->template === null ? null : [
                'name' => $message->template->name,
                'header' => $message->template->header_type === TemplateHeader::Text
                    ? Template::withValues($message->template->header_text, array_filter([$message->template_params['header'] ?? null]))
                    : null,
                'footer' => $message->template->footer,
                'buttons' => array_column($message->template->buttons ?? [], 'text'),
            ],
        ];
    }

    /**
     * @param  Collection<int, Tag>  $tags
     * @return list<array{id: int, name: string, color: string, by_bot: bool}>
     */
    public static function tags(Collection $tags): array
    {
        return $tags->map(fn (Tag $tag): array => [
            'id' => $tag->id,
            'name' => $tag->name,
            'color' => $tag->color->value,
            'by_bot' => (bool) ($tag->pivot->by_bot ?? false),
        ])->values()->all();
    }

    /**
     * @param  array<int, string>  $names
     */
    public static function eventText(ConversationEvent $event, array $names): string
    {
        $data = $event->data ?? [];
        $actor = $event->actor?->name;
        $name = fn (string $key): string => $names[$data[$key] ?? 0] ?? __('app.inbox.events.someone');

        return match ($event->type) {
            ConversationEventType::Assigned => match (true) {
                $actor === null => __('app.inbox.events.routed', ['to' => $name('to')]),
                $event->actor_id === ($data['to'] ?? null) => __('app.inbox.events.took', ['to' => $actor]),
                default => __('app.inbox.events.assigned', ['to' => $name('to'), 'actor' => $actor]),
            },
            ConversationEventType::Transferred => __('app.inbox.events.transferred', [
                'from' => $name('from'),
                'to' => $name('to'),
                'actor' => $actor ?? __('app.inbox.events.someone'),
            ]),
            ConversationEventType::Unassigned => $actor === null
                ? __('app.inbox.events.unassigned_unavailable', ['from' => $name('from')])
                : __('app.inbox.events.unassigned', ['actor' => $actor]),
            ConversationEventType::Tagged => ($data['by_bot'] ?? false)
                ? __('app.inbox.events.tagged_by_bot', ['tag' => $data['tag'] ?? ''])
                : __('app.inbox.events.tagged', ['tag' => $data['tag'] ?? '', 'actor' => $actor ?? __('app.inbox.events.someone')]),
            ConversationEventType::Untagged => __('app.inbox.events.untagged', ['tag' => $data['tag'] ?? '', 'actor' => $actor ?? __('app.inbox.events.someone')]),
            ConversationEventType::Closed => __('app.inbox.events.closed', ['actor' => $actor ?? __('app.inbox.events.someone')]),
            ConversationEventType::Reopened => $actor === null
                ? __('app.inbox.events.reopened_by_customer')
                : __('app.inbox.events.reopened', ['actor' => $actor]),
            ConversationEventType::HandedOver => isset($data['to'])
                ? __('app.inbox.events.handed_over', ['to' => $name('to')])
                : __('app.inbox.events.handed_over_waiting'),
            ConversationEventType::AutoReplied => __('app.inbox.events.auto_replied'),
            ConversationEventType::OptedOut => __('app.inbox.events.opted_out'),
            ConversationEventType::SentToSales => __('app.inbox.events.sent_to_sales', ['actor' => $actor ?? __('app.inbox.events.someone')]),
            ConversationEventType::Classified => __('app.inbox.events.classified', [
                'tags' => implode('، ', (array) ($data['tags'] ?? [])) ?: '—',
                'confidence' => number_format((float) ($data['confidence'] ?? 0) * 100).'%',
            ]),
            ConversationEventType::AssistantToggled => __(($data['active'] ?? false) ? 'app.inbox.events.assistant_on' : 'app.inbox.events.assistant_off', [
                'actor' => $actor ?? __('app.inbox.events.someone'),
            ]),
        };
    }

    /**
     * Names of the members events point at, read in one query.
     *
     * @param  Collection<int, ConversationEvent>  $events
     * @return array<int, string>
     */
    private static function memberNames(Collection $events): array
    {
        $ids = $events->flatMap(fn (ConversationEvent $event): array => array_filter([
            $event->data['to'] ?? null,
            $event->data['from'] ?? null,
        ]))->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return User::query()->whereKey($ids)->pluck('name', 'id')->all();
    }
}
