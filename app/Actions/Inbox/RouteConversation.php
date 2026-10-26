<?php

namespace App\Actions\Inbox;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\RoutingRule;
use App\Models\User;
use App\Support\Routing;

/**
 * Finds the member a waiting conversation goes to, following the routing
 * rule of its first tag that has one, or the default rule. No one
 * available leaves it unassigned.
 */
class RouteConversation
{
    public function __construct(
        private Routing $routing,
        private AssignConversation $assign,
    ) {}

    public function handle(Conversation $conversation): ?User
    {
        $rule = $conversation->tags()->with('routingRule')->get()
            ->map(fn ($tag) => $tag->routingRule)
            ->filter()
            ->first()
            ?? RoutingRule::fallback();

        $member = $this->routing->pick($rule, fn (User $user): int => Conversation::query()
            ->where('assigned_to', $user->id)
            ->where('status', ConversationStatus::Open)
            ->count());

        if ($member !== null) {
            $this->assign->handle($conversation, $member);
        }

        return $member;
    }
}
