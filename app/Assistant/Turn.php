<?php

namespace App\Assistant;

use App\Models\KnowledgeEntry;

/**
 * Everything the brain is shown for one customer message: the message,
 * the conversation before it, the tags it may choose from, and the
 * knowledge entries closest to the question.
 */
final readonly class Turn
{
    /**
     * @param  list<array{from: 'customer'|'team', text: string}>  $history  oldest first, without the message itself
     * @param  list<array{name: string, description: string|null}>  $tags
     * @param  list<KnowledgeEntry>  $passages  closest first
     */
    public function __construct(
        public string $message,
        public array $history = [],
        public array $tags = [],
        public array $passages = [],
    ) {}
}
