<?php

namespace App\Models;

use App\Enums\RecipientStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $broadcast_id
 * @property int|null $contact_id
 * @property string $phone
 * @property string|null $name
 * @property array{body?: list<string>, header?: string|null, button?: string|null}|null $params
 * @property RecipientStatus $status
 * @property string|null $skip_reason
 * @property int|null $message_id
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property Carbon|null $replied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BroadcastRecipient extends Model
{
    public const string SKIP_OPTED_OUT = 'opted_out';

    public const string SKIP_DUPLICATE = 'duplicate';

    public const string SKIP_BAD_PHONE = 'bad_phone';

    public const string SKIP_MISSING_VALUE = 'missing_value';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'params' => 'array',
            'status' => RecipientStatus::class,
            'sent_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Broadcast, $this>
     */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
