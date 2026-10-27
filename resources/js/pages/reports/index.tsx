import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Bot, Download } from 'lucide-react';
import type { ReactNode } from 'react';
import { Fragment, useState } from 'react';

import { BroadcastStatusBadge } from '@/components/broadcasts/broadcast-status';
import { CategoryTag } from '@/components/inbox/category-tag';
import type { TagTone } from '@/components/inbox/category-tag';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useDuration } from '@/hooks/use-duration';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate } from '@/lib/time';
import { cn } from '@/lib/utils';
import { show as broadcast } from '@/routes/broadcasts';
import { exportMethod as exportSection, index } from '@/routes/reports';
import type { BroadcastStats, BroadcastStatus } from '@/types/broadcasts';

type Preset = 'today' | 'week' | 'month' | 'custom';

type Props = {
    period: { from: string; to: string; preset: Preset };
    overview: {
        customers: number;
        inbound: number;
        replies: number;
        average_reply: number | null;
        median_reply: number | null;
        within_target: number | null;
        closed: number;
        average_close: number | null;
        open_now: number;
        unassigned_now: number;
    };
    members: {
        id: number;
        name: string;
        active: boolean;
        messages: number;
        conversations: number;
        average_reply: number | null;
        median_reply: number | null;
        closed: number;
        average_close: number | null;
        open_now: number;
    }[];
    tags: {
        tags: { id: number; name: string; color: TagTone; customers: number }[];
        untagged: number;
    };
    hours: number[][];
    days: { day: string; inbound: number; replies: number }[];
    campaigns: {
        id: number;
        name: string;
        status: BroadcastStatus;
        template: string | null;
        started_at: string | null;
        stats: BroadcastStats;
    }[];
    targetMinutes: number;
    assistant: Record<'live' | 'shadow', AssistantSummary>;
};

type AssistantSummary = {
    turns: number;
    settled: number | null;
    handed_over: number | null;
    failed: number;
    agreement: number | null;
    judged: number;
    median_ms: number | null;
};

const number = (value: number) => value.toLocaleString('en');

export default function Reports(props: Props) {
    const { period, overview } = props;
    const { t } = useTranslations();
    const duration = useDuration();

    setLayoutProps({
        breadcrumbs: [{ title: t('reports.title'), href: index() }],
    });

    // Every export carries the period on screen.
    const query =
        period.preset === 'custom'
            ? { preset: 'custom', from: period.from, to: period.to }
            : { preset: period.preset };
    const download = (section: string) => exportSection.url(section, { query });

    return (
        <>
            <Head title={t('reports.title')} />

            <div className="flex flex-col gap-6 px-4 py-8 sm:px-6">
                <header className="flex flex-col gap-4">
                    <div className="flex max-w-3xl flex-col gap-1.5">
                        <h1 className="text-display font-extrabold">
                            {t('reports.title')}
                        </h1>
                        <p className="text-body-sm text-muted-foreground">
                            {t('reports.description')}
                        </p>
                    </div>
                    <PeriodPicker period={period} />
                </header>

                <section className="grid grid-cols-2 gap-3 lg:grid-cols-3 2xl:grid-cols-6">
                    <Tile
                        label={t('reports.overview.customers')}
                        value={number(overview.customers)}
                        detail={t('reports.overview.inbound', {
                            count: number(overview.inbound),
                        })}
                    />
                    <Tile
                        label={t('reports.overview.replies')}
                        value={number(overview.replies)}
                    />
                    <Tile
                        label={t('reports.overview.median_reply')}
                        value={duration(overview.median_reply)}
                        detail={
                            overview.average_reply === null
                                ? undefined
                                : t('reports.overview.average_reply', {
                                      time: duration(overview.average_reply),
                                  })
                        }
                        hint={t('reports.reply_hint')}
                    />
                    <Tile
                        label={t('reports.overview.within_target', {
                            minutes: String(props.targetMinutes),
                        })}
                        value={
                            overview.within_target === null
                                ? '—'
                                : `${overview.within_target}%`
                        }
                    />
                    <Tile
                        label={t('reports.overview.closed')}
                        value={number(overview.closed)}
                        detail={
                            overview.average_close === null
                                ? undefined
                                : t('reports.overview.average_close', {
                                      time: duration(overview.average_close),
                                  })
                        }
                    />
                    <Tile
                        label={t('reports.overview.open_now')}
                        value={number(overview.open_now)}
                        detail={t('reports.overview.unassigned_now', {
                            count: number(overview.unassigned_now),
                        })}
                    />
                </section>

                <Panel
                    title={t('reports.days.title')}
                    exportHref={download('days')}
                >
                    <DayBars days={props.days} />
                </Panel>

                <Panel
                    title={t('reports.members.title')}
                    exportHref={download('members')}
                    flush
                >
                    <MembersTable members={props.members} />
                </Panel>

                <div className="grid items-start gap-6 xl:grid-cols-2">
                    <Panel
                        title={t('reports.tags.title')}
                        hint={t('reports.tags.hint')}
                        exportHref={download('tags')}
                    >
                        <TagBars tags={props.tags} />
                    </Panel>
                    <Panel
                        title={t('reports.hours.title')}
                        hint={t('reports.hours.hint')}
                        exportHref={download('hours')}
                    >
                        <HourGrid hours={props.hours} />
                    </Panel>
                </div>

                <Panel
                    title={t('reports.campaigns.title')}
                    exportHref={
                        props.campaigns.length > 0
                            ? download('campaigns')
                            : undefined
                    }
                    flush
                >
                    <CampaignsTable campaigns={props.campaigns} />
                </Panel>

                <AssistantPanel assistant={props.assistant} />
            </div>
        </>
    );
}

function PeriodPicker({ period }: { period: Props['period'] }) {
    const { t } = useTranslations();
    const [from, setFrom] = useState(period.from);
    const [to, setTo] = useState(period.to);
    const [custom, setCustom] = useState(period.preset === 'custom');

    const go = (query: Record<string, string>) =>
        router.get(index.url(), query, { preserveScroll: true, replace: true });

    return (
        <div className="flex flex-wrap items-center gap-3">
            <div className="flex flex-wrap gap-1 rounded-full bg-muted p-1">
                {(['today', 'week', 'month', 'custom'] as const).map(
                    (preset) => {
                        const active = custom
                            ? preset === 'custom'
                            : period.preset === preset;

                        return (
                            <button
                                key={preset}
                                type="button"
                                aria-pressed={active}
                                onClick={() => {
                                    if (preset === 'custom') {
                                        setCustom(true);

                                        return;
                                    }

                                    setCustom(false);
                                    go({ preset });
                                }}
                                className={cn(
                                    'h-8 rounded-full px-3.5 text-body-sm transition-colors',
                                    active
                                        ? 'bg-card font-semibold shadow-raised'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {t(`reports.period.${preset}`)}
                            </button>
                        );
                    },
                )}
            </div>
            {custom && (
                <form
                    className="flex flex-wrap items-center gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        go({ preset: 'custom', from, to });
                    }}
                >
                    <label className="flex items-center gap-2 text-body-sm">
                        {t('reports.period.from')}
                        <Input
                            type="date"
                            dir="ltr"
                            value={from}
                            max={to}
                            onChange={(event) => setFrom(event.target.value)}
                            className="h-9 w-40"
                        />
                    </label>
                    <label className="flex items-center gap-2 text-body-sm">
                        {t('reports.period.to')}
                        <Input
                            type="date"
                            dir="ltr"
                            value={to}
                            min={from}
                            onChange={(event) => setTo(event.target.value)}
                            className="h-9 w-40"
                        />
                    </label>
                    <Button type="submit" size="sm">
                        {t('reports.period.apply')}
                    </Button>
                </form>
            )}
        </div>
    );
}

function Tile({
    label,
    value,
    detail,
    hint,
}: {
    label: string;
    value: string;
    detail?: string;
    hint?: string;
}) {
    return (
        <div
            className="flex flex-col gap-2 rounded-xl border bg-card p-4"
            title={hint}
        >
            <span className="text-body-sm text-muted-foreground">{label}</span>
            <span className="text-display leading-none font-extrabold tabular">
                {value}
            </span>
            {detail && (
                <span className="text-caption text-muted-foreground tabular">
                    {detail}
                </span>
            )}
        </div>
    );
}

function Panel({
    title,
    hint,
    exportHref,
    flush = false,
    children,
}: {
    title: string;
    hint?: string;
    exportHref?: string;
    flush?: boolean;
    children: ReactNode;
}) {
    const { t } = useTranslations();

    return (
        <section className="flex flex-col overflow-hidden rounded-2xl border bg-card">
            <header className="flex flex-wrap items-start justify-between gap-2 p-4 pb-3">
                <div className="flex flex-col gap-0.5">
                    <h2 className="text-title font-bold">{title}</h2>
                    {hint && (
                        <p className="text-caption text-muted-foreground">
                            {hint}
                        </p>
                    )}
                </div>
                {exportHref && (
                    <Button size="sm" variant="ghost" asChild>
                        <a href={exportHref} download>
                            <Download />
                            {t('reports.export.button')}
                        </a>
                    </Button>
                )}
            </header>
            <div className={flush ? undefined : 'px-4 pb-4'}>{children}</div>
        </section>
    );
}

/** One bar per day for customer messages; replies show on hover. */
function DayBars({ days }: { days: Props['days'] }) {
    const { t, locale } = useTranslations();
    const highest = Math.max(1, ...days.map((day) => day.inbound));
    // Plain digits: Arabic date formatting adds direction marks that scramble
    // inside the left-to-right axis. Day first in Arabic, month first in English.
    const label = (day: string) => {
        const [, month, date] = day.split('-').map(Number);

        return locale === 'ar' ? `${date}/${month}` : `${month}/${date}`;
    };
    const every = Math.ceil(days.length / 15);

    return (
        // Time runs left to right in both languages, as charts are read.
        <div className="flex h-44 items-end gap-0.5" role="list" dir="ltr">
            {days.map((day, position) => {
                const text = t('reports.days.tooltip', {
                    date: formatDate(`${day.day}T12:00:00`, locale),
                    inbound: number(day.inbound),
                    replies: number(day.replies),
                });

                return (
                    <div
                        key={day.day}
                        role="listitem"
                        aria-label={text}
                        title={text}
                        className="group flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1.5"
                    >
                        {day.inbound > 0 && days.length <= 31 && (
                            <span className="text-caption text-muted-foreground tabular opacity-0 group-hover:opacity-100">
                                {day.inbound}
                            </span>
                        )}
                        <div
                            className={cn(
                                'w-full max-w-10 rounded-t-[4px] transition-colors',
                                day.inbound > 0
                                    ? 'bg-primary group-hover:bg-primary/80 dark:bg-tag-blue-foreground'
                                    : 'bg-muted',
                            )}
                            style={{
                                height: `${day.inbound > 0 ? Math.max(4, (day.inbound / highest) * 100) : 2}%`,
                            }}
                        />
                        <span
                            className={cn(
                                'text-caption text-muted-foreground tabular',
                                position % every !== 0 && 'invisible',
                            )}
                        >
                            {label(day.day)}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

function MembersTable({ members }: { members: Props['members'] }) {
    const { t } = useTranslations();
    const duration = useDuration();

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-body-sm">
                <thead className="border-y bg-sunken text-caption text-muted-foreground">
                    <tr>
                        <th className="px-4 py-2.5 text-start font-medium">
                            {t('reports.members.member')}
                        </th>
                        <th className="px-4 py-2.5 text-end font-medium">
                            {t('reports.members.replies')}
                        </th>
                        <th className="px-4 py-2.5 text-end font-medium">
                            {t('reports.members.conversations')}
                        </th>
                        <th className="px-4 py-2.5 text-end font-medium">
                            {t('reports.members.reply')}
                            <span className="block font-normal opacity-75">
                                {t('reports.members.reply_detail')}
                            </span>
                        </th>
                        <th className="px-4 py-2.5 text-end font-medium">
                            {t('reports.members.closed')}
                        </th>
                        <th className="px-4 py-2.5 text-end font-medium">
                            {t('reports.members.close')}
                        </th>
                        <th className="px-4 py-2.5 text-end font-medium">
                            {t('reports.members.open_now')}
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {members.map((member) => (
                        <tr key={member.id}>
                            <td className="px-4 py-3 font-medium">
                                {member.name}
                                {!member.active && (
                                    <span className="ms-2 rounded-full bg-muted px-2 py-0.5 text-caption text-muted-foreground">
                                        {t('reports.members.inactive')}
                                    </span>
                                )}
                            </td>
                            <td className="px-4 py-3 text-end tabular">
                                {number(member.messages)}
                            </td>
                            <td className="px-4 py-3 text-end tabular">
                                {number(member.conversations)}
                            </td>
                            <td className="px-4 py-3 text-end tabular">
                                <span className="font-semibold">
                                    {duration(member.median_reply)}
                                </span>
                                {member.average_reply !== null && (
                                    <span className="text-muted-foreground">
                                        {' · '}
                                        {duration(member.average_reply)}
                                    </span>
                                )}
                            </td>
                            <td className="px-4 py-3 text-end tabular">
                                {number(member.closed)}
                            </td>
                            <td className="px-4 py-3 text-end tabular">
                                {duration(member.average_close)}
                            </td>
                            <td className="px-4 py-3 text-end tabular">
                                {number(member.open_now)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/** Customers per tag as one-colour bars, longest first, untagged last. */
function TagBars({ tags }: { tags: Props['tags'] }) {
    const { t } = useTranslations();
    const rows = [
        ...tags.tags.map((tag) => ({
            key: String(tag.id),
            label: <CategoryTag tone={tag.color}>{tag.name}</CategoryTag>,
            count: tag.customers,
        })),
        ...(tags.untagged > 0
            ? [
                  {
                      key: 'untagged',
                      label: (
                          <span className="text-body-sm text-muted-foreground">
                              {t('reports.tags.untagged')}
                          </span>
                      ),
                      count: tags.untagged,
                  },
              ]
            : []),
    ];
    const highest = Math.max(1, ...rows.map((row) => row.count));

    if (rows.length === 0) {
        return (
            <p className="py-6 text-center text-body-sm text-muted-foreground">
                {t('reports.tags.empty')}
            </p>
        );
    }

    return (
        <ul className="flex flex-col gap-2.5">
            {rows.map((row) => (
                <li
                    key={row.key}
                    className="grid grid-cols-[9rem_minmax(0,1fr)_2.5rem] items-center gap-3"
                >
                    <span className="min-w-0 truncate">{row.label}</span>
                    {/* Grows from the start, beside its label, in either direction. */}
                    <span className="flex h-3 overflow-hidden rounded-full bg-muted">
                        <span
                            className="h-full rounded-e-[4px] bg-primary dark:bg-tag-blue-foreground"
                            style={{ width: `${(row.count / highest) * 100}%` }}
                        />
                    </span>
                    <span className="text-end text-body-sm font-semibold tabular">
                        {number(row.count)}
                    </span>
                </li>
            ))}
        </ul>
    );
}

const SHADES = [
    'bg-muted',
    'bg-primary/15 dark:bg-tag-blue-foreground/15',
    'bg-primary/35 dark:bg-tag-blue-foreground/35',
    'bg-primary/60 dark:bg-tag-blue-foreground/60',
    'bg-primary dark:bg-tag-blue-foreground',
];

/**
 * Days down, hours across, in the team's week (Sunday first). One hue,
 * light to dark; each cell names its day, hour and count on hover.
 */
function HourGrid({ hours }: { hours: number[][] }) {
    const { t } = useTranslations();
    const highest = Math.max(1, ...hours.flat());
    const shade = (count: number) =>
        count === 0 ? 0 : Math.min(4, Math.ceil((count / highest) * 4));

    // A grid, not a table: empty table cells would collapse to nothing.
    // Hours run left to right in both languages, as on a clock.
    return (
        <div className="flex flex-col gap-3">
            <div className="overflow-x-auto">
                <div
                    role="img"
                    aria-label={t('reports.hours.title')}
                    dir="ltr"
                    className="grid min-w-[30rem] grid-cols-[4.5rem_repeat(24,minmax(0,1fr))] gap-0.5"
                >
                    {hours.map((row, day) => (
                        <Fragment key={day}>
                            <span className="truncate pe-2 text-end text-caption leading-5 text-muted-foreground">
                                {t(`team.hours.days.${day}`)}
                            </span>
                            {row.map((count, hour) => (
                                <span
                                    key={hour}
                                    title={t('reports.hours.tooltip', {
                                        day: t(`team.hours.days.${day}`),
                                        hour: String(hour).padStart(2, '0'),
                                        count: number(count),
                                    })}
                                    className={cn(
                                        'h-5 rounded-[3px]',
                                        SHADES[shade(count)],
                                    )}
                                />
                            ))}
                        </Fragment>
                    ))}
                    <span />
                    {Array.from({ length: 24 }, (_, hour) => (
                        <span
                            key={hour}
                            className="pt-1 text-center text-[0.625rem] text-muted-foreground tabular"
                        >
                            {hour % 3 === 0 ? hour : ''}
                        </span>
                    ))}
                </div>
            </div>
            <div className="flex items-center gap-1.5 self-end text-caption text-muted-foreground">
                {t('reports.hours.less')}
                {SHADES.map((shade) => (
                    <span
                        key={shade}
                        className={cn('size-3 rounded-[3px]', shade)}
                    />
                ))}
                {t('reports.hours.more')}
            </div>
        </div>
    );
}

function CampaignsTable({ campaigns }: { campaigns: Props['campaigns'] }) {
    const { t, locale } = useTranslations();

    if (campaigns.length === 0) {
        return (
            <p className="px-4 pb-6 text-center text-body-sm text-muted-foreground">
                {t('reports.campaigns.empty')}
            </p>
        );
    }

    const columns = ['sent', 'delivered', 'read', 'replied', 'failed'] as const;

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-body-sm">
                <thead className="border-y bg-sunken text-caption text-muted-foreground">
                    <tr>
                        <th className="px-4 py-2.5 text-start font-medium">
                            {t('reports.export.campaign')}
                        </th>
                        {columns.map((column) => (
                            <th
                                key={column}
                                className="px-4 py-2.5 text-end font-medium"
                            >
                                {t(`broadcasts.stats.${column}`)}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {campaigns.map((campaign) => (
                        <tr key={campaign.id}>
                            <td className="px-4 py-3">
                                <Link
                                    href={broadcast(campaign.id)}
                                    className="font-medium hover:underline"
                                >
                                    {campaign.name}
                                </Link>
                                <span className="mt-1 flex flex-wrap items-center gap-2 text-caption text-muted-foreground">
                                    <BroadcastStatusBadge
                                        status={campaign.status}
                                    />
                                    {campaign.started_at && (
                                        <span className="tabular">
                                            {formatDate(
                                                campaign.started_at,
                                                locale,
                                            )}
                                        </span>
                                    )}
                                </span>
                            </td>
                            {columns.map((column) => (
                                <td
                                    key={column}
                                    className={cn(
                                        'px-4 py-3 text-end tabular',
                                        column === 'failed' &&
                                            campaign.stats.failed > 0 &&
                                            'text-danger',
                                    )}
                                >
                                    {number(campaign.stats[column])}
                                    {column !== 'sent' &&
                                        campaign.stats.sent > 0 && (
                                            <span className="block text-caption text-muted-foreground">
                                                {Math.round(
                                                    (campaign.stats[column] /
                                                        campaign.stats.sent) *
                                                        100,
                                                )}
                                                %
                                            </span>
                                        )}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/** The assistant's turns in the period, answering and silent apart. */
function AssistantPanel({ assistant }: { assistant: Props['assistant'] }) {
    const { t } = useTranslations();
    const modes = (['live', 'shadow'] as const).filter(
        (mode) => assistant[mode].turns > 0,
    );
    const percent = (value: number | null) =>
        value === null ? '—' : `${value}%`;

    return (
        <section className="flex flex-col gap-4 rounded-2xl border bg-card p-5">
            <h2 className="flex items-center gap-2 text-title font-bold">
                <Bot className="size-5 text-muted-foreground" />
                {t('reports.assistant.title')}
            </h2>
            {modes.length === 0 ? (
                <p className="text-body-sm text-muted-foreground">
                    {t('reports.assistant.body')}
                </p>
            ) : (
                <div className="grid gap-4 lg:grid-cols-2">
                    {modes.map((mode) => {
                        const row = assistant[mode];

                        return (
                            <dl
                                key={mode}
                                className="grid grid-cols-2 gap-3 rounded-xl bg-sunken p-4 sm:grid-cols-4"
                            >
                                <dt className="col-span-full text-body-sm font-semibold">
                                    {t(`reports.assistant.${mode}`)}
                                </dt>
                                {(
                                    [
                                        [
                                            'assistant.summary.turns',
                                            String(row.turns),
                                        ],
                                        [
                                            'assistant.summary.settled',
                                            percent(row.settled),
                                        ],
                                        [
                                            'assistant.summary.handed_over',
                                            percent(row.handed_over),
                                        ],
                                        [
                                            'assistant.summary.agreement',
                                            percent(row.agreement),
                                        ],
                                    ] as const
                                ).map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="flex flex-col gap-1"
                                    >
                                        <dt className="text-caption text-muted-foreground">
                                            {t(label)}
                                        </dt>
                                        <dd className="text-heading font-extrabold tabular">
                                            {value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        );
                    })}
                </div>
            )}
        </section>
    );
}
