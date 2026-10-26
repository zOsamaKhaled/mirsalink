<?php

namespace App\Http\Controllers\Routing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Routing\SaveTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TagController extends Controller
{
    public function store(SaveTagRequest $request): RedirectResponse
    {
        $tag = Tag::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.routing.tags.flash.created', ['name' => $tag->name])]);

        return to_route('routing.index');
    }

    public function update(SaveTagRequest $request, Tag $tag): RedirectResponse
    {
        $tag->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.routing.tags.flash.updated', ['name' => $tag->name])]);

        return to_route('routing.index');
    }

    /**
     * Removes the tag from every contact (and later every conversation) and
     * drops its routing rule; its conversations fall to the default rule.
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        Gate::authorize('manage-team');

        $tag->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.routing.tags.flash.deleted', ['name' => $tag->name])]);

        return to_route('routing.index');
    }
}
