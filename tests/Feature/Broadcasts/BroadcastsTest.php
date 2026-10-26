<?php

namespace Tests\Feature\Broadcasts;

use App\Actions\Broadcasts\LaunchBroadcast;
use App\Actions\Broadcasts\PrepareRecipients;
use App\Actions\Broadcasts\RunBroadcasts;
use App\Actions\Inbox\ReceiveMessage;
use App\Actions\Inbox\UpdateMessageStatus;
use App\Enums\AudienceType;
use App\Enums\BroadcastStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Enums\RecipientStatus;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Tag;
use App\Models\Template;
use App\Models\User;
use App\WhatsApp\InboundMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BroadcastsTest extends TestCase
{
    use RefreshDatabase;

    private function prepared(array $attributes = []): Broadcast
    {
        $broadcast = Broadcast::factory()->create($attributes);
        app(PrepareRecipients::class)->handle($broadcast);

        return $broadcast->fresh();
    }

    public function test_the_list_fills_each_recipients_values_and_says_who_is_left_out_and_why(): void
    {
        Contact::factory()->create(['name' => 'فهد', 'phone' => '+966551000001']);
        Contact::factory()->create(['name' => null, 'phone' => '+966551000002']);
        Contact::factory()->optedOut()->create(['phone' => '+966551000003']);

        $broadcast = $this->prepared();
        $recipients = $broadcast->recipients()->orderBy('phone')->get();

        $this->assertSame(['فهد', '1024'], $recipients[0]->params['body']);
        $this->assertSame(['عميلنا', '1024'], $recipients[1]->params['body']);
        $this->assertSame(RecipientStatus::Skipped, $recipients[2]->status);
        $this->assertSame(BroadcastRecipient::SKIP_OPTED_OUT, $recipients[2]->skip_reason);

        $strict = $this->prepared(['variables' => ['body' => [['source' => 'name'], ['source' => 'text', 'value' => '1']]]]);
        $this->assertSame(1, $strict->recipients()->where('skip_reason', BroadcastRecipient::SKIP_MISSING_VALUE)->count());
    }

    public function test_a_tag_audience_takes_contact_tags_and_conversation_tags(): void
    {
        $sales = Tag::findOrMake('عميل محتمل');
        Contact::factory()->create()->tags()->attach($sales);
        Conversation::factory()->create()->tags()->attach($sales);
        Contact::factory()->create();

        $broadcast = $this->prepared(['audience_type' => AudienceType::Tags, 'audience_tags' => [$sales->id]]);

        $this->assertSame(2, $broadcast->recipients()->count());
    }

    public function test_a_csv_audience_becomes_contacts_and_fills_variables_from_its_columns(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('broadcasts/list.csv', implode("\n", [
            'الجوال,الاسم,رقم الطلب',
            '0551112233,سلمان,A-17',
            '966551112233,سلمان,A-17',
            'not a number,خالد,A-18',
        ]));

        $broadcast = $this->prepared([
            'audience_type' => AudienceType::Csv,
            'audience_file' => 'broadcasts/list.csv',
            'variables' => ['body' => [['source' => 'name'], ['source' => 'column', 'value' => 'رقم الطلب']]],
        ]);

        $this->assertSame('سلمان', Contact::query()->where('phone', '+966551112233')->value('name'));
        $this->assertSame(['سلمان', 'A-17'], $broadcast->recipients()->where('status', RecipientStatus::Queued)->sole()->params['body']);
        $this->assertSame(1, $broadcast->recipients()->where('skip_reason', BroadcastRecipient::SKIP_DUPLICATE)->count());
        $this->assertSame(1, $broadcast->recipients()->where('skip_reason', BroadcastRecipient::SKIP_BAD_PHONE)->count());
    }

    public function test_campaigns_keep_to_the_pace_per_minute_and_the_daily_limit(): void
    {
        config(['broadcasts.per_minute' => 10, 'broadcasts.daily_limit' => 25]);
        Contact::factory()->count(40)->create();
        $broadcast = $this->prepared();
        app(LaunchBroadcast::class)->handle($broadcast);
        $run = app(RunBroadcasts::class);

        $this->assertSame([10, 10, 5, 0], [$run->handle(), $run->handle(), $run->handle(), $run->handle()]);
        $this->assertSame(15, $broadcast->recipients()->where('status', RecipientStatus::Queued)->count());

        $this->travel(25)->hours();
        $this->assertSame([10, 5], [$run->handle(), $run->handle()]);

        $broadcast->refresh();
        $this->assertSame(BroadcastStatus::Completed, $broadcast->status);
        $this->assertSame(40, $broadcast->stats()['sent']);
    }

    public function test_messages_land_in_the_thread_and_a_new_customers_conversation_waits_closed(): void
    {
        $contact = Contact::factory()->create(['name' => 'نورة']);
        $broadcast = $this->prepared();
        app(LaunchBroadcast::class)->handle($broadcast);
        app(RunBroadcasts::class)->handle();

        $conversation = $contact->conversation()->sole();
        $message = $conversation->messages()->sole();

        $this->assertSame(ConversationStatus::Closed, $conversation->status);
        $this->assertSame($broadcast->id, $message->broadcast_id);
        $this->assertSame('مرحبًا نورة، نتابع معك طلبك رقم 1024. هل تحتاج مساعدة؟', $message->body);
    }

    public function test_a_scheduled_campaign_waits_for_its_time(): void
    {
        Contact::factory()->create();
        $broadcast = $this->prepared();
        app(LaunchBroadcast::class)->handle($broadcast, now()->addHour());

        $this->assertSame(0, app(RunBroadcasts::class)->handle());
        $this->assertSame(BroadcastStatus::Scheduled, $broadcast->fresh()->status);

        $this->travel(61)->minutes();
        $this->assertSame(1, app(RunBroadcasts::class)->handle());
    }

    public function test_results_count_delivery_reading_replies_and_failures_and_stop_ends_broadcasts(): void
    {
        User::factory()->create();
        $contacts = Contact::factory()->count(4)->create();
        $broadcast = $this->prepared();
        app(LaunchBroadcast::class)->handle($broadcast);
        app(RunBroadcasts::class)->handle();

        $messages = $broadcast->recipients()->orderBy('id')->with('message')->get()->pluck('message');
        $update = app(UpdateMessageStatus::class);
        $update->handle($messages[0]->external_id, MessageStatus::Delivered);
        $update->handle($messages[1]->external_id, MessageStatus::Read);
        $update->handle($messages[2]->external_id, MessageStatus::Failed, '131026', 'Undeliverable');

        app(ReceiveMessage::class)->handle(new InboundMessage(phone: $contacts[1]->phone, body: 'إيقاف'));

        $this->assertSame(
            ['total' => 4, 'queued' => 0, 'skipped' => 0, 'sent' => 4, 'delivered' => 2, 'read' => 1, 'replied' => 1, 'failed' => 1],
            $broadcast->stats(),
        );
        $this->assertFalse($contacts[1]->fresh()->acceptsBroadcasts());
        $this->assertDatabaseHas('conversation_events', ['type' => 'opted_out']);

        $next = $this->prepared();
        $this->assertSame(BroadcastRecipient::SKIP_OPTED_OUT, $next->recipients()->where('contact_id', $contacts[1]->id)->value('skip_reason'));
    }

    public function test_a_campaign_whose_template_lost_its_approval_holds(): void
    {
        Contact::factory()->create();
        $broadcast = $this->prepared();
        app(LaunchBroadcast::class)->handle($broadcast);
        $broadcast->template->forceFill(['status' => 'rejected'])->save();

        app(RunBroadcasts::class)->handle();

        $this->assertSame(BroadcastStatus::Paused, $broadcast->fresh()->status);
        $this->assertSame(1, $broadcast->recipients()->where('status', RecipientStatus::Queued)->count());
    }

    public function test_a_supervisor_writes_launches_and_follows_a_campaign_on_the_simulator(): void
    {
        Storage::fake('local');
        $supervisor = User::factory()->supervisor()->create();
        $template = Template::factory()->approved()->create();
        Contact::factory()->count(3)->create();

        $this->actingAs(User::factory()->create())->get(route('broadcasts.index'))->assertForbidden();

        $this->actingAs($supervisor)->post(route('broadcasts.store'), [
            'name' => 'تجربة', 'template_id' => $template->id, 'audience_type' => 'all',
            'variables' => ['body' => [['source' => 'name', 'fallback' => 'عميلنا'], ['source' => 'text']]],
        ])->assertSessionHasErrors('variables.body.1.value');

        $this->actingAs($supervisor)->post(route('broadcasts.store'), [
            'name' => 'تجربة', 'template_id' => $template->id, 'audience_type' => 'csv',
            'file' => UploadedFile::fake()->createWithContent('list.csv', "phone,order\n0551234567,77"),
            'variables' => ['body' => [['source' => 'name', 'fallback' => 'عميلنا'], ['source' => 'column', 'value' => 'order']]],
        ])->assertSessionHasNoErrors();

        $broadcast = Broadcast::query()->sole();
        $this->assertSame(1, $broadcast->recipients()->where('status', RecipientStatus::Queued)->count());

        $this->actingAs($supervisor)->post(route('broadcasts.launch', $broadcast))->assertInertiaFlash('toast.type', 'success');
        $this->actingAs($supervisor)->post(route('broadcasts.tick'));

        $this->actingAs($supervisor)
            ->get(route('broadcasts.show', $broadcast))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('broadcasts/show')
                ->where('broadcast.status', 'completed')
                ->where('stats.sent', 1)
                ->where('sample.body.1', '77'));
    }

    public function test_a_marketing_template_must_let_the_customer_stop(): void
    {
        $form = [
            'name' => 'eid_offer', 'language' => 'ar', 'category' => 'marketing', 'header_type' => 'none',
            'body' => 'عرض العيد يا {{1}} على كل الباقات.', 'body_examples' => ['أحمد'],
            'buttons' => [['type' => 'quick_reply', 'text' => 'اعرف أكثر']],
        ];
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($supervisor)->post(route('templates.store'), $form)->assertSessionHasErrors('buttons');

        $form['buttons'][] = ['type' => 'quick_reply', 'text' => 'إيقاف'];
        $this->actingAs($supervisor)->post(route('templates.store'), $form)->assertSessionHasNoErrors();
    }
}
