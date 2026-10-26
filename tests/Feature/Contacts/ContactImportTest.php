<?php

namespace Tests\Feature\Contacts;

use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ContactImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<list<string>>  $rows
     */
    private function csv(array $rows, string $delimiter = ','): UploadedFile
    {
        $lines = array_map(
            fn (array $row): string => implode($delimiter, array_map(
                fn (string $cell): string => str_contains($cell, $delimiter) ? '"'.$cell.'"' : $cell,
                $row,
            )),
            $rows,
        );

        return UploadedFile::fake()->createWithContent('contacts.csv', "\u{FEFF}".implode("\n", $lines)."\n");
    }

    private function import(UploadedFile $file): void
    {
        $this->actingAs(User::factory()->supervisor()->create())
            ->post(route('contacts.import.store'), ['file' => $file])
            ->assertRedirect(route('contacts.import.create'))
            ->assertSessionHasNoErrors();
    }

    public function test_the_sample_of_five_hundred_contacts_imports_without_errors(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'contacts-import-sample.csv',
            (string) file_get_contents(base_path('docs/samples/contacts-import-sample.csv')),
        );

        $this->import($file);

        // 500 rows, three of them deliberately broken.
        $this->assertSame(497, Contact::query()->count());
        $this->assertTrue(Tag::query()->where('name', 'VIP')->exists());
        $this->assertTrue(Contact::query()->where('phone', '+966510007919')->exists());
    }

    public function test_respond_io_style_columns_are_found_in_any_order(): void
    {
        $this->import($this->csv([
            ['Tags', 'Email', 'Phone Number', 'Last Name', 'First Name'],
            ['عميل محتمل; VIP', 'sara@example.com', '0551234567', 'العتيبي', 'سارة'],
        ]));

        $contact = Contact::query()->with('tags')->firstOrFail();
        $this->assertSame('سارة العتيبي', $contact->name);
        $this->assertSame('+966551234567', $contact->phone);
        $this->assertSame('sara@example.com', $contact->email);
        $this->assertEqualsCanonicalizing(['عميل محتمل', 'VIP'], $contact->tags->pluck('name')->all());
        $this->assertSame(Contact::SOURCE_IMPORT, $contact->source);
    }

    public function test_arabic_headings_and_semicolons_work(): void
    {
        $this->import($this->csv([
            ['الاسم', 'رقم الجوال', 'الوسوم'],
            ['فهد', '٠٥٥١٢٣٤٥٦٧', 'دعم فني'],
        ], ';'));

        $this->assertSame('فهد', Contact::query()->firstOrFail()->name);
    }

    public function test_an_existing_number_is_merged_without_overwriting_what_was_typed(): void
    {
        $existing = Contact::factory()->create(['name' => 'الاسم الذي كتبه الفريق', 'phone' => '+966551234567', 'email' => null]);

        $this->import($this->csv([
            ['Name', 'Phone', 'Email', 'Tags'],
            ['اسم من الملف', '0551234567', 'new@example.com', 'عميل محتمل'],
        ]));

        $existing->refresh();
        $this->assertSame(1, Contact::query()->count());
        $this->assertSame('الاسم الذي كتبه الفريق', $existing->name);
        $this->assertSame('new@example.com', $existing->email);
        $this->assertSame(['عميل محتمل'], $existing->tags->pluck('name')->all());
    }

    public function test_the_same_number_twice_in_a_file_is_one_contact(): void
    {
        $this->import($this->csv([
            ['Phone', 'Tags'],
            ['0551234567', 'أ'],
            ['+966551234567', 'ب'],
        ]));

        $this->assertSame(1, Contact::query()->count());
        $this->assertSame(2, Contact::query()->firstOrFail()->tags()->count());
    }

    public function test_a_file_without_a_number_column_imports_nothing(): void
    {
        $this->import($this->csv([
            ['Name', 'Email'],
            ['سارة', 'sara@example.com'],
        ]));

        $this->assertSame(0, Contact::query()->count());
    }

    public function test_only_supervisors_import(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('contacts.import.create'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->post(route('contacts.import.store'), ['file' => $this->csv([['Phone'], ['0551234567']])])
            ->assertForbidden();
    }

    public function test_only_csv_files_are_accepted(): void
    {
        $this->actingAs(User::factory()->supervisor()->create())
            ->post(route('contacts.import.store'), ['file' => UploadedFile::fake()->image('photo.png')])
            ->assertSessionHasErrors(['file' => __('app.contacts.import.errors.not_csv')]);
    }
}
