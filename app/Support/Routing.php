<?php

namespace App\Support;

use App\Enums\Availability;
use App\Enums\RoutingMethod;
use App\Models\RoutingRule;
use App\Models\Tag;
use App\Models\User;
use Closure;
use Illuminate\Support\Collection;

/**
 * Chooses who takes a new conversation.
 *
 * A tag's rule if it has one, otherwise the default rule. Among the rule's
 * members (or the whole team when it names none), only those active, in,
 * and available are candidates. No candidate means no one: the conversation
 * waits in "Unassigned" for whoever picks it up.
 *
 * The inbox (phase 4) calls pick() for every new conversation and passes
 * how many open conversations each member holds, for the least-busy rule.
 */
final class Routing
{
    public function ruleFor(?Tag $tag): RoutingRule
    {
        $rule = $tag?->routingRule;

        return $rule ?? RoutingRule::fallback();
    }

    /**
     * @param  (Closure(User): int)|null  $openConversations
     */
    public function pick(RoutingRule $rule, ?Closure $openConversations = null): ?User
    {
        $candidates = $this->candidates($rule);

        if ($candidates->isEmpty()) {
            return null;
        }

        $chosen = $rule->method === RoutingMethod::LeastBusy
            ? $this->leastBusy($rule, $candidates, $openConversations ?? fn (): int => 0)
            : $this->nextInTurn($rule, $candidates);

        $rule->forceFill(['last_assigned_user_id' => $chosen->id])->save();

        return $chosen;
    }

    /**
     * @return Collection<int, User>
     */
    public function candidates(RoutingRule $rule): Collection
    {
        $pool = $rule->members()->exists()
            ? $rule->members()->getQuery()
            : User::query();

        return $pool
            ->whereNull('users.deactivated_at')
            ->whereNotNull('users.email_verified_at')
            ->where('users.availability', Availability::Available)
            ->orderBy('users.id')
            ->get();
    }

    /**
     * @param  Collection<int, User>  $candidates
     */
    private function nextInTurn(RoutingRule $rule, Collection $candidates): User
    {
        $last = $rule->last_assigned_user_id;

        return $candidates->first(fn (User $user): bool => $last !== null && $user->id > $last)
            ?? $candidates->first();
    }

    /**
     * The fewest open conversations; a tie goes to whoever's turn is next,
     * so equal members still alternate.
     *
     * @param  Collection<int, User>  $candidates
     * @param  Closure(User): int  $openConversations
     */
    private function leastBusy(RoutingRule $rule, Collection $candidates, Closure $openConversations): User
    {
        $loads = $candidates->mapWithKeys(fn (User $user): array => [$user->id => $openConversations($user)]);
        $lightest = $loads->min();
        $tied = $candidates->filter(fn (User $user): bool => $loads[$user->id] === $lightest)->values();

        return $this->nextInTurn($rule, $tied);
    }
}
