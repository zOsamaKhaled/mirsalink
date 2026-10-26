<?php

namespace App\Http\Controllers\Routing;

use App\Enums\RoutingMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Routing\UpdateRoutingRuleRequest;
use App\Models\RoutingRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RoutingRuleController extends Controller
{
    /**
     * Saves the rule for one tag, or the default rule when no tag is given,
     * making it if the tag had none yet.
     */
    public function update(UpdateRoutingRuleRequest $request): RedirectResponse
    {
        $tagId = $request->integer('tag_id') ?: null;

        DB::transaction(function () use ($request, $tagId): void {
            $rule = $tagId === null
                ? RoutingRule::fallback()
                : RoutingRule::query()->firstOrNew(['tag_id' => $tagId]);

            $rule->method = $request->enum('method', RoutingMethod::class);
            $rule->save();
            $rule->members()->sync($request->array('members'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.routing.rules.flash.saved')]);

        return to_route('routing.index');
    }
}
