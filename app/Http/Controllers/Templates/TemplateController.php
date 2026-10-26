<?php

namespace App\Http\Controllers\Templates;

use App\Actions\Templates\SaveTemplate;
use App\Actions\Templates\SubmitTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\SaveTemplateRequest;
use App\Models\QuickReply;
use App\Models\Template;
use App\Support\TemplatePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Templates and replies": the member's quick replies and the team's, and
 * the WhatsApp templates. Every member sees both; supervisors write the
 * templates and the team's replies.
 */
class TemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('templates/index', [
            'tab' => $request->query('tab') === 'templates' ? 'templates' : 'replies',
            'quickReplies' => QuickReply::query()
                ->availableTo($user)
                ->orderBy('shortcut')
                ->get()
                ->map(fn (QuickReply $reply): array => [
                    'id' => $reply->id,
                    'shortcut' => $reply->shortcut,
                    'title' => $reply->title,
                    'body' => $reply->body,
                    'shared' => $reply->isShared(),
                    'can_manage' => $reply->isManageableBy($user),
                ]),
            'templates' => Template::query()
                ->orderByRaw("case status when 'approved' then 0 when 'pending' then 1 when 'rejected' then 2 else 3 end")
                ->orderBy('name')
                ->get()
                ->map(fn (Template $template): array => TemplatePresenter::present($template)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('templates/form', ['template' => null]);
    }

    public function store(SaveTemplateRequest $request, SaveTemplate $save): RedirectResponse
    {
        $save->handle($request->validated(), actor: $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.templates.flash.saved')]);

        return to_route('templates.index', ['tab' => 'templates']);
    }

    public function edit(Template $template): Response|RedirectResponse
    {
        if (! $template->status->isEditable()) {
            return $this->locked();
        }

        return Inertia::render('templates/form', ['template' => TemplatePresenter::present($template)]);
    }

    public function update(SaveTemplateRequest $request, Template $template, SaveTemplate $save): RedirectResponse
    {
        if (! $template->status->isEditable()) {
            return $this->locked();
        }

        $save->handle($request->validated(), $template);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.templates.flash.saved')]);

        return to_route('templates.index', ['tab' => 'templates']);
    }

    public function submit(Template $template, SubmitTemplate $submit): RedirectResponse
    {
        $submit->handle($template);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.templates.flash.submitted')]);

        return back();
    }

    public function destroy(Template $template): RedirectResponse
    {
        $template->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.templates.flash.deleted')]);

        return to_route('templates.index', ['tab' => 'templates']);
    }

    private function locked(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'info', 'message' => __('app.templates.flash.locked')]);

        return to_route('templates.index', ['tab' => 'templates']);
    }
}
