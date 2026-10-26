<?php

namespace Tests\Feature\Routing;

use App\Enums\RoutingMethod;
use App\Enums\TagColor;
use App\Models\Contact;
use App\Models\RoutingRule;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TagsAndRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisors_see_tags_rules_and_the_people_who_can_take_conversations(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        User::factory()->invited($supervisor)->create();
        User::factory()->deactivated()->create();
        Tag::factory()->create();

        $this->actingAs($supervisor)
            ->get(route('routing.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('routing/index')
                ->has('tags', 1)
                ->where('fallback.method', 'round_robin')
                ->has('members', 1));
    }

    public function test_agents_cannot_open_tags_and_routing(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('routing.index'))
            ->assertForbidden();
    }

    public function test_a_supervisor_creates_and_edits_a_tag(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($supervisor)->post(route('routing.tags.store'), [
            'name' => ' عميل محتمل ',
            'color' => 'violet',
            'description' => 'يسأل عن الأسعار.',
        ])->assertRedirect(route('routing.index'));

        $tag = Tag::query()->firstOrFail();
        $this->assertSame('عميل محتمل', $tag->name);
        $this->assertSame(TagColor::Violet, $tag->color);

        $this->actingAs($supervisor)->put(route('routing.tags.update', $tag), [
            'name' => 'عميل محتمل',
            'color' => 'blue',
        ])->assertSessionHasNoErrors();

        $this->assertSame(TagColor::Blue, $tag->fresh()->color);
    }

    public function test_two_tags_cannot_share_a_name(): void
    {
        Tag::factory()->create(['name' => 'دعم فني']);

        $this->actingAs(User::factory()->supervisor()->create())
            ->post(route('routing.tags.store'), ['name' => 'دعم فني', 'color' => 'blue'])
            ->assertSessionHasErrors(['name' => __('app.routing.tags.errors.name_taken')]);
    }

    public function test_deleting_a_tag_takes_it_off_contacts_and_drops_its_rule(): void
    {
        $tag = Tag::factory()->create();
        $contact = Contact::factory()->create();
        $contact->tags()->attach($tag);
        RoutingRule::query()->create(['tag_id' => $tag->id, 'method' => RoutingMethod::RoundRobin]);

        $this->actingAs(User::factory()->supervisor()->create())
            ->delete(route('routing.tags.destroy', $tag));

        $this->assertModelMissing($tag);
        $this->assertSame(0, $contact->tags()->count());
        $this->assertSame(0, RoutingRule::query()->whereNotNull('tag_id')->count());
    }

    public function test_a_supervisor_sets_who_takes_a_tag_and_how(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $sara = User::factory()->create();
        $tag = Tag::factory()->create();

        $this->actingAs($supervisor)->put(route('routing.rules.update'), [
            'tag_id' => $tag->id,
            'method' => 'least_busy',
            'members' => [$sara->id],
        ])->assertRedirect(route('routing.index'));

        $rule = $tag->fresh()->routingRule;
        $this->assertSame(RoutingMethod::LeastBusy, $rule->method);
        $this->assertSame([$sara->id], $rule->members->pluck('id')->all());
    }

    public function test_the_default_rule_is_saved_without_a_tag(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($supervisor)->put(route('routing.rules.update'), [
            'method' => 'round_robin',
            'members' => [$supervisor->id],
        ]);

        $this->assertSame(1, RoutingRule::query()->whereNull('tag_id')->count());
        $this->assertSame([$supervisor->id], RoutingRule::fallback()->members->pluck('id')->all());
    }

    public function test_a_deactivated_member_cannot_be_put_on_a_rule(): void
    {
        $gone = User::factory()->deactivated()->create();

        $this->actingAs(User::factory()->supervisor()->create())
            ->put(route('routing.rules.update'), ['method' => 'round_robin', 'members' => [$gone->id]])
            ->assertSessionHasErrors('members.0');
    }
}
