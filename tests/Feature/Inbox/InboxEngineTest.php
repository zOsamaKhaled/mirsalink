<?php

namespace Tests\Feature\Inbox;

use App\Actions\Inbox\AssignConversation;
use App\Actions\Inbox\ReceiveMessage;
use App\Actions\Inbox\SendMessage;
use App\Actions\Inbox\UpdateMessageStatus;
use App\Enums\Availability;
use App\Enums\ConversationEventType;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageSender;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\RoutingMethod;
use App\Exceptions\InboxRuleException;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\RoutingRule;
use App\Models\Tag;
use App\Models\User;
use App\Support\WorkingHours;
use App\WhatsApp\InboundMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InboxEngineTest extends TestCase
{
    use RefreshDatabase;

    private function receive(string $phone, ?string $body = 'السلام عليكم', ?string $externalId = null, ?string $name = null): Conversation
    {
        return app(ReceiveMessage::class)
            ->handle(new InboundMessage(phone: $phone, body: $body, profileName: $name, externalId: $externalId))
            ->conversation->fresh();
    }

    public function test_a_message_from_a_new_number_makes_a_contact_and_routes_the_conversation(): void
    {
        $agent = User::factory()->create();

        $conversation = $this->receive('0501234567', name: 'Abu Fahad');

        $this->assertSame('+966501234567', $conversation->contact->phone);
        $this->assertSame('Abu Fahad', $conversation->contact->name);
        $this->assertSame(Contact::SOURCE_WHATSAPP, $conversation->contact->source);
        $this->assertSame(ConversationStatus::Open, $conversation->status);
        $this->assertSame($agent->id, $conversation->assigned_to);
        $this->assertSame(1, $conversation->unread_count);
        $this->assertNotNull($conversation->awaiting_reply_since);
        $this->assertTrue($conversation->windowIsOpen());
        $this->assertSame(MessageDirection::Inbound, $conversation->messages()->first()->direction);
        $this->assertDatabaseHas('conversation_events', [
            'conversation_id' => $conversation->id,
            'type' => ConversationEventType::Assigned->value,
            'actor_id' => null,
        ]);
    }

    public function test_the_same_whatsapp_message_twice_is_kept_once(): void
    {
        $this->receive('0501234567', externalId: 'wamid.1');
        $conversation = $this->receive('0501234567', externalId: 'wamid.1');

        $this->assertSame(1, $conversation->messages()->count());
        $this->assertSame(1, $conversation->unread_count);
    }

    public function test_with_no_one_available_the_conversation_waits_unassigned(): void
    {
        User::factory()->availability(Availability::Away)->create();

        $conversation = $this->receive('0501234567');

        $this->assertNull($conversation->assigned_to);
    }

    public function test_a_returning_customer_goes_back_to_the_member_who_helped_them(): void
    {
        [$sara, $khalid] = User::factory()->count(2)->create()->all();
        $contact = Contact::factory()->create(['phone' => '+966501234567']);
        Conversation::factory()->closed()->assignedTo($khalid)->create(['contact_id' => $contact->id]);

        $conversation = $this->receive('0501234567');

        $this->assertSame($khalid->id, $conversation->assigned_to);
        $this->assertTrue($conversation->isOpen());
        $this->assertDatabaseHas('conversation_events', [
            'conversation_id' => $conversation->id,
            'type' => ConversationEventType::Reopened->value,
            'actor_id' => null,
        ]);
    }

    public function test_a_returning_customer_whose_member_is_away_is_routed_again(): void
    {
        $sara = User::factory()->create();
        $khalid = User::factory()->availability(Availability::Away)->create();
        $contact = Contact::factory()->create(['phone' => '+966501234567']);
        Conversation::factory()->closed()->assignedTo($khalid)->create(['contact_id' => $contact->id]);

        $conversation = $this->receive('0501234567');

        $this->assertSame($sara->id, $conversation->assigned_to);
        $this->assertDatabaseHas('conversation_events', [
            'conversation_id' => $conversation->id,
            'type' => ConversationEventType::Unassigned->value,
        ]);
    }

    public function test_a_tagged_conversation_follows_its_tags_routing_rule(): void
    {
        [$sara, $khalid] = User::factory()->count(2)->create()->all();
        $sales = Tag::findOrMake('مبيعات');
        $rule = RoutingRule::query()->create(['tag_id' => $sales->id, 'method' => RoutingMethod::RoundRobin]);
        $rule->members()->sync([$khalid->id]);

        $contact = Contact::factory()->create(['phone' => '+966501234567']);
        $existing = Conversation::factory()->closed()->create(['contact_id' => $contact->id]);
        $existing->tags()->attach($sales->id);

        $conversation = $this->receive('0501234567');

        $this->assertSame($khalid->id, $conversation->assigned_to);
    }

    public function test_out_of_hours_the_customer_gets_the_auto_reply_once_while_waiting(): void
    {
        User::factory()->create();
        $closedAllWeek = WorkingHours::defaults()->toArray();
        $closedAllWeek['days'] = array_map(fn (array $day): array => [...$day, 'open' => false], $closedAllWeek['days']);
        WorkingHours::fromArray([...$closedAllWeek, 'auto_reply_enabled' => true, 'auto_reply_message' => 'نحن خارج الدوام'])->save();

        $this->receive('0501234567', 'مرحبا');
        $this->travel(1)->minutes();
        $conversation = $this->receive('0501234567', 'هل من أحد؟');

        $replies = $conversation->messages()->where('sender', MessageSender::System)->get();
        $this->assertCount(1, $replies);
        $this->assertSame('نحن خارج الدوام', $replies->first()->body);
        $this->assertSame(MessageStatus::Sent, $replies->first()->status);

        // After the team answers, the next message out of hours is told again.
        $this->travel(1)->minutes();
        app(SendMessage::class)->handle($conversation, $conversation->assignee, 'أهلًا');
        $this->travel(1)->minutes();
        $conversation = $this->receive('0501234567', 'شكرًا');

        $this->assertSame(2, $conversation->messages()->where('sender', MessageSender::System)->count());
    }

    public function test_a_reply_goes_out_through_the_gateway_and_clears_the_wait(): void
    {
        $agent = User::factory()->availability(Availability::Away)->create();
        $conversation = Conversation::factory()->create(['unread_count' => 3, 'awaiting_reply_since' => now()]);

        $message = app(SendMessage::class)->handle($conversation, $agent, 'كيف أساعدك؟');
        $conversation->refresh();

        $this->assertSame(MessageStatus::Sent, $message->status);
        $this->assertStringStartsWith('wamid.sim.', $message->external_id);
        $this->assertSame(0, $conversation->unread_count);
        $this->assertNull($conversation->awaiting_reply_since);
        $this->assertNotNull($conversation->first_response_at);
        $this->assertSame($agent->id, $conversation->assigned_to);
    }

    public function test_a_file_is_kept_on_the_private_disk_and_sent_as_its_kind(): void
    {
        Storage::fake('local');
        $agent = User::factory()->create();
        $conversation = Conversation::factory()->assignedTo($agent)->create();

        $message = app(SendMessage::class)->handle($conversation, $agent, 'عرض السعر', UploadedFile::fake()->create('offer.pdf', 120, 'application/pdf'));

        $this->assertSame(MessageType::Document, $message->type);
        $this->assertSame('offer.pdf', $message->media_name);
        $this->assertSame('عرض السعر', $message->body);
        Storage::disk('local')->assertExists($message->media_path);
    }

    public function test_no_free_reply_after_the_24_hour_window_or_on_a_closed_conversation(): void
    {
        $agent = User::factory()->create();
        $send = app(SendMessage::class);

        $stale = Conversation::factory()->assignedTo($agent)->create(['last_inbound_at' => now()->subHours(25)]);
        $closed = Conversation::factory()->closed()->assignedTo($agent)->create();

        foreach ([$stale, $closed] as $conversation) {
            try {
                $send->handle($conversation, $agent, 'مرحبا');
                $this->fail('The reply should have been refused.');
            } catch (InboxRuleException) {
                $this->assertSame(0, $conversation->messages()->count());
            }
        }
    }

    public function test_statuses_only_move_forward_and_a_failure_keeps_its_reason(): void
    {
        $agent = User::factory()->create();
        $conversation = Conversation::factory()->assignedTo($agent)->create();
        $update = app(UpdateMessageStatus::class);

        $message = app(SendMessage::class)->handle($conversation, $agent, 'مرحبا');
        $update->handle($message->external_id, MessageStatus::Read);
        $update->handle($message->external_id, MessageStatus::Delivered);

        $message->refresh();
        $this->assertSame(MessageStatus::Read, $message->status);
        $this->assertNotNull($message->delivered_at);
        $this->assertNotNull($message->read_at);

        $other = app(SendMessage::class)->handle($conversation, $agent, 'هل وصلت؟');
        $update->handle($other->external_id, MessageStatus::Failed, '131026', 'Undeliverable');

        $other->refresh();
        $this->assertSame(MessageStatus::Failed, $other->status);
        $this->assertSame('131026', $other->error_code);
    }

    public function test_an_agent_cannot_move_a_colleagues_conversation_and_no_one_gets_an_absent_member(): void
    {
        [$sara, $khalid] = User::factory()->count(2)->create()->all();
        $gone = User::factory()->deactivated()->create();
        $supervisor = User::factory()->supervisor()->create();
        $conversation = Conversation::factory()->assignedTo($khalid)->create();
        $assign = app(AssignConversation::class);

        $this->assertRefused(fn () => $assign->handle($conversation, $sara, $sara));
        $this->assertRefused(fn () => $assign->handle($conversation, $gone, $supervisor));

        $assign->handle($conversation, $sara, $supervisor);

        $this->assertSame($sara->id, $conversation->fresh()->assigned_to);
        $this->assertDatabaseHas('conversation_events', [
            'conversation_id' => $conversation->id,
            'type' => ConversationEventType::Transferred->value,
            'actor_id' => $supervisor->id,
        ]);
    }

    private function assertRefused(callable $action): void
    {
        try {
            $action();
            $this->fail('The action should have been refused.');
        } catch (InboxRuleException) {
            $this->addToAssertionCount(1);
        }
    }
}
