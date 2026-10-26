<?php

namespace App\Actions\Templates;

use App\Enums\TemplateStatus;
use App\Models\Template;

/**
 * Meta's verdict on a template, from the simulator page now and Meta's
 * webhook in phase 9: approved, or refused with its reason.
 */
class RecordTemplateDecision
{
    public function handle(Template $template, TemplateStatus $status, ?string $reason = null): void
    {
        $template->forceFill([
            'status' => $status,
            'rejection_reason' => $status === TemplateStatus::Rejected ? $reason : null,
            'decided_at' => now(),
        ])->save();
    }
}
