<?php

namespace Tests\Feature\Templates;

use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\TemplateStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TemplatesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'name' => 'Order Follow Up',
            'language' => 'ar',
            'category' => 'utility',
            'header_type' => 'text',
            'header_text' => 'طلبك رقم {{1}}',
            'header_example' => '1024',
            'body' => 'مرحبًا {{1}}، طلبك جاهز للاستلام يوم {{2}} من فرعنا.',
            'body_examples' => ['أحمد', 'الأحد'],
            'footer' => 'رزق للاستقدام',
            'buttons' => [
                ['type' => 'quick_reply', 'text' => 'تم، شكرًا'],
                ['type' => 'url', 'text' => 'تتبع الطلب', 'url' => 'https://rizq.sa/orders/{{1}}', 'example' => '1024'],
                ['type' => 'phone', 'text' => 'اتصل بنا', 'phone' => '0551234567'],
            ],
            ...$overrides,
        ];
    }

    public function test_a_supervisor_writes_a_template_sends_it_for_review_and_the_simulator_approves_it(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($supervisor)
            ->post(route('templates.store'), $this->form())
            ->assertRedirect(route('templates.index', ['tab' => 'templates']));

        $template = Template::query()->sole();
        $this->assertSame('order_follow_up', $template->name);
        $this->assertSame(TemplateStatus::Draft, $template->status);
        $this->assertSame('+966551234567', $template->buttons[2]['phone']);
        $this->assertSame(2, $template->bodyVariableCount());
        $this->assertTrue($template->headerHasVariable());

        $this->actingAs($supervisor)->post(route('templates.submit', $template));
        $this->assertSame(TemplateStatus::Pending, $template->fresh()->status);
        $this->assertStringStartsWith('tpl.sim.', $template->fresh()->external_id);

        $this->actingAs($supervisor)
            ->put(route('simulator.templates.decide', $template), ['decision' => 'approved']);
        $this->assertSame(TemplateStatus::Approved, $template->fresh()->status);

        $this->actingAs($supervisor)
            ->get(route('templates.edit', $template))
            ->assertRedirect(route('templates.index', ['tab' => 'templates']))
            ->assertInertiaFlash('toast.type', 'info');
    }

    public function test_a_refused_template_returns_to_draft_when_edited(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $template = Template::factory()->pending()->create();

        $this->actingAs($supervisor)
            ->put(route('simulator.templates.decide', $template), ['decision' => 'rejected', 'reason' => 'صياغة تسويقية']);
        $this->assertSame('صياغة تسويقية', $template->fresh()->rejection_reason);

        $this->actingAs($supervisor)
            ->put(route('templates.update', $template), $this->form(['name' => $template->name]))
            ->assertSessionHasNoErrors();

        $this->assertSame(TemplateStatus::Draft, $template->fresh()->status);
        $this->assertNull($template->fresh()->rejection_reason);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function refusedForms(): array
    {
        return [
            'starts with a variable' => [['body' => '{{1}} طلبك جاهز للاستلام يوم {{2}} من فرعنا.'], 'body'],
            'ends with a variable' => [['body' => 'مرحبًا {{1}}، طلبك جاهز يوم {{2}}'], 'body'],
            'skips a number' => [['body' => 'مرحبًا {{1}}، طلبك جاهز يوم {{3}} من فرعنا.'], 'body'],
            'variables side by side' => [['body' => 'مرحبًا {{1}} {{2}} طلبك جاهز.'], 'body'],
            'an example missing' => [['body_examples' => ['أحمد']], 'body_examples'],
            'a name with capitals' => [['name' => 'Order-Fóllow'], 'name'],
            'a variable in the footer' => [['footer' => 'فرع {{1}}'], 'footer'],
            'a variable mid-link' => [['buttons' => [['type' => 'url', 'text' => 'افتح', 'url' => 'https://rizq.sa/{{1}}/x', 'example' => '1']]], 'buttons.0.url'],
            'three links' => [['buttons' => array_fill(0, 3, ['type' => 'url', 'text' => 'افتح', 'url' => 'https://rizq.sa'])], 'buttons'],
            'a plain http link' => [['buttons' => [['type' => 'url', 'text' => 'افتح', 'url' => 'http://rizq.sa']]], 'buttons.0.url'],
        ];
    }

    #[DataProvider('refusedForms')]
    public function test_templates_meta_would_refuse_are_stopped_here(array $overrides, string $field): void
    {
        $this->actingAs(User::factory()->supervisor()->create())
            ->post(route('templates.store'), $this->form($overrides))
            ->assertSessionHasErrors($field);

        $this->assertSame(0, Template::query()->count());
    }

    public function test_agents_read_templates_but_do_not_write_them(): void
    {
        $agent = User::factory()->create();
        Template::factory()->approved()->create();

        $this->actingAs($agent)
            ->get(route('templates.index', ['tab' => 'templates']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('templates/index')
                ->where('tab', 'templates')
                ->has('templates', 1));

        $this->actingAs($agent)->post(route('templates.store'), $this->form())->assertForbidden();
        $this->actingAs($agent)->get(route('templates.create'))->assertForbidden();
    }

    public function test_an_approved_template_reaches_a_customer_after_the_window_closes(): void
    {
        $agent = User::factory()->create();
        $conversation = Conversation::factory()->assignedTo($agent)->windowClosed()->create();
        $template = Template::factory()->approved()->create();

        $this->actingAs($agent)
            ->post(route('inbox.template.store', $conversation), [
                'template_id' => $template->id,
                'body' => ['خالد', '1024'],
            ])
            ->assertInertiaFlashMissing('toast');

        $message = $conversation->messages()->sole();
        $this->assertSame(MessageType::Template, $message->type);
        $this->assertSame(MessageStatus::Sent, $message->status);
        $this->assertSame('مرحبًا خالد، نتابع معك طلبك رقم 1024. هل تحتاج مساعدة؟', $message->body);
        $this->assertSame(['خالد', '1024'], $message->template_params['body']);

        $this->actingAs($agent)
            ->get(route('inbox.show', $conversation))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('thread.0.template.name', $template->name)
                ->has('templates', 1));
    }

    public function test_a_template_is_refused_unless_approved_complete_and_allowed_for_the_contact(): void
    {
        $agent = User::factory()->create();
        $conversation = Conversation::factory()->assignedTo($agent)->create();
        $optedOut = Conversation::factory()->assignedTo($agent)->for(Contact::factory()->optedOut())->create();

        $cases = [
            [$conversation, Template::factory()->create(), ['أحمد', '1']],
            [$conversation, Template::factory()->approved()->create(), ['أحمد']],
            [$optedOut, Template::factory()->approved()->marketing()->create(), ['أحمد', '1']],
        ];

        foreach ($cases as [$target, $template, $body]) {
            $this->actingAs($agent)
                ->post(route('inbox.template.store', $target), ['template_id' => $template->id, 'body' => $body])
                ->assertInertiaFlash('toast.type', 'error');
        }

        $this->assertSame(0, $conversation->messages()->count() + $optedOut->messages()->count());
    }
}
