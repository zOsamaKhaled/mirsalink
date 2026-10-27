<?php

namespace App\Models;

use Database\Factories\KnowledgeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One thing the team knows, as a question and its answer. An entry no one
 * has confirmed for REVIEW_DAYS asks to be checked again: prices and
 * policies change, and the assistant must not repeat old ones.
 *
 * @property int $id
 * @property string $question
 * @property string $answer
 * @property string|null $source
 * @property bool $active
 * @property list<float>|null $embedding
 * @property string|null $embedding_model
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['question', 'answer', 'source', 'active'])]
class KnowledgeEntry extends Model
{
    /** @use HasFactory<KnowledgeEntryFactory> */
    use HasFactory;

    public const int REVIEW_DAYS = 90;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'reviewed_at' => 'datetime',
            'embedding' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function needsReview(): bool
    {
        return $this->reviewed_at === null || $this->reviewed_at->lte(now()->subDays(self::REVIEW_DAYS));
    }

    /**
     * @param  Builder<KnowledgeEntry>  $query
     * @return Builder<KnowledgeEntry>
     */
    public function scopeNeedingReview(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereNull('reviewed_at')
            ->orWhere('reviewed_at', '<=', now()->subDays(self::REVIEW_DAYS)));
    }

    /**
     * @param  Builder<KnowledgeEntry>  $query
     * @return Builder<KnowledgeEntry>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(fn (Builder $query) => $query
            ->where('question', 'like', $like)
            ->orWhere('answer', 'like', $like)
            ->orWhere('source', 'like', $like));
    }
}
