<?php

namespace App\Http\Controllers\Templates;

use App\Http\Controllers\Controller;
use App\Models\QuickReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Saved replies: every member keeps their own; supervisors also keep the
 * team's. A shortcut is unique among the replies one member can use.
 */
class QuickReplyController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $reply = new QuickReply([
            'shortcut' => $data['shortcut'],
            'title' => $data['title'],
            'body' => $data['body'],
            'user_id' => $data['shared'] ? null : $request->user()->id,
        ]);
        $reply->created_by = $request->user()->id;
        $reply->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.quick_replies.flash.saved')]);

        return back();
    }

    public function update(Request $request, QuickReply $quickReply): RedirectResponse
    {
        abort_unless($quickReply->isManageableBy($request->user()), 403);

        $data = $this->validated($request, $quickReply);

        $quickReply->fill([
            'shortcut' => $data['shortcut'],
            'title' => $data['title'],
            'body' => $data['body'],
            'user_id' => $data['shared'] ? null : ($quickReply->user_id ?? $request->user()->id),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.quick_replies.flash.saved')]);

        return back();
    }

    public function destroy(Request $request, QuickReply $quickReply): RedirectResponse
    {
        abort_unless($quickReply->isManageableBy($request->user()), 403);

        $quickReply->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.quick_replies.flash.deleted')]);

        return back();
    }

    /**
     * @return array{shortcut: string, title: string, body: string, shared: bool}
     */
    private function validated(Request $request, ?QuickReply $reply = null): array
    {
        $request->merge([
            'shortcut' => mb_strtolower(ltrim(trim((string) $request->input('shortcut')), '/')),
            'shared' => $request->boolean('shared') && $request->user()->can('manage-team'),
        ]);

        $user = $request->user();

        $validated = $request->validate([
            'shortcut' => [
                'required', 'string', 'max:40', 'regex:/^[\p{L}\p{N}_-]+$/u',
                Rule::unique('quick_replies')
                    ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->id))
                    ->ignore($reply?->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:4096'],
            'shared' => ['boolean'],
        ], [
            'shortcut.regex' => __('app.quick_replies.rules.shortcut'),
            'shortcut.unique' => __('app.quick_replies.rules.shortcut_taken'),
        ], [
            'shortcut' => __('app.quick_replies.form.shortcut'),
            'title' => __('app.quick_replies.form.title'),
            'body' => __('app.quick_replies.form.body'),
        ]);

        return [
            'shortcut' => $validated['shortcut'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'shared' => (bool) $validated['shared'],
        ];
    }
}
