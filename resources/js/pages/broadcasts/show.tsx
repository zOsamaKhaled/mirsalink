import { Head, Link, router, setLayoutProps, usePoll } from '@inertiajs/react';
import {
    CalendarClock,
    FastForward,
    Gauge,
    Pause,
    Pencil,
    Play,
    Send,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';

import {
    BroadcastProgress,
    BroadcastStatusBadge,
} from '@/components/broadcasts/broadcast-status';
import { ConfirmDeleteDialog } from '@/components/confirm-dialog';
import { BackChevron, ForwardChevron } from '@/components/direction-icons';
import { CategoryTag } from '@/components/inbox/category-tag';
import type { TagTone } from '@/components/inbox/category-tag';
import { MessageStatus } from '@/components/inbox/message-status';
import type { DeliveryStatus } from '@/components/inbox/message-status';
import { TemplatePreview } from '@/components/inbox/template-preview';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import { formatPhone } from '@/lib/phone';
import { formatDate, formatTime } from '@/lib/time';
import { cn } from '@/lib/utils';
import {
    cancel,
    destroy,
    edit,
    index,
    launch,
    pause,
    resume,
    show,
    tick,
} from '@/routes/broadcasts';
import type {
    AudienceType,
    BroadcastQuota,
    BroadcastStats,
    BroadcastStatus,
} from '@/types/broadcasts';
import type { WhatsAppTemplate } from '@/types/templates';

type Outcome =
    | 'all'
    | 'queued'
    | 'sent'
    | 'delivered'
    | 'read'
    | 'replied'
    | 'failed'
    | 'skipped';

type Recipient = {
    id: number;
    name: string | null;
    phone: string;
    status: 'queued' | 'skipped' | 'sent' | 'failed' | 'cancelled';
    message_status: DeliveryStatus | null;
    skip_reason: string | null;
    error: string | null;
    replied: boolean;
    sent_at: string | null;
};

type Props = {
    broadcast: {
        id: number;
        name: string;
        status: BroadcastStatus;
        audience_type: AudienceType;
        audience_tags: { id: number; name: string; color: TagTone }[];
        scheduled_at: string | null;
        started_at: string | null;
        completed_at: string | null;
        created_by: string | null;
        template: WhatsAppTemplate | null;
    };
    sample: { body?: string[]; header?: string | null } | null;
    stats: BroadcastStats;
    skipReasons: Record<string, number>;
    recipients: {
        data: Recipient[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    outcome: Outcome;
    quota: BroadcastQuota;
    simulator: boolean;
};

const OUTCOMES: Outcome[] = [
    'all',
    'queued',
    'sent',
    'delivered',
    'read',
    'replied',
    'failed',
    'skipped',
];

export default function BroadcastShow(props: Props) {
    const { broadcast, stats, quota } = props;
    const { t, locale } = useTranslations();
    const [deleting, setDeleting] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const [scheduling, setScheduling] = useState(false);
    const live =
        broadcast.status === 'sending' || broadcast.status === 'scheduled';
    const options = { preserveScroll: true, preserveState: true };

    usePoll(
        5000,
        { only: ['broadcast', 'stats', 'recipients', 'quota'] },
        { autoStart: live },
    );

    setLayoutProps({
        breadcrumbs: [
            { title: t('broadcasts.title'), href: index() },
            { title: broadcast.name, href: show(broadcast.id) },
        ],
    });

    const stamp = (iso: string) =>
        `${formatDate(iso, locale)} ${formatTime(iso, locale)}`;
    const editable =
        broadcast.status === 'draft' || broadcast.status === 'scheduled';
    const minutes = Math.ceil(stats.queued / Math.max(quota.per_minute, 1));
    const days =
        stats.queued > quota.remaining_today
            ? Math.ceil(
                  (stats.queued - quota.remaining_today) /
                      Math.max(quota.daily_limit, 1),
              ) + 1
            : 0;

    return (
        <>
            <Head title={broadcast.name} />

            <div className="flex flex-col gap-6 px-4 py-8 sm:px-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex min-w-0 flex-col gap-2">
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-display font-extrabold">
                                {broadcast.name}
                            </h1>
                            <BroadcastStatusBadge status={broadcast.status} />
                        </div>
                        <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-body-sm text-muted-foreground">
                            {broadcast.scheduled_at &&
                                broadcast.status === 'scheduled' && (
                                    <span className="tabular">
                                        {t('broadcasts.show.scheduled_for', {
                                            date: stamp(broadcast.scheduled_at),
                                        })}
                                    </span>
                                )}
                            {broadcast.started_at && (
                                <span className="tabular">
                                    {t('broadcasts.show.started', {
                                        date: stamp(broadcast.started_at),
                                    })}
                                </span>
                            )}
                            {broadcast.completed_at && (
                                <span className="tabular">
                                    {t('broadcasts.show.completed', {
                                        date: stamp(broadcast.completed_at),
                                    })}
                                </span>
                            )}
                            {broadcast.created_by && (
                                <span>
                                    {t('broadcasts.show.created_by', {
                                        name: broadcast.created_by,
                                    })}
                                </span>
                            )}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {editable && (
                            <>
                                <Button
                                    onClick={() =>
                                        router.post(
                                            launch.url(broadcast.id),
                                            {},
                                            options,
                                        )
                                    }
                                >
                                    <Send className="rtl:-scale-x-100" />
                                    {t('broadcasts.show.send_now')}
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => setScheduling(true)}
                                >
                                    <CalendarClock />
                                    {t('broadcasts.show.schedule')}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={edit(broadcast.id)}>
                                        <Pencil />
                                        {t('broadcasts.show.edit')}
                                    </Link>
                                </Button>
                            </>
                        )}
                        {broadcast.status === 'sending' && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        pause.url(broadcast.id),
                                        {},
                                        options,
                                    )
                                }
                            >
                                <Pause />
                                {t('broadcasts.show.pause')}
                            </Button>
                        )}
                        {broadcast.status === 'paused' && (
                            <Button
                                onClick={() =>
                                    router.post(
                                        resume.url(broadcast.id),
                                        {},
                                        options,
                                    )
                                }
                            >
                                <Play />
                                {t('broadcasts.show.resume')}
                            </Button>
                        )}
                        {(broadcast.status === 'sending' ||
                            broadcast.status === 'paused') && (
                            <Button
                                variant="ghost"
                                onClick={() => setCancelling(true)}
                            >
                                <X />
                                {t('broadcasts.show.cancel')}
                            </Button>
                        )}
                        {broadcast.status !== 'sending' &&
                            broadcast.status !== 'paused' && (
                                <Button
                                    variant="ghost"
                                    onClick={() => setDeleting(true)}
                                >
                                    <Trash2 />
                                    {t('broadcasts.show.delete')}
                                </Button>
                            )}
                    </div>
                </header>

                <section className="grid gap-4 rounded-2xl border bg-card p-5">
                    <BroadcastProgress stats={stats} />
                    <dl className="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                        {(
                            [
                                'total',
                                'sent',
                                'delivered',
                                'read',
                                'replied',
                                'failed',
                                'skipped',
                            ] as const
                        ).map((key) => (
                            <div
                                key={key}
                                className="flex flex-col gap-1 rounded-xl bg-sunken px-3 py-2.5"
                            >
                                <dt className="text-caption text-muted-foreground">
                                    {t(`broadcasts.stats.${key}`)}
                                </dt>
                                <dd
                                    className={cn(
                                        'text-heading font-extrabold tabular',
                                        key === 'failed' &&
                                            stats.failed > 0 &&
                                            'text-danger',
                                    )}
                                >
                                    {stats[key].toLocaleString('en')}
                                </dd>
                            </div>
                        ))}
                    </dl>
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-caption text-muted-foreground">
                        <span className="inline-flex items-center gap-1.5 tabular">
                            <Gauge className="size-3.5" />
                            {t('broadcasts.quota', {
                                remaining:
                                    quota.remaining_today.toLocaleString('en'),
                                limit: quota.daily_limit.toLocaleString('en'),
                                per_minute: String(quota.per_minute),
                            })}
                        </span>
                        {stats.queued > 0 && (
                            <span className="tabular">
                                {days > 0
                                    ? t('broadcasts.show.eta_days', {
                                          days: String(days),
                                      })
                                    : t('broadcasts.show.eta', {
                                          minutes: String(minutes),
                                      })}
                            </span>
                        )}
                        {props.simulator && broadcast.status === 'sending' && (
                            <Button
                                size="sm"
                                variant="outline"
                                className="ms-auto"
                                title={t('broadcasts.show.tick_hint')}
                                onClick={() =>
                                    router.post(tick.url(), {}, options)
                                }
                            >
                                <FastForward />
                                {t('broadcasts.show.tick')}
                            </Button>
                        )}
                    </div>
                </section>

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <RecipientsTable {...props} />

                    <aside className="flex flex-col gap-4">
                        <section className="flex flex-col gap-3 rounded-2xl border bg-card p-4">
                            <h2 className="text-body-sm font-semibold text-muted-foreground">
                                {t('broadcasts.show.audience')}
                            </h2>
                            <p className="font-semibold">
                                {t(
                                    `broadcasts.audience.${broadcast.audience_type}`,
                                )}
                            </p>
                            {broadcast.audience_tags.length > 0 && (
                                <div className="flex flex-wrap gap-1.5">
                                    {broadcast.audience_tags.map((tag) => (
                                        <CategoryTag
                                            key={tag.id}
                                            tone={tag.color}
                                        >
                                            {tag.name}
                                        </CategoryTag>
                                    ))}
                                </div>
                            )}
                            {Object.keys(props.skipReasons).length > 0 && (
                                <div className="flex flex-col gap-1.5 border-t pt-3">
                                    <span className="text-caption font-semibold text-muted-foreground">
                                        {t('broadcasts.show.left_out')}
                                    </span>
                                    {Object.entries(props.skipReasons).map(
                                        ([reason, total]) => (
                                            <span
                                                key={reason}
                                                className="flex items-center justify-between text-body-sm"
                                            >
                                                {t(
                                                    `broadcasts.show.skip.${reason}`,
                                                )}
                                                <span className="font-semibold tabular">
                                                    {total.toLocaleString('en')}
                                                </span>
                                            </span>
                                        ),
                                    )}
                                </div>
                            )}
                        </section>

                        {broadcast.template && (
                            <section className="flex flex-col gap-3 rounded-2xl border bg-card p-4">
                                <h2 className="flex items-center justify-between gap-2 text-body-sm font-semibold text-muted-foreground">
                                    {t('broadcasts.show.sample')}
                                    <bdi
                                        dir="ltr"
                                        className="truncate font-mono text-caption"
                                    >
                                        {broadcast.template.name}
                                    </bdi>
                                </h2>
                                <div className="rounded-xl bg-sunken p-3">
                                    <TemplatePreview
                                        headerType={
                                            broadcast.template.header_type
                                        }
                                        headerText={
                                            broadcast.template.header_text
                                        }
                                        headerValue={props.sample?.header}
                                        body={broadcast.template.body}
                                        values={props.sample?.body ?? []}
                                        footer={broadcast.template.footer}
                                        buttons={broadcast.template.buttons}
                                    />
                                </div>
                            </section>
                        )}
                    </aside>
                </div>
            </div>

            <ScheduleDialog
                open={scheduling}
                onClose={() => setScheduling(false)}
                broadcastId={broadcast.id}
            />
            <Dialog open={cancelling} onOpenChange={setCancelling}>
                <DialogContent className="sm:max-w-md">
                    <DialogTitle>
                        {t('broadcasts.show.cancel_title', {
                            name: broadcast.name,
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {t('broadcasts.show.cancel_body')}
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">
                                {t('common.back')}
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                router.post(
                                    cancel.url(broadcast.id),
                                    {},
                                    {
                                        ...options,
                                        onFinish: () => setCancelling(false),
                                    },
                                )
                            }
                        >
                            {t('broadcasts.show.cancel')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <ConfirmDeleteDialog
                url={deleting ? destroy.url(broadcast.id) : null}
                title={t('broadcasts.show.delete_title', {
                    name: broadcast.name,
                })}
                body={t('broadcasts.show.delete_body')}
                confirmLabel={t('broadcasts.show.delete')}
                onClose={() => setDeleting(false)}
            />
        </>
    );
}

function RecipientsTable({ broadcast, recipients, outcome }: Props) {
    const { t, locale } = useTranslations();

    const result = (recipient: Recipient) => {
        if (recipient.status === 'skipped') {
            return (
                <span className="text-muted-foreground">
                    {t(
                        `broadcasts.show.skip.${recipient.skip_reason ?? 'bad_phone'}`,
                    )}
                </span>
            );
        }

        if (recipient.status === 'queued' || recipient.status === 'cancelled') {
            return (
                <span className="text-muted-foreground">
                    {t(`broadcasts.show.result.${recipient.status}`)}
                </span>
            );
        }

        const status: DeliveryStatus =
            recipient.status === 'failed'
                ? 'failed'
                : (recipient.message_status ?? 'sent');

        return (
            <span className="flex flex-col gap-0.5">
                <span
                    className={cn(
                        'inline-flex items-center gap-1.5',
                        status === 'failed' && 'text-danger',
                    )}
                >
                    <MessageStatus
                        status={status}
                        label={t(`broadcasts.show.result.${status}`)}
                    />
                    {t(`broadcasts.show.result.${status}`)}
                    {recipient.replied && (
                        <span className="rounded-full bg-success-soft px-2 py-0.5 text-caption font-semibold text-success">
                            {t('broadcasts.show.result.replied')}
                        </span>
                    )}
                </span>
                {status === 'failed' && recipient.error && (
                    <span className="text-caption text-danger">
                        {recipient.error}
                    </span>
                )}
            </span>
        );
    };

    return (
        <section className="flex min-w-0 flex-col gap-3">
            <nav className="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1">
                {OUTCOMES.map((name) => (
                    <Link
                        key={name}
                        href={show.url(broadcast.id, {
                            query: name === 'all' ? {} : { outcome: name },
                        })}
                        preserveScroll
                        preserveState
                        replace
                        aria-current={outcome === name ? 'page' : undefined}
                        className={cn(
                            'inline-flex h-8 shrink-0 items-center rounded-full px-3 text-body-sm transition-colors',
                            outcome === name
                                ? 'bg-primary font-semibold text-primary-foreground'
                                : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                        )}
                    >
                        {t(`broadcasts.show.outcomes.${name}`)}
                    </Link>
                ))}
            </nav>

            <div className="overflow-x-auto rounded-2xl border bg-card">
                <table className="w-full text-body-sm">
                    <thead className="border-b text-caption text-muted-foreground">
                        <tr>
                            <th className="px-4 py-2.5 text-start font-medium">
                                {t('broadcasts.show.columns.recipient')}
                            </th>
                            <th className="px-4 py-2.5 text-start font-medium">
                                {t('broadcasts.show.columns.result')}
                            </th>
                            <th className="px-4 py-2.5 text-start font-medium">
                                {t('broadcasts.show.columns.sent_at')}
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {recipients.data.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={3}
                                    className="px-4 py-8 text-center text-muted-foreground"
                                >
                                    {t('broadcasts.show.no_rows')}
                                </td>
                            </tr>
                        ) : (
                            recipients.data.map((recipient) => (
                                <tr key={recipient.id}>
                                    <td className="px-4 py-2.5">
                                        <span className="flex flex-col">
                                            <span
                                                dir="auto"
                                                className="font-medium"
                                            >
                                                {recipient.name ??
                                                    t('inbox.unnamed')}
                                            </span>
                                            <bdi
                                                dir="ltr"
                                                className="text-caption text-muted-foreground tabular"
                                            >
                                                {formatPhone(recipient.phone)}
                                            </bdi>
                                        </span>
                                    </td>
                                    <td className="px-4 py-2.5">
                                        {result(recipient)}
                                    </td>
                                    <td className="px-4 py-2.5 text-caption text-muted-foreground tabular">
                                        {recipient.sent_at
                                            ? `${formatDate(recipient.sent_at, locale)} ${formatTime(recipient.sent_at, locale)}`
                                            : '—'}
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {recipients.last_page > 1 && (
                <nav className="flex items-center justify-center gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        disabled={!recipients.prev_page_url}
                        asChild={!!recipients.prev_page_url}
                    >
                        {recipients.prev_page_url ? (
                            <Link
                                href={recipients.prev_page_url}
                                preserveScroll
                            >
                                <BackChevron />
                            </Link>
                        ) : (
                            <BackChevron />
                        )}
                    </Button>
                    <span className="text-body-sm tabular">
                        {recipients.current_page} / {recipients.last_page}
                    </span>
                    <Button
                        variant="outline"
                        size="icon"
                        disabled={!recipients.next_page_url}
                        asChild={!!recipients.next_page_url}
                    >
                        {recipients.next_page_url ? (
                            <Link
                                href={recipients.next_page_url}
                                preserveScroll
                            >
                                <ForwardChevron />
                            </Link>
                        ) : (
                            <ForwardChevron />
                        )}
                    </Button>
                </nav>
            )}
        </section>
    );
}

function ScheduleDialog({
    open,
    onClose,
    broadcastId,
}: {
    open: boolean;
    onClose: () => void;
    broadcastId: number;
}) {
    const { t } = useTranslations();
    const [at, setAt] = useState('');

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-sm">
                <DialogTitle>{t('broadcasts.show.schedule')}</DialogTitle>
                <DialogDescription className="sr-only">
                    {t('broadcasts.show.schedule_at')}
                </DialogDescription>
                <div className="grid gap-2">
                    <Label htmlFor="schedule-at">
                        {t('broadcasts.show.schedule_at')}
                    </Label>
                    <Input
                        id="schedule-at"
                        type="datetime-local"
                        dir="ltr"
                        value={at}
                        onChange={(event) => setAt(event.target.value)}
                        className="h-10"
                    />
                </div>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">
                            {t('common.cancel')}
                        </Button>
                    </DialogClose>
                    <Button
                        disabled={at === ''}
                        onClick={() =>
                            router.post(
                                launch.url(broadcastId),
                                // The member's local time, sent with its offset.
                                { at: new Date(at).toISOString() },
                                { preserveScroll: true, onSuccess: onClose },
                            )
                        }
                    >
                        <CalendarClock />
                        {t('broadcasts.show.schedule')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
