<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a local supervisor to try the platform with.
     *
     * Local only: log in as supervisor@mirsalink.test with the factory's
     * password, "password". On a server the first account comes from
     * `php artisan mirsalink:supervisor` instead.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'مشرف ميرسالينك',
            'email' => 'supervisor@mirsalink.test',
        ]);
    }
}
