<?php

namespace App\Support;

/**
 * Text brought to one spelling before words are compared, so the same
 * word written differently still matches: vowel marks and stretched
 * letters dropped, the alef forms, taa marbuta and alef maqsura unified,
 * Arabic-Indic digits made Latin, and the definite article taken off.
 */
class ArabicText
{
    /** Words that say nothing about what a message is about. */
    private const array STOPWORDS = [
        'في', 'من', 'الي', 'علي', 'عن', 'مع', 'هل', 'ما', 'ماذا', 'كم', 'كيف', 'متي', 'اين', 'لماذا', 'هو', 'هي', 'انا', 'انت',
        'نحن', 'هذا', 'هذه', 'ذلك', 'التي', 'الذي', 'او', 'ان', 'لا', 'نعم', 'لو', 'يا', 'اذا', 'قد', 'كل', 'بعد', 'قبل', 'عند',
        'ابغي', 'ابي', 'اريد', 'ودي', 'ممكن', 'لكم', 'عندكم', 'لي', 'لك', 'بس', 'شي', 'وش', 'ايش',
        'the', 'a', 'an', 'is', 'are', 'do', 'does', 'i', 'you', 'we', 'to', 'of', 'for', 'and', 'or', 'in', 'on', 'my', 'your', 'can', 'how', 'what',
    ];

    public static function normalize(string $text): string
    {
        $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);
        $text = strtr($text, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return mb_strtolower($text);
    }

    /**
     * The meaningful words of a text, each once.
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        $words = [];

        foreach (preg_split('/[^\p{L}\p{N}]+/u', self::normalize($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (mb_strlen($word) > 4 && str_starts_with($word, 'ال')) {
                $word = mb_substr($word, 2);
            } elseif (mb_strlen($word) > 5 && (str_starts_with($word, 'وال') || str_starts_with($word, 'بال'))) {
                $word = mb_substr($word, 3);
            }

            if (mb_strlen($word) >= 2 && ! in_array($word, self::STOPWORDS, true)) {
                $words[$word] = true;
            }
        }

        return array_keys($words);
    }

    /**
     * The numbers written in a text, for checking a reply invents none.
     *
     * @return list<string>
     */
    public static function numbers(string $text): array
    {
        preg_match_all('/\d+(?:[.,]\d+)*/', self::normalize($text), $matches);

        return array_values(array_unique(array_map(fn (string $number): string => str_replace(',', '', $number), $matches[0])));
    }

    /**
     * How much of the question the passage covers, from 0 to 1.
     */
    public static function overlap(string $question, string $passage): float
    {
        $asked = self::words($question);

        if ($asked === []) {
            return 0.0;
        }

        $known = array_flip(self::words($passage));

        return count(array_filter($asked, fn (string $word): bool => isset($known[$word]))) / count($asked);
    }
}
