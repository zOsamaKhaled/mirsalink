<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use Carbon\CarbonInterface;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A contact's thread with the team. One per contact, as on WhatsApp.
 *
 * @property int $id
 * @property int $contact_id
 * @property ConversationStatus $status
 * @property int|null $assigned_to
 * @property bool $bot_active
 * @property int $unread_count
 * @property Carbon|null $last_message_at
 * @property Carbon|null $last_inbound_at
 * @property Carbon|null $awaiting_reply_since
 * @property Carbon|null $first_response_at
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /** WhatsApp lets the team write freely for this long after the customer's last message. */
    public const int WINDOW_HOURS = 24;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'bot_active' => 'boolean',
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'awaiting_reply_since' => 'datetime',
            'first_response_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * @return HasMany<ConversationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ConversationEvent::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->withPivot(['tagged_by', 'by_bot'])
            ->withTimestamps()
            ->orderBy('name');
    }

    public function isOpen(): bool
    {
        return $this->status === ConversationStatus::Open;
    }

    /**
     * When free-form replies stop being allowed, or null if the customer
     * has never written.
     */
    public function windowClosesAt(): ?CarbonInterface
    {
        return $this->last_inbound_at?->copy()->addHours(self::WINDOW_HOURS);
    }

    public function windowIsOpen(): bool
    {
        return $this->windowClosesAt()?->isFuture() ?? false;
    }

    /**
     * What a member may see: a supervisor sees everything, an agent their
     * own and the unassigned.
     *
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSupervisor()) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query
            ->where('assigned_to', $user->id)
            ->orWhereNull('assigned_to'));
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->isSupervisor() || $this->assigned_to === null || $this->assigned_to === $user->id;
    }
}
