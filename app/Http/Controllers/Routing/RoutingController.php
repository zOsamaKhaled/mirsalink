<?php

namespace App\Http\Controllers\Routing;

use App\Http\Controllers\Controller;
use App\Models\RoutingRule;
use App\Models\Tag;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tags and routing: the labels conversations are sorted by, and for each
 * one, who takes its conversations. Supervisors only.
 */
class RoutingController extends Controller
{
    public function index(): Response
    {
        $rules = RoutingRule::query()->with('members:id')->get();
        $fallback = $rules->firstWhere('tag_id', null) ?? RoutingRule::fallback();

        $describe = fn (?RoutingRule $rule): array => [
            'method' => $rule?->method->value ?? 'round_robin',
            'members' => $rule?->members->pluck('id')->all() ?? [],
        ];

        return Inertia::render('routing/index', [
            'tags' => Tag::query()
                ->withCount('contacts')
                ->orderBy('name')
                ->get()
                ->map(fn (Tag $tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color->value,
                    'description' => $tag->description,
                    'contacts_count' => $tag->contacts_count,
                    'rule' => $describe($rules->firstWhere('tag_id', $tag->id)),
                ]),
            'fallback' => $describe($fallback->loadMissing('members:id')),
            'members' => User::query()
                ->active()
                ->whereNotNull('email_verified_at')
                ->orderBy('name')
                ->get(['id', 'name', 'role', 'availability'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role->value,
                    'availability' => $user->availability->value,
                ]),
        ]);
    }
}
