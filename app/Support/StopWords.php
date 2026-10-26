<?php

namespace App\Support;

/**
 * Whether a customer's message asks to stop broadcasts: the whole message
 * is one of the stop words, however it was spelt (vowel marks, stretched
 * letters, capitals and spaces aside).
 */
class StopWords
{
    public static function matches(?string $text): bool
    {
        $said = self::normalize((string) $text);

        if ($said === '') {
            return false;
        }

        foreach ((array) config('broadcasts.stop_words') as $word) {
            if (self::normalize((string) $word) === $said) {
                return true;
            }
        }

        return false;
    }

    public static function normalize(string $text): string
    {
        $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);
        $text = (string) preg_replace('/[\s.!؟?،,]+/u', ' ', $text);

        return mb_strtolower(trim($text));
    }
}
