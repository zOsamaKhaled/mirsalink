<?php

namespace Tests\Feature\Assistant;

use App\Actions\Assistant\RunAssistant;
use App\Actions\Inbox\ReceiveMessage;
use App\Actions\Inbox\SendMessage;
use App\Assistant\AssistantReport;
use App\Assistant\AssistantSettings;
use App\Assistant\AssistantUnavailable;
use App\Assistant\Brain;
use App\Assistant\Classification;
use App\Assistant\KnowledgeSearch;
use App\Assistant\OllamaBrain;
use App\Assistant\Turn;
use App\Enums\ConversationEventType;
use App\Enums\MessageSender;
use App\Enums\RoutingMethod;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\KnowledgeEntry;
use App\Models\Message;
use App\Models\RoutingRule;
use App\Models\Tag;
use App\Models\User;
use App\Support\Migration;
use App\WhatsApp\InboundMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private function mode(string $mode): void
    {
        $defaults = AssistantSettings::defaults();
        (new AssistantSettings($mode, $defaults->greeting, $defaults->outOfScopeReply, $defaults->handoverReply))->save();
    }

    /**
     * The customer writes, and the assistant takes its turn, as it would
     * once the response has gone.
     */
    private function customerWrites(string $text, string $phone = '0551234567'): Message
    {
        $message = app(ReceiveMessage::class)->handle(new InboundMessage(phone: $phone, body: $text));
        app(RunAssistant::class)->handle($message);

        return $message->fresh();
    }

    /**
     * @param  array{intent?: string, tags?: list<string>, confidence?: float, answer?: string|null, fails?: bool}  $script
     */
    private function brain(array $script): void
    {
        $this->app->instance(Brain::class, new class($script) implements Brain
        {
            public function __construct(private array $script) {}

            public function classify(Turn $turn): Classification
            {
                if ($this->script['fails'] ?? false) {
                    throw AssistantUnavailable::because('Ollama could not be reached.');
                }

                return new Classification($this->script['intent'] ?? 'in_scope', $this->script['tags'] ?? [], $this->script['confidence'] ?? 0.9);
            }

            public function answer(Turn $turn): ?array
            {
                $text = $this->script['answer'] ?? null;

                return $text === null ? null : ['text' => $text, 'sources' => []];
            }

            public function embed(string $text): ?array
            {
                return null;
            }

            public function model(): string
            {
                return 'scripted';
            }

            public function embeddingModel(): ?string
            {
                return null;
            }

            public function healthy(): bool
            {
                return true;
            }
        });
    }

    public function test_switched_off_it_does_nothing_and_the_team_gets_the_customer_at_once(): void
    {
        $agent = User::factory()->create();

        $message = $this->customerWrites('كم سعر الباقة؟');

        $this->assertSame($agent->id, $message->conversation->assigned_to);
        $this->assertSame(0, AiRun::query()->count());
    }

    public function test_live_it_answers_from_the_knowledge_base_and_keeps_the_conversation(): void
    {
        $this->mode(AssistantSettings::LIVE);
        User::factory()->create();
        KnowledgeEntry::factory()->create([
            'question' => 'كم مدة وصول العمالة بعد التعاقد؟',
            'answer' => 'من أسبوعين إلى ستة أسابيع حسب المهنة والجنسية.',
        ]);

        $message = $this->customerWrites('كم مدة وصول العمالة بعد التعاقد؟');
        $conversation = $message->conversation->fresh();
        $reply = $conversation->messages()->where('sender', MessageSender::Bot)->sole();
        $run = AiRun::query()->sole();

        $this->assertSame('من أسبوعين إلى ستة أسابيع حسب المهنة والجنسية.', $reply->body);
        $this->assertSame(AiRun::ANSWERED, $run->decision);
        $this->assertSame('live', $run->mode);
        $this->assertTrue($conversation->bot_active);
        $this->assertNull($conversation->assigned_to);
        $this->assertNull($conversation->awaiting_reply_since);
    }

    public function test_live_it_greets_and_says_politely_what_it_cannot_help_with(): void
    {
        $this->mode(AssistantSettings::LIVE);

        $this->customerWrites('السلام عليكم');
        $this->customerWrites('ما رأيك في مباراة الأمس؟');

        $replies = Message::query()->where('sender', MessageSender::Bot)->orderBy('id')->pluck('body')->all();

        $this->assertSame([AssistantSettings::defaults()->greeting, AssistantSettings::defaults()->outOfScopeReply], $replies);
        $this->assertSame([AiRun::GREETED, AiRun::OUT_OF_SCOPE], AiRun::query()->orderBy('id')->pluck('decision')->all());
    }

    public function test_something_sensitive_goes_to_a_person_by_the_tags_rule_and_the_assistant_steps_back(): void
    {
        $this->mode(AssistantSettings::LIVE);
        [$sara, $supervisor] = [User::factory()->create(), User::factory()->supervisor()->create()];
        $complaints = Tag::query()->create(['name' => 'شكوى', 'color' => 'red', 'description' => 'عميل غاضب يشتكي من الخدمة.']);
        RoutingRule::query()->create(['tag_id' => $complaints->id, 'method' => RoutingMethod::RoundRobin])->members()->sync([$supervisor->id]);

        $message = $this->customerWrites('عندي شكوى، العاملة لم تصل وأنا غاضب');
        $conversation = $message->conversation->fresh();

        $this->assertSame(AiRun::HANDED_OVER, AiRun::query()->sole()->decision);
        $this->assertFalse($conversation->bot_active);
        $this->assertSame($supervisor->id, $conversation->assigned_to);
        $this->assertTrue($conversation->tags()->whereKey($complaints->id)->wherePivot('by_bot', true)->exists());
        $this->assertSame(AssistantSettings::defaults()->handoverReply, $conversation->messages()->where('sender', MessageSender::Bot)->sole()->body);
        $this->assertNotNull($conversation->awaiting_reply_since);
        $this->assertDatabaseHas('conversation_events', ['conversation_id' => $conversation->id, 'type' => ConversationEventType::HandedOver->value]);
        $this->assertDatabaseHas('conversation_events', ['conversation_id' => $conversation->id, 'type' => ConversationEventType::Classified->value]);

        // The assistant stays out until the conversation closes.
        $this->customerWrites('متى سيتواصل معي أحد؟');
        $this->assertSame(1, AiRun::query()->count());
    }

    public function test_a_members_reply_ends_the_assistants_part(): void
    {
        $this->mode(AssistantSettings::LIVE);
        $agent = User::factory()->create();

        $message = $this->customerWrites('السلام عليكم');
        app(SendMessage::class)->handle($message->conversation->fresh(), $agent, 'أهلًا، معك خالد');
        $this->customerWrites('كم سعر الباقة؟');

        $this->assertFalse($message->conversation->fresh()->bot_active);
        $this->assertSame(1, AiRun::query()->count());
    }

    public function test_a_number_the_knowledge_base_does_not_hold_never_reaches_the_customer(): void
    {
        $this->mode(AssistantSettings::LIVE);
        User::factory()->create();
        KnowledgeEntry::factory()->create(['question' => 'كم سعر الباقة الشهرية؟', 'answer' => 'تبدأ من 1,500 ريال للعامل.']);
        $this->brain(['answer' => 'سعر الباقة 999 ريال فقط.']);

        $message = $this->customerWrites('كم سعر الباقة الشهرية؟');

        $this->assertSame(AiRun::HANDED_OVER, AiRun::query()->sole()->decision);
        $this->assertFalse($message->conversation->messages()->where('body', 'like', '%999%')->exists());

        $this->brain(['answer' => 'تبدأ الباقة من 1500 ريال للعامل.']);
        $this->assertSame(AiRun::ANSWERED, app(RunAssistant::class)->trial('كم سعر الباقة الشهرية؟')->decision);
    }

    public function test_low_confidence_or_a_model_that_fails_goes_to_a_person_without_an_automatic_reply(): void
    {
        $this->mode(AssistantSettings::LIVE);
        $agent = User::factory()->create();

        $this->brain(['confidence' => 0.4, 'answer' => 'أي شيء']);
        $this->customerWrites('سؤال غامض', '0551000001');
        $this->assertSame(AiRun::HANDED_OVER, AiRun::query()->latest('id')->value('decision'));

        $this->brain(['fails' => true]);
        $message = $this->customerWrites('كم السعر؟', '0551000002');
        $run = AiRun::query()->latest('id')->first();

        $this->assertSame('failed', $run->status);
        $this->assertSame($agent->id, $message->conversation->fresh()->assigned_to);
        $this->assertFalse($message->conversation->messages()->where('sender', MessageSender::Bot)->exists());
    }

    public function test_silent_it_only_logs_what_it_would_have_done(): void
    {
        $this->mode(AssistantSettings::SHADOW);
        $agent = User::factory()->create();
        $complaints = Tag::query()->create(['name' => 'شكوى', 'color' => 'red', 'description' => 'عميل يشتكي']);

        $message = $this->customerWrites('عندي شكوى على الخدمة');
        $conversation = $message->conversation->fresh();
        $run = AiRun::query()->sole();

        $this->assertSame('shadow', $run->mode);
        $this->assertSame(AiRun::HANDED_OVER, $run->decision);
        $this->assertSame(['شكوى'], $run->tags);
        $this->assertSame($agent->id, $conversation->assigned_to);
        $this->assertFalse($conversation->messages()->where('sender', MessageSender::Bot)->exists());
        $this->assertFalse($conversation->tags()->exists());
        $this->assertNull(AssistantReport::agrees($run));

        // The team tags it as the assistant suggested and answers: they agree.
        $conversation->tags()->attach($complaints->id, ['tagged_by' => $agent->id]);
        app(SendMessage::class)->handle($conversation, $agent, 'نعتذر، أتابع معك الآن');

        $this->assertTrue(AssistantReport::agrees($run->fresh()));
        $this->assertSame(100, AssistantReport::summary('shadow', now()->subDay(), now()->addMinute())['agreement']);
    }

    public function test_ollama_is_asked_for_json_and_only_known_tags_are_kept(): void
    {
        config(['assistant.ollama.url' => 'http://ollama.test']);
        Http::fake([
            'ollama.test/api/chat' => Http::sequence()
                ->push(['message' => ['content' => json_encode([
                    'intent' => 'in_scope', 'tags' => ['دعم فني', 'وسم مخترع'], 'confidence' => 0.82, 'language' => 'ar',
                ])]])
                ->push('down', 500),
        ]);

        $classification = (new OllamaBrain)->classify(new Turn(
            message: 'التطبيق لا يفتح',
            tags: [['name' => 'دعم فني', 'description' => 'مشكلة في التطبيق']],
        ));

        $this->assertSame(['دعم فني'], $classification->tags);
        $this->assertSame(0.82, $classification->confidence);
        Http::assertSent(fn ($request): bool => $request['format']['properties']['intent']['enum'] === Classification::INTENTS
            && $request['options']['temperature'] === 0
            && $request['stream'] === false);

        $this->expectException(AssistantUnavailable::class);
        (new OllamaBrain)->classify(new Turn(message: 'مرحبا'));
    }

    public function test_knowledge_is_found_by_meaning_when_entries_carry_the_models_embeddings(): void
    {
        config(['assistant.driver' => 'ollama', 'assistant.ollama.url' => 'http://ollama.test', 'assistant.ollama.embedding_model' => 'bge-m3']);
        $near = KnowledgeEntry::factory()->create(['question' => 'مدة الاستقدام', 'embedding' => [0.9, 0.1, 0.0], 'embedding_model' => 'bge-m3']);
        KnowledgeEntry::factory()->create(['question' => 'طرق الدفع', 'embedding' => [0.0, 0.2, 0.9], 'embedding_model' => 'bge-m3']);
        Http::fake(['ollama.test/api/embed' => Http::response(['embeddings' => [[1.0, 0.0, 0.0]]])]);

        $found = (new KnowledgeSearch(new OllamaBrain))->closest('متى تصل العاملة؟');

        $this->assertSame([$near->id], array_map(fn (KnowledgeEntry $entry): int => $entry->id, $found));
    }

    public function test_supervisors_switch_it_and_try_it_and_members_hand_a_conversation_back(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $agent = User::factory()->create();
        KnowledgeEntry::factory()->create(['question' => 'هل يمكن الدفع بالتقسيط؟', 'answer' => 'نعم، على أربع دفعات.']);

        $this->actingAs($agent)->get(route('assistant.index'))->assertForbidden();

        $live = [
            'mode' => 'live',
            'greeting' => 'أهلًا بك',
            'out_of_scope_reply' => 'أساعدك في خدمات رزق فقط.',
            'handover_reply' => '',
        ];

        // While Respond.io's bot still answers on the number, ours may only listen.
        $this->actingAs($supervisor)->put(route('assistant.update'), $live)->assertSessionHasErrors('mode');

        Migration::update(['respond_io_bot_active' => false]);
        $this->actingAs($supervisor)->put(route('assistant.update'), $live)->assertInertiaFlash('toast.type', 'success');
        $this->assertTrue(AssistantSettings::current()->isLive());

        $this->actingAs($supervisor)
            ->post(route('assistant.trial'), ['message' => 'هل يمكن الدفع بالتقسيط؟'])
            ->assertInertiaFlash('trial.decision', AiRun::ANSWERED);
        $this->assertSame(0, Message::query()->count());

        $this->actingAs($supervisor)
            ->get(route('assistant.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('assistant/index')
                ->where('settings.mode', 'live')
                ->where('brain.driver', 'simulator')
                ->where('knowledge.active', 1));

        $conversation = Conversation::factory()->assignedTo($agent)->create();
        $this->actingAs($agent)->put(route('inbox.assistant.update', $conversation), ['active' => true]);
        $this->assertTrue($conversation->fresh()->bot_active);
        $this->assertDatabaseHas('conversation_events', ['type' => ConversationEventType::AssistantToggled->value, 'actor_id' => $agent->id]);
    }
}
