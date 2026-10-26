<?php

namespace App\Models;

use App\Enums\AudienceType;
use App\Enums\BroadcastStatus;
use App\Enums\RecipientStatus;
use Database\Factories\BroadcastFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A campaign. Its variables say where each template variable takes its
 * value from for a recipient: {source: name|company|phone|column|text,
 * value, fallback}, for the body ("body", a list), the header and the link
 * button.
 *
 * @property int $id
 * @property string $name
 * @property int|null $template_id
 * @property AudienceType $audience_type
 * @property list<int>|null $audience_tags
 * @property string|null $audience_file
 * @property array{body?: list<array{source: string, value?: string|null, fallback?: string|null}>, header?: array{source: string, value?: string|null, fallback?: string|null}|null, button?: array{source: string, value?: string|null, fallback?: string|null}|null}|null $variables
 * @property BroadcastStatus $status
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Broadcast extends Model
{
    /** @use HasFactory<BroadcastFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience_type' => AudienceType::class,
            'audience_tags' => 'array',
            'variables' => 'array',
            'status' => BroadcastStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<BroadcastRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class);
    }

    /**
     * The campaign's results in one query: how many were left out, sent,
     * delivered, read, replied and failed (refused when sent, or reported
     * failed by WhatsApp afterwards).
     *
     * @return array{total: int, queued: int, skipped: int, sent: int, delivered: int, read: int, replied: int, failed: int}
     */
    public function stats(): array
    {
        $row = $this->recipients()
            ->leftJoin('messages', 'messages.id', '=', 'broadcast_recipients.message_id')
            ->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when broadcast_recipients.status = ? then 1 else 0 end) as queued', [RecipientStatus::Queued->value])
            ->selectRaw('sum(case when broadcast_recipients.status = ? then 1 else 0 end) as skipped', [RecipientStatus::Skipped->value])
            ->selectRaw('sum(case when broadcast_recipients.status = ? then 1 else 0 end) as sent', [RecipientStatus::Sent->value])
            ->selectRaw("sum(case when messages.status in ('delivered', 'read') then 1 else 0 end) as delivered")
            ->selectRaw("sum(case when messages.status = 'read' then 1 else 0 end) as read_count")
            ->selectRaw('sum(case when broadcast_recipients.replied_at is not null then 1 else 0 end) as replied')
            ->selectRaw("sum(case when broadcast_recipients.status = ? or messages.status = 'failed' then 1 else 0 end) as failed", [RecipientStatus::Failed->value])
            ->first();

        return [
            'total' => (int) $row->total,
            'queued' => (int) $row->queued,
            'skipped' => (int) $row->skipped,
            'sent' => (int) $row->sent,
            'delivered' => (int) $row->delivered,
            'read' => (int) $row->read_count,
            'replied' => (int) $row->replied,
            'failed' => (int) $row->failed,
        ];
    }
}
