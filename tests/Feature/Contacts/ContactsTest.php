<?php

namespace Tests\Feature\Contacts;

use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ContactsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_member_sees_the_contacts(): void
    {
        Contact::factory()->count(3)->create();

        $this->actingAs(User::factory()->create())
            ->get(route('contacts.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('contacts/index')
                ->has('contacts.data', 3)
                ->where('total', 3));
    }

    public function test_search_finds_a_number_however_it_is_typed(): void
    {
        Contact::factory()->create(['name' => 'عبدالله', 'phone' => '+966551234567']);
        Contact::factory()->create(['name' => 'سارة', 'phone' => '+966559999999']);

        $this->actingAs(User::factory()->create())
            ->get(route('contacts.index', ['q' => '055 123']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('contacts.data', 1)
                ->where('contacts.data.0.name', 'عبدالله'));

        $this->actingAs(User::factory()->create())
            ->get(route('contacts.index', ['q' => 'سارة']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('contacts.data', 1));
    }

    public function test_contacts_filter_by_tag_and_by_stopped_broadcasts(): void
    {
        $lead = Tag::factory()->create();
        Contact::factory()->create()->tags()->attach($lead);
        Contact::factory()->create();
        Contact::factory()->optedOut()->create();

        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('contacts.index', ['tag' => $lead->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('contacts.data', 1));

        $this->actingAs($member)
            ->get(route('contacts.index', ['opted_out' => 1]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('contacts.data', 1)
                ->where('contacts.data.0.opted_out', true));
    }

    public function test_a_member_adds_a_contact_with_its_number_normalized_and_tags(): void
    {
        $tag = Tag::factory()->create();
        $member = User::factory()->create();

        $this->actingAs($member)
            ->post(route('contacts.store'), [
                'name' => 'عبدالله الحربي',
                'phone' => '055 123 4567',
                'tags' => [$tag->id],
            ])
            ->assertSessionHasNoErrors();

        $contact = Contact::query()->firstOrFail();
        $this->assertSame('+966551234567', $contact->phone);
        $this->assertSame($member->id, $contact->created_by);
        $this->assertTrue($contact->tags->contains($tag));
    }

    public function test_a_number_that_cannot_be_read_is_refused_with_a_clear_message(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('contacts.store'), ['phone' => '12345'])
            ->assertSessionHasErrors(['phone' => __('app.contacts.errors.bad_phone')]);
    }

    public function test_the_same_number_cannot_be_two_contacts(): void
    {
        Contact::factory()->create(['phone' => '+966551234567']);

        $this->actingAs(User::factory()->create())
            ->post(route('contacts.store'), ['phone' => '0551234567'])
            ->assertSessionHasErrors(['phone' => __('app.contacts.errors.phone_taken')]);
    }

    public function test_adding_a_deleted_number_brings_the_contact_back(): void
    {
        $contact = Contact::factory()->create(['phone' => '+966551234567']);
        $contact->delete();

        $this->actingAs(User::factory()->create())
            ->post(route('contacts.store'), ['name' => 'عاد من جديد', 'phone' => '0551234567'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Contact::withTrashed()->count());
        $this->assertSame('عاد من جديد', $contact->fresh()->name);
        $this->assertFalse($contact->fresh()->trashed());
    }

    public function test_a_member_edits_a_contact_and_stops_its_broadcasts(): void
    {
        $contact = Contact::factory()->create(['phone' => '+966551234567']);

        $this->actingAs(User::factory()->create())
            ->put(route('contacts.update', $contact), [
                'name' => 'اسم جديد',
                'phone' => '+966551234567',
                'opted_out' => true,
                'tags' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('اسم جديد', $contact->fresh()->name);
        $this->assertFalse($contact->fresh()->acceptsBroadcasts());
    }

    public function test_only_supervisors_delete_contacts(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('contacts.destroy', $contact))
            ->assertForbidden();

        $this->actingAs(User::factory()->supervisor()->create())
            ->delete(route('contacts.destroy', $contact));

        $this->assertSoftDeleted($contact);
    }
}
