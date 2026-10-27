<?php

namespace App\Actions\Assistant;

use App\Assistant\AssistantUnavailable;
use App\Assistant\Brain;
use App\Models\KnowledgeEntry;

/**
 * Stores an entry's meaning as numbers, when the brain makes them. An entry
 * the model could not embed is still found, by its words, until the next
 * try (saving it again, or php artisan mirsalink:embed-knowledge).
 */
class EmbedKnowledgeEntry
{
    public function __construct(private Brain $brain) {}

    public function handle(KnowledgeEntry $entry): bool
    {
        if ($this->brain->embeddingModel() === null) {
            return false;
        }

        try {
            $vector = $this->brain->embed($entry->question."\n".$entry->answer);
        } catch (AssistantUnavailable $exception) {
            report($exception);

            return false;
        }

        $entry->forceFill(['embedding' => $vector, 'embedding_model' => $this->brain->embeddingModel()])->saveQuietly();

        return true;
    }
}
