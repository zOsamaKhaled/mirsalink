<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One turn of the assistant on a customer message.
 *
 * @property int $id
 * @property int|null $conversation_id
 * @property int|null $message_id
 * @property string $mode live, shadow or trial (the settings page's try-it box)
 * @property string $driver
 * @property string|null $model
 * @property string $status ok, failed or skipped
 * @property string|null $intent
 * @property list<string>|null $tags
 * @property float|null $confidence
 * @property string|null $decision answered, greeted, out_of_scope or handed_over
 * @property string|null $reply
 * @property list<int>|null $sources knowledge entries the reply came from
 * @property int|null $latency_ms
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AiRun extends Model
{
    public const string ANSWERED = 'answered';

    public const string GREETED = 'greeted';

    public const string OUT_OF_SCOPE = 'out_of_scope';

    public const string HANDED_OVER = 'handed_over';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'sources' => 'array',
            'confidence' => 'float',
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
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
