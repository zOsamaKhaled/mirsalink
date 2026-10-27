<?php

namespace App\Assistant;

use App\Models\KnowledgeEntry;
use App\Support\ArabicText;
use Illuminate\Support\Collection;

/**
 * The active knowledge entries closest to a question. By meaning when the
 * brain makes embeddings and the entries carry ones from the same model;
 * by shared words otherwise (the simulator, or entries not embedded yet).
 */
class KnowledgeSearch
{
    /** The share of the question an entry must cover when matching words. */
    private const float WORD_MATCH = 0.3;

    public function __construct(private Brain $brain) {}

    /**
     * @return list<KnowledgeEntry>
     */
    public function closest(string $question): array
    {
        $entries = KnowledgeEntry::query()->where('active', true)->get();

        if ($entries->isEmpty() || trim($question) === '') {
            return [];
        }

        $model = $this->brain->embeddingModel();
        $vector = null;

        if ($model !== null && $entries->contains(fn (KnowledgeEntry $entry): bool => $entry->embedding_model === $model)) {
            try {
                $vector = $this->brain->embed($question);
            } catch (AssistantUnavailable) {
                $vector = null;
            }
        }

        $scored = $vector === null
            ? $this->byWords($entries, $question)
            : $this->byMeaning($entries->where('embedding_model', $model), $vector);

        return $scored->sortByDesc('score')
            ->take((int) config('assistant.sources'))
            ->pluck('entry')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, KnowledgeEntry>  $entries
     * @return Collection<int, array{entry: KnowledgeEntry, score: float}>
     */
    private function byWords(Collection $entries, string $question): Collection
    {
        return $entries
            ->map(fn (KnowledgeEntry $entry): array => [
                'entry' => $entry,
                'score' => ArabicText::overlap($question, $entry->question.' '.$entry->answer),
            ])
            ->filter(fn (array $hit): bool => $hit['score'] >= self::WORD_MATCH);
    }

    /**
     * @param  Collection<int, KnowledgeEntry>  $entries
     * @param  list<float>  $vector
     * @return Collection<int, array{entry: KnowledgeEntry, score: float}>
     */
    private function byMeaning(Collection $entries, array $vector): Collection
    {
        return $entries
            ->map(fn (KnowledgeEntry $entry): array => [
                'entry' => $entry,
                'score' => self::cosine($vector, (array) $entry->embedding),
            ])
            ->filter(fn (array $hit): bool => $hit['score'] >= (float) config('assistant.min_similarity'));
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public static function cosine(array $a, array $b): float
    {
        if ($a === [] || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = $normA = $normB = 0.0;

        foreach ($a as $i => $value) {
            $dot += $value * $b[$i];
            $normA += $value ** 2;
            $normB += $b[$i] ** 2;
        }

        return $normA === 0.0 || $normB === 0.0 ? 0.0 : $dot / (sqrt($normA) * sqrt($normB));
    }
}
