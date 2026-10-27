<?php

namespace App\Assistant;

/**
 * What reads customer messages for the assistant.
 *
 * Ollama on the team's own server in production; a keyword simulator
 * until then, the way the WhatsApp simulator stands in for Meta. Nothing
 * else knows which one is answering (config('assistant.driver')).
 */
interface Brain
{
    /**
     * What the message is: its intent, the tags it deserves, and how sure
     * the brain is.
     *
     * @throws AssistantUnavailable When the model can't be reached in time or answers nonsense.
     */
    public function classify(Turn $turn): Classification;

    /**
     * A reply built only from the turn's knowledge passages, or null when
     * they don't answer the question.
     *
     * @return array{text: string, sources: list<int>}|null
     *
     * @throws AssistantUnavailable
     */
    public function answer(Turn $turn): ?array;

    /**
     * The text's meaning as numbers, or null when this brain has none (the
     * simulator); knowledge search then falls back to matching words.
     *
     * @return list<float>|null
     *
     * @throws AssistantUnavailable
     */
    public function embed(string $text): ?array;

    /** The chat model's name, for the log. */
    public function model(): string;

    /** The embedding model's name, or null. Vectors from different models don't compare. */
    public function embeddingModel(): ?string;

    /** Whether the brain can be reached now, for the settings page. */
    public function healthy(): bool;
}
