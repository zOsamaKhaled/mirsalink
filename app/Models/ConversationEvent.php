<?php

namespace App\Models;

use App\Enums\ConversationEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property ConversationEventType $type
 * @property int|null $actor_id
 * @property array<string, mixed>|null $data
 * @property Carbon|null $created_at
 */
class ConversationEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConversationEventType::class,
            'data' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function record(Conversation $conversation, ConversationEventType $type, ?User $actor = null, array $data = []): self
    {
        return static::query()->create([
            'conversation_id' => $conversation->id,
            'type' => $type,
            'actor_id' => $actor?->id,
            'data' => $data === [] ? null : $data,
        ]);
    }
}
