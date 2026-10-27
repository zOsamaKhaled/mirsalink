<?php

namespace Tests\Feature\Reports;

use App\Enums\ConversationEventType;
use App\Enums\MessageDirection;
use App\Enums\MessageSender;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\Message;
use App\Models\Tag;
use App\Models\User;
use App\Support\TeamReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("2026-10-25 {$time}", 'UTC');
    }

    private function write(Conversation $conversation, string $time, string $from, ?User $member = null, MessageType $type = MessageType::Text): void
    {
        Message::factory()->for($conversation)->create([
            'direction' => $from === 'customer' ? MessageDirection::Inbound : MessageDirection::Outbound,
            'sender' => match ($from) {
                'customer' => MessageSender::Contact,
                'system' => MessageSender::System,
                default => MessageSender::Agent,
            },
            'user_id' => $member?->id,
            'type' => $type,
            'created_at' => $this->at($time),
        ]);
    }

    private function report(): TeamReport
    {
        return TeamReport::forDays('2026-10-25', '2026-10-25', 'UTC');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo($this->at('20:00'));
    }

    public function test_a_reply_time_runs_from_the_first_waiting_message_to_a_members_reply(): void
    {
        $sara = User::factory()->create();
        $conversation = Conversation::factory()->assignedTo($sara)->create();

        $this->write($conversation, '10:00:00', 'customer');
        $this->write($conversation, '10:01:00', 'system');
        $this->write($conversation, '10:02:00', 'customer');
        $this->write($conversation, '10:05:00', 'agent', $sara, MessageType::Note);
        $this->write($conversation, '10:10:00', 'agent', $sara);
        $this->write($conversation, '10:12:00', 'agent', $sara);
        $this->write($conversation, '11:00:00', 'customer');
        $this->write($conversation, '11:30:00', 'agent', $sara);

        $report = $this->report();
        $overview = $report->overview();

        $this->assertSame([600, 1800], array_column($report->replies(), 'seconds'));
        $this->assertSame(1200, $overview['average_reply']);
        $this->assertSame(1200, $overview['median_reply']);
        $this->assertSame(50.0, $overview['within_target']);
        $this->assertSame(3, $overview['replies']);
        $this->assertSame(3, $overview['inbound']);
        $this->assertSame(1, $overview['customers']);

        $row = collect($report->members())->firstWhere('id', $sara->id);
        $this->assertSame(3, $row['messages']);
        $this->assertSame(1, $row['conversations']);
        $this->assertSame(1, $row['open_now']);
    }

    public function test_a_close_time_runs_from_when_the_conversation_last_opened(): void
    {
        $khalid = User::factory()->create();
        $conversation = Conversation::factory()->closed()->create(['created_at' => $this->at('09:00')]);

        foreach ([['10:00', ConversationEventType::Closed], ['12:00', ConversationEventType::Reopened], ['12:30', ConversationEventType::Closed]] as [$time, $type]) {
            ConversationEvent::query()->create([
                'conversation_id' => $conversation->id,
                'type' => $type,
                'actor_id' => $type === ConversationEventType::Closed ? $khalid->id : null,
                'created_at' => $this->at($time),
            ]);
        }

        $report = $this->report();

        $this->assertSame([3600, 1800], array_column($report->closures(), 'seconds'));
        $this->assertSame(2, collect($report->members())->firstWhere('id', $khalid->id)['closed']);
    }

    public function test_customers_are_counted_by_tag_and_by_the_teams_hour(): void
    {
        $sales = Tag::findOrMake('عميل محتمل');
        $tagged = Conversation::factory()->create();
        $tagged->tags()->attach($sales);
        $this->write($tagged, '07:15:00', 'customer');
        $this->write(Conversation::factory()->create(), '07:40:00', 'customer');

        $report = TeamReport::forDays('2026-10-25', '2026-10-25', 'Asia/Riyadh');

        $this->assertSame(1, $report->tags()['tags'][0]['customers']);
        $this->assertSame(1, $report->tags()['untagged']);
        // 07:15 UTC on Sunday is 10:15 in Riyadh.
        $this->assertSame(2, $report->hours()[0][10]);
        $this->assertSame(2, collect($report->days())->firstWhere('day', '2026-10-25')['inbound']);
    }

    public function test_supervisors_see_reports_and_download_each_table(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->write(Conversation::factory()->create(), '10:00:00', 'customer');

        $this->actingAs(User::factory()->create())->get(route('reports.index'))->assertForbidden();

        $this->actingAs($supervisor)
            ->get(route('reports.index', ['preset' => 'custom', 'from' => '2026-10-20', 'to' => '2026-10-25']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('reports/index')
                ->where('period.from', '2026-10-20')
                ->where('overview.customers', 1)
                ->has('days', 6)
                ->has('hours', 7));

        $this->actingAs($supervisor)
            ->get(route('reports.index', ['preset' => 'custom', 'from' => '2026-10-25', 'to' => '2026-10-20']))
            ->assertSessionHasErrors('to');

        $csv = $this->actingAs($supervisor)->get(route('reports.export', 'members'))->streamedContent();
        $this->assertStringStartsWith("\u{FEFF}", $csv);
        $this->assertStringContainsString(__('app.reports.export.member'), $csv);

        $this->actingAs($supervisor)->get(route('reports.export', 'nothing'))->assertNotFound();
    }

    public function test_the_home_page_shows_an_agent_their_own_day(): void
    {
        $sara = User::factory()->create();
        $mine = Conversation::factory()->assignedTo($sara)->create();
        Conversation::factory()->create();
        $this->write($mine, '10:00:00', 'customer');
        $this->write($mine, '10:04:00', 'agent', $sara);

        $this->actingAs($sara)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.mine', true)
                ->where('stats.open', 1)
                ->where('stats.waiting', 1)
                ->where('stats.reply', 240));
    }

    public function test_a_supervisor_narrows_the_inbox_to_one_member(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        [$sara, $khalid] = User::factory()->count(2)->create()->all();
        Conversation::factory()->assignedTo($sara)->create();
        Conversation::factory()->assignedTo($khalid)->count(2)->create();

        $this->actingAs($supervisor)
            ->get(route('inbox.index', ['tab' => 'all', 'member' => $khalid->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.member', $khalid->id)
                ->has('conversations', 2));

        $this->actingAs($sara)
            ->get(route('inbox.index', ['member' => $khalid->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('filters.member', null));
    }
}
