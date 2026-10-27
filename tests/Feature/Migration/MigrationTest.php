<?php

namespace Tests\Feature\Migration;

use App\Actions\Contacts\ImportContacts;
use App\Actions\Inbox\ReceiveMessage;
use App\Assistant\AssistantSettings;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageSender;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Migration;
use App\Support\MigrationReadiness;
use App\WhatsApp\InboundMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{key: string, status: string, value: string|null}>
     */
    private function criteria(): array
    {
        return collect(MigrationReadiness::criteria())->keyBy('key')->all();
    }

    public function test_customers_moved_from_respond_io_stay_with_their_member(): void
    {
        $sara = User::factory()->create(['email' => 'sara@rizq.sa']);
        User::factory()->create();
        $path = tempnam(sys_get_temp_dir(), 'contacts');
        file_put_contents($path, implode("\n", [
            'Phone Number,First Name,Assignee',
            '0551110001,فهد,Sara@rizq.sa',
            '0551110002,نورة,موظف سابق',
            '0551110003,خالد,',
        ]));

        $result = app(ImportContacts::class)->handle($path);

        $this->assertSame(1, $result['owners']);
        $this->assertSame(['موظف سابق'], $result['unknown_owners']);
        $kept = Contact::query()->where('phone', '+966551110001')->sole()->conversation;
        $this->assertSame($sara->id, $kept->assigned_to);
        $this->assertSame(ConversationStatus::Closed, $kept->status);

        // Their first message goes back to Sara, not to whoever routing picks.
        $message = app(ReceiveMessage::class)->handle(new InboundMessage(phone: '0551110001', body: 'مرحبا'));
        $this->assertSame($sara->id, $message->conversation->fresh()->assigned_to);
    }

    public function test_saying_respond_ios_bot_answers_puts_ours_back_to_silent(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        Migration::update(['respond_io_bot_active' => false]);
        $defaults = AssistantSettings::defaults();
        (new AssistantSettings(AssistantSettings::LIVE, $defaults->greeting, $defaults->outOfScopeReply, $defaults->handoverReply))->save();

        $this->actingAs($supervisor)
            ->put(route('migration.update'), ['respond_io_bot_active' => true, 'baseline_reply_minutes' => 12])
            ->assertInertiaFlash('toast.type', 'info');

        $this->assertSame(AssistantSettings::SHADOW, AssistantSettings::current()->mode);
        $this->assertSame(12, Migration::current()->baselineReplyMinutes);
    }

    public function test_steps_are_ticked_and_a_supervisors_review_settles_the_price_condition(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs(User::factory()->create())->get(route('migration.index'))->assertForbidden();

        $this->actingAs($supervisor)->put(route('migration.steps.update', 'import_contacts'), ['done' => true]);
        $this->actingAs($supervisor)->put(route('migration.steps.update', 'invented'), ['done' => true])->assertNotFound();
        $this->assertSame('manual', $this->criteria()['prices']['status']);

        $this->actingAs($supervisor)->post(route('migration.prices'));

        $this->assertSame('met', $this->criteria()['prices']['status']);
        $this->actingAs($supervisor)
            ->get(route('migration.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('migration/index')
                ->has('migration.steps.import_contacts')
                ->has('criteria', 6));
    }

    public function test_conditions_are_measured_from_the_platforms_own_records(): void
    {
        config(['whatsapp.gateway' => 'cloud']);
        $agents = User::factory()->count(2)->create();
        $conversation = Conversation::factory()->assignedTo($agents[0])->create();

        $message = fn (array $attributes) => Message::factory()->for($conversation)->create($attributes);
        $message(['direction' => MessageDirection::Inbound, 'created_at' => now()->subHours(2)]);
        $message(['direction' => MessageDirection::Outbound, 'sender' => MessageSender::Agent, 'user_id' => $agents[0]->id, 'created_at' => now()->subHours(2)->addMinutes(4)]);

        // Meta's message stamped at 10:00, received at 10:07: a seven-minute gap.
        WebhookEvent::query()->create([
            'payload' => ['entry' => [['changes' => [['field' => 'messages', 'value' => ['messages' => [['timestamp' => (string) now()->subHour()->subMinutes(7)->timestamp]]]]]]]],
            'processed_at' => now(),
            'created_at' => now()->subHour(),
        ]);
        Migration::update(['baseline_reply_minutes' => 5]);

        $criteria = $this->criteria();

        $this->assertSame('met', $criteria['nothing_lost']['status']);
        $this->assertSame('not_met', $criteria['receiving']['status']);
        $this->assertSame('420', $criteria['receiving']['value']);
        $this->assertSame('not_met', $criteria['whole_team']['status']);
        $this->assertSame('1/2', $criteria['whole_team']['value']);
        $this->assertSame('met', $criteria['reply_time']['status']);
        $this->assertSame('gathering', $criteria['agreement']['status']);

        WebhookEvent::query()->create(['payload' => ['entry' => []], 'created_at' => now()->subMinutes(30)]);
        $this->assertSame('not_met', $this->criteria()['nothing_lost']['status']);
    }
}
