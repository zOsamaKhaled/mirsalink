<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Adds or edits a contact with its tags. A number that belonged to a
 * deleted contact brings that contact back rather than making a second one.
 */
class SaveContact
{
    /**
     * @param  array{name?: string|null, phone: string, email?: string|null, company?: string|null, notes?: string|null, opted_out?: bool, tags?: list<int>}  $data
     */
    public function handle(array $data, ?Contact $contact = null, ?User $actor = null): Contact
    {
        return DB::transaction(function () use ($data, $contact, $actor): Contact {
            if ($contact === null) {
                $contact = Contact::withTrashed()->where('phone', $data['phone'])->first() ?? new Contact;
                $contact->deleted_at = null;

                if (! $contact->exists) {
                    $contact->source = Contact::SOURCE_MANUAL;
                    $contact->created_by = $actor?->id;
                }
            }

            $contact->fill([
                'name' => $data['name'] ?? null,
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'company' => $data['company'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if (array_key_exists('opted_out', $data)) {
                $contact->opted_out_at = $data['opted_out']
                    ? ($contact->opted_out_at ?? now())
                    : null;
            }

            $contact->save();

            if (array_key_exists('tags', $data)) {
                $contact->tags()->sync($data['tags']);
            }

            return $contact;
        });
    }
}
