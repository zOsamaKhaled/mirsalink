<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_are_served_in_arabic_by_default(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('locale', 'ar')
                ->where('dir', 'rtl')
                ->where('translations.auth.login.submit', 'دخول'));
    }

    public function test_choosing_english_switches_the_page_and_remembers_it(): void
    {
        $this->get(route('login', ['lang' => 'en']))
            ->assertCookie('locale', 'en', encrypted: false)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('locale', 'en')
                ->where('dir', 'ltr')
                ->where('translations.auth.login.submit', 'Log in'));

        $this->withUnencryptedCookie('locale', 'en')
            ->get(route('login'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'en'));
    }

    public function test_an_unknown_language_falls_back_to_arabic(): void
    {
        $this->get(route('login', ['lang' => 'fr']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'ar'));
    }

    public function test_login_errors_are_written_in_the_chosen_language(): void
    {
        $this->post(route('login.store'), [
            'email' => 'nobody@mirsalink.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email' => trans('auth.failed', [], 'ar')]);
    }

    public function test_there_is_no_public_sign_up(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
