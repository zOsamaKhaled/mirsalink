<?php

namespace App\Actions\Codes;

use App\Enums\MessageStatus;
use App\Models\CodeDelivery;
use App\Rizq\RizqClient;

/**
 * WhatsApp telling us what became of a code: delivered, read or failed,
 * never moving backwards. A code that failed is reported to Rizq once, so
 * the person waiting for it gets it by email.
 */
class RecordCodeStatus
{
    public function __construct(private RizqClient $rizq) {}

    public function handle(string $externalId, MessageStatus $status, ?string $errorCode = null, ?string $errorMessage = null): ?CodeDelivery
    {
        $delivery = CodeDelivery::query()->where('external_id', $externalId)->first();

        if ($delivery === null || $status->rank() <= $delivery->status->rank()) {
            return $delivery;
        }

        $delivery->status = $status;

        match ($status) {
            MessageStatus::Delivered => $delivery->delivered_at ??= now(),
            MessageStatus::Read => [$delivery->delivered_at ??= now(), $delivery->read_at ??= now()],
            MessageStatus::Failed => $delivery->forceFill([
                'failed_at' => now(),
                'error_code' => $errorCode,
                'error_message' => $errorMessage === null ? null : mb_substr($errorMessage, 0, 250),
            ]),
            default => null,
        };

        $delivery->save();

        if ($status === MessageStatus::Failed && $delivery->rizq_notified_at === null) {
            $this->tellRizq($delivery);
        }

        return $delivery;
    }

    private function tellRizq(CodeDelivery $delivery): void
    {
        $told = $this->rizq->post((string) config('rizq.codes.path'), [
            'reference' => $delivery->reference,
            'status' => 'failed',
            'error_code' => $delivery->error_code,
        ]);

        if ($told) {
            $delivery->forceFill(['rizq_notified_at' => now()])->save();
        }
    }
}
