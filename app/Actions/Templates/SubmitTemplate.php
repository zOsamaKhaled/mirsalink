<?php

namespace App\Actions\Templates;

use App\Enums\TemplateStatus;
use App\Models\Template;
use App\WhatsApp\Gateway;
use RuntimeException;

/**
 * Sends a draft to Meta for review. Meta's verdict arrives later, through
 * RecordTemplateDecision.
 */
class SubmitTemplate
{
    public function __construct(private Gateway $gateway) {}

    public function handle(Template $template): void
    {
        if (! $template->status->isEditable()) {
            return;
        }

        $result = $this->gateway->submitTemplate($template);

        if (! $result->accepted) {
            throw new RuntimeException((string) $result->errorMessage);
        }

        $template->forceFill([
            'status' => TemplateStatus::Pending,
            'external_id' => $result->externalId,
            'submitted_at' => now(),
            'decided_at' => null,
            'rejection_reason' => null,
        ])->save();
    }
}
