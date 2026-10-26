<?php

namespace App\Actions\Broadcasts;

use App\Actions\Contacts\ImportContacts;
use App\Enums\AudienceType;
use App\Enums\RecipientStatus;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Contact;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Storage;
use SplFileObject;

/**
 * Turns a campaign's audience into its list of recipients, each with the
 * values their variables take, before anything is sent. Who is left out
 * stays on the list with the reason: stopped broadcasts, a number twice, a
 * number that can't be read, or a variable with no value and no fallback.
 *
 * A CSV audience is first imported as contacts (merged the usual way), so
 * every recipient is a contact the team can later talk to.
 */
class PrepareRecipients
{
    private const int CHUNK = 500;

    public function __construct(private ImportContacts $import) {}

    public function handle(Broadcast $broadcast): void
    {
        $broadcast->recipients()->delete();

        $rows = [];
        $seen = [];

        foreach ($this->candidates($broadcast) as [$contact, $phone, $cells]) {
            $rows[] = $this->row($broadcast, $contact, $phone, $cells, $seen);

            if (count($rows) === self::CHUNK) {
                BroadcastRecipient::query()->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            BroadcastRecipient::query()->insert($rows);
        }
    }

    /**
     * @param  array<string, true>  $seen
     * @param  array<string, string>  $cells
     * @return array<string, mixed>
     */
    private function row(Broadcast $broadcast, ?Contact $contact, ?string $phone, array $cells, array &$seen): array
    {
        $row = [
            'broadcast_id' => $broadcast->id,
            'contact_id' => $contact?->id,
            'phone' => mb_substr($phone ?? ($cells['__phone'] ?? ''), 0, 20),
            'name' => $contact?->name,
            'params' => null,
            'status' => RecipientStatus::Queued->value,
            'skip_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $skip = fn (string $reason): array => [...$row, 'status' => RecipientStatus::Skipped->value, 'skip_reason' => $reason];

        if ($contact === null || $phone === null) {
            return $skip(BroadcastRecipient::SKIP_BAD_PHONE);
        }

        if (isset($seen[$phone])) {
            return $skip(BroadcastRecipient::SKIP_DUPLICATE);
        }

        $seen[$phone] = true;

        if (! $contact->acceptsBroadcasts()) {
            return $skip(BroadcastRecipient::SKIP_OPTED_OUT);
        }

        $params = $this->params($broadcast, $contact, $cells);

        if ($params === null) {
            return $skip(BroadcastRecipient::SKIP_MISSING_VALUE);
        }

        return [...$row, 'params' => json_encode($params, JSON_UNESCAPED_UNICODE)];
    }

    /**
     * The values for one recipient, or null when one has none.
     *
     * @param  array<string, string>  $cells
     * @return array{body: list<string>, header: string|null, button: string|null}|null
     */
    private function params(Broadcast $broadcast, Contact $contact, array $cells): ?array
    {
        $variables = $broadcast->variables ?? [];
        $resolve = function (?array $spec) use ($contact, $cells): ?string {
            if ($spec === null) {
                return null;
            }

            $value = match ($spec['source'] ?? 'text') {
                'name' => $contact->name,
                'company' => $contact->company,
                'phone' => $contact->phone,
                'column' => $cells[(string) ($spec['value'] ?? '')] ?? null,
                default => $spec['value'] ?? null,
            };

            $value = trim((string) $value) !== '' ? trim((string) $value) : trim((string) ($spec['fallback'] ?? ''));

            return $value === '' ? '' : $value;
        };

        $body = array_map($resolve, $variables['body'] ?? []);
        $header = $resolve($variables['header'] ?? null);
        $button = $resolve($variables['button'] ?? null);

        if (in_array('', [...$body, $header, $button], true)) {
            return null;
        }

        return ['body' => $body, 'header' => $header, 'button' => $button];
    }

    /**
     * Each candidate as [contact, phone, the row's cells by heading].
     *
     * @return iterable<array{0: Contact|null, 1: string|null, 2: array<string, string>}>
     */
    private function candidates(Broadcast $broadcast): iterable
    {
        if ($broadcast->audience_type === AudienceType::Csv) {
            yield from $this->fromFile($broadcast);

            return;
        }

        $query = Contact::query()->orderBy('id');

        if ($broadcast->audience_type === AudienceType::Tags) {
            $tags = $broadcast->audience_tags ?? [];
            $query->where(fn ($query) => $query
                ->whereHas('tags', fn ($tagged) => $tagged->whereIn('tags.id', $tags))
                ->orWhereHas('conversation.tags', fn ($tagged) => $tagged->whereIn('tags.id', $tags)));
        }

        foreach ($query->lazy(self::CHUNK) as $contact) {
            yield [$contact, $contact->phone, []];
        }
    }

    /**
     * @return iterable<array{0: Contact|null, 1: string|null, 2: array<string, string>}>
     */
    private function fromFile(Broadcast $broadcast): iterable
    {
        if ($broadcast->audience_file === null || ! Storage::disk('local')->exists($broadcast->audience_file)) {
            return;
        }

        $path = Storage::disk('local')->path($broadcast->audience_file);
        $this->import->handle($path, $broadcast->creator);

        $file = new SplFileObject($path);
        $header = null;
        $delimiter = ',';

        while (! $file->eof()) {
            $line = rtrim((string) $file->fgets(), "\r\n");

            if (trim($line) === '') {
                continue;
            }

            if ($header === null) {
                $delimiter = self::delimiterOf($line);
                $header = array_map(fn ($heading): string => trim(str_replace("\u{FEFF}", '', (string) $heading)), str_getcsv($line, $delimiter, '"', ''));

                continue;
            }

            $values = str_getcsv($line, $delimiter, '"', '');
            $cells = [];

            foreach ($header as $index => $heading) {
                $cells[$heading] = trim((string) ($values[$index] ?? ''));
            }

            $typed = self::phoneOf($cells);
            $phone = PhoneNumber::normalize($typed);
            $contact = $phone === null ? null : Contact::query()->where('phone', $phone)->first();

            yield [$contact, $phone, [...$cells, '__phone' => (string) $typed]];
        }
    }

    /**
     * The headings of a CSV file's first line, for choosing which column
     * fills a variable.
     *
     * @return list<string>
     */
    public static function headingsOf(string $path): array
    {
        $file = new SplFileObject($path);

        while (! $file->eof()) {
            $line = rtrim((string) $file->fgets(), "\r\n");

            if (trim($line) !== '') {
                return array_values(array_filter(array_map(
                    fn ($heading): string => trim(str_replace("\u{FEFF}", '', (string) $heading)),
                    str_getcsv($line, self::delimiterOf($line), '"', ''),
                )));
            }
        }

        return [];
    }

    /**
     * @param  array<string, string>  $cells
     */
    private static function phoneOf(array $cells): ?string
    {
        foreach ($cells as $heading => $value) {
            $key = mb_strtolower((string) preg_replace('/[\s_\-]+/u', '', $heading));

            if (in_array($key, ImportContacts::HEADINGS['phone'], true)) {
                return $value === '' ? null : $value;
            }
        }

        return null;
    }

    private static function delimiterOf(string $line): string
    {
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }
}
