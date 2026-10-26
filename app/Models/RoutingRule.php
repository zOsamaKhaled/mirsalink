<?php

namespace App\Models;

use App\Enums\RoutingMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Who takes the conversations with one tag, or, with no tag, everything
 * that no other rule claims. App\Support\Routing picks the member.
 *
 * @property int $id
 * @property int|null $tag_id
 * @property RoutingMethod $method
 * @property int|null $last_assigned_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tag_id', 'method'])]
class RoutingRule extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => RoutingMethod::class,
        ];
    }

    /**
     * @return BelongsTo<Tag, $this>
     */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->orderBy('users.id');
    }

    public function isDefault(): bool
    {
        return $this->tag_id === null;
    }

    /**
     * The rule for everything no tagged rule claims, made the first time it
     * is asked for: round robin across the whole team until set otherwise.
     */
    public static function fallback(): self
    {
        return static::query()->firstOrCreate(['tag_id' => null], ['method' => RoutingMethod::RoundRobin]);
    }
}
