import { AlertCircle, Check, CheckCheck, Clock3 } from 'lucide-react';

import { cn } from '@/lib/utils';

export type DeliveryStatus =
    | 'pending'
    | 'sent'
    | 'delivered'
    | 'read'
    | 'failed';

/**
 * What WhatsApp last said about a message the team sent. The states differ
 * by shape and strength, not by colour, so they read inside any bubble:
 * one tick sent, two faint ticks delivered, two full ticks read. A failed
 * message keeps the bubble's own colour here; the red explanation goes under
 * the bubble, where it stays legible.
 */
export function MessageStatus({
    status,
    label,
    className,
}: {
    status: DeliveryStatus;
    label: string;
    className?: string;
}) {
    const icon = {
        pending: <Clock3 className="size-3.5" />,
        sent: <Check className="size-3.5" />,
        delivered: <CheckCheck className="size-3.5" />,
        read: <CheckCheck className="size-3.5" />,
        failed: <AlertCircle className="size-3.5" />,
    }[status];

    return (
        <span
            role="img"
            aria-label={label}
            title={label}
            className={cn(
                'inline-flex',
                status !== 'read' && status !== 'failed' && 'opacity-60',
                className,
            )}
        >
            {icon}
        </span>
    );
}
