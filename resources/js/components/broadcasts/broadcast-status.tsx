import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { BroadcastStats, BroadcastStatus } from '@/types/broadcasts';

const CLASSES: Record<BroadcastStatus, string> = {
    draft: 'bg-muted text-muted-foreground',
    scheduled: 'bg-tag-blue text-tag-blue-foreground',
    sending: 'bg-success-soft text-success',
    paused: 'bg-warning-soft text-warning',
    completed: 'bg-secondary text-secondary-foreground',
    cancelled: 'bg-danger-soft text-danger',
};

export function BroadcastStatusBadge({ status }: { status: BroadcastStatus }) {
    const { t } = useTranslations();

    return (
        <span
            className={cn(
                'inline-flex h-6 items-center gap-1.5 rounded-full px-2.5 text-caption font-semibold',
                CLASSES[status],
            )}
        >
            {status === 'sending' && (
                <span
                    className="size-1.5 animate-pulse rounded-full bg-current"
                    aria-hidden
                />
            )}
            {t(`broadcasts.status.${status}`)}
        </span>
    );
}

/**
 * How far a campaign has gone: sent out of those it will reach (the left
 * out don't count).
 */
export function BroadcastProgress({
    stats,
    className,
}: {
    stats: BroadcastStats;
    className?: string;
}) {
    const { t } = useTranslations();
    const reachable = Math.max(stats.total - stats.skipped, 0);
    const done = reachable - stats.queued;
    const percent = reachable === 0 ? 0 : Math.round((done / reachable) * 100);

    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <div
                className="h-2 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={percent}
            >
                <div
                    className="h-full rounded-full bg-primary transition-[width] dark:bg-tag-blue-foreground"
                    style={{ width: `${percent}%` }}
                />
            </div>
            <span className="text-caption text-muted-foreground tabular">
                {t('broadcasts.progress', {
                    sent: done.toLocaleString('en'),
                    total: reachable.toLocaleString('en'),
                })}
            </span>
        </div>
    );
}
