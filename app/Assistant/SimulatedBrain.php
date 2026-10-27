<?php

namespace App\Assistant;

use App\Models\KnowledgeEntry;
use App\Support\ArabicText;

/**
 * Plays the model's part until Ollama runs on the team's server, by
 * matching words: a tag fits when the message shares words with its name
 * or description, a knowledge entry answers when it covers most of the
 * question, and a handful of words mark a message as sensitive, about the
 * customer's account, or a greeting. Crude on purpose: enough to try every
 * path of the assistant, not to judge it.
 */
class SimulatedBrain implements Brain
{
    /** How much of the question an entry must cover to answer it. */
    private const float ANSWERS_AT = 0.5;

    private const array SENSITIVE = ['شكوي', 'اشتكي', 'غاضب', 'زعلان', 'نصب', 'احتيال', 'مستعجل', 'فلوسي', 'استرجاع', 'استرداد', 'موظف', 'بشري', 'اكلم', 'مسوول', 'مدير', 'complaint', 'refund', 'human', 'agent', 'manager', 'angry', 'scam'];

    private const array ACCOUNT = ['اشتراكي', 'حسابي', 'اعلاني', 'اعلانات', 'توثيق', 'توثيقي', 'فاتورتي', 'طلبي', 'account', 'subscription', 'invoice'];

    private const array GREETINGS = ['السلام', 'سلام', 'عليكم', 'مرحبا', 'هلا', 'اهلا', 'صباح', 'مساء', 'الخير', 'النور', 'hi', 'hello', 'hey'];

    public function classify(Turn $turn): Classification
    {
        $words = ArabicText::words($turn->message);

        if (array_intersect($words, self::SENSITIVE) !== []) {
            return new Classification('sensitive', $this->tagsFor($turn), 0.9, $this->language($turn->message));
        }

        if (array_intersect($words, self::ACCOUNT) !== []) {
            return new Classification('account', $this->tagsFor($turn), 0.8, $this->language($turn->message));
        }

        $greetingWords = array_map(fn (string $word): string => ArabicText::words($word)[0] ?? $word, self::GREETINGS);

        if ($words !== [] && array_diff($words, $greetingWords) === []) {
            return new Classification('greeting', [], 0.9, $this->language($turn->message));
        }

        $tags = $this->tagsFor($turn);
        $answerable = $this->best($turn) !== null;

        if ($tags === [] && ! $answerable) {
            return new Classification('out_of_scope', [], 0.7, $this->language($turn->message));
        }

        return new Classification('in_scope', $tags, $answerable ? 0.85 : 0.65, $this->language($turn->message));
    }

    public function answer(Turn $turn): ?array
    {
        $entry = $this->best($turn);

        return $entry === null ? null : ['text' => $entry->answer, 'sources' => [$entry->id]];
    }

    public function embed(string $text): ?array
    {
        return null;
    }

    public function model(): string
    {
        return 'simulator';
    }

    public function embeddingModel(): ?string
    {
        return null;
    }

    public function healthy(): bool
    {
        return true;
    }

    /**
     * Up to two tags sharing the most words with the message.
     *
     * @return list<string>
     */
    private function tagsFor(Turn $turn): array
    {
        $words = ArabicText::words($turn->message);
        $scores = [];

        foreach ($turn->tags as $tag) {
            $tagWords = ArabicText::words($tag['name'].' '.($tag['description'] ?? ''));
            $shared = collect($words)
                ->filter(fn (string $word): bool => collect($tagWords)->contains(fn (string $tagWord): bool => self::sameWord($word, $tagWord)))
                ->count();

            if ($shared > 0) {
                $scores[$tag['name']] = $shared;
            }
        }

        arsort($scores);

        return array_slice(array_keys($scores), 0, 2);
    }

    /**
     * The same word, or two forms of it (الباقة and الباقات): longer words
     * sharing their first three letters count as one.
     */
    private static function sameWord(string $a, string $b): bool
    {
        return $a === $b
            || (mb_strlen($a) >= 4 && mb_strlen($b) >= 4 && mb_substr($a, 0, 3) === mb_substr($b, 0, 3));
    }

    private function best(Turn $turn): ?KnowledgeEntry
    {
        foreach ($turn->passages as $entry) {
            if (ArabicText::overlap($turn->message, $entry->question.' '.$entry->answer) >= self::ANSWERS_AT) {
                return $entry;
            }
        }

        return null;
    }

    private function language(string $text): string
    {
        return preg_match('/\p{Arabic}/u', $text) ? 'ar' : 'en';
    }
}
