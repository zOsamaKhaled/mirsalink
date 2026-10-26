<?php

namespace Database\Seeders;

use App\Enums\Availability;
use App\Enums\RoutingMethod;
use App\Enums\TagColor;
use App\Models\Contact;
use App\Models\RoutingRule;
use App\Models\Tag;
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

        $this->seedTagsAndRouting();
        $this->seedContacts();
    }

    /**
     * The categories Rizq's team sorts its WhatsApp conversations by, with
     * who takes the two that need people most.
     */
    private function seedTagsAndRouting(): void
    {
        $tags = [
            ['عميل محتمل', TagColor::Blue, 'صاحب عمل يسأل عن الباقات أو الأسعار ولم يشترك بعد.'],
            ['صاحب شركة', TagColor::Violet, 'صاحب شركة أو مسؤول توظيف لديه حساب في رزق.'],
            ['باحث عن عمل', TagColor::Cyan, 'باحث عن عمل يسأل عن حسابه أو ملفه أو الوظائف.'],
            ['دعم فني', TagColor::Slate, 'مشكلة في الدخول أو التطبيق أو رفع الملفات.'],
            ['دفع وفوترة', TagColor::Amber, 'سؤال عن الدفع أو الفواتير أو الاشتراك المدفوع.'],
            ['شكوى', TagColor::Red, 'عميل غاضب أو يشتكي من الخدمة.'],
        ];

        foreach ($tags as [$name, $color, $description]) {
            Tag::query()->create(['name' => $name, 'color' => $color, 'description' => $description]);
        }

        $leads = RoutingRule::query()->create([
            'tag_id' => Tag::query()->where('name', 'عميل محتمل')->value('id'),
            'method' => RoutingMethod::RoundRobin,
        ]);
        $leads->members()->sync(User::query()->whereIn('email', ['sara@mirsalink.test', 'khalid@mirsalink.test'])->pluck('id'));

        $support = RoutingRule::query()->create([
            'tag_id' => Tag::query()->where('name', 'دعم فني')->value('id'),
            'method' => RoutingMethod::LeastBusy,
        ]);
        $support->members()->sync(User::query()->whereIn('email', ['noura@mirsalink.test', 'fahad@mirsalink.test'])->pluck('id'));

        RoutingRule::fallback();
    }

    private function seedContacts(): void
    {
        $tagIds = Tag::query()->pluck('id');

        Contact::factory()->count(40)->create()->each(
            fn (Contact $contact) => $contact->tags()->sync($tagIds->random(random_int(0, 2))),
        );

        Contact::factory()->count(3)->optedOut()->create();
    }
}
