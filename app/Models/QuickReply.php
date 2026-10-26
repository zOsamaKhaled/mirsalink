<?php

namespace App\Models;

use Database\Factories\QuickReplyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A saved reply. With an owner it is that member's own; without, the
 * team's, which supervisors keep. {name} becomes the customer's name and
 * {agent} the member's when it is used.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $shortcut
 * @property string $title
 * @property string $body
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'shortcut', 'title', 'body'])]
class QuickReply extends Model
{
    /** @use HasFactory<QuickReplyFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isShared(): bool
    {
        return $this->user_id === null;
    }

    /**
     * The team's replies and the member's own.
     *
     * @param  Builder<QuickReply>  $query
     * @return Builder<QuickReply>
     */
    public function scopeAvailableTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereNull('user_id')
            ->orWhere('user_id', $user->id));
    }

    /** Whether the member may change or delete it. */
    public function isManageableBy(User $user): bool
    {
        return $this->isShared() ? $user->can('manage-team') : $this->user_id === $user->id;
    }
}
