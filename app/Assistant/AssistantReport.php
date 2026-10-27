<?php

namespace App\Assistant;

use App\Enums\ConversationStatus;
use App\Enums\MessageSender;
use App\Models\AiRun;
use App\Models\Conversation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * How the assistant has done: what share it settled alone, what share it
 * handed over, how often the model failed, how long it took, and how often
 * its tags agreed with the team's.
 *
 * A turn is judged once the team has seen the conversation (a member wrote
 * in it, or it was closed): its tags agree when they are the conversation's
 * tags as the team left them, nothing missing and nothing extra.
 */
class AssistantReport
{
    /**
     * @return array{turns: int, settled: int|null, handed_over: int|null, failed: int, agreement: int|null, judged: int, median_ms: int|null}
     */
    public static function summary(string $mode, CarbonInterface $from, CarbonInterface $until): array
    {
        $runs = AiRun::query()
            ->with('conversation.tags', 'conversation.messages:id,conversation_id,sender,created_at')
            ->where('mode', $mode)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $until)
            ->get();

        $judged = $runs->map(fn (AiRun $run): ?bool => self::agrees($run))->filter(fn (?bool $agrees): bool => $agrees !== null);
        $latency = $runs->pluck('latency_ms')->filter()->sort()->values();
        $share = fn (int $count): ?int => $runs->isEmpty() ? null : (int) round(100 * $count / $runs->count());

        return [
            'turns' => $runs->count(),
            'settled' => $share($runs->whereIn('decision', [AiRun::ANSWERED, AiRun::GREETED, AiRun::OUT_OF_SCOPE])->count()),
            'handed_over' => $share($runs->where('decision', AiRun::HANDED_OVER)->count()),
            'failed' => $runs->where('status', 'failed')->count(),
            'agreement' => $judged->isEmpty() ? null : (int) round(100 * $judged->filter()->count() / $judged->count()),
            'judged' => $judged->count(),
            'median_ms' => $latency->isEmpty() ? null : (int) $latency->median(),
        ];
    }

    /**
     * The latest turns, newest first, as the settings page lists them.
     *
     * @return list<array<string, mixed>>
     */
    public static function recent(int $limit = 30): array
    {
        return AiRun::query()
            ->with(['conversation.contact', 'conversation.tags', 'conversation.messages:id,conversation_id,sender,created_at', 'message:id,body'])
            ->whereIn('mode', ['live', 'shadow'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (AiRun $run): array => [
                'id' => $run->id,
                'mode' => $run->mode,
                'conversation_id' => $run->conversation_id,
                'customer' => $run->conversation?->contact?->name,
                'phone' => $run->conversation?->contact?->phone,
                'message' => $run->message?->body,
                'status' => $run->status,
                'intent' => $run->intent,
                'tags' => $run->tags ?? [],
                'team_tags' => self::teamHasSeen($run) ? self::finalTags($run->conversation) : null,
                'agrees' => self::agrees($run),
                'confidence' => $run->confidence,
                'decision' => $run->decision,
                'reply' => $run->reply,
                'latency_ms' => $run->latency_ms,
                'error' => $run->error,
                'at' => $run->created_at?->toIso8601String(),
            ])
            ->all();
    }

    public static function agrees(AiRun $run): ?bool
    {
        if ($run->status !== 'ok' || ! self::teamHasSeen($run)) {
            return null;
        }

        $suggested = collect($run->tags ?? [])->sort()->values()->all();

        return $suggested === self::finalTags($run->conversation);
    }

    /**
     * @return list<string>
     */
    private static function finalTags(?Conversation $conversation): array
    {
        return ($conversation?->tags ?? new Collection)->pluck('name')->sort()->values()->all();
    }

    private static function teamHasSeen(AiRun $run): bool
    {
        $conversation = $run->conversation;

        if ($conversation === null) {
            return false;
        }

        return $conversation->status === ConversationStatus::Closed
            || $conversation->messages->contains(fn ($message): bool => $message->sender === MessageSender::Agent && $message->created_at->gte($run->created_at));
    }
}
