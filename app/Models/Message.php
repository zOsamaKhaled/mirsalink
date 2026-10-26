<?php

namespace App\Models;

use App\Enums\MessageDirection;
use App\Enums\MessageSender;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property MessageDirection $direction
 * @property MessageSender $sender
 * @property int|null $user_id
 * @property MessageType $type
 * @property int|null $template_id
 * @property array{body?: list<string>, header?: string|null, button?: string|null}|null $template_params
 * @property int|null $broadcast_id
 * @property string|null $body
 * @property string|null $media_path
 * @property string|null $media_mime
 * @property string|null $media_name
 * @property int|null $media_size
 * @property string|null $external_id
 * @property MessageStatus|null $status
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'sender' => MessageSender::class,
            'type' => MessageType::class,
            'status' => MessageStatus::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
            'template_params' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return BelongsTo<Broadcast, $this>
     */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isNote(): bool
    {
        return $this->type === MessageType::Note;
    }

    /**
     * Text shown for the message in the conversation list.
     */
    public function preview(): string
    {
        return match ($this->type) {
            MessageType::Text, MessageType::Note, MessageType::Template => (string) $this->body,
            MessageType::Location => __('app.inbox.preview.location'),
            default => trim(__("app.inbox.preview.{$this->type->value}").' '.($this->body ?? '')),
        };
    }
}
