<?php

namespace Tests\Feature\Routing;

use App\Enums\Availability;
use App\Enums\RoutingMethod;
use App\Models\RoutingRule;
use App\Models\Tag;
use App\Models\User;
use App\Support\Routing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingEngineTest extends TestCase
{
    use RefreshDatabase;

    private function rule(RoutingMethod $method, array $members = [], ?Tag $tag = null): RoutingRule
    {
        $rule = RoutingRule::query()->create(['tag_id' => $tag?->id, 'method' => $method]);
        $rule->members()->sync(collect($members)->pluck('id'));

        return $rule->fresh();
    }

    public function test_round_robin_takes_each_available_member_in_turn(): void
    {
        [$sara, $khalid, $noura] = User::factory()->count(3)->create()->all();
        $rule = $this->rule(RoutingMethod::RoundRobin, [$sara, $khalid, $noura]);
        $routing = new Routing;

        $picked = collect(range(1, 4))->map(fn () => $routing->pick($rule->fresh())?->id)->all();

        $this->assertSame([$sara->id, $khalid->id, $noura->id, $sara->id], $picked);
    }

    public function test_only_available_active_members_who_joined_are_candidates(): void
    {
        $available = User::factory()->create();
        $busy = User::factory()->availability(Availability::Busy)->create();
        $away = User::factory()->availability(Availability::Away)->create();
        $gone = User::factory()->deactivated()->create();
        $invited = User::factory()->invited()->create(['availability' => Availability::Available]);

        $rule = $this->rule(RoutingMethod::RoundRobin, [$available, $busy, $away, $gone, $invited]);

        $this->assertSame([$available->id], (new Routing)->candidates($rule)->pluck('id')->all());
    }

    public function test_no_one_available_means_the_conversation_waits_unassigned(): void
    {
        $busy = User::factory()->availability(Availability::Busy)->create();
        $rule = $this->rule(RoutingMethod::RoundRobin, [$busy]);

        $this->assertNull((new Routing)->pick($rule));
    }

    public function test_least_busy_goes_to_the_lightest_load_and_alternates_on_a_tie(): void
    {
        [$sara, $khalid, $noura] = User::factory()->count(3)->create()->all();
        $rule = $this->rule(RoutingMethod::LeastBusy, [$sara, $khalid, $noura]);
        $routing = new Routing;

        $loads = [$sara->id => 4, $khalid->id => 1, $noura->id => 1];
        $count = fn (User $user): int => $loads[$user->id];

        $first = $routing->pick($rule, $count);
        $second = $routing->pick($rule->fresh(), $count);

        $this->assertSame($khalid->id, $first?->id);
        $this->assertSame($noura->id, $second?->id);
    }

    public function test_a_rule_without_members_shares_across_the_whole_team(): void
    {
        $team = User::factory()->count(2)->create();

        $this->assertEqualsCanonicalizing(
            $team->pluck('id')->all(),
            (new Routing)->candidates(RoutingRule::fallback())->pluck('id')->all(),
        );
    }

    public function test_a_tag_without_a_rule_follows_the_default_rule(): void
    {
        $routing = new Routing;
        $untagged = Tag::factory()->create();
        $tagged = Tag::factory()->create();
        $rule = $this->rule(RoutingMethod::LeastBusy, tag: $tagged);

        $this->assertTrue($routing->ruleFor($untagged)->isDefault());
        $this->assertTrue($routing->ruleFor(null)->isDefault());
        $this->assertTrue($routing->ruleFor($tagged->fresh())->is($rule));
    }
}
