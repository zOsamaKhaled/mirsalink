<?php

namespace Database\Factories;

use App\Enums\TemplateCategory;
use App\Enums\TemplateHeader;
use App\Enums\TemplateStatus;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->lexify('follow_up_????'),
            'language' => 'ar',
            'category' => TemplateCategory::Utility,
            'header_type' => TemplateHeader::None,
            'body' => 'مرحبًا {{1}}، نتابع معك طلبك رقم {{2}}. هل تحتاج مساعدة؟',
            'body_examples' => ['أحمد', '1024'],
            'status' => TemplateStatus::Draft,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TemplateStatus::Approved,
            'submitted_at' => now()->subDay(),
            'decided_at' => now()->subDay(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TemplateStatus::Pending,
            'submitted_at' => now(),
        ]);
    }

    public function marketing(): static
    {
        return $this->state(fn (array $attributes) => ['category' => TemplateCategory::Marketing]);
    }
}
