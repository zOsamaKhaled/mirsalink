<?php

namespace App\Http\Controllers;

use App\Actions\Contacts\SaveContact;
use App\Http\Requests\Contacts\SaveContactRequest;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The contacts list: search, filter by tag or by who has stopped broadcasts,
 * add and edit. Every member uses it; deleting and importing are for
 * supervisors.
 */
class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'tag' => $request->integer('tag') ?: null,
            'opted_out' => $request->boolean('opted_out'),
        ];

        $contacts = Contact::query()
            ->with('tags:id,name,color')
            ->search($filters['q'])
            ->when($filters['tag'], fn ($query, int $tag) => $query->whereHas('tags', fn ($tags) => $tags->whereKey($tag)))
            ->when($filters['opted_out'], fn ($query) => $query->whereNotNull('opted_out_at'))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Contact $contact): array => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'company' => $contact->company,
                'notes' => $contact->notes,
                'opted_out' => $contact->opted_out_at !== null,
                'source' => $contact->source,
                'tags' => $contact->tags->map(fn (Tag $tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color->value,
                ])->all(),
                'created_at' => $contact->created_at?->toIso8601String(),
            ]);

        return Inertia::render('contacts/index', [
            'contacts' => $contacts,
            'filters' => $filters,
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name', 'color']),
            'total' => Contact::query()->count(),
        ]);
    }

    public function store(SaveContactRequest $request, SaveContact $save): RedirectResponse
    {
        $contact = $save->handle($request->validated(), actor: $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.contacts.flash.created', ['name' => $contact->name ?? $contact->phone])]);

        return back();
    }

    public function update(SaveContactRequest $request, Contact $contact, SaveContact $save): RedirectResponse
    {
        $save->handle($request->validated(), $contact, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.contacts.flash.updated')]);

        return back();
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        Gate::authorize('manage-team');

        $contact->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.contacts.flash.deleted', ['name' => $contact->name ?? $contact->phone])]);

        return back();
    }
}
