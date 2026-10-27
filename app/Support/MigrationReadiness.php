<?php

namespace App\Support;

use App\Assistant\AssistantReport;
use App\Enums\MessageSender;
use App\Enums\MessageType;
use App\Enums\Role;
use App\Models\AiRun;
use App\Models\Message;
use App\Models\User;
use App\Models\WebhookEvent;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The plan's six conditions for cancelling Respond.io (section 2), each
 * measured over the last two weeks where the platform can measure it, and
 * left to a supervisor's word where it can't (a wrong price can only be
 * spotted by reading the answers).
 *
 * A condition is met, not met, still gathering (too little to judge yet),
 * or waiting for a supervisor.
 */
class MigrationReadiness
{
    public const int DAYS = 14;

    /** Fewer judged conversations than this can't vouch for the assistant. */
    public const int MIN_JUDGED = 30;

    public const int MAX_DELAY_SECONDS = 300;

    public const int AGREEMENT_TARGET = 85;

    /**
     * @return list<array{key: string, status: 'met'|'not_met'|'gathering'|'manual', value: string|null}>
     */
    public static function criteria(): array
    {
        $from = now()->subDays(self::DAYS);
        $connected = config('whatsapp.gateway') === 'cloud';

        return [
            self::nothingLost($from, $connected),
            self::receivingDelay($from, $connected),
            self::agreement($from),
            self::prices($from),
            self::wholeTeam(),
            self::replyTime(),
        ];
    }

    /**
     * Every notification from Meta was read: none stuck, none waiting long.
     *
     * @return array{key: string, status: string, value: string|null}
     */
    private static function nothingLost(CarbonInterface $from, bool $connected): array
    {
        if (! $connected) {
            return ['key' => 'nothing_lost', 'status' => 'gathering', 'value' => null];
        }

        $received = WebhookEvent::query()->where('created_at', '>=', $from)->count();
        $unread = WebhookEvent::query()
            ->where('created_at', '>=', $from)
            ->whereNull('processed_at')
            ->where('created_at', '<=', now()->subMinutes(10))
            ->count();

        return [
            'key' => 'nothing_lost',
            'status' => $received === 0 ? 'gathering' : ($unread === 0 ? 'met' : 'not_met'),
            'value' => "{$unread}/{$received}",
        ];
    }

    /**
     * The longest a customer's message took from Meta to the platform. Meta
     * keeps resending while the platform is down, so an outage shows here as
     * a long delay.
     *
     * @return array{key: string, status: string, value: string|null}
     */
    private static function receivingDelay(CarbonInterface $from, bool $connected): array
    {
        $longest = null;

        if ($connected) {
            foreach (WebhookEvent::query()->where('created_at', '>=', $from)->cursor() as $event) {
                foreach ((array) ($event->payload['entry'] ?? []) as $entry) {
                    foreach ((array) ($entry['changes'] ?? []) as $change) {
                        foreach ((array) ($change['value']['messages'] ?? []) as $message) {
                            if (isset($message['timestamp'])) {
                                $delay = (int) $event->created_at->timestamp - (int) $message['timestamp'];
                                $longest = max($longest ?? 0, $delay);
                            }
                        }
                    }
                }
            }
        }

        return [
            'key' => 'receiving',
            'status' => $longest === null ? 'gathering' : ($longest <= self::MAX_DELAY_SECONDS ? 'met' : 'not_met'),
            'value' => $longest === null ? null : (string) max(0, $longest),
        ];
    }

    /**
     * The assistant's tags against the team's, silent and live together.
     *
     * @return array{key: string, status: string, value: string|null}
     */
    private static function agreement(CarbonInterface $from): array
    {
        $verdicts = AiRun::query()
            ->with(['conversation.tags', 'conversation.messages:id,conversation_id,sender,created_at'])
            ->whereIn('mode', ['live', 'shadow'])
            ->where('created_at', '>=', $from)
            ->get()
            ->map(fn (AiRun $run): ?bool => AssistantReport::agrees($run))
            ->reject(fn (?bool $agrees): bool => $agrees === null);

        $judged = $verdicts->count();
        $percent = $judged === 0 ? null : (int) round(100 * $verdicts->filter()->count() / $judged);

        return [
            'key' => 'agreement',
            'status' => $judged < self::MIN_JUDGED ? 'gathering' : ($percent >= self::AGREEMENT_TARGET ? 'met' : 'not_met'),
            'value' => $percent === null ? null : "{$percent}% · {$judged}",
        ];
    }

    /**
     * Only a person reading the answers can say none was wrong; the number
     * check stops made-up figures, and this asks for that reading.
     *
     * @return array{key: string, status: string, value: string|null}
     */
    private static function prices(CarbonInterface $from): array
    {
        $checked = Migration::current()->pricesCheckedAt;
        $recent = $checked !== null && CarbonImmutable::parse($checked)->gte($from);

        return ['key' => 'prices', 'status' => $recent ? 'met' : 'manual', 'value' => $checked];
    }

    /**
     * Every active agent answered customers from the platform this week.
     *
     * @return array{key: string, status: string, value: string|null}
     */
    private static function wholeTeam(): array
    {
        $members = User::query()->where('role', Role::Agent)->whereNull('deactivated_at')->whereNotNull('email_verified_at')->pluck('id');
        $working = Message::query()
            ->where('sender', MessageSender::Agent)
            ->where('type', '!=', MessageType::Note)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereIn('user_id', $members)
            ->distinct()
            ->count('user_id');

        return [
            'key' => 'whole_team',
            'status' => $working === 0 ? 'gathering' : ($working >= $members->count() ? 'met' : 'not_met'),
            'value' => "{$working}/{$members->count()}",
        ];
    }

    /**
     * The team's median reply time over two weeks, against the one they had
     * on Respond.io.
     *
     * @return array{key: string, status: string, value: string|null}
     */
    private static function replyTime(): array
    {
        $baseline = Migration::current()->baselineReplyMinutes;
        $timezone = WorkingHours::current()->timezone;
        $median = TeamReport::forDays(
            now($timezone)->subDays(self::DAYS - 1)->toDateString(),
            now($timezone)->toDateString(),
            $timezone,
        )->overview()['median_reply'];

        return [
            'key' => 'reply_time',
            'status' => match (true) {
                $baseline === null => 'manual',
                $median === null => 'gathering',
                default => $median <= $baseline * 60 ? 'met' : 'not_met',
            },
            'value' => $median === null ? null : (string) $median,
        ];
    }
}
