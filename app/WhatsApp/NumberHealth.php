<?php

namespace App\WhatsApp;

use App\Models\Setting;

/**
 * What Meta last said about the number: its quality rating (GREEN, YELLOW,
 * RED) and its messaging tier, the number of customers it may start
 * conversations with in 24 hours. Campaigns keep under that tier.
 */
final class NumberHealth
{
    public const string SETTING = 'whatsapp_number';

    private const array TIERS = [
        'TIER_50' => 50,
        'TIER_250' => 250,
        'TIER_1K' => 1000,
        'TIER_2K' => 2000,
        'TIER_10K' => 10000,
        'TIER_100K' => 100000,
        'TIER_UNLIMITED' => PHP_INT_MAX,
    ];

    /**
     * @return array{quality: string|null, tier: string|null, event: string|null, at: string|null}
     */
    public static function current(): array
    {
        $stored = Setting::read(self::SETTING) ?? [];

        return [
            'quality' => $stored['quality'] ?? null,
            'tier' => $stored['tier'] ?? null,
            'event' => $stored['event'] ?? null,
            'at' => $stored['at'] ?? null,
        ];
    }

    /**
     * @param  array{quality?: string|null, tier?: string|null, event?: string|null}  $changes
     */
    public static function record(array $changes): void
    {
        Setting::write(self::SETTING, [
            ...self::current(),
            ...array_filter($changes, fn ($value): bool => $value !== null),
            'at' => now()->toIso8601String(),
        ]);
    }

    /** The tier's daily limit, or null before Meta has told us one. */
    public static function dailyLimit(): ?int
    {
        $tier = self::current()['tier'];

        return $tier === null ? null : (self::TIERS[strtoupper($tier)] ?? null);
    }
}
