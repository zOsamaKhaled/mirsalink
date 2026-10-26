<?php

namespace Tests\Feature\Templates;

use App\Models\Conversation;
use App\Models\KnowledgeEntry;
use App\Models\QuickReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class QuickRepliesAndKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_keeps_their_own_replies_and_only_a_supervisor_shares_one(): void
    {
        $agent = User::factory()->create();
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($agent)->post(route('quick-replies.store'), [
            'shortcut' => '/Prices', 'title' => 'الأسعار', 'body' => 'أهلًا {name}، الأسعار تبدأ من 500 ريال.', 'shared' => true,
        ])->assertSessionHasNoErrors();

        $mine = QuickReply::query()->sole();
        $this->assertSame('prices', $mine->shortcut);
        $this->assertSame($agent->id, $mine->user_id);

        $this->actingAs($supervisor)->post(route('quick-replies.store'), [
            'shortcut' => 'welcome', 'title' => 'ترحيب', 'body' => 'أهلًا بك، معك {agent}.', 'shared' => true,
        ]);
        $shared = QuickReply::query()->where('shortcut', 'welcome')->sole();
        $this->assertTrue($shared->isShared());

        $this->actingAs($agent)
            ->post(route('quick-replies.store'), ['shortcut' => 'welcome', 'title' => 'x', 'body' => 'y'])
            ->assertSessionHasErrors('shortcut');
        $this->actingAs($agent)->put(route('quick-replies.update', $shared), ['shortcut' => 'hi', 'title' => 'x', 'body' => 'y'])->assertForbidden();
        $this->actingAs($supervisor)->delete(route('quick-replies.destroy', $mine))->assertForbidden();
    }

    public function test_the_inbox_offers_a_member_their_replies_and_the_teams_but_not_a_colleagues(): void
    {
        [$sara, $khalid] = User::factory()->count(2)->create()->all();
        QuickReply::factory()->ownedBy($sara)->create(['shortcut' => 'sara']);
        QuickReply::factory()->ownedBy($khalid)->create(['shortcut' => 'khalid']);
        QuickReply::factory()->create(['shortcut' => 'team']);
        $conversation = Conversation::factory()->assignedTo($sara)->create();

        $this->actingAs($sara)
            ->get(route('inbox.show', $conversation))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('quickReplies', 2)
                ->where('quickReplies.0.shortcut', 'sara')
                ->where('quickReplies.1.shortcut', 'team'));
    }

    public function test_supervisors_keep_the_knowledge_base_and_confirm_what_is_still_true(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $agent = User::factory()->create();
        $stale = KnowledgeEntry::factory()->stale()->create();
        KnowledgeEntry::factory()->create();

        $this->actingAs($supervisor)->post(route('knowledge.store'), [
            'question' => 'هل يمكن الدفع بالتقسيط؟', 'answer' => 'نعم، على أربع دفعات عبر تابي.', 'source' => 'سياسة الدفع', 'active' => true,
        ])->assertSessionHasNoErrors();

        $this->actingAs($agent)
            ->get(route('knowledge.index', ['show' => 'review']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('knowledge/index')
                ->where('counts.all', 3)
                ->where('counts.review', 1)
                ->has('entries.data', 1)
                ->where('entries.data.0.needs_review', true));

        $this->actingAs($supervisor)->post(route('knowledge.review', $stale));
        $this->assertFalse($stale->fresh()->needsReview());
        $this->assertSame($supervisor->id, $stale->fresh()->reviewed_by);

        $this->actingAs($agent)->post(route('knowledge.store'), ['question' => 'x', 'answer' => 'y'])->assertForbidden();
        $this->actingAs($agent)->delete(route('knowledge.destroy', $stale))->assertForbidden();

        $this->actingAs($agent)
            ->get(route('knowledge.index', ['q' => 'تابي']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('entries.data', 1));
    }
}
