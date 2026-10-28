<?php

namespace Tests\Feature\Inbox;

use App\Enums\ConversationEventType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use App\Support\SignedRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RizqCardTest extends TestCase
{
    use RefreshDatabase;

    private const string CARD = 'https://rizq.example/internal/mirsalink/card';

    private const string LEADS = 'https://rizq.example/internal/mirsalink/leads';

    private User $supervisor;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rizq.url' => 'https://rizq.example', 'rizq.secret' => 'shared-secret']);
        $this->supervisor = User::factory()->supervisor()->create(['name' => 'Sara']);
        $contact = Contact::factory()->create(['phone' => '+966501111111', 'name' => 'Fahd']);
        $this->conversation = Conversation::factory()->for($contact)->assignedTo($this->supervisor)->create();
    }

    private function openCard(): AssertableInertia
    {
        $assertion = null;

        $this->actingAs($this->supervisor)
            ->get(route('inbox.show', $this->conversation))
            ->assertInertia(function (AssertableInertia $page) use (&$assertion): void {
                $page->missing('rizqCard')->loadDeferredProps(function (AssertableInertia $reload) use (&$assertion): void {
                    $assertion = $reload;
                });
            });

        return $assertion;
    }

    public function test_the_card_asks_rizq_once_signed_and_keeps_the_answer_a_while(): void
    {
        Http::fake([self::CARD => Http::response([
            'account' => ['type' => 'company_member', 'name' => 'Najd Logistics', 'plan' => null, 'verification' => 'pending'],
            'sales' => ['status' => 'lead', 'owner' => 'Huda'],
        ])]);

        $this->openCard()
            ->where('rizqCard.state', 'found')
            ->where('rizqCard.account.name', 'Najd Logistics')
            ->where('rizqCard.sales.owner', 'Huda');
        $this->openCard()->where('rizqCard.state', 'found');

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $timestamp = (int) $request->header(SignedRequest::TIMESTAMP_HEADER)[0];
            $expected = SignedRequest::headers('POST', $request->url(), $request->body(), 'shared-secret', $timestamp);

            return $request['phone'] === '+966501111111'
                && $request->header(SignedRequest::SIGNATURE_HEADER)[0] === $expected[SignedRequest::SIGNATURE_HEADER];
        });
    }

    public function test_the_card_stays_quiet_when_rizq_is_not_linked_or_down(): void
    {
        Http::fake([self::CARD => Http::response(['message' => 'Server error'], 500)]);

        $this->openCard()->where('rizqCard.state', 'unavailable');

        config(['rizq.secret' => null]);
        $this->openCard()->where('rizqCard.state', 'not_linked');
        Http::assertSentCount(1);
    }

    public function test_a_member_hands_the_customer_to_sales_and_the_thread_says_so(): void
    {
        Http::fake([
            self::LEADS => Http::response(['added' => true, 'status' => 'lead', 'owner' => null], 201),
            self::CARD => Http::response(['account' => null, 'sales' => ['status' => 'lead', 'owner' => null]]),
        ]);

        $this->actingAs($this->supervisor)
            ->post(route('inbox.rizq-lead.store', $this->conversation), [
                'type' => 'company',
                'name' => 'Najd Logistics',
                'note' => 'Asked about company packages.',
            ])
            ->assertInertiaFlash('toast.type', 'success');

        Http::assertSent(fn (Request $request): bool => $request->url() === self::LEADS
            && $request['phone'] === '+966501111111'
            && $request['added_by'] === 'Sara'
            && $request['note'] === 'Asked about company packages.');
        $this->assertDatabaseHas('conversation_events', [
            'conversation_id' => $this->conversation->id,
            'type' => ConversationEventType::SentToSales->value,
        ]);
    }

    public function test_a_customer_sales_already_has_is_named_and_a_silent_rizq_is_said_plainly(): void
    {
        Http::fake([self::LEADS => Http::sequence()
            ->push(['added' => false, 'status' => 'prospect', 'owner' => 'Huda'])
            ->push(['message' => 'Down'], 503)]);
        $lead = ['type' => 'individual', 'name' => 'Fahd'];

        $this->actingAs($this->supervisor)
            ->post(route('inbox.rizq-lead.store', $this->conversation), $lead)
            ->assertInertiaFlash('toast.message', __('app.inbox.rizq.flash.already_owned', ['owner' => 'Huda']));

        $this->actingAs($this->supervisor)
            ->post(route('inbox.rizq-lead.store', $this->conversation), $lead)
            ->assertInertiaFlash('toast.type', 'error');

        $this->assertDatabaseMissing('conversation_events', ['type' => ConversationEventType::SentToSales->value]);
    }
}
