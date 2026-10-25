import { cn } from '@/lib/utils';

export type Availability = 'available' | 'busy' | 'away';

const DOT: Record<Availability, string> = {
    available: 'bg-success',
    busy: 'bg-warning',
    away: 'bg-muted-foreground/50',
};

/** Whether a team member takes new conversations right now. */
export function AgentStatusDot({
    status,
    className,
}: {
    status: Availability;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'block size-2.5 rounded-full ring-2 ring-card',
                DOT[status],
                className,
            )}
            aria-hidden
        />
    );
}

export function AgentStatus({
    status,
    label,
}: {
    status: Availability;
    label: string;
}) {
    return (
        <span className="inline-flex items-center gap-2 text-body-sm">
            <AgentStatusDot status={status} className="ring-0" />
            {label}
        </span>
    );
}
