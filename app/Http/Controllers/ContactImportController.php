<?php

namespace App\Http\Controllers;

use App\Actions\Contacts\ImportContacts;
use App\Http\Requests\Contacts\ImportContactsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bringing contacts in from a CSV file, such as Respond.io's export.
 * The result (added, updated, skipped lines) comes back as flash data.
 */
class ContactImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('contacts/import', [
            'maxRows' => ImportContacts::MAX_ROWS,
        ]);
    }

    public function store(ImportContactsRequest $request, ImportContacts $import): RedirectResponse
    {
        $result = $import->handle($request->file('file')->getRealPath(), $request->user());

        Inertia::flash('import', $result);

        return to_route('contacts.import.create');
    }
}
