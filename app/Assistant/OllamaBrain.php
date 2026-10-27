<?php

namespace App\Assistant;

use App\Models\KnowledgeEntry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The model on the team's own server, through Ollama's HTTP API: /api/chat
 * with a JSON schema in "format" so the answer is always parseable, at
 * temperature 0 so the same message gets the same reading, and /api/embed
 * for the knowledge base. Nothing leaves the server.
 */
class OllamaBrain implements Brain
{
    public function classify(Turn $turn): Classification
    {
        $tags = array_column($turn->tags, 'name');

        $result = $this->chat($this->classifyInstructions($turn), $this->conversation($turn), [
            'type' => 'object',
            'properties' => [
                'intent' => ['type' => 'string', 'enum' => Classification::INTENTS],
                'tags' => ['type' => 'array', 'items' => $tags === [] ? ['type' => 'string'] : ['type' => 'string', 'enum' => $tags]],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                'language' => ['type' => 'string', 'enum' => ['ar', 'en']],
            ],
            'required' => ['intent', 'tags', 'confidence', 'language'],
        ]);

        if (! in_array($result['intent'] ?? null, Classification::INTENTS, true)) {
            throw AssistantUnavailable::because('The model answered with an unknown intent.');
        }

        return new Classification(
            intent: $result['intent'],
            tags: array_values(array_intersect((array) ($result['tags'] ?? []), $tags)),
            confidence: max(0.0, min(1.0, (float) ($result['confidence'] ?? 0))),
            language: ($result['language'] ?? 'ar') === 'en' ? 'en' : 'ar',
        );
    }

    public function answer(Turn $turn): ?array
    {
        if ($turn->passages === []) {
            return null;
        }

        $result = $this->chat($this->answerInstructions($turn), $this->conversation($turn), [
            'type' => 'object',
            'properties' => [
                'answer' => ['type' => ['string', 'null']],
                'sources' => ['type' => 'array', 'items' => ['type' => 'integer']],
            ],
            'required' => ['answer', 'sources'],
        ]);

        $text = trim((string) ($result['answer'] ?? ''));
        $known = array_map(fn (KnowledgeEntry $entry): int => $entry->id, $turn->passages);

        return $text === '' ? null : [
            'text' => $text,
            'sources' => array_values(array_intersect(array_map('intval', (array) ($result['sources'] ?? [])), $known)),
        ];
    }

    public function embed(string $text): ?array
    {
        $response = $this->post('/api/embed', ['model' => $this->embeddingModel(), 'input' => $text]);
        $vector = $response['embeddings'][0] ?? null;

        if (! is_array($vector) || $vector === []) {
            throw AssistantUnavailable::because('The embedding model returned no vector.');
        }

        return array_map('floatval', $vector);
    }

    public function model(): string
    {
        return (string) config('assistant.ollama.chat_model');
    }

    public function embeddingModel(): ?string
    {
        return (string) config('assistant.ollama.embedding_model');
    }

    public function healthy(): bool
    {
        try {
            return Http::baseUrl((string) config('assistant.ollama.url'))->timeout(3)->get('/api/tags')->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function chat(string $instructions, array $messages, array $schema): array
    {
        $response = $this->post('/api/chat', [
            'model' => $this->model(),
            'messages' => [['role' => 'system', 'content' => $instructions], ...$messages],
            'format' => $schema,
            'stream' => false,
            'options' => ['temperature' => 0],
        ]);

        $decoded = json_decode((string) ($response['message']['content'] ?? ''), true);

        if (! is_array($decoded)) {
            throw AssistantUnavailable::because('The model did not answer in JSON.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        try {
            $response = Http::baseUrl((string) config('assistant.ollama.url'))
                ->timeout((int) config('assistant.timeout_seconds'))
                ->post($path, $payload);
        } catch (ConnectionException $exception) {
            throw AssistantUnavailable::because('Ollama could not be reached: '.$exception->getMessage());
        }

        if ($response->failed()) {
            throw AssistantUnavailable::because("Ollama answered HTTP {$response->status()}.");
        }

        return (array) $response->json();
    }

    /**
     * The conversation so far, as the chat API takes it.
     *
     * @return list<array{role: string, content: string}>
     */
    private function conversation(Turn $turn): array
    {
        return [
            ...array_map(fn (array $line): array => [
                'role' => $line['from'] === 'customer' ? 'user' : 'assistant',
                'content' => $line['text'],
            ], $turn->history),
            ['role' => 'user', 'content' => $turn->message],
        ];
    }

    private function classifyInstructions(Turn $turn): string
    {
        $tags = implode("\n", array_map(
            fn (array $tag): string => "- {$tag['name']}: ".($tag['description'] ?? ''),
            $turn->tags,
        ));
        $topics = implode("\n", array_map(fn (KnowledgeEntry $entry): string => "- {$entry->question}", $turn->passages));

        return <<<PROMPT
        You sort WhatsApp messages for Rizq, a Saudi recruitment platform. Read the customer's last message in the context of the conversation and classify it.

        intent:
        - in_scope: a question about Rizq's services that the knowledge base may answer.
        - greeting: only a greeting, nothing asked yet.
        - account: about the customer's own subscription, ads, invoice or verification status.
        - out_of_scope: nothing to do with Rizq.
        - sensitive: a complaint, a payment problem, anger, a refund, or asking for a person.

        tags: choose from this list only, those that fit; none if none fits.
        {$tags}

        Questions the knowledge base can answer:
        {$topics}

        confidence: how sure you are of the intent, from 0 to 1. Be honest; a low number sends the customer to a person.
        language: the language the customer wrote in.
        PROMPT;
    }

    private function answerInstructions(Turn $turn): string
    {
        $passages = implode("\n\n", array_map(
            fn (KnowledgeEntry $entry): string => "[{$entry->id}] Q: {$entry->question}\nA: {$entry->answer}",
            $turn->passages,
        ));

        return <<<PROMPT
        You answer customers of Rizq, a Saudi recruitment platform, on WhatsApp. Answer the customer's last message using only the passages below.

        Rules:
        - If the passages don't answer it, set "answer" to null. Never guess.
        - Never write a price, duration, number or condition that isn't in the passages.
        - Reply in the customer's language: clear, short, polite Modern Standard Arabic for Arabic.
        - Two or three sentences at most, no greeting line, no sign-off.
        - In "sources", list the numbers of the passages you used.

        Passages:
        {$passages}
        PROMPT;
    }
}
