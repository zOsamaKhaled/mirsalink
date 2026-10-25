import type { ReactNode } from 'react';

import { HandoverMark } from '@/components/inbox/handover-mark';
import { cn } from '@/lib/utils';

/**
 * A line the platform writes into the conversation: tagged, assigned,
 * closed. The handover, when the bot passes the customer to a person, is
 * the one drawn in rose.
 */
export function SystemEvent({
    children,
    time,
    icon,
    handover = false,
}: {
    children: ReactNode;
    time?: string;
    icon?: ReactNode;
    handover?: boolean;
}) {
    return (
        <div className="flex justify-center">
            <p
                className={cn(
                    'inline-flex max-w-full items-center gap-2 rounded-full px-3 py-1 text-caption',
                    handover
                        ? 'bg-handover-soft font-medium text-handover-ink'
                        : 'bg-muted text-muted-foreground',
                )}
            >
                {handover ? <HandoverMark className="size-4" /> : icon}
                <span className="min-w-0">{children}</span>
                {time && (
                    <time dir="ltr" className="tabular opacity-75">
                        {time}
                    </time>
                )}
            </p>
        </div>
    );
}
