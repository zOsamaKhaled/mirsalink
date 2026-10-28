<?php

namespace App\Models;

use App\Enums\MessageStatus;
use Database\Factories\CodeDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A verification code Rizq asked us to send on WhatsApp. The code is never
 * stored; the reference is Rizq's, and goes back to it if the code could
 * not be delivered, so it can send the code by email instead.
 *
 * @property int $id
 * @property string $reference
 * @property string $phone
 * @property string $language
 * @property string|null $purpose
 * @property MessageStatus $status
 * @property string|null $external_id
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $rizq_notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CodeDelivery extends Model
{
    /** @use HasFactory<CodeDeliveryFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MessageStatus::class,
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
            'rizq_notified_at' => 'datetime',
        ];
    }

    /**
     * Enough of the number to tell two apart, not enough to read it out.
     */
    public function maskedPhone(): string
    {
        return substr($this->phone, 0, 5).'•••'.substr($this->phone, -3);
    }
}
