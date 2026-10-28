<?php

namespace App\Rizq;

use Illuminate\Support\Facades\Cache;

/**
 * Who a customer is on Rizq, for the card beside the conversation: their
 * account's kind and name, package, verification, and whether sales has
 * them and who. Kept for a few minutes, so moving between conversations
 * does not ask Rizq again each time; adding the customer to sales clears it.
 */
class ClientCard
{
    private const int CACHE_MINUTES = 5;

    public function __construct(private RizqClient $rizq) {}

    /**
     * @return array{state: 'not_linked'}|array{state: 'unavailable'}|array{state: 'found', account: array<string, mixed>|null, sales: array<string, mixed>|null}
     */
    public function forPhone(string $phone): array
    {
        if (! $this->rizq->configured()) {
            return ['state' => 'not_linked'];
        }

        $cached = Cache::get(self::key($phone));

        if (is_array($cached)) {
            return $cached;
        }

        $answer = $this->rizq->ask((string) config('rizq.paths.card'), ['phone' => $phone]);

        // Not kept: the next conversation opened asks again.
        if ($answer === null) {
            return ['state' => 'unavailable'];
        }

        $card = [
            'state' => 'found',
            'account' => is_array($answer['account'] ?? null) ? $answer['account'] : null,
            'sales' => is_array($answer['sales'] ?? null) ? $answer['sales'] : null,
        ];

        Cache::put(self::key($phone), $card, now()->addMinutes(self::CACHE_MINUTES));

        return $card;
    }

    public static function forget(string $phone): void
    {
        Cache::forget(self::key($phone));
    }

    private static function key(string $phone): string
    {
        return 'rizq-card:'.$phone;
    }
}
