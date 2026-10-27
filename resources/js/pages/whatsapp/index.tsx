import { Head, router, setLayoutProps } from '@inertiajs/react';
import {
    CircleCheck,
    CircleX,
    Copy,
    FlaskConical,
    RefreshCw,
    ShieldCheck,
} from 'lucide-react';
import type { ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate, formatTime } from '@/lib/time';
import { cn } from '@/lib/utils';
import { index, retry } from '@/routes/whatsapp';
import { sync } from '@/routes/whatsapp/templates';

type Key =
    | 'token'
    | 'phone_number_id'
    | 'business_account_id'
    | 'app_secret'
    | 'verify_token';

type Props = {
    gateway: 'simulator' | 'cloud';
    keys: Record<Key, boolean>;
    webhookUrl: string;
    graphVersion: string;
    number:
        | { error: string }
        | {
              display: string | null;
              name: string | null;
              name_status: string | null;
              quality: string | null;
              tier: string | null;
          }
        | null;
    health: {
        quality: string | null;
        tier: string | null;
        event: string | null;
        at: string | null;
    };
    events: {
        last: string | null;
        today: number;
        waiting: number;
        stuck: number;
        error: string | null;
    };
};

const KEYS: Key[] = [
    'token',
    'phone_number_id',
    'business_account_id',
    'app_secret',
    'verify_token',
];

const QUALITY: Record<string, string> = {
    GREEN: 'bg-success-soft text-success',
    YELLOW: 'bg-warning-soft text-warning',
    RED: 'bg-danger-soft text-danger',
};

export default function WhatsApp({
    gateway,
    keys,
    webhookUrl,
    graphVersion,
    number,
    health,
    events,
}: Props) {
    const { t, locale } = useTranslations();
    const [copied, copy] = useClipboard();
    const cloud = gateway === 'cloud';
    const quality =
        (number && !('error' in number) ? number.quality : null) ??
        health.quality;
    const tier =
        (number && !('error' in number) ? number.tier : null) ?? health.tier;

    setLayoutProps({
        breadcrumbs: [{ title: t('whatsapp.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('whatsapp.title')} />

            <div className="flex flex-col gap-6 px-4 py-8 sm:px-6">
                <header className="flex max-w-3xl flex-col gap-1.5">
                    <h1 className="text-display font-extrabold">
                        {t('whatsapp.title')}
                    </h1>
                    <p className="text-body-sm text-muted-foreground">
                        {t('whatsapp.description')}
                    </p>
                </header>

                <div
                    className={cn(
                        'flex flex-wrap items-start gap-3 rounded-2xl border p-4',
                        cloud ? 'border-success/30 bg-success-soft' : 'bg-card',
                    )}
                >
                    {cloud ? (
                        <ShieldCheck className="mt-0.5 size-5 shrink-0 text-success" />
                    ) : (
                        <FlaskConical className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
                    )}
                    <div className="flex min-w-0 flex-1 flex-col gap-1">
                        <p className="font-semibold">
                            {t(`whatsapp.gateway.${gateway}`)}
                        </p>
                        {!cloud && (
                            <p className="text-body-sm text-muted-foreground">
                                {t('whatsapp.gateway.switch')}
                            </p>
                        )}
                    </div>
                </div>

                <div className="grid items-start gap-6 xl:grid-cols-2">
                    <Panel
                        title={t('whatsapp.keys.title')}
                        hint={t('whatsapp.keys.hint')}
                    >
                        <ul className="flex flex-col divide-y">
                            {KEYS.map((key) => (
                                <li
                                    key={key}
                                    className="flex items-center gap-3 py-2.5 text-body-sm"
                                >
                                    {keys[key] ? (
                                        <CircleCheck className="size-4 shrink-0 text-success" />
                                    ) : (
                                        <CircleX className="size-4 shrink-0 text-muted-foreground" />
                                    )}
                                    <span className="min-w-0 flex-1">
                                        {t(`whatsapp.keys.${key}`)}
                                    </span>
                                    <span
                                        className={cn(
                                            'shrink-0 text-caption font-semibold',
                                            keys[key]
                                                ? 'text-success'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {keys[key]
                                            ? t('whatsapp.keys.present')
                                            : t('whatsapp.keys.missing')}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        <p className="text-caption text-muted-foreground">
                            Graph API{' '}
                            <bdi dir="ltr" className="font-mono">
                                {graphVersion}
                            </bdi>
                        </p>
                    </Panel>

                    <Panel
                        title={t('whatsapp.webhook.title')}
                        hint={t('whatsapp.webhook.hint')}
                    >
                        <div className="flex items-center gap-2 rounded-xl border bg-sunken p-2 ps-3">
                            <bdi
                                dir="ltr"
                                className="min-w-0 flex-1 truncate font-mono text-body-sm"
                            >
                                {webhookUrl}
                            </bdi>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => void copy(webhookUrl)}
                            >
                                <Copy />
                                {copied
                                    ? t('whatsapp.webhook.copied')
                                    : t('whatsapp.webhook.copy')}
                            </Button>
                        </div>
                    </Panel>

                    <Panel title={t('whatsapp.number.title')}>
                        {number === null ? (
                            <p className="text-body-sm text-muted-foreground">
                                {t('whatsapp.number.not_connected')}
                            </p>
                        ) : 'error' in number ? (
                            <p className="rounded-xl bg-danger-soft px-3 py-2.5 text-body-sm text-danger">
                                {t('whatsapp.number.error', {
                                    error: number.error,
                                })}
                            </p>
                        ) : (
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Fact label={t('whatsapp.number.title')}>
                                    <bdi dir="ltr" className="tabular">
                                        {number.display ?? '—'}
                                    </bdi>
                                </Fact>
                                <Fact label={t('whatsapp.number.name')}>
                                    {number.name ?? '—'}
                                </Fact>
                                <Fact label={t('whatsapp.number.quality')}>
                                    <span
                                        className={cn(
                                            'inline-flex h-6 items-center rounded-full px-2.5 text-caption font-semibold',
                                            QUALITY[quality ?? ''] ??
                                                'bg-muted text-muted-foreground',
                                        )}
                                    >
                                        {t(
                                            `whatsapp.number.qualities.${quality ?? 'UNKNOWN'}`,
                                        )}
                                    </span>
                                </Fact>
                                <Fact label={t('whatsapp.number.tier')}>
                                    {tier
                                        ? t(`whatsapp.number.tiers.${tier}`)
                                        : '—'}
                                </Fact>
                            </dl>
                        )}
                    </Panel>

                    <Panel
                        title={t('whatsapp.events.title')}
                        hint={t('whatsapp.events.hint')}
                    >
                        <ul className="flex flex-col gap-1.5 text-body-sm tabular">
                            <li>
                                {events.last
                                    ? t('whatsapp.events.last', {
                                          time: `${formatDate(events.last, locale)} ${formatTime(events.last, locale)}`,
                                      })
                                    : t('whatsapp.events.none')}
                            </li>
                            <li className="text-muted-foreground">
                                {t('whatsapp.events.today', {
                                    count: String(events.today),
                                })}
                            </li>
                            <li
                                className={cn(
                                    events.waiting > 0
                                        ? 'text-warning'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {t('whatsapp.events.waiting', {
                                    count: String(events.waiting),
                                })}
                            </li>
                            <li
                                className={cn(
                                    events.stuck > 0
                                        ? 'text-danger'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {t('whatsapp.events.stuck', {
                                    count: String(events.stuck),
                                })}
                            </li>
                            {events.error && (
                                <li className="text-caption text-danger">
                                    {t('whatsapp.events.error', {
                                        error: events.error,
                                    })}
                                </li>
                            )}
                        </ul>
                        {events.waiting + events.stuck > 0 && (
                            <Button
                                variant="outline"
                                className="self-start"
                                onClick={() =>
                                    router.post(
                                        retry.url(),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <RefreshCw />
                                {t('whatsapp.events.retry')}
                            </Button>
                        )}
                    </Panel>

                    {cloud && (
                        <Panel
                            title={t('whatsapp.templates.title')}
                            hint={t('whatsapp.templates.hint')}
                        >
                            <Button
                                variant="outline"
                                className="self-start"
                                onClick={() =>
                                    router.post(
                                        sync.url(),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <RefreshCw />
                                {t('whatsapp.templates.sync')}
                            </Button>
                        </Panel>
                    )}
                </div>
            </div>
        </>
    );
}

function Panel({
    title,
    hint,
    children,
}: {
    title: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <section className="flex flex-col gap-4 rounded-2xl border bg-card p-5">
            <div className="flex flex-col gap-1">
                <h2 className="text-title font-bold">{title}</h2>
                {hint && (
                    <p className="text-body-sm text-muted-foreground">{hint}</p>
                )}
            </div>
            {children}
        </section>
    );
}

function Fact({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex flex-col gap-1">
            <dt className="text-caption text-muted-foreground">{label}</dt>
            <dd className="text-body-sm font-semibold">{children}</dd>
        </div>
    );
}
