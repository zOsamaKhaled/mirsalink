<?php

namespace Database\Seeders;

use App\Enums\Availability;
use App\Enums\ConversationEventType;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageSender;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\RoutingMethod;
use App\Enums\TagColor;
use App\Enums\TemplateCategory;
use App\Enums\TemplateHeader;
use App\Enums\TemplateStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\KnowledgeEntry;
use App\Models\QuickReply;
use App\Models\RoutingRule;
use App\Models\Tag;
use App\Models\Template;
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
        $this->seedConversations();
        $this->seedRepliesAndTemplates($supervisor);
        $this->seedKnowledge($supervisor);
    }

    /**
     * The team's usual replies, one of Sara's own, and templates in each
     * state Meta can leave them.
     */
    private function seedRepliesAndTemplates(User $supervisor): void
    {
        $replies = [
            [null, 'welcome', 'ترحيب', 'أهلًا {name}، معك {agent} من فريق رزق. كيف أقدر أخدمك اليوم؟'],
            [null, 'prices', 'الأسعار', "باقاتنا تبدأ من 1,500 ريال شهريًا للعامل، وتشمل التأمين والسكن.\nأرسل لي المهنة وعدد العمال وأجهز لك عرضًا."],
            [null, 'docs', 'المستندات المطلوبة', 'نحتاج صورة السجل التجاري، وصورة هوية المفوَّض، وعنوان الموقع.'],
            [null, 'thanks', 'شكر وإغلاق', 'شكرًا لتواصلك يا {name}. إن احتجت أي شيء فنحن هنا.'],
            ['sara@mirsalink.test', 'followup', 'متابعة عرض', 'أهلًا {name}، هل اطلعت على العرض الذي أرسلته؟ يسعدني الإجابة عن أي سؤال.'],
        ];

        foreach ($replies as [$owner, $shortcut, $title, $body]) {
            $reply = new QuickReply([
                'user_id' => $owner === null ? null : User::query()->where('email', $owner)->value('id'),
                'shortcut' => $shortcut,
                'title' => $title,
                'body' => $body,
            ]);
            $reply->created_by = $supervisor->id;
            $reply->save();
        }

        $templates = [
            [
                'name' => 'order_follow_up', 'category' => TemplateCategory::Utility, 'status' => TemplateStatus::Approved,
                'header_type' => TemplateHeader::Text, 'header_text' => 'طلبك رقم {{1}}', 'header_example' => '1024',
                'body' => 'مرحبًا {{1}}، نتابع معك طلب العمالة الذي قدمته يوم {{2}}. هل تحتاج مساعدة في إكماله؟',
                'body_examples' => ['أبو فهد', 'الأحد'], 'footer' => 'رزق للاستقدام',
                'buttons' => [['type' => 'quick_reply', 'text' => 'نعم، أحتاج مساعدة'], ['type' => 'quick_reply', 'text' => 'لا، شكرًا']],
            ],
            [
                'name' => 'payment_reminder', 'category' => TemplateCategory::Utility, 'status' => TemplateStatus::Approved,
                'header_type' => TemplateHeader::None,
                'body' => 'مرحبًا {{1}}، نذكّرك بأن فاتورة اشتراكك بمبلغ {{2}} ريال تستحق خلال ثلاثة أيام.',
                'body_examples' => ['شركة الأفق', '4,500'], 'footer' => null,
                'buttons' => [['type' => 'url', 'text' => 'ادفع الآن', 'url' => 'https://rizq.sa/pay/{{1}}', 'example' => 'INV-1024']],
            ],
            [
                'name' => 'ramadan_offer', 'category' => TemplateCategory::Marketing, 'status' => TemplateStatus::Pending,
                'header_type' => TemplateHeader::Image,
                'body' => 'عرض رمضان يا {{1}}: خصم 20٪ على العمالة المنزلية حتى نهاية الشهر.',
                'body_examples' => ['أحمد'], 'footer' => 'للإلغاء أرسل: إيقاف',
                'buttons' => [['type' => 'phone', 'text' => 'اتصل بنا', 'phone' => '+966920000000']],
            ],
            [
                'name' => 'win_back', 'category' => TemplateCategory::Utility, 'status' => TemplateStatus::Rejected,
                'rejection_reason' => 'صياغة تسويقية في قالب خدمي',
                'header_type' => TemplateHeader::None,
                'body' => 'اشتقنا لك يا {{1}}! ارجع لرزق واحصل على أول شهر مجانًا.',
                'body_examples' => ['خالد'], 'footer' => null, 'buttons' => null,
            ],
            [
                'name' => 'interview_invite', 'category' => TemplateCategory::Utility, 'status' => TemplateStatus::Draft,
                'header_type' => TemplateHeader::None,
                'body' => 'مرحبًا {{1}}، تمت دعوتك لمقابلة عمل يوم {{2}} في مقر الشركة.',
                'body_examples' => ['منى', 'الثلاثاء'], 'footer' => null, 'buttons' => null,
            ],
        ];

        foreach ($templates as $template) {
            Template::query()->create([
                'language' => 'ar',
                'created_by' => $supervisor->id,
                'submitted_at' => $template['status'] === TemplateStatus::Draft ? null : now()->subDays(3),
                'decided_at' => in_array($template['status'], [TemplateStatus::Approved, TemplateStatus::Rejected], true) ? now()->subDays(2) : null,
                ...$template,
            ]);
        }
    }

    private function seedKnowledge(User $supervisor): void
    {
        $entries = [
            ['كم مدة وصول العمالة بعد التعاقد؟', 'من أسبوعين إلى ستة أسابيع حسب المهنة والجنسية، ونبلغ العميل بكل مرحلة.', 'سياسة الاستقدام 2026', 20],
            ['هل يمكن استبدال العامل إن لم يناسبني؟', 'نعم، مرة واحدة مجانًا خلال أول 90 يومًا من وصوله.', 'شروط العقد، البند 7', 45],
            ['ما طرق الدفع المتاحة؟', 'مدى وبطاقات الائتمان والتحويل البنكي، والتقسيط على أربع دفعات عبر تابي.', 'صفحة الدفع في رزق', 130],
            ['هل تشمل الباقة السكن والتأمين؟', 'تشمل التأمين الطبي دائمًا، والسكن في الباقة المتقدمة فقط.', 'جدول الباقات', 10],
            ['كيف يرفع الباحث عن عمل سيرته الذاتية؟', 'من التطبيق: الملف الشخصي ثم السيرة الذاتية، بصيغة PDF وحجم أقل من 5 ميجا.', 'دليل التطبيق', 200],
        ];

        foreach ($entries as $position => [$question, $answer, $source, $daysAgo]) {
            $entry = new KnowledgeEntry([
                'question' => $question,
                'answer' => $answer,
                'source' => $source,
                'active' => $position !== 4,
            ]);
            $entry->forceFill([
                'created_by' => $supervisor->id,
                'reviewed_by' => $supervisor->id,
                'reviewed_at' => now()->subDays($daysAgo),
            ])->save();
        }
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

    /**
     * Conversations that show each state of the inbox: waiting long,
     * unassigned, a reply window closing and one closed, a failed message,
     * notes and the platform's own lines.
     */
    private function seedConversations(): void
    {
        $member = fn (string $email): User => User::query()->where('email', $email)->firstOrFail();
        $tag = fn (string $name): int => (int) Tag::query()->where('name', $name)->value('id');
        $sara = $member('sara@mirsalink.test');
        $khalid = $member('khalid@mirsalink.test');
        $noura = $member('noura@mirsalink.test');

        $this->conversation('أبو فهد', '+966551234567', $sara, [$tag('عميل محتمل'), $tag('صاحب شركة')], [
            [95, 'in', 'السلام عليكم، عندنا شركة مقاولات فيها 40 موظف ونبغى نوظف عمالة عن طريقكم'],
            [94, 'event', ConversationEventType::Assigned, ['to' => $sara->id]],
            [90, 'out', 'وعليكم السلام أبو فهد، حياك الله. معك سارة من فريق المبيعات. كم عدد العمال اللي تحتاجهم تقريبًا؟', $sara, MessageStatus::Read],
            [70, 'in', 'تقريبًا 12 عامل بناء و3 كهربائيين'],
            [66, 'note', 'عميل جاد، أرسلي له عرض الباقة المتقدمة مع خصم السنوي.', $khalid],
            [60, 'out', 'ممتاز، أرسل لك الآن تفاصيل الباقة المتقدمة وسعرها.', $sara, MessageStatus::Delivered],
            [12, 'in', 'تمام، هل فيه خصم لو اشتركنا سنة كاملة؟'],
            [11, 'in', 'وكم المدة لين يوصل العمال؟'],
        ], unread: 2, awaitingSince: 12);

        $this->conversation('منى السالم', '+966559876543', null, [$tag('باحث عن عمل')], [
            [42, 'in', 'مرحبا، سجلت في التطبيق من أسبوع وما وصلني أي رد على طلباتي'],
        ], unread: 1, awaitingSince: 42);

        $this->conversation(null, '+966561112233', null, [], [
            [3, 'in', 'السلام عليكم، أبغى أعرف الأسعار لو سمحت'],
        ], unread: 1, awaitingSince: 3);

        $this->conversation('شركة الأفق للتجارة', '+966503334455', $khalid, [$tag('دفع وفوترة')], [
            [23 * 60 + 5, 'in', 'دفعت الفاتورة الشهرية لكن الحساب ما زال موقوف'],
            [23 * 60 + 2, 'out', 'أهلًا بكم، أتحقق من الدفعة الآن وأرجع لكم.', $khalid, MessageStatus::Read],
            [22 * 60 + 50, 'out', 'تم تفعيل الحساب، والمعذرة على التأخير.', $khalid, MessageStatus::Read],
        ]);

        $this->conversation('عبدالله المطيري', '+966507778899', $sara, [$tag('عميل محتمل')], [
            [2 * 24 * 60 + 30, 'in', 'هل عندكم سائقين خاصين؟'],
            [2 * 24 * 60 + 25, 'out', 'نعم، عندنا سائقين بخبرات مختلفة. أرسل لك القائمة؟', $sara, MessageStatus::Read],
        ]);

        $this->conversation('ياسر الغامدي', '+966540001122', $noura, [$tag('دعم فني')], [
            [180, 'in', 'التطبيق يطلع لي خطأ لما أرفع السيرة الذاتية'],
            [175, 'out', 'أهلًا ياسر، هل الملف بصيغة PDF وحجمه أقل من 5 ميجا؟', $noura, MessageStatus::Read],
            [30, 'in', 'نعم PDF وحجمه 2 ميجا'],
            [25, 'out', 'جرّب الآن من جديد بعد تحديث التطبيق، وأخبرني بالنتيجة.', $noura, MessageStatus::Failed],
        ]);

        $closed = $this->conversation('هند العنزي', '+966533445566', $khalid, [$tag('شكوى')], [
            [26 * 60, 'in', 'العاملة ما وصلت في الموعد المتفق عليه!'],
            [25 * 60 + 50, 'out', 'نعتذر جدًا عن التأخير، تواصلنا مع مكتب الاستقدام وستصل غدًا صباحًا.', $khalid, MessageStatus::Read],
            [25 * 60 + 40, 'in', 'تمام شكرًا'],
            [25 * 60 + 30, 'out', 'العفو، نحن في الخدمة.', $khalid, MessageStatus::Read],
            [25 * 60 + 20, 'event', ConversationEventType::Closed, null, $khalid],
        ]);
        $closed->forceFill([
            'status' => ConversationStatus::Closed,
            'closed_at' => now()->subMinutes(25 * 60 + 20),
            'closed_by' => $khalid->id,
        ])->save();
    }

    /**
     * One conversation from a script of lines, each so many minutes ago:
     * [minutes, 'in', text] from the customer, [minutes, 'out', text, member,
     * status] from the team, [minutes, 'note', text, member], and
     * [minutes, 'event', type, data, actor].
     *
     * @param  list<int>  $tagIds
     * @param  list<array<int, mixed>>  $lines
     */
    private function conversation(?string $name, string $phone, ?User $assignee, array $tagIds, array $lines, int $unread = 0, ?int $awaitingSince = null): Conversation
    {
        $contact = Contact::query()->create(['name' => $name, 'phone' => $phone, 'source' => Contact::SOURCE_WHATSAPP]);
        $conversation = Conversation::query()->create(['contact_id' => $contact->id, 'assigned_to' => $assignee?->id]);
        $conversation->tags()->attach($tagIds);

        foreach ($lines as $line) {
            $at = now()->subMinutes($line[0]);

            if ($line[1] === 'event') {
                ConversationEvent::query()->create([
                    'conversation_id' => $conversation->id,
                    'type' => $line[2],
                    'actor_id' => ($line[4] ?? null)?->id,
                    'data' => $line[3],
                    'created_at' => $at,
                ]);

                continue;
            }

            $failed = ($line[4] ?? null) === MessageStatus::Failed;

            $conversation->messages()->create([
                'direction' => $line[1] === 'in' ? MessageDirection::Inbound : MessageDirection::Outbound,
                'sender' => $line[1] === 'in' ? MessageSender::Contact : MessageSender::Agent,
                'user_id' => ($line[3] ?? null)?->id,
                'type' => $line[1] === 'note' ? MessageType::Note : MessageType::Text,
                'body' => $line[2],
                'external_id' => $line[1] === 'note' ? null : "wamid.seed.{$conversation->id}.{$line[0]}",
                'status' => $line[4] ?? null,
                'error_code' => $failed ? '131026' : null,
                'error_message' => $failed ? __('app.simulator.failure', [], 'ar') : null,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }

        $minutes = fn (string $kind) => collect($lines)->where(1, $kind)->pluck(0);
        $firstReply = $minutes('out')->max();

        $conversation->forceFill([
            'status' => ConversationStatus::Open,
            'unread_count' => $unread,
            'last_message_at' => now()->subMinutes(collect($lines)->whereIn(1, ['in', 'out', 'note'])->min(0)),
            'last_inbound_at' => now()->subMinutes($minutes('in')->min()),
            'awaiting_reply_since' => $awaitingSince === null ? null : now()->subMinutes($awaitingSince),
            'first_response_at' => $firstReply === null ? null : now()->subMinutes($firstReply),
            'created_at' => now()->subMinutes(collect($lines)->max(0)),
        ])->save();

        $contact->forceFill(['last_message_at' => $conversation->last_message_at])->save();

        return $conversation;
    }
}
