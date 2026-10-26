import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Gauge, Megaphone, Plus } from 'lucide-react';

import {
    BroadcastProgress,
    BroadcastStatusBadge,
} from '@/components/broadcasts/broadcast-status';
import { BackChevron, ForwardChevron } from '@/components/direction-icons';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate, formatTime } from '@/lib/time';
import { create, index, show } from '@/routes/broadcasts';
import type {
    AudienceType,
    BroadcastQuota,
    BroadcastStats,
    BroadcastStatus,
} from '@/types/broadcasts';

type Row = {
    id: number;
    name: string;
    status: BroadcastStatus;
    template: string | null;
    audience_type: AudienceType;
    scheduled_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string | null;
    stats: BroadcastStats;
};

type Props = {
    broadcasts: {
        data: Row[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    quota: BroadcastQuota;
};

export default function Broadcasts({ broadcasts, quota }: Props) {
    const { t, locale } = useTranslations();

    setLayoutProps({
        breadcrumbs: [{ title: t('broadcasts.title'), href: index() }],
    });

    const when = (row: Row): string | null => {
        const at = row.completed_at ?? row.started_at ?? row.scheduled_at;

        return at
            ? `${formatDate(at, locale)} ${formatTime(at, locale)}`
            : null;
    };

    return (
        <>
            <Head title={t('broadcasts.title')} />

            <div className="flex flex-col gap-6 px-4 py-8 sm:px-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div className="flex max-w-2xl flex-col gap-1.5">
                        <h1 className="text-display font-extrabold">
                            {t('broadcasts.title')}
                        </h1>
                        <p className="text-body-sm text-muted-foreground">
                            {t('broadcasts.description')}
                        </p>
                        <p className="inline-flex items-center gap-1.5 text-caption text-muted-foreground tabular">
                            <Gauge className="size-3.5" />
                            {t('broadcasts.quota', {
                                remaining:
                                    quota.remaining_today.toLocaleString('en'),
                                limit: quota.daily_limit.toLocaleString('en'),
                                per_minute: String(quota.per_minute),
                            })}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            {t('broadcasts.add')}
                        </Link>
                    </Button>
                </header>

                {broadcasts.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed p-10 text-center">
                        <Megaphone className="size-8 text-muted-foreground" />
                        <p className="font-semibold">{t('broadcasts.empty')}</p>
                        <p className="text-body-sm text-muted-foreground">
                            {t('broadcasts.empty_hint')}
                        </p>
                    </div>
                ) : (
                    <ul className="flex flex-col divide-y overflow-hidden rounded-2xl border bg-card">
                        {broadcasts.data.map((row) => (
                            <li key={row.id}>
                                <Link
                                    href={show(row.id)}
                                    className="grid gap-4 p-4 transition-colors hover:bg-accent/50 md:grid-cols-[minmax(0,2fr)_minmax(0,1.2fr)_minmax(0,2fr)] md:items-center"
                                >
                                    <div className="flex min-w-0 flex-col gap-1.5">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="truncate font-semibold">
                                                {row.name}
                                            </span>
                                            <BroadcastStatusBadge
                                                status={row.status}
                                            />
                                        </div>
                                        <span className="flex flex-wrap items-center gap-2 text-caption text-muted-foreground">
                                            {row.template && (
                                                <bdi
                                                    dir="ltr"
                                                    className="font-mono"
                                                >
                                                    {row.template}
                                                </bdi>
                                            )}
                                            <span>
                                                {t(
                                                    `broadcasts.audience.${row.audience_type}`,
                                                )}
                                            </span>
                                            {when(row) && (
                                                <span className="tabular">
                                                    {when(row)}
                                                </span>
                                            )}
                                        </span>
                                    </div>
                                    <BroadcastProgress stats={row.stats} />
                                    <dl className="grid grid-cols-4 gap-2 text-center">
                                        {(
                                            [
                                                'delivered',
                                                'read',
                                                'replied',
                                                'failed',
                                            ] as const
                                        ).map((key) => (
                                            <div
                                                key={key}
                                                className="flex flex-col gap-0.5"
                                            >
                                                <dt className="text-caption text-muted-foreground">
                                                    {t(
                                                        `broadcasts.stats.${key}`,
                                                    )}
                                                </dt>
                                                <dd className="font-bold tabular">
                                                    {row.stats[
                                                        key
                                                    ].toLocaleString('en')}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                {broadcasts.last_page > 1 && (
                    <nav className="flex items-center justify-center gap-2">
                        <Button
                            variant="outline"
                            size="icon"
                            disabled={!broadcasts.prev_page_url}
                            asChild={!!broadcasts.prev_page_url}
                        >
                            {broadcasts.prev_page_url ? (
                                <Link href={broadcasts.prev_page_url}>
                                    <BackChevron />
                                </Link>
                            ) : (
                                <BackChevron />
                            )}
                        </Button>
                        <span className="text-body-sm tabular">
                            {broadcasts.current_page} / {broadcasts.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="icon"
                            disabled={!broadcasts.next_page_url}
                            asChild={!!broadcasts.next_page_url}
                        >
                            {broadcasts.next_page_url ? (
                                <Link href={broadcasts.next_page_url}>
                                    <ForwardChevron />
                                </Link>
                            ) : (
                                <ForwardChevron />
                            )}
                        </Button>
                    </nav>
                )}
            </div>
        </>
    );
}
