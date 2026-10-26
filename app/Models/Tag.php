<?php

namespace App\Models;

use App\Enums\TagColor;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A label for sorting conversations and contacts. Its description tells the
 * assistant (phase 8) when a conversation deserves it.
 *
 * @property int $id
 * @property string $name
 * @property TagColor $color
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'color', 'description'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => TagColor::class,
        ];
    }

    /**
     * @return BelongsToMany<Contact, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class)->withTimestamps();
    }

    /**
     * @return HasOne<RoutingRule, $this>
     */
    public function routingRule(): HasOne
    {
        return $this->hasOne(RoutingRule::class);
    }

    /**
     * The tag with this name, made with the next colour in turn if it does
     * not exist yet. Used by the import, where tags arrive as plain words.
     */
    public static function findOrMake(string $name): self
    {
        $name = trim($name);

        return static::query()->firstOrCreate(
            ['name' => $name],
            ['color' => TagColor::forPosition(static::query()->count())],
        );
    }
}
