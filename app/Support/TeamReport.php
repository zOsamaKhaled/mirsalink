<?php

namespace App\Support;

use App\Enums\BroadcastStatus;
use App\Enums\ConversationEventType;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageSender;
use App\Enums\MessageType;
use App\Models\Broadcast;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The team's figures for a period of days in the team's time zone.
 *
 * A reply time runs from the moment a customer starts waiting (their first
 * message since the team last wrote) to the next message a member sends;
 * the out-of-hours reply and campaigns don't stop that clock, since the
 * customer is still waiting for a person. A close time runs from when the
 * conversation last opened (created, or reopened) to when it was closed.
 */
class TeamReport
{
    /** How far back a wait may have started and still count when answered in the period. */
    private const int LOOKBACK_DAYS = 7;

    /** @var list<array{user_id: int, seconds: int}>|null */
    private ?array $replies = null;

    /** @var list<array{user_id: int|null, seconds: int}>|null */
    private ?array $closures = null;

    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $until,
        public readonly string $timezone,
    ) {}

    /**
     * From the first day's start to the end of the last, read in the zone.
     */
    public static function forDays(string $firstDay, string $lastDay, string $timezone): self
    {
        return new self(
            CarbonImmutable::parse($firstDay, $timezone)->startOfDay()->utc(),
            CarbonImmutable::parse($lastDay, $timezone)->addDay()->startOfDay()->utc(),
            $timezone,
        );
    }

    /**
     * @return array<string, int|float|null>
     */
    public function overview(): array
    {
        $replies = array_column($this->replies(), 'seconds');
        $closures = array_column($this->closures(), 'seconds');
        $target = (int) config('inbox.long_wait_minutes') * 60;

        return [
            'customers' => (int) $this->inbound()->distinct()->count('conversation_id'),
            'inbound' => $this->inbound()->count(),
            'replies' => $this->agentMessages()->count(),
            'average_reply' => self::average($replies),
            'median_reply' => self::median($replies),
            'within_target' => $replies === [] ? null : round(100 * count(array_filter($replies, fn (int $seconds): bool => $seconds <= $target)) / count($replies)),
            'closed' => count($closures),
            'average_close' => self::average($closures),
            'open_now' => Conversation::query()->where('status', ConversationStatus::Open)->count(),
            'unassigned_now' => Conversation::query()->where('status', ConversationStatus::Open)->whereNull('assigned_to')->count(),
        ];
    }

    /**
     * One row per member who works conversations, or did in the period.
     *
     * @return list<array<string, int|string|float|null>>
     */
    public function members(): array
    {
        $replies = collect($this->replies())->groupBy('user_id');
        $closures = collect($this->closures())->groupBy('user_id');
        $sent = $this->agentMessages()
            ->toBase()
            ->select('user_id', DB::raw('count(*) as messages'), DB::raw('count(distinct conversation_id) as conversations'))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');
        $open = Conversation::query()
            ->where('status', ConversationStatus::Open)
            ->whereNotNull('assigned_to')
            ->toBase()
            ->select('assigned_to', DB::raw('count(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $ids = collect([...$sent->keys(), ...$closures->keys(), ...$open->keys()])->filter()->unique();

        return User::query()
            ->where(fn ($query) => $query
                ->where(fn ($working) => $working->whereNull('deactivated_at')->whereNotNull('email_verified_at'))
                ->orWhereIn('id', $ids))
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($replies, $closures, $sent, $open): array {
                $times = $replies->get($user->id, collect())->pluck('seconds')->all();
                $closes = $closures->get($user->id, collect())->pluck('seconds')->all();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'active' => $user->isActive(),
                    'messages' => (int) ($sent->get($user->id)->messages ?? 0),
                    'conversations' => (int) ($sent->get($user->id)->conversations ?? 0),
                    'average_reply' => self::average($times),
                    'median_reply' => self::median($times),
                    'closed' => count($closes),
                    'average_close' => self::average($closes),
                    'open_now' => (int) ($open[$user->id] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Customers who wrote in the period, by their conversation's tags; one
     * customer counts under each of their tags.
     *
     * @return array{tags: list<array{id: int, name: string, color: string, customers: int}>, untagged: int}
     */
    public function tags(): array
    {
        $writers = $this->inbound()->select('conversation_id')->distinct();

        $tags = DB::table('conversation_tag')
            ->join('tags', 'tags.id', '=', 'conversation_tag.tag_id')
            ->whereIn('conversation_tag.conversation_id', $writers)
            ->select('tags.id', 'tags.name', 'tags.color', DB::raw('count(distinct conversation_tag.conversation_id) as customers'))
            ->groupBy('tags.id', 'tags.name', 'tags.color')
            ->orderByDesc('customers')
            ->get()
            ->map(fn ($row): array => ['id' => (int) $row->id, 'name' => $row->name, 'color' => $row->color, 'customers' => (int) $row->customers])
            ->all();

        $tagged = DB::table('conversation_tag')->whereIn('conversation_id', $writers)->distinct()->count('conversation_id');

        return ['tags' => $tags, 'untagged' => max(0, (int) $this->inbound()->distinct()->count('conversation_id') - $tagged)];
    }

    /**
     * Customer messages by day of the week (0 = Sunday) and hour, in the
     * team's zone: when the team is needed most.
     *
     * @return list<list<int>>
     */
    public function hours(): array
    {
        $grid = array_fill(0, 7, array_fill(0, 24, 0));

        foreach ($this->inbound()->toBase()->select('created_at')->cursor() as $row) {
            $local = CarbonImmutable::parse($row->created_at, 'UTC')->setTimezone($this->timezone);
            $grid[$local->dayOfWeek][$local->hour]++;
        }

        return $grid;
    }

    /**
     * Customer messages and team replies on each day of the period.
     *
     * @return list<array{day: string, inbound: int, replies: int}>
     */
    public function days(): array
    {
        $days = [];

        for ($day = $this->from->setTimezone($this->timezone); $day->lt($this->until); $day = $day->addDay()) {
            $days[$day->toDateString()] = ['day' => $day->toDateString(), 'inbound' => 0, 'replies' => 0];
        }

        $count = function ($query, string $key) use (&$days): void {
            foreach ($query->toBase()->select('created_at')->cursor() as $row) {
                $date = CarbonImmutable::parse($row->created_at, 'UTC')->setTimezone($this->timezone)->toDateString();

                if (isset($days[$date])) {
                    $days[$date][$key]++;
                }
            }
        };

        $count($this->inbound(), 'inbound');
        $count($this->agentMessages(), 'replies');

        return array_values($days);
    }

    /**
     * Campaigns that went out in the period, with their results.
     *
     * @return list<array<string, mixed>>
     */
    public function campaigns(): array
    {
        return Broadcast::query()
            ->with('template:id,name')
            ->whereNotIn('status', [BroadcastStatus::Draft, BroadcastStatus::Scheduled])
            ->tap(fn (Builder $query) => $this->inPeriod($query, 'started_at'))
            ->orderByDesc('started_at')
            ->get()
            ->map(fn (Broadcast $broadcast): array => [
                'id' => $broadcast->id,
                'name' => $broadcast->name,
                'status' => $broadcast->status->value,
                'template' => $broadcast->template?->name,
                'started_at' => $broadcast->started_at?->toIso8601String(),
                'stats' => $broadcast->stats(),
            ])
            ->all();
    }

    /**
     * Replies by member in the period, each with how long the customer had waited.
     *
     * @return list<array{user_id: int, seconds: int}>
     */
    public function replies(): array
    {
        if ($this->replies !== null) {
            return $this->replies;
        }

        $samples = [];
        $conversation = null;
        $waitingSince = null;

        $messages = DB::table('messages')
            ->where('created_at', '>=', $this->from->subDays(self::LOOKBACK_DAYS))
            ->where('created_at', '<', $this->until)
            ->where('type', '!=', MessageType::Note->value)
            ->orderBy('conversation_id')
            ->orderBy('id')
            ->select('conversation_id', 'direction', 'sender', 'user_id', 'created_at');

        foreach ($messages->cursor() as $message) {
            if ($message->conversation_id !== $conversation) {
                $conversation = $message->conversation_id;
                $waitingSince = null;
            }

            $at = CarbonImmutable::parse($message->created_at, 'UTC');

            if ($message->direction === MessageDirection::Inbound->value) {
                $waitingSince ??= $at;

                continue;
            }

            if ($message->sender !== MessageSender::Agent->value || $waitingSince === null) {
                continue;
            }

            if ($at->gte($this->from) && $message->user_id !== null) {
                $samples[] = ['user_id' => (int) $message->user_id, 'seconds' => (int) $waitingSince->diffInSeconds($at)];
            }

            $waitingSince = null;
        }

        return $this->replies = $samples;
    }

    /**
     * Conversations closed in the period, by who closed them and how long
     * they had been open.
     *
     * @return list<array{user_id: int|null, seconds: int}>
     */
    public function closures(): array
    {
        if ($this->closures !== null) {
            return $this->closures;
        }

        $closes = ConversationEvent::query()
            ->with('conversation:id,created_at')
            ->where('type', ConversationEventType::Closed)
            ->tap(fn (Builder $query) => $this->inPeriod($query))
            ->get();

        $reopenings = ConversationEvent::query()
            ->where('type', ConversationEventType::Reopened)
            ->whereIn('conversation_id', $closes->pluck('conversation_id')->unique())
            ->where('created_at', '<', $this->until)
            ->orderBy('created_at')
            ->get()
            ->groupBy('conversation_id');

        return $this->closures = $closes->map(function (ConversationEvent $close) use ($reopenings): array {
            $opened = $reopenings->get($close->conversation_id, collect())
                ->filter(fn (ConversationEvent $reopen): bool => $reopen->created_at->lte($close->created_at))
                ->last()?->created_at
                ?? $close->conversation?->created_at
                ?? $close->created_at;

            return ['user_id' => $close->actor_id, 'seconds' => (int) $opened->diffInSeconds($close->created_at)];
        })->values()->all();
    }

    /**
     * @return Builder<Message>
     */
    private function inbound(): Builder
    {
        return $this->inPeriod(Message::query()->where('direction', MessageDirection::Inbound));
    }

    /**
     * What members wrote to customers: their replies, not their notes.
     *
     * @return Builder<Message>
     */
    private function agentMessages(): Builder
    {
        return $this->inPeriod(Message::query()
            ->where('sender', MessageSender::Agent)
            ->where('type', '!=', MessageType::Note));
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function inPeriod(Builder $query, string $column = 'created_at'): Builder
    {
        return $query->where($column, '>=', $this->from)->where($column, '<', $this->until);
    }

    /**
     * @param  list<int>  $values
     */
    private static function average(array $values): ?int
    {
        return $values === [] ? null : (int) round(array_sum($values) / count($values));
    }

    /**
     * @param  list<int>  $values
     */
    private static function median(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1 ? $values[$middle] : (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }
}
