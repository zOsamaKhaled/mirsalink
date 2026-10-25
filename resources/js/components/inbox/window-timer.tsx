import { Clock3, LockKeyhole } from 'lucide-react';

import { cn } from '@/lib/utils';

/**
 * WhatsApp lets the team write freely for 24 hours after the customer's
 * last message; after that only an approved template goes through.
 * Open is quiet, the last two hours warn, closed says what to send instead.
 */
export type WindowState = 'open' | 'closing' | 'closed';

export function windowStateFor(hoursLeft: number): WindowState {
    if (hoursLeft <= 0) {
        return 'closed';
    }

    return hoursLeft <= 2 ? 'closing' : 'open';
}

export function WindowTimer({
    hoursLeft,
    label,
    className,
}: {
    hoursLeft: number;
    /** e.g. "21 h left to reply" / "Window closed: send a template". */
    label: string;
    className?: string;
}) {
    const state = windowStateFor(hoursLeft);

    return (
        <span
            className={cn(
                'inline-flex h-7 items-center gap-1.5 rounded-full px-2.5 text-caption font-medium',
                state === 'open' && 'bg-muted text-muted-foreground',
                state === 'closing' && 'bg-warning-soft text-warning',
                state === 'closed' && 'bg-danger-soft text-danger',
                className,
            )}
        >
            {state === 'closed' ? (
                <LockKeyhole className="size-3.5" />
            ) : (
                <Clock3 className="size-3.5" />
            )}
            {label}
        </span>
    );
}
