<?php

namespace Database\Seeders;

use App\Enums\Availability;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * A local team to try the platform with.
     *
     * Local only: log in as supervisor@mirsalink.test, or any member below,
     * with the factory's password, "password". On a server the first account
     * comes from `php artisan mirsalink:supervisor` instead.
     */
    public function run(): void
    {
        $supervisor = User::factory()->supervisor()->create([
            'name' => 'مشرف ميرسالينك',
            'email' => 'supervisor@mirsalink.test',
        ]);

        $agents = [
            ['سارة العتيبي', 'sara@mirsalink.test', Availability::Available],
            ['خالد القحطاني', 'khalid@mirsalink.test', Availability::Available],
            ['نورة الشمري', 'noura@mirsalink.test', Availability::Busy],
            ['فهد الدوسري', 'fahad@mirsalink.test', Availability::Away],
        ];

        foreach ($agents as [$name, $email, $availability]) {
            User::factory()->availability($availability)->create([
                'name' => $name,
                'email' => $email,
                'invited_by' => $supervisor->id,
                'invited_at' => now()->subWeek(),
            ]);
        }

        User::factory()->invited($supervisor)->create([
            'name' => 'ريم الحربي',
            'email' => 'reem@mirsalink.test',
        ]);

        User::factory()->deactivated()->create([
            'name' => 'عبدالرحمن السبيعي',
            'email' => 'abdulrahman@mirsalink.test',
            'invited_by' => $supervisor->id,
        ]);
    }
}
