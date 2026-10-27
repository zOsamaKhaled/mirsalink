<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One notification from Meta, as it arrived.
 *
 * @property int $id
 * @property array<string, mixed> $payload
 * @property int $attempts
 * @property Carbon|null $processed_at
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WebhookEvent extends Model
{
    /** Reading is tried this many times before the event waits for a person. */
    public const int MAX_ATTEMPTS = 5;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
