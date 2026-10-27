<?php

namespace App\Actions\Assistant;

use App\Actions\Inbox\RouteConversation;
use App\Actions\Inbox\SendAutoReply;
use App\Actions\Inbox\TagConversation;
use App\Assistant\AssistantSettings;
use App\Assistant\AssistantUnavailable;
use App\Assistant\Brain;
use App\Assistant\Classification;
use App\Assistant\KnowledgeSearch;
use App\Assistant\Turn;
use App\Enums\ConversationEventType;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\KnowledgeEntry;
use App\Models\Message;
use App\Models\Tag;
use App\Support\ArabicText;
use Throwable;

/**
 * The assistant's turn on one customer message.
 *
 * It reads the message with the conversation before it, the tags and the
 * closest knowledge entries, and decides: answer from the knowledge base
 * only, greet, say politely what it can't help with, or hand the customer
 * to a person (anything sensitive, about their own account, below the
 * confidence threshold, unanswered by the knowledge base, a reply carrying
 * a number the knowledge base doesn't, or a model that fails or is slow).
 *
 * Live, it tags the conversation, replies, and on handover routes it by its
 * tags and steps back until a member closes the conversation or turns it
 * on again. Silent, it does none of that and only logs what it would have
 * done, for comparing with what the team did. Every turn is an AiRun.
 */
class RunAssistant
{
    public function __construct(
        private Brain $brain,
        private KnowledgeSearch $knowledge,
        private TagConversation $tags,
        private RouteConversation $route,
        private SendBotMessage $send,
        private SendAutoReply $autoReply,
    ) {}

    public function handle(Message $message): ?AiRun
    {
        $settings = AssistantSettings::current();
        $conversation = $message->conversation;

        if ($settings->isOff() || $message->direction !== MessageDirection::Inbound) {
            return null;
        }

        $live = $settings->isLive() && $conversation->bot_active;

        if ($settings->isLive() && ! $live) {
            return null;
        }

        return $this->turn($message->body ?? '', $settings, $live ? 'live' : 'shadow', $conversation, $message);
    }

    /**
     * The settings page's try-it box: the same reading and decision on a
     * typed message, with nothing sent.
     */
    public function trial(string $text): AiRun
    {
        return $this->turn($text, AssistantSettings::current(), 'trial');
    }

    private function turn(string $text, AssistantSettings $settings, string $mode, ?Conversation $conversation = null, ?Message $message = null): AiRun
    {
        $started = hrtime(true);
        $run = new AiRun([
            'conversation_id' => $conversation?->id,
            'message_id' => $message?->id,
            'mode' => $mode,
            'driver' => (string) config('assistant.driver'),
            'model' => $this->brain->model(),
        ]);

        try {
            $turn = new Turn(
                message: $text,
                history: $conversation === null ? [] : $this->history($conversation, $message),
                tags: Tag::query()->orderBy('name')->get(['name', 'description'])->map(fn (Tag $tag): array => ['name' => $tag->name, 'description' => $tag->description])->all(),
                passages: $this->knowledge->closest($text),
            );

            $classification = $this->brain->classify($turn);
            [$decision, $reply, $sources] = $this->decide($turn, $classification, $settings);

            $run->fill([
                'status' => 'ok',
                'intent' => $classification->intent,
                'tags' => $classification->tags,
                'confidence' => $classification->confidence,
                'decision' => $decision,
                'reply' => $reply,
                'sources' => $sources,
            ]);
        } catch (AssistantUnavailable $exception) {
            $classification = null;
            $run->fill(['status' => 'failed', 'decision' => AiRun::HANDED_OVER, 'error' => $exception->getMessage()]);
        }

        $run->latency_ms = (int) ((hrtime(true) - $started) / 1_000_000);
        $run->save();

        if ($mode === 'live' && $conversation !== null) {
            $this->act($conversation, $run, $classification, $settings);
        }

        return $run;
    }

    /**
     * @return array{0: string, 1: string|null, 2: list<int>}
     */
    private function decide(Turn $turn, Classification $classification, AssistantSettings $settings): array
    {
        $handOver = [AiRun::HANDED_OVER, $settings->handoverReply ?: null, []];

        if (in_array($classification->intent, ['sensitive', 'account'], true)
            || $classification->confidence < (float) config('assistant.confidence_threshold')) {
            return $handOver;
        }

        if ($classification->intent === 'greeting') {
            return [AiRun::GREETED, $settings->greeting, []];
        }

        if ($classification->intent === 'out_of_scope') {
            return [AiRun::OUT_OF_SCOPE, $settings->outOfScopeReply, []];
        }

        $answer = $this->brain->answer($turn);

        if ($answer === null || ! $this->numbersAreKnown($answer['text'], $turn->passages)) {
            return $handOver;
        }

        return [AiRun::ANSWERED, $answer['text'], $answer['sources']];
    }

    /**
     * Every number in the reply must be written in the knowledge it came
     * from: a price or a duration the assistant made up never goes out.
     *
     * @param  list<KnowledgeEntry>  $passages
     */
    private function numbersAreKnown(string $reply, array $passages): bool
    {
        $known = ArabicText::numbers(implode(' ', array_map(
            fn (KnowledgeEntry $entry): string => $entry->question.' '.$entry->answer,
            $passages,
        )));

        return array_diff(ArabicText::numbers($reply), $known) === [];
    }

    private function act(Conversation $conversation, AiRun $run, ?Classification $classification, AssistantSettings $settings): void
    {
        try {
            if ($classification !== null && $classification->tags !== []) {
                Tag::query()->whereIn('name', $classification->tags)->get()
                    ->each(fn (Tag $tag) => $this->tags->attach($conversation, $tag, byBot: true));

                ConversationEvent::record($conversation, ConversationEventType::Classified, null, [
                    'tags' => $classification->tags,
                    'confidence' => $classification->confidence,
                ]);
            }

            if ($run->decision === AiRun::HANDED_OVER) {
                $this->handOver($conversation, $run);

                return;
            }

            if (filled($run->reply)) {
                $this->send->handle($conversation, (string) $run->reply);
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->handOver($conversation->fresh(), $run);
        }
    }

    /**
     * To a person: a line telling the customer so (if the team wrote one),
     * the assistant steps back, and the conversation goes to whoever its
     * tags route it to, or stays with the member it already has.
     */
    private function handOver(Conversation $conversation, AiRun $run): void
    {
        if (filled($run->reply) && $run->status === 'ok') {
            $this->send->handle($conversation, (string) $run->reply, answers: false);
        }

        $conversation->forceFill(['bot_active' => false])->save();

        // A returning customer's member keeps them if still taking conversations.
        $previous = $conversation->assignee;
        $member = $previous !== null && $previous->isActive() && $previous->availability->takesNewConversations()
            ? $previous
            : $this->route->handle($conversation->fresh());

        ConversationEvent::record($conversation, ConversationEventType::HandedOver, null, ['to' => $member?->id]);

        $this->autoReply->handle($conversation->fresh());
    }

    /**
     * The conversation before the message, oldest first, without notes.
     *
     * @return list<array{from: 'customer'|'team', text: string}>
     */
    private function history(Conversation $conversation, ?Message $message): array
    {
        return $conversation->messages()
            ->where('type', '!=', MessageType::Note)
            ->whereNotNull('body')
            ->when($message, fn ($query) => $query->where('id', '<', $message->id))
            ->latest('id')
            ->limit((int) config('assistant.history'))
            ->get(['direction', 'body'])
            ->reverse()
            ->map(fn (Message $line): array => [
                'from' => $line->direction === MessageDirection::Inbound ? 'customer' : 'team',
                'text' => (string) $line->body,
            ])
            ->values()
            ->all();
    }
}
