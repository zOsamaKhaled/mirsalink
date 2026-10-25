<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonInterface;

/**
 * When the team works, in its own time zone, and what a customer who writes
 * outside those hours is told.
 *
 * Days are keyed 0 (Sunday) to 6 (Saturday), as Carbon counts them; each is
 * open or closed with a from and a to in 24-hour "HH:MM". The inbox (phase 4)
 * asks isOpenAt() before it sends the out-of-hours reply.
 */
final class WorkingHours
{
    public const string SETTING = 'working_hours';

    /**
     * @param  array<int, array{open: bool, from: string, to: string}>  $days
     */
    public function __construct(
        public readonly string $timezone,
        public readonly array $days,
        public readonly bool $autoReplyEnabled,
        public readonly string $autoReplyMessage,
    ) {}

    public static function current(): self
    {
        $stored = Setting::read(self::SETTING);

        return $stored === null ? self::defaults() : self::fromArray($stored);
    }

    /**
     * A Saudi working week until the supervisor sets their own: Sunday to
     * Thursday, nine to five, Riyadh time, with the reply switched off.
     */
    public static function defaults(): self
    {
        $days = [];

        foreach (range(0, 6) as $day) {
            $days[$day] = ['open' => $day <= 4, 'from' => '09:00', 'to' => '17:00'];
        }

        return new self(
            timezone: 'Asia/Riyadh',
            days: $days,
            autoReplyEnabled: false,
            autoReplyMessage: (string) trans('app.team.hours.default_reply', [], 'ar'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $defaults = self::defaults();
        $days = [];

        foreach (range(0, 6) as $day) {
            $stored = $data['days'][$day] ?? $defaults->days[$day];
            $days[$day] = [
                'open' => (bool) $stored['open'],
                'from' => (string) $stored['from'],
                'to' => (string) $stored['to'],
            ];
        }

        return new self(
            timezone: (string) ($data['timezone'] ?? $defaults->timezone),
            days: $days,
            autoReplyEnabled: (bool) ($data['auto_reply_enabled'] ?? false),
            autoReplyMessage: (string) ($data['auto_reply_message'] ?? $defaults->autoReplyMessage),
        );
    }

    /**
     * @return array{timezone: string, days: array<int, array{open: bool, from: string, to: string}>, auto_reply_enabled: bool, auto_reply_message: string}
     */
    public function toArray(): array
    {
        return [
            'timezone' => $this->timezone,
            'days' => $this->days,
            'auto_reply_enabled' => $this->autoReplyEnabled,
            'auto_reply_message' => $this->autoReplyMessage,
        ];
    }

    public function save(): void
    {
        Setting::write(self::SETTING, $this->toArray());
    }

    /**
     * Whether the moment falls inside the team's hours, read in the team's
     * time zone rather than the server's.
     */
    public function isOpenAt(CarbonInterface $moment): bool
    {
        $local = $moment->copy()->setTimezone($this->timezone);
        $day = $this->days[$local->dayOfWeek];

        if (! $day['open']) {
            return false;
        }

        $time = $local->format('H:i');

        return $time >= $day['from'] && $time < $day['to'];
    }
}
