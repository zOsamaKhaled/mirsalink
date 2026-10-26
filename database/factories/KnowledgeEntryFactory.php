<?php

namespace Database\Factories;

use App\Models\KnowledgeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeEntry>
 */
class KnowledgeEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => 'كم مدة وصول العمالة بعد التعاقد؟',
            'answer' => 'من أسبوعين إلى ستة أسابيع حسب المهنة والجنسية.',
            'source' => 'سياسة الاستقدام 2026',
            'active' => true,
            'reviewed_at' => now()->subDays(10),
        ];
    }

    public function stale(): static
    {
        return $this->state(fn (array $attributes) => ['reviewed_at' => now()->subDays(KnowledgeEntry::REVIEW_DAYS + 5)]);
    }
}
