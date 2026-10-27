<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Where the move from Respond.io stands: whether Respond.io's bot still
 * answers on the number (then ours may only listen), the team's first-reply
 * time there to compare against, the last time a supervisor checked the
 * assistant's answers, and the steps of the plan done so far.
 */
final class Migration
{
    public const string SETTING = 'migration';

    public const array STEPS = ['import_contacts', 'connect_number', 'both_receive', 'week_one', 'week_two', 'week_three', 'cancel'];

    /**
     * @param  array<string, string>  $steps  step => when it was ticked
     */
    public function __construct(
        public readonly bool $respondIoBotActive,
        public readonly ?int $baselineReplyMinutes,
        public readonly ?string $pricesCheckedAt,
        public readonly array $steps,
    ) {}

    public static function current(): self
    {
        $stored = Setting::read(self::SETTING) ?? [];

        return new self(
            // Until someone says otherwise, Respond.io's bot is assumed to be answering.
            respondIoBotActive: (bool) ($stored['respond_io_bot_active'] ?? true),
            baselineReplyMinutes: isset($stored['baseline_reply_minutes']) ? (int) $stored['baseline_reply_minutes'] : null,
            pricesCheckedAt: $stored['prices_checked_at'] ?? null,
            steps: array_intersect_key((array) ($stored['steps'] ?? []), array_flip(self::STEPS)),
        );
    }

    /**
     * @param  array{respond_io_bot_active?: bool, baseline_reply_minutes?: int|null, prices_checked_at?: string|null, steps?: array<string, string>}  $changes
     */
    public static function update(array $changes): self
    {
        $current = self::current();

        Setting::write(self::SETTING, [
            'respond_io_bot_active' => $changes['respond_io_bot_active'] ?? $current->respondIoBotActive,
            'baseline_reply_minutes' => array_key_exists('baseline_reply_minutes', $changes) ? $changes['baseline_reply_minutes'] : $current->baselineReplyMinutes,
            'prices_checked_at' => array_key_exists('prices_checked_at', $changes) ? $changes['prices_checked_at'] : $current->pricesCheckedAt,
            'steps' => $changes['steps'] ?? $current->steps,
        ]);

        return self::current();
    }
}
