<?php

namespace App\Enums;

/**
 * Where a template stands with Meta: written here, waiting for review,
 * approved and ready to send, or refused with a reason.
 */
enum TemplateStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /** Only what Meta has not judged yet, or refused, may still change. */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Rejected;
    }
}
