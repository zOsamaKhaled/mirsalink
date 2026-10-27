<?php

namespace App\Assistant;

use App\Models\Setting;

/**
 * What the supervisor has set for the assistant: whether it is off (the
 * switch that stops it everywhere at once), silent (it reads and suggests,
 * and sends nothing), or answering customers; and the three fixed replies
 * it sends when it greets, can't help, or hands over.
 */
final class AssistantSettings
{
    public const string SETTING = 'assistant';

    public const string OFF = 'off';

    public const string SHADOW = 'shadow';

    public const string LIVE = 'live';

    public function __construct(
        public readonly string $mode,
        public readonly string $greeting,
        public readonly string $outOfScopeReply,
        public readonly string $handoverReply,
    ) {}

    public static function current(): self
    {
        $stored = Setting::read(self::SETTING) ?? [];
        $defaults = self::defaults();

        return new self(
            mode: in_array($stored['mode'] ?? null, [self::OFF, self::SHADOW, self::LIVE], true) ? $stored['mode'] : $defaults->mode,
            greeting: (string) ($stored['greeting'] ?? $defaults->greeting),
            outOfScopeReply: (string) ($stored['out_of_scope_reply'] ?? $defaults->outOfScopeReply),
            handoverReply: (string) ($stored['handover_reply'] ?? $defaults->handoverReply),
        );
    }

    /**
     * Off until a supervisor turns it on, with replies in Arabic.
     */
    public static function defaults(): self
    {
        return new self(
            mode: self::OFF,
            greeting: (string) trans('app.assistant.defaults.greeting', [], 'ar'),
            outOfScopeReply: (string) trans('app.assistant.defaults.out_of_scope', [], 'ar'),
            handoverReply: (string) trans('app.assistant.defaults.handover', [], 'ar'),
        );
    }

    public function save(): void
    {
        Setting::write(self::SETTING, [
            'mode' => $this->mode,
            'greeting' => $this->greeting,
            'out_of_scope_reply' => $this->outOfScopeReply,
            'handover_reply' => $this->handoverReply,
        ]);
    }

    public function isLive(): bool
    {
        return $this->mode === self::LIVE;
    }

    public function isOff(): bool
    {
        return $this->mode === self::OFF;
    }
}
