<?php

namespace Tests\Feature\WhatsApp;

use App\Actions\Inbox\SendMessage;
use App\Actions\Inbox\SendTemplate;
use App\Actions\Templates\SubmitTemplate;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\TemplateHeader;
use App\Enums\TemplateStatus;
use App\Exceptions\TemplateSubmissionFailed;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Template;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\BroadcastQuota;
use App\WhatsApp\CloudGateway;
use App\WhatsApp\Gateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CloudApiTest extends TestCase
{
    use RefreshDatabase;

    private const string GRAPH = 'https://graph.facebook.com/v23.0';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.gateway' => 'cloud',
            'whatsapp.cloud.token' => 'test-token',
            'whatsapp.cloud.phone_number_id' => '1111',
            'whatsapp.cloud.business_account_id' => '2222',
            'whatsapp.cloud.app_secret' => 'app-secret',
            'whatsapp.cloud.verify_token' => 'verify-me',
        ]);
    }

    /**
     * Meta's webhook shape around one change.
     *
     * @param  array<string, mixed>  $value
     */
    private function notify(string $field, array $value): TestResponse
    {
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => '2222', 'changes' => [['field' => $field, 'value' => $value]]]]]);

        return $this->call('POST', route('webhooks.whatsapp.receive'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'app-secret'),
        ], $body);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function customerMessage(array $message, string $name = 'Salman'): TestResponse
    {
        return $this->notify('messages', [
            'messaging_product' => 'whatsapp',
            'metadata' => ['display_phone_number' => '966500000000', 'phone_number_id' => '1111'],
            'contacts' => [['profile' => ['name' => $name], 'wa_id' => '966551234567']],
            'messages' => [['from' => '966551234567', 'timestamp' => '1761530000', ...$message]],
        ]);
    }

    public function test_meta_checks_the_address_with_the_verify_token(): void
    {
        $this->get(route('webhooks.whatsapp.verify', ['hub.mode' => 'subscribe', 'hub.verify_token' => 'verify-me', 'hub.challenge' => '1158201444']))
            ->assertOk()
            ->assertSeeText('1158201444');

        $this->get(route('webhooks.whatsapp.verify', ['hub.mode' => 'subscribe', 'hub.verify_token' => 'guess', 'hub.challenge' => 'x']))
            ->assertForbidden();
    }

    public function test_an_unsigned_notification_is_turned_away_and_not_kept(): void
    {
        $this->postJson(route('webhooks.whatsapp.receive'), ['entry' => []], ['X-Hub-Signature-256' => 'sha256=forged'])
            ->assertForbidden();

        $this->assertSame(0, WebhookEvent::query()->count());
    }

    public function test_a_customers_text_reaches_the_inbox_once_however_often_meta_sends_it(): void
    {
        User::factory()->create();
        $message = ['id' => 'wamid.HBgM1', 'type' => 'text', 'text' => ['body' => 'السلام عليكم، كم السعر؟']];

        $this->customerMessage($message)->assertOk()->assertSeeText('EVENT_RECEIVED');
        $this->customerMessage($message)->assertOk();

        $contact = Contact::query()->sole();
        $this->assertSame('+966551234567', $contact->phone);
        $this->assertSame('Salman', $contact->name);
        $this->assertSame(1, Message::query()->count());
        $this->assertSame('السلام عليكم، كم السعر؟', Message::query()->sole()->body);
        $this->assertSame(2, WebhookEvent::query()->whereNotNull('processed_at')->count());
    }

    public function test_a_file_is_fetched_from_meta_at_once_onto_the_private_disk(): void
    {
        Storage::fake('local');
        Http::fake([
            self::GRAPH.'/MEDIA1' => Http::response(['url' => 'https://lookaside.fbsbx.com/whatsapp/MEDIA1', 'mime_type' => 'application/pdf', 'file_size' => 9]),
            'https://lookaside.fbsbx.com/*' => Http::response('%PDF-1.7', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $this->customerMessage([
            'id' => 'wamid.DOC', 'type' => 'document',
            'document' => ['id' => 'MEDIA1', 'filename' => 'عقد.pdf', 'mime_type' => 'application/pdf', 'caption' => 'العقد'],
        ]);

        $message = Message::query()->sole();
        $this->assertSame(MessageType::Document, $message->type);
        $this->assertSame('عقد.pdf', $message->media_name);
        $this->assertSame('العقد', $message->body);
        Storage::disk('local')->assertExists($message->media_path);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'lookaside') && $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_a_notification_that_fails_is_kept_and_read_again_later(): void
    {
        Storage::fake('local');
        Http::fake([
            self::GRAPH.'/MEDIA2' => Http::sequence()
                ->push(['error' => ['message' => 'Temporary', 'code' => 2]], 500)
                ->push(['url' => 'https://lookaside.fbsbx.com/whatsapp/MEDIA2', 'mime_type' => 'image/jpeg']),
            'https://lookaside.fbsbx.com/*' => Http::response('jpeg-bytes'),
        ]);

        $this->customerMessage(['id' => 'wamid.IMG', 'type' => 'image', 'image' => ['id' => 'MEDIA2', 'mime_type' => 'image/jpeg']]);

        $event = WebhookEvent::query()->sole();
        $this->assertNull($event->processed_at);
        $this->assertSame(0, Message::query()->count());

        $this->travel(2)->minutes();
        $this->artisan('mirsalink:webhooks')->assertSuccessful();

        $this->assertNotNull($event->fresh()->processed_at);
        $this->assertSame(MessageType::Image, Message::query()->sole()->type);
    }

    public function test_delivery_reading_and_failure_come_back_onto_what_the_team_sent(): void
    {
        $sent = Message::factory()->fromAgent(User::factory()->create())->create(['external_id' => 'wamid.OUT1', 'status' => MessageStatus::Sent]);
        $other = Message::factory()->fromAgent(User::factory()->create())->create(['external_id' => 'wamid.OUT2', 'status' => MessageStatus::Sent]);

        $this->notify('messages', ['statuses' => [
            ['id' => 'wamid.OUT1', 'status' => 'read', 'recipient_id' => '966551234567'],
            ['id' => 'wamid.OUT1', 'status' => 'delivered', 'recipient_id' => '966551234567'],
            ['id' => 'wamid.OUT2', 'status' => 'failed', 'errors' => [['code' => 131047, 'title' => 'Re-engagement message']]],
        ]]);

        $this->assertSame(MessageStatus::Read, $sent->fresh()->status);
        $this->assertSame(MessageStatus::Failed, $other->fresh()->status);
        $this->assertSame(__('app.whatsapp.errors.131047'), $other->fresh()->error_message);
    }

    public function test_metas_verdict_on_a_template_and_the_numbers_tier_are_recorded(): void
    {
        $template = Template::factory()->pending()->create(['external_id' => '9001']);

        $this->notify('message_template_status_update', [
            'event' => 'REJECTED', 'message_template_id' => 9001, 'message_template_name' => $template->name,
            'message_template_language' => 'ar', 'reason' => 'INCORRECT_CATEGORY',
        ]);
        $this->notify('phone_number_quality_update', ['display_phone_number' => '966500000000', 'event' => 'UPGRADE', 'current_limit' => 'TIER_10K']);

        $this->assertSame(TemplateStatus::Rejected, $template->fresh()->status);
        $this->assertSame('INCORRECT_CATEGORY', $template->fresh()->rejection_reason);
        $this->assertSame(10000, BroadcastQuota::dailyLimit());
    }

    public function test_a_reply_goes_to_meta_as_text_and_a_file_is_uploaded_first(): void
    {
        Storage::fake('local');
        Http::fake([
            self::GRAPH.'/1111/media' => Http::response(['id' => 'UPLOADED1']),
            self::GRAPH.'/1111/messages' => Http::sequence()
                ->push(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.SENT']]])
                ->push(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.SENT2']]]),
        ]);
        $agent = User::factory()->create();
        $conversation = Conversation::factory()->assignedTo($agent)->for(Contact::factory()->state(['phone' => '+966551234567']))->create();

        $this->assertInstanceOf(CloudGateway::class, app(Gateway::class));

        $text = app(SendMessage::class)->handle($conversation, $agent, 'أهلًا بك');
        $file = app(SendMessage::class)->handle($conversation, $agent, 'العرض', UploadedFile::fake()->create('offer.pdf', 10, 'application/pdf'));

        $this->assertSame('wamid.SENT', $text->external_id);
        $this->assertSame(MessageStatus::Sent, $file->status);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::GRAPH.'/1111/messages'
            && $request['to'] === '966551234567'
            && $request['type'] === 'text'
            && $request['text']['body'] === 'أهلًا بك'
            && $request->hasHeader('Authorization', 'Bearer test-token'));
        Http::assertSent(fn (Request $request): bool => $request->url() === self::GRAPH.'/1111/messages'
            && ($request['type'] ?? null) === 'document'
            && $request['document'] === ['id' => 'UPLOADED1', 'caption' => 'العرض', 'filename' => 'offer.pdf']);
    }

    public function test_metas_refusal_marks_the_message_failed_with_a_reason_people_can_read(): void
    {
        Http::fake([self::GRAPH.'/1111/messages' => Http::response(['error' => [
            'message' => '(#131047) Re-engagement message', 'type' => 'OAuthException', 'code' => 131047,
        ]], 400)]);
        $agent = User::factory()->create();
        $conversation = Conversation::factory()->assignedTo($agent)->create();

        $message = app(SendMessage::class)->handle($conversation, $agent, 'مرحبا');

        $this->assertSame(MessageStatus::Failed, $message->status);
        $this->assertSame('131047', $message->error_code);
        $this->assertSame(__('app.whatsapp.errors.131047'), $message->error_message);
    }

    public function test_a_template_goes_out_with_its_values_and_goes_to_review_with_its_examples(): void
    {
        Http::fake([
            self::GRAPH.'/1111/messages' => Http::response(['messages' => [['id' => 'wamid.TPL']]]),
            self::GRAPH.'/2222/message_templates' => Http::response(['id' => '7788', 'status' => 'PENDING', 'category' => 'UTILITY']),
        ]);
        $agent = User::factory()->create();
        $conversation = Conversation::factory()->assignedTo($agent)->windowClosed()->create();
        $template = Template::factory()->approved()->create([
            'header_type' => TemplateHeader::Text, 'header_text' => 'طلب رقم {{1}}', 'header_example' => '1024',
            'buttons' => [['type' => 'url', 'text' => 'تتبع', 'url' => 'https://rizq.sa/o/{{1}}', 'example' => '1024']],
        ]);

        app(SendTemplate::class)->handle($conversation, $agent, $template, ['body' => ['خالد', '77'], 'header' => '77', 'button' => '77']);

        Http::assertSent(fn (Request $request): bool => ($request['type'] ?? null) === 'template'
            && $request['template']['name'] === $template->name
            && $request['template']['language'] === ['code' => 'ar']
            && $request['template']['components'] === [
                ['type' => 'header', 'parameters' => [['type' => 'text', 'text' => '77']]],
                ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => 'خالد'], ['type' => 'text', 'text' => '77']]],
                ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => '77']]],
            ]);

        $draft = Template::factory()->create();
        app(SubmitTemplate::class)->handle($draft);

        $this->assertSame('7788', $draft->fresh()->external_id);
        $this->assertSame(TemplateStatus::Pending, $draft->fresh()->status);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::GRAPH.'/2222/message_templates'
            && $request['category'] === 'UTILITY'
            && $request['components'][0] === ['type' => 'BODY', 'text' => $draft->body, 'example' => ['body_text' => [['أحمد', '1024']]]]);

        $this->expectException(TemplateSubmissionFailed::class);
        app(SubmitTemplate::class)->handle(Template::factory()->create(['header_type' => TemplateHeader::Image]));
    }

    public function test_supervisors_see_the_connection_without_its_secrets(): void
    {
        Http::fake([self::GRAPH.'/1111*' => Http::response([
            'display_phone_number' => '+966 50 000 0000', 'verified_name' => 'رزق', 'quality_rating' => 'GREEN', 'messaging_limit_tier' => 'TIER_1K',
        ])]);

        $this->actingAs(User::factory()->create())->get(route('whatsapp.index'))->assertForbidden();

        $response = $this->actingAs(User::factory()->supervisor()->create())->get(route('whatsapp.index'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('whatsapp/index')
            ->where('gateway', 'cloud')
            ->where('keys.token', true)
            ->where('number.quality', 'GREEN')
            ->where('number.tier', 'TIER_1K'));
        $response->assertDontSee('test-token');
        $response->assertDontSee('app-secret');
        $this->assertSame(1000, BroadcastQuota::dailyLimit());
    }
}
