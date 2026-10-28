<?php

namespace Tests\Feature\Codes;

use App\Codes\CodeSettings;
use App\Enums\MessageStatus;
use App\Enums\TemplateStatus;
use App\Models\CodeDelivery;
use App\Models\User;
use App\Support\SignedRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CodesTest extends TestCase
{
    use RefreshDatabase;

    private const string GRAPH = 'https://graph.facebook.com/v23.0';

    private const string SECRET = 'shared-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'rizq.url' => 'https://rizq.example',
            'rizq.secret' => self::SECRET,
            'whatsapp.gateway' => 'cloud',
            'whatsapp.cloud.token' => 'test-token',
            'whatsapp.cloud.phone_number_id' => '1111',
            'whatsapp.cloud.business_account_id' => '2222',
            'whatsapp.cloud.app_secret' => 'app-secret',
            'whatsapp.cloud.verify_token' => 'verify-me',
        ]);
    }

    /**
     * Rizq's request, signed as Rizq signs it.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>|null  $headers
     */
    private function askForCode(array $payload = [], ?array $headers = null): TestResponse
    {
        $body = (string) json_encode([
            'reference' => 'otp-1',
            'phone' => '0551234567',
            'code' => '482915',
            'language' => 'ar',
            'purpose' => 'login',
            ...$payload,
        ]);
        $url = route('internal.codes.store');
        $headers ??= SignedRequest::headers('POST', $url, $body, self::SECRET);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', $url, [], [], [], $server, $body);
    }

    private function approveTemplate(string $language = 'ar'): void
    {
        CodeSettings::recordSubmission($language, 'tpl-'.$language);
        CodeSettings::recordDecision($language, TemplateStatus::Approved);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function notify(string $field, array $value): TestResponse
    {
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => '2222', 'changes' => [['field' => $field, 'value' => $value]]]]]);

        return $this->call('POST', route('webhooks.whatsapp.receive'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', (string) $body, 'app-secret'),
        ], $body);
    }

    public function test_only_a_request_signed_by_rizq_and_seen_once_gets_through(): void
    {
        $this->approveTemplate();
        Http::fake([self::GRAPH.'/1111/messages' => Http::response(['messages' => [['id' => 'wamid.code']]])]);

        $this->askForCode(headers: [])->assertUnauthorized();
        $this->askForCode(headers: SignedRequest::headers('POST', route('internal.codes.store'), '{}', self::SECRET))->assertUnauthorized();
        $this->askForCode(headers: SignedRequest::headers('POST', route('internal.codes.store'), (string) json_encode([
            'reference' => 'otp-1', 'phone' => '0551234567', 'code' => '482915', 'language' => 'ar', 'purpose' => 'login',
        ]), self::SECRET, now()->subMinutes(10)->timestamp))->assertUnauthorized();

        $body = (string) json_encode(['reference' => 'otp-1', 'phone' => '0551234567', 'code' => '482915', 'language' => 'ar', 'purpose' => 'login']);
        $headers = SignedRequest::headers('POST', route('internal.codes.store'), $body, self::SECRET);

        $this->askForCode(headers: $headers)->assertCreated();
        // The very same request, copied and sent again.
        $this->askForCode(headers: $headers)->assertUnauthorized();
    }

    public function test_a_code_goes_out_with_metas_authentication_template_and_is_never_kept(): void
    {
        $this->approveTemplate();
        Http::fake([self::GRAPH.'/1111/messages' => Http::response(['messages' => [['id' => 'wamid.code']]])]);

        $this->askForCode()->assertCreated()->assertJson(['status' => 'sent', 'language' => 'ar']);

        Http::assertSent(fn (Request $request): bool => $request['to'] === '966551234567'
            && $request['template']['name'] === 'verification_code'
            && $request['template']['components'][0]['parameters'][0]['text'] === '482915'
            && $request['template']['components'][1]['sub_type'] === 'url');

        $delivery = CodeDelivery::query()->sole();
        $this->assertSame('+966551234567', $delivery->phone);
        $this->assertSame('wamid.code', $delivery->external_id);
        $this->assertStringNotContainsString('482915', (string) json_encode($delivery->toArray()));
        $this->assertDatabaseCount('messages', 0);

        // Rizq asking again with the same reference, a moment later, sends nothing more.
        $this->travel(2)->seconds();
        $this->askForCode()->assertOk();
        Http::assertSentCount(1);
    }

    public function test_rizq_is_told_to_use_email_when_whatsapp_cannot_be_used(): void
    {
        Http::fake([self::GRAPH.'/1111/messages' => Http::response(['error' => ['code' => 131026, 'message' => 'Message undeliverable']], 400)]);

        // No approved template yet.
        $this->askForCode()->assertStatus(503)->assertJson(['error' => 'unavailable']);

        // Approved in English only: the code goes out in English.
        $this->approveTemplate('en');
        $this->askForCode(['reference' => 'otp-2'])->assertStatus(502)->assertJson(['error' => 'refused']);
        $refused = CodeDelivery::query()->where('reference', 'otp-2')->sole();
        $this->assertSame('en', $refused->language);
        $this->assertSame(MessageStatus::Failed, $refused->status);

        CodeSettings::setEnabled(false);
        $this->askForCode(['reference' => 'otp-3'])->assertStatus(503);
    }

    public function test_a_number_asking_too_often_is_refused(): void
    {
        $this->approveTemplate();
        CodeDelivery::factory()->count(5)->create(['phone' => '+966551234567']);

        $this->askForCode()->assertStatus(429)->assertJson(['error' => 'too_many']);
    }

    public function test_a_code_that_fails_on_the_way_goes_back_to_rizq_once(): void
    {
        $delivery = CodeDelivery::factory()->create(['reference' => 'otp-9', 'external_id' => 'wamid.code']);
        Http::fake(['https://rizq.example/*' => Http::response(['message' => 'Handled.'])]);

        $this->notify('messages', ['statuses' => [['id' => 'wamid.code', 'status' => 'delivered', 'timestamp' => (string) now()->timestamp]]])->assertOk();
        Http::assertNothingSent();

        $failed = ['statuses' => [['id' => 'wamid.code', 'status' => 'failed', 'timestamp' => (string) now()->timestamp, 'errors' => [['code' => 131026, 'title' => 'Message undeliverable']]]]];
        $this->notify('messages', $failed)->assertOk();
        $this->notify('messages', $failed)->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            $timestamp = (int) $request->header(SignedRequest::TIMESTAMP_HEADER)[0];
            $expected = SignedRequest::headers('POST', $request->url(), $request->body(), self::SECRET, $timestamp);

            return $path === '/webhooks/mirsalink/codes'
                && $request['reference'] === 'otp-9'
                && $request->header(SignedRequest::SIGNATURE_HEADER)[0] === $expected[SignedRequest::SIGNATURE_HEADER];
        });
        $this->assertNotNull($delivery->fresh()->rizq_notified_at);
    }

    public function test_the_template_goes_to_meta_as_authentication_and_its_verdict_comes_back(): void
    {
        Http::fake([self::GRAPH.'/2222/message_templates' => Http::sequence()
            ->push(['id' => 'tpl-ar', 'status' => 'PENDING'])
            ->push(['id' => 'tpl-en', 'status' => 'PENDING'])]);
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($supervisor)->post(route('codes.templates.submit'))->assertInertiaFlash('toast.type', 'success');

        Http::assertSent(fn (Request $request): bool => $request['category'] === 'AUTHENTICATION'
            && $request['language'] === 'ar'
            && $request['components'][2]['buttons'][0]['otp_type'] === 'COPY_CODE');
        $this->assertSame(TemplateStatus::Pending, CodeSettings::current()->status('en'));

        $this->notify('message_template_status_update', [
            'event' => 'APPROVED',
            'message_template_id' => 999,
            'message_template_name' => 'verification_code',
            'message_template_language' => 'ar',
        ])->assertOk();

        $this->assertSame(TemplateStatus::Approved, CodeSettings::current()->status('ar'));
        $this->assertSame('ar', CodeSettings::current()->languageFor('en'));
    }

    public function test_supervisors_see_the_codes_page_and_play_meta_on_the_simulator(): void
    {
        config(['whatsapp.gateway' => 'simulator']);
        Http::fake(['https://rizq.example/*' => Http::response()]);
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs(User::factory()->create())->get(route('codes.index'))->assertForbidden();

        $this->actingAs($supervisor)->post(route('codes.templates.submit'));
        $this->actingAs($supervisor)->put(route('codes.templates.decide', 'ar'), ['decision' => 'approved']);
        $this->assertSame('ar', CodeSettings::current()->languageFor('ar'));

        $this->askForCode()->assertCreated();
        $delivery = CodeDelivery::query()->sole();

        $this->actingAs($supervisor)->put(route('codes.deliveries.status', $delivery), ['status' => 'failed']);
        $this->assertNotNull($delivery->fresh()->rizq_notified_at);

        $this->actingAs($supervisor)
            ->get(route('codes.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('codes/index')
                ->where('simulator', true)
                ->where('rizq.secret', true)
                ->where('week.to_email', 1)
                ->where('deliveries.0.phone', '+9665•••567')
                ->missing('deliveries.0.code'));

        $this->actingAs($supervisor)->put(route('codes.update'), ['enabled' => false]);
        $this->assertFalse(CodeSettings::current()->enabled);
    }
}
