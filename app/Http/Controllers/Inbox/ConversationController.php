<?php

namespace App\Http\Controllers\Inbox;

use App\Actions\Inbox\AddNote;
use App\Actions\Inbox\AssignConversation;
use App\Actions\Inbox\CloseConversation;
use App\Actions\Inbox\SendMessage;
use App\Actions\Inbox\SendTemplate;
use App\Actions\Inbox\TagConversation;
use App\Assistant\AssistantSettings;
use App\Enums\ConversationEventType;
use App\Exceptions\InboxRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inbox\SendMessageRequest;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\Message;
use App\Models\Tag;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What a member does inside an open thread: reply, write a note, give the
 * conversation to someone, tag it, close or reopen it. Each needs the
 * conversation to be one the member can see.
 */
class ConversationController extends Controller
{
    public function message(SendMessageRequest $request, Conversation $conversation, SendMessage $send, AddNote $note): RedirectResponse
    {
        $this->authorizeVisible($request, $conversation);

        if ($request->boolean('note')) {
            $note->handle($conversation, $request->user(), (string) $request->validated('body'));

            return back();
        }

        return $this->attempt(fn () => $send->handle(
            $conversation,
            $request->user(),
            $request->validated('body'),
            $request->file('file'),
        ));
    }

    public function template(Request $request, Conversation $conversation, SendTemplate $send): RedirectResponse
    {
        $this->authorizeVisible($request, $conversation);

        $validated = $request->validate([
            'template_id' => ['required', 'integer', 'exists:templates,id'],
            'body' => ['array'],
            'body.*' => ['nullable', 'string', 'max:1000'],
            'header' => ['nullable', 'string', 'max:60'],
            'button' => ['nullable', 'string', 'max:200'],
        ]);

        return $this->attempt(fn () => $send->handle(
            $conversation,
            $request->user(),
            Template::query()->findOrFail($validated['template_id']),
            [
                'body' => array_values($validated['body'] ?? []),
                'header' => $validated['header'] ?? null,
                'button' => $validated['button'] ?? null,
            ],
        ));
    }

    /**
     * Gives the conversation back to the assistant, or takes it from it.
     * Only while the assistant is answering customers at all.
     */
    public function assistant(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorizeVisible($request, $conversation);
        abort_unless(AssistantSettings::current()->isLive(), 409);

        $active = $request->validate(['active' => ['required', 'boolean']])['active'];

        if ($conversation->bot_active !== (bool) $active) {
            $conversation->forceFill(['bot_active' => (bool) $active])->save();
            ConversationEvent::record($conversation, ConversationEventType::AssistantToggled, $request->user(), ['active' => (bool) $active]);
        }

        return back();
    }

    public function assign(Request $request, Conversation $conversation, AssignConversation $assign): RedirectResponse
    {
        $this->authorizeVisible($request, $conversation);

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $to = $validated['user_id'] === null ? null : User::query()->findOrFail($validated['user_id']);
        $user = $request->user();

        if (! $user->isSupervisor() && $to !== null && $conversation->assigned_to === null && ! $to->is($user)) {
            return $this->refuse(InboxRuleException::because('take_only'));
        }

        return $this->attempt(fn () => $assign->handle($conversation, $to, $user));
    }

    public function tag(Request $request, Conversation $conversation, TagConversation $tags): RedirectResponse
    {
        $this->authorizeVisible($request, $conversation);

        $validated = $request->validate(['tag_id' => ['required', 'integer', 'exists:tags,id']]);

        $tags->attach($conversation, Tag::query()->findOrFail($validated['tag_id']), $request->user());

        return back();
    }

    public function untag(Request $request, Conversation $conversation, Tag $tag, TagConversation $tags): RedirectResponse
    {
        $this->authorizeVisible($request, $conversation);

        $tags->detach($conversation, $tag, $request->user());

        return back();
    }

    public function close(Request $request, Conversation $conversation, CloseConversation $close): RedirectResponse
    {
        $this->authorizeVisible($request, $conversation);

        $close->close($conversation, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.inbox.flash.closed')]);

        return back();
    }

    public function reopen(Request $request, Conversation $conversation, CloseConversation $close): RedirectResponse
    {
        $this->authorizeVisible($request, $conversation);

        $close->reopen($conversation, $request->user());

        return back();
    }

    /**
     * A file in the thread, opened through the platform: the disk is private.
     */
    public function media(Request $request, Message $message): StreamedResponse
    {
        $this->authorizeVisible($request, $message->conversation);

        abort_if($message->media_path === null, 404);

        $disk = Storage::disk(config('whatsapp.media_disk'));
        abort_unless($disk->exists($message->media_path), 404);

        return $disk->response($message->media_path, $message->media_name, [
            'Content-Type' => $message->media_mime ?? 'application/octet-stream',
            'Cache-Control' => 'private, max-age=86400',
        ], $request->boolean('download') ? 'attachment' : 'inline');
    }

    private function authorizeVisible(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->isVisibleTo($request->user()), 403);
    }

    private function attempt(callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (InboxRuleException $exception) {
            return $this->refuse($exception);
        }

        return back();
    }

    private function refuse(InboxRuleException $exception): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

        return back();
    }
}
