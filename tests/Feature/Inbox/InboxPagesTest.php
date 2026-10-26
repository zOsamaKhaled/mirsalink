<?php

namespace Tests\Feature\Inbox;

use App\Enums\MessageSender;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InboxPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_agent_sees_their_own_and_the_unassigned_but_not_a_colleagues(): void
    {
        [$sara, $khalid] = User::factory()->count(2)->create()->all();
        $mine = Conversation::factory()->assignedTo($sara)->create();
        Conversation::factory()->create();
        $theirs = Conversation::factory()->assignedTo($khalid)->create();

        $this->actingAs($sara)
            ->get(route('inbox.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('inbox/index')
                ->where('tab', 'mine')
                ->where('tabs', ['mine', 'unassigned', 'closed'])
                ->has('conversations', 1)
                ->where('conversations.0.id', $mine->id)
                ->where('counts.mine', 1)
                ->where('counts.unassigned', 1)
                ->missing('counts.all'));

        $this->actingAs($sara)
            ->get(route('inbox.show', $theirs))
            ->assertRedirect(route('inbox.index'))
            ->assertInertiaFlash('toast.type', 'info');
    }

    public function test_opening_their_own_conversation_marks_it_read_with_the_thread(): void
    {
        $agent = User::factory()->create();
        $conversation = Conversation::factory()->assignedTo($agent)->create(['unread_count' => 2]);
        Message::factory()->for($conversation)->create(['body' => 'مرحبا']);

        $this->actingAs($agent)
            ->get(route('inbox.show', $conversation))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selected.id', $conversation->id)
                ->where('selected.window_open', true)
                ->where('thread.0.body', 'مرحبا'));

        $this->assertSame(0, $conversation->fresh()->unread_count);
    }

    public function test_a_supervisor_sees_everyone_and_who_has_waited_too_long(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $agent = User::factory()->create();
        Conversation::factory()->assignedTo($agent)->create(['awaiting_reply_since' => now()->subMinutes(40)]);
        Conversation::factory()->assignedTo($agent)->create(['awaiting_reply_since' => now()->subMinutes(2)]);

        $this->actingAs($supervisor)
            ->get(route('inbox.index', ['tab' => 'waiting']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tab', 'waiting')
                ->where('counts.waiting', 1)
                ->where('counts.all', 2)
                ->has('conversations', 1)
                ->where('conversations.0.waiting_long', true));
    }

    public function test_search_and_tag_filter_narrow_the_list(): void
    {
        $agent = User::factory()->create();
        $sales = Tag::findOrMake('مبيعات');
        $fahad = Conversation::factory()->assignedTo($agent)
            ->for(Contact::factory()->state(['name' => 'فهد العتيبي']))
            ->create();
        $fahad->tags()->attach($sales->id);
        Conversation::factory()->assignedTo($agent)->for(Contact::factory()->state(['name' => 'نورة']))->create();

        $this->actingAs($agent)
            ->get(route('inbox.index', ['q' => 'فهد']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('conversations', 1));

        $this->actingAs($agent)
            ->get(route('inbox.index', ['tag' => $sales->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('conversations', 1)
                ->where('conversations.0.id', $fahad->id));
    }

    public function test_replying_and_writing_a_note_from_the_composer(): void
    {
        Storage::fake('local');
        $agent = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $this->actingAs($agent)
            ->post(route('inbox.messages.store', $conversation), ['body' => 'أهلًا بك'])
            ->assertRedirect();

        $this->actingAs($agent)
            ->post(route('inbox.messages.store', $conversation), ['note' => true, 'body' => 'عميل مهم'])
            ->assertRedirect();

        $this->actingAs($agent)
            ->post(route('inbox.messages.store', $conversation), ['file' => UploadedFile::fake()->image('menu.jpg')])
            ->assertRedirect();

        $this->assertSame($agent->id, $conversation->fresh()->assigned_to);
        $this->assertSame(
            [MessageType::Text, MessageType::Note, MessageType::Image],
            $conversation->messages()->orderBy('id')->pluck('type')->all(),
        );
    }

    public function test_an_empty_reply_is_refused_and_a_late_one_explains_the_window(): void
    {
        $agent = User::factory()->create();
        $stale = Conversation::factory()->assignedTo($agent)->create(['last_inbound_at' => now()->subDays(2)]);

        $this->actingAs($agent)
            ->post(route('inbox.messages.store', $stale), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->actingAs($agent)
            ->post(route('inbox.messages.store', $stale), ['body' => 'مرحبا'])
            ->assertInertiaFlash('toast.message', __('app.inbox.rules.window_closed'));

        $this->assertSame(0, $stale->messages()->count());
    }

    public function test_an_agent_takes_an_unassigned_conversation_but_cannot_hand_it_to_others(): void
    {
        [$sara, $khalid] = User::factory()->count(2)->create()->all();
        $conversation = Conversation::factory()->create();

        $this->actingAs($sara)
            ->put(route('inbox.assignee.update', $conversation), ['user_id' => $khalid->id])
            ->assertInertiaFlash('toast.type', 'error');
        $this->assertNull($conversation->fresh()->assigned_to);

        $this->actingAs($sara)->put(route('inbox.assignee.update', $conversation), ['user_id' => $sara->id]);
        $this->assertSame($sara->id, $conversation->fresh()->assigned_to);

        $this->actingAs($sara)->put(route('inbox.assignee.update', $conversation), ['user_id' => $khalid->id]);
        $this->assertSame($khalid->id, $conversation->fresh()->assigned_to);
    }

    public function test_tagging_closing_and_reopening(): void
    {
        $agent = User::factory()->create();
        $conversation = Conversation::factory()->assignedTo($agent)->create();
        $tag = Tag::findOrMake('دعم فني');

        $this->actingAs($agent)->post(route('inbox.tags.store', $conversation), ['tag_id' => $tag->id]);
        $this->assertTrue($conversation->tags()->whereKey($tag->id)->exists());

        $this->actingAs($agent)->delete(route('inbox.tags.destroy', [$conversation, $tag]));
        $this->assertFalse($conversation->tags()->whereKey($tag->id)->exists());

        $this->actingAs($agent)->post(route('inbox.close', $conversation))->assertInertiaFlash('toast.type', 'success');
        $this->assertFalse($conversation->fresh()->isOpen());

        $this->actingAs($agent)->post(route('inbox.reopen', $conversation));
        $this->assertTrue($conversation->fresh()->isOpen());
        $this->assertSame($agent->id, $conversation->fresh()->assigned_to);
    }

    public function test_files_open_only_for_members_who_see_the_conversation(): void
    {
        Storage::fake('local');
        [$sara, $khalid] = User::factory()->count(2)->create()->all();
        $conversation = Conversation::factory()->assignedTo($sara)->create();
        Storage::disk('local')->put('media/1/menu.jpg', 'image');
        $message = Message::factory()->for($conversation)->create([
            'type' => MessageType::Image,
            'media_path' => 'media/1/menu.jpg',
            'media_mime' => 'image/jpeg',
            'media_name' => 'menu.jpg',
        ]);

        $this->actingAs($sara)->get(route('inbox.media', $message))->assertOk();
        $this->actingAs($khalid)->get(route('inbox.media', $message))->assertForbidden();
    }

    public function test_the_simulator_is_for_supervisors_and_plays_both_sides_of_whatsapp(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $agent = User::factory()->create();

        $this->actingAs($agent)->get(route('simulator.index'))->assertForbidden();

        $this->actingAs($supervisor)
            ->post(route('simulator.messages.store'), ['phone' => '0551112233', 'name' => 'Noura', 'body' => 'كم السعر؟'])
            ->assertInertiaFlash('toast.type', 'success');

        $conversation = Contact::query()->where('phone', '+966551112233')->firstOrFail()->conversation;
        $this->assertSame('كم السعر؟', $conversation->messages()->first()->body);

        $this->actingAs($conversation->assignee)
            ->post(route('inbox.messages.store', $conversation), ['body' => 'مئة ريال']);
        $reply = $conversation->messages()->where('sender', MessageSender::Agent)->firstOrFail();

        $this->actingAs($supervisor)
            ->get(route('simulator.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('inbox/simulator')
                ->where('sent.0.id', $reply->id));

        $this->actingAs($supervisor)
            ->put(route('simulator.messages.status', $reply), ['status' => 'read']);

        $this->assertSame(MessageStatus::Read, $reply->fresh()->status);
    }
}
