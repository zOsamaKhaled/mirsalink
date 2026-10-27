<?php

namespace App\Actions\Contacts;

use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Tag;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use SplFileObject;

/**
 * Reads contacts from a CSV file, such as the one Respond.io exports.
 *
 * Columns are found by their heading, in Arabic or English (phone, name or
 * first and last name, email, company, tags), in any order; the rest are
 * ignored. Tags may be separated by commas, semicolons or pipes, and new
 * ones are made as they appear.
 *
 * A number already in the platform is merged, not duplicated: its empty
 * fields are filled and its tags added to, but nothing a person typed is
 * overwritten. Rows without a usable number are skipped and reported by
 * line, so the file can be fixed and imported again.
 *
 * An assignee column (a member's email or name, as Respond.io exports it)
 * keeps each customer with their member through the move: a customer the
 * platform has no conversation with gets a closed one owned by that
 * member, so their first message goes back to them.
 */
class ImportContacts
{
    public const int MAX_ROWS = 20000;

    private const int CHUNK = 500;

    /**
     * Headings each column is known by, written without spaces, dashes or
     * underscores, in lower case.
     *
     * @var array<string, list<string>>
     */
    public const array HEADINGS = [
        'phone' => ['phone', 'phonenumber', 'mobile', 'mobilenumber', 'whatsapp', 'whatsappnumber', 'number', 'contactphone', 'رقم', 'الرقم', 'الجوال', 'رقمالجوال', 'الهاتف', 'رقمالهاتف', 'واتساب', 'رقمالواتساب'],
        'name' => ['name', 'fullname', 'contactname', 'الاسم', 'الاسمالكامل', 'اسم'],
        'first_name' => ['firstname', 'givenname', 'الاسمالأول'],
        'last_name' => ['lastname', 'surname', 'familyname', 'اسمالعائلة', 'العائلة'],
        'email' => ['email', 'emailaddress', 'mail', 'البريد', 'البريدالإلكتروني', 'الإيميل'],
        'company' => ['company', 'companyname', 'organization', 'organisation', 'الشركة', 'اسمالشركة', 'المؤسسة'],
        'tags' => ['tags', 'tag', 'labels', 'label', 'الوسوم', 'وسوم', 'الوسم'],
        'assignee' => ['assignee', 'assignedto', 'assigneeemail', 'assigneename', 'owner', 'contactowner', 'المسؤول', 'الموظف', 'الموظفالمسؤول'],
    ];

    /** @var array<string, int> */
    private array $tagIds = [];

    /** @var array<string, int> members by lower-case email and by name */
    private array $members = [];

    /**
     * @return array{created: int, updated: int, skipped: list<array{line: int, reason: string}>, skipped_count: int, tags_created: int, owners: int, unknown_owners: list<string>, missing_phone_column: bool}
     */
    public function handle(string $path, ?User $actor = null): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => [], 'skipped_count' => 0, 'tags_created' => 0, 'owners' => 0, 'unknown_owners' => [], 'missing_phone_column' => false];
        $tagsBefore = Tag::query()->count();
        $this->tagIds = Tag::query()->pluck('id', 'name')->all();
        $this->members = User::query()->whereNull('deactivated_at')->get(['id', 'name', 'email'])
            ->flatMap(fn (User $member): array => [mb_strtolower($member->email) => $member->id, mb_strtolower($member->name) => $member->id])
            ->all();

        $file = new SplFileObject($path);
        $header = $this->readLine($file);

        if ($header === null) {
            $result['missing_phone_column'] = true;

            return $result;
        }

        $delimiter = $this->delimiterOf($header);
        $columns = $this->columns(str_getcsv($header, $delimiter, '"', ''));

        if (! isset($columns['phone'])) {
            $result['missing_phone_column'] = true;

            return $result;
        }

        $chunk = [];
        $line = 1;

        while (($raw = $this->readLine($file)) !== null && $line <= self::MAX_ROWS) {
            $line++;

            if (trim($raw) === '') {
                continue;
            }

            $chunk[$line] = str_getcsv($raw, $delimiter, '"', '');

            if (count($chunk) === self::CHUNK) {
                $this->importChunk($chunk, $columns, $actor, $result);
                $chunk = [];
            }
        }

        $this->importChunk($chunk, $columns, $actor, $result);
        $result['tags_created'] = Tag::query()->count() - $tagsBefore;

        return $result;
    }

    /**
     * @param  array<int, list<string|null>>  $rows
     * @param  array<string, int>  $columns
     * @param  array{created: int, updated: int, skipped: list<array{line: int, reason: string}>, skipped_count: int, tags_created: int, owners: int, unknown_owners: list<string>, missing_phone_column: bool}  $result
     */
    private function importChunk(array $rows, array $columns, ?User $actor, array &$result): void
    {
        if ($rows === []) {
            return;
        }

        DB::transaction(function () use ($rows, $columns, $actor, &$result): void {
            $parsed = [];

            foreach ($rows as $line => $cells) {
                $phone = PhoneNumber::normalize($this->cell($cells, $columns, 'phone'));

                if ($phone === null) {
                    $this->skip($result, $line, $this->cell($cells, $columns, 'phone') === null ? 'no_phone' : 'bad_phone');

                    continue;
                }

                $parsed[$line] = ['phone' => $phone, 'cells' => $cells];
            }

            $existing = Contact::withTrashed()
                ->whereIn('phone', array_column($parsed, 'phone'))
                ->get()
                ->keyBy('phone');

            foreach ($parsed as ['phone' => $phone, 'cells' => $cells]) {
                $contact = $existing->get($phone);
                $isNew = $contact === null || $contact->trashed();

                $contact ??= new Contact(['phone' => $phone, 'source' => Contact::SOURCE_IMPORT]);
                $contact->deleted_at = null;
                $contact->created_by ??= $actor?->id;

                // Fill what is empty; never overwrite what someone typed.
                foreach (['name' => $this->name($cells, $columns), 'email' => $this->email($cells, $columns), 'company' => $this->cell($cells, $columns, 'company')] as $field => $value) {
                    if ($value !== null && blank($contact->{$field})) {
                        $contact->{$field} = $value;
                    }
                }

                $wasDirty = $contact->isDirty();
                $contact->save();
                $existing->put($phone, $contact);

                $tagIds = $this->tagIds($this->cell($cells, $columns, 'tags'));

                if ($tagIds !== []) {
                    $attached = $contact->tags()->syncWithoutDetaching($tagIds)['attached'];
                    $wasDirty = $wasDirty || $attached !== [];
                }

                $this->keepWithOwner($contact, $this->cell($cells, $columns, 'assignee'), $result);

                if ($isNew) {
                    $result['created']++;
                } elseif ($wasDirty) {
                    $result['updated']++;
                }
            }
        });
    }

    /**
     * @param  list<string|null>  $headings
     * @return array<string, int>
     */
    private function columns(array $headings): array
    {
        $columns = [];

        foreach ($headings as $index => $heading) {
            $key = mb_strtolower((string) preg_replace('/[\s_\-]+/u', '', str_replace("\u{FEFF}", '', (string) $heading)));

            foreach (self::HEADINGS as $column => $names) {
                if (! isset($columns[$column]) && in_array($key, $names, true)) {
                    $columns[$column] = $index;
                }
            }
        }

        return $columns;
    }

    /**
     * @param  list<string|null>  $cells
     * @param  array<string, int>  $columns
     */
    private function cell(array $cells, array $columns, string $column): ?string
    {
        if (! isset($columns[$column])) {
            return null;
        }

        $value = trim((string) ($cells[$columns[$column]] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param  list<string|null>  $cells
     * @param  array<string, int>  $columns
     */
    private function name(array $cells, array $columns): ?string
    {
        $name = $this->cell($cells, $columns, 'name')
            ?? trim(implode(' ', array_filter([
                $this->cell($cells, $columns, 'first_name'),
                $this->cell($cells, $columns, 'last_name'),
            ])));

        return $name === '' ? null : mb_substr($name, 0, 255);
    }

    /**
     * @param  list<string|null>  $cells
     * @param  array<string, int>  $columns
     */
    private function email(array $cells, array $columns): ?string
    {
        $email = $this->cell($cells, $columns, 'email');

        return $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * @param  array{created: int, updated: int, skipped: list<array{line: int, reason: string}>, skipped_count: int, tags_created: int, owners: int, unknown_owners: list<string>, missing_phone_column: bool}  $result
     */
    private function keepWithOwner(Contact $contact, ?string $owner, array &$result): void
    {
        if ($owner === null) {
            return;
        }

        $memberId = $this->members[mb_strtolower(trim($owner))] ?? null;

        if ($memberId === null) {
            if (count($result['unknown_owners']) < 10 && ! in_array($owner, $result['unknown_owners'], true)) {
                $result['unknown_owners'][] = mb_substr($owner, 0, 100);
            }

            return;
        }

        if (Conversation::query()->where('contact_id', $contact->id)->exists()) {
            return;
        }

        Conversation::query()->create([
            'contact_id' => $contact->id,
            'assigned_to' => $memberId,
            'status' => ConversationStatus::Closed,
            'closed_at' => now(),
        ]);
        $result['owners']++;
    }

    /**
     * @return list<int>
     */
    private function tagIds(?string $cell): array
    {
        if ($cell === null) {
            return [];
        }

        $ids = [];

        foreach (preg_split('/[,;|،]+/u', $cell) ?: [] as $name) {
            $name = mb_substr(trim($name), 0, 60);

            if ($name === '') {
                continue;
            }

            $ids[] = $this->tagIds[$name] ??= Tag::findOrMake($name)->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array{created: int, updated: int, skipped: list<array{line: int, reason: string}>, skipped_count: int, tags_created: int, owners: int, unknown_owners: list<string>, missing_phone_column: bool}  $result
     */
    private function skip(array &$result, int $line, string $reason): void
    {
        $result['skipped_count']++;

        // The first fifty are enough to find the pattern; the count says the rest.
        if (count($result['skipped']) < 50) {
            $result['skipped'][] = ['line' => $line, 'reason' => $reason];
        }
    }

    /**
     * The next physical line, without its line break, or null at the end.
     * Quoted fields spanning lines are not expected in contact exports.
     */
    private function readLine(SplFileObject $file): ?string
    {
        if ($file->eof()) {
            return null;
        }

        $line = $file->fgets();

        if ($line === '' && $file->eof()) {
            return null;
        }

        return rtrim($line, "\r\n");
    }

    private function delimiterOf(string $header): string
    {
        $counts = [',' => substr_count($header, ','), ';' => substr_count($header, ';'), "\t" => substr_count($header, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }
}
