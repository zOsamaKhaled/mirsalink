<?php

namespace App\Enums;

/**
 * Where a template stands with Meta: written here, waiting for review,
 * approved and ready to send, or refused with a reason. Meta can later
 * pause an approved template for poor quality, or disable it.
 */
enum TemplateStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paused = 'paused';
    case Disabled = 'disabled';

    /** Only what Meta has not judged yet, or refused, may still change. */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Rejected;
    }

    /**
     * Meta's word for a status, from its API or its webhook.
     */
    public static function fromMeta(string $status): ?self
    {
        return match (strtoupper($status)) {
            'APPROVED' => self::Approved,
            'PENDING', 'IN_APPEAL', 'PENDING_DELETION' => self::Pending,
            'REJECTED' => self::Rejected,
            'PAUSED', 'LIMIT_EXCEEDED' => self::Paused,
            'DISABLED', 'FLAGGED' => self::Disabled,
            default => null,
        };
    }
}
