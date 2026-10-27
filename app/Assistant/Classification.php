<?php

namespace App\Assistant;

/**
 * What the brain made of a message.
 *
 * Intents: in_scope (a question the knowledge base may answer), greeting,
 * account (about the customer's own subscription, ads or verification,
 * which needs Rizq's data), out_of_scope, and sensitive (a complaint, a
 * payment problem, anger, or asking for a person), which goes straight to
 * the team.
 */
final readonly class Classification
{
    public const array INTENTS = ['in_scope', 'greeting', 'account', 'out_of_scope', 'sensitive'];

    /**
     * @param  list<string>  $tags  names of existing tags
     */
    public function __construct(
        public string $intent,
        public array $tags,
        public float $confidence,
        public string $language = 'ar',
    ) {}
}
