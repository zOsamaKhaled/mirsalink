<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Someone the team talks to on WhatsApp, known by their number.
 *
 * @property int $id
 * @property string|null $name
 * @property string $phone
 * @property string|null $email
 * @property string|null $company
 * @property string|null $notes
 * @property string $source
 * @property Carbon|null $opted_out_at
 * @property Carbon|null $last_message_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'phone', 'email', 'company', 'notes', 'source'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory, SoftDeletes;

    public const string SOURCE_MANUAL = 'manual';

    public const string SOURCE_IMPORT = 'import';

    public const string SOURCE_WHATSAPP = 'whatsapp';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opted_out_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps()->orderBy('name');
    }

    /**
     * Their one conversation thread; it is closed and reopened, never
     * replaced.
     *
     * @return HasOne<Conversation, $this>
     */
    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    /**
     * Whether broadcasts may reach them. Replies to their own messages
     * always may: opting out stops campaigns, not conversations.
     */
    public function acceptsBroadcasts(): bool
    {
        return $this->opted_out_at === null;
    }

    /**
     * Name, number or email containing the words typed. A number is matched
     * on its digits, so "055 123" finds +96655123….
     *
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $digits = preg_replace('/\D+/', '', $term) ?? '';
        $localDigits = ltrim($digits, '0');

        return $query->where(function (Builder $query) use ($term, $localDigits): void {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('company', 'like', "%{$term}%");

            if (strlen($localDigits) >= 3) {
                $query->orWhere('phone', 'like', "%{$localDigits}%");
            }
        });
    }
}
