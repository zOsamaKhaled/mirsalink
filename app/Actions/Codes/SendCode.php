<?php

namespace App\Actions\Codes;

use App\Codes\CodeNotSent;
use App\Codes\CodeSettings;
use App\Enums\MessageStatus;
use App\Models\CodeDelivery;
use App\WhatsApp\Gateway;

/**
 * Sends a verification code for Rizq on WhatsApp. Asked twice with the same
 * reference, it sends once. It refuses when codes are off or no template is
 * approved, when the number asked too often, or when WhatsApp refuses the
 * message; each time Rizq sends the code by email instead.
 */
class SendCode
{
    public function __construct(private Gateway $gateway) {}

    /**
     * @throws CodeNotSent
     */
    public function handle(string $reference, string $phone, string $code, string $language, ?string $purpose = null): CodeDelivery
    {
        $existing = CodeDelivery::query()->where('reference', $reference)->first();

        if ($existing !== null) {
            return $existing->status === MessageStatus::Failed
                ? throw CodeNotSent::refused((string) $existing->error_message)
                : $existing;
        }

        $language = CodeSettings::current()->languageFor($language) ?? throw CodeNotSent::unavailable();

        $recent = CodeDelivery::query()->where('phone', $phone)->where('created_at', '>=', now()->subHour())->count();

        if ($recent >= (int) config('rizq.codes.per_phone_per_hour')) {
            throw CodeNotSent::tooMany();
        }

        $result = $this->gateway->sendCode($phone, $code, $language);

        $delivery = CodeDelivery::query()->create([
            'reference' => $reference,
            'phone' => $phone,
            'language' => $language,
            'purpose' => $purpose,
            'status' => $result->accepted ? MessageStatus::Sent : MessageStatus::Failed,
            'external_id' => $result->externalId,
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage === null ? null : mb_substr($result->errorMessage, 0, 250),
            'failed_at' => $result->accepted ? null : now(),
        ]);

        if (! $result->accepted) {
            // Rizq hears it in this answer, so it isn't called back as well.
            $delivery->forceFill(['rizq_notified_at' => now()])->save();

            throw CodeNotSent::refused((string) $result->errorMessage);
        }

        return $delivery;
    }
}
