import { Head, router, setLayoutProps } from '@inertiajs/react';
import {
    CircleCheck,
    CircleX,
    Copy,
    FlaskConical,
    Mail,
    Send,
} from 'lucide-react';
import type { ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate, formatTime } from '@/lib/time';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/codes';
import { status as deliveryStatus } from '@/routes/codes/deliveries';
import { decide, submit as submitTemplates } from '@/routes/codes/templates';

type TemplateState = {
    language: 'ar' | 'en';
    status:
        | 'draft'
        | 'pending'
        | 'approved'
        | 'rejected'
        | 'paused'
        | 'disabled';
    reason: string | null;
    submitted_at: string | null;
};

type Delivery = {
    id: number;
    phone: string;
    language: string;
    purpose: string | null;
    status: 'pending' | 'sent' | 'delivered' | 'read' | 'failed';
    error: string | null;
    to_email: boolean;
    at: string | null;
};

type Props = {
    enabled: boolean;
    simulator: boolean;
    rizq: { url: string | null; secret: boolean };
    templateName: string;
    expiryMinutes: number;
    templates: TemplateState[];
    week: { sent: number; delivered: number; failed: number; to_email: number };
    deliveries: Delivery[];
};

const TEMPLATE_TONE: Record<TemplateState['status'], string> = {
    draft: 'bg-muted text-muted-foreground',
    pending: 'bg-warning-soft text-warning',
    approved: 'bg-success-soft text-success',
    rejected: 'bg-danger-soft text-danger',
    paused: 'bg-warning-soft text-warning',
    disabled: 'bg-danger-soft text-danger',
};

const DELIVERY_TONE: Record<Delivery['status'], string> = {
    pending: 'bg-muted text-muted-foreground',
    sent: 'bg-muted text-muted-foreground',
    delivered: 'bg-success-soft text-success',
    read: 'bg-success-soft text-success',
    failed: 'bg-danger-soft text-danger',
};

export default function Codes({
    enabled,
    simulator,
    rizq,
    templateName,
    expiryMinutes,
    templates,
    week,
    deliveries,
}: Props) {
    const { t } = useTranslations();
    const canSubmit = templates.some(
        (template) =>
            template.status === 'draft' || template.status === 'rejected',
    );
    const noneApproved = !templates.some(
        (template) => template.status === 'approved',
    );

    setLayoutProps({
        breadcrumbs: [{ title: t('codes.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('codes.title')} />

            <div className="flex flex-col gap-6 px-4 py-8 sm:px-6">
                <header className="flex max-w-3xl flex-col gap-1.5">
                    <h1 className="text-display font-extrabold">
                        {t('codes.title')}
                    </h1>
                    <p className="text-body-sm text-muted-foreground">
                        {t('codes.description')}
                    </p>
                </header>

                <label
                    className={cn(
                        'flex items-start gap-3 rounded-2xl border p-4',
                        enabled
                            ? 'border-success/30 bg-success-soft'
                            : 'bg-card',
                    )}
                >
                    <Switch
                        checked={enabled}
                        onCheckedChange={(checked) =>
                            router.put(
                                update.url(),
                                { enabled: checked },
                                { preserveScroll: true },
                            )
                        }
                    />
                    <span className="flex flex-col gap-0.5">
                        <span className="font-semibold">
                            {t('codes.switch.label')}
                        </span>
                        <span className="text-body-sm text-muted-foreground">
                            {enabled
                                ? t('codes.switch.on')
                                : t('codes.switch.off')}
                        </span>
                    </span>
                </label>

                {simulator && (
                    <p className="flex items-center gap-2 rounded-xl bg-muted px-4 py-3 text-body-sm text-muted-foreground">
                        <FlaskConical className="size-4 shrink-0" />
                        {t('codes.simulator.note')}
                    </p>
                )}

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
                    <Panel
                        title={t('codes.template.title')}
                        hint={t('codes.template.hint', {
                            minutes: String(expiryMinutes),
                        })}
                    >
                        <dl className="flex flex-col gap-1">
                            <dt className="text-caption text-muted-foreground">
                                {t('codes.template.name')}
                            </dt>
                            <dd className="font-mono text-body-sm" dir="ltr">
                                <bdi>{templateName}</bdi>
                            </dd>
                        </dl>

                        <ul className="flex flex-col divide-y">
                            {templates.map((template) => (
                                <li
                                    key={template.language}
                                    className="flex flex-col gap-1.5 py-3 first:pt-0 last:pb-0"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-semibold">
                                            {t(
                                                `templates.language.${template.language}`,
                                            )}
                                        </span>
                                        <span
                                            className={cn(
                                                'rounded-full px-2.5 py-0.5 text-caption font-semibold',
                                                TEMPLATE_TONE[template.status],
                                            )}
                                        >
                                            {t(
                                                `templates.status.${template.status}`,
                                            )}
                                        </span>
                                        {simulator &&
                                            template.status === 'pending' && (
                                                <span className="ms-auto flex gap-1.5">
                                                    <SimulatorButton
                                                        onClick={() =>
                                                            router.put(
                                                                decide.url(
                                                                    template.language,
                                                                ),
                                                                {
                                                                    decision:
                                                                        'approved',
                                                                },
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        {t(
                                                            'codes.simulator.approve',
                                                        )}
                                                    </SimulatorButton>
                                                    <SimulatorButton
                                                        onClick={() =>
                                                            router.put(
                                                                decide.url(
                                                                    template.language,
                                                                ),
                                                                {
                                                                    decision:
                                                                        'rejected',
                                                                },
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        {t(
                                                            'codes.simulator.reject',
                                                        )}
                                                    </SimulatorButton>
                                                </span>
                                            )}
                                    </div>
                                    {template.status === 'rejected' &&
                                        template.reason && (
                                            <p className="text-caption text-danger">
                                                {t('codes.template.rejected', {
                                                    reason: template.reason,
                                                })}
                                            </p>
                                        )}
                                </li>
                            ))}
                        </ul>

                        {noneApproved && (
                            <p className="rounded-xl bg-warning-soft px-3 py-2 text-body-sm text-warning">
                                {t('codes.template.none_approved')}
                            </p>
                        )}

                        {canSubmit && (
                            <Button
                                className="self-start"
                                onClick={() =>
                                    router.post(
                                        submitTemplates.url(),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Send />
                                {t('codes.template.submit')}
                            </Button>
                        )}

                        <Preview minutes={expiryMinutes} />
                    </Panel>

                    <div className="flex flex-col gap-6">
                        <Panel
                            title={t('codes.rizq.title')}
                            hint={t('codes.rizq.hint')}
                        >
                            <ul className="flex flex-col divide-y text-body-sm">
                                <Fact
                                    label={t('codes.rizq.url')}
                                    ok={rizq.url !== null && rizq.url !== ''}
                                >
                                    {rizq.url ? (
                                        <bdi dir="ltr">{rizq.url}</bdi>
                                    ) : (
                                        t('codes.rizq.missing')
                                    )}
                                </Fact>
                                <Fact
                                    label={t('codes.rizq.secret')}
                                    ok={rizq.secret}
                                >
                                    {rizq.secret
                                        ? t('codes.rizq.set')
                                        : t('codes.rizq.missing')}
                                </Fact>
                            </ul>
                        </Panel>

                        <Panel title={t('codes.week.title')}>
                            <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                {(
                                    [
                                        'sent',
                                        'delivered',
                                        'failed',
                                        'to_email',
                                    ] as const
                                ).map((key) => (
                                    <div
                                        key={key}
                                        className="flex flex-col gap-1 rounded-xl bg-muted/60 p-3"
                                    >
                                        <dt className="text-caption text-muted-foreground">
                                            {t(`codes.week.${key}`)}
                                        </dt>
                                        <dd className="text-title font-bold tabular">
                                            {week[key]}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </Panel>
                    </div>
                </div>

                <Panel
                    title={t('codes.deliveries.title')}
                    hint={t('codes.deliveries.hint')}
                >
                    {deliveries.length === 0 ? (
                        <p className="py-6 text-center text-body-sm text-muted-foreground">
                            {t('codes.deliveries.empty')}
                        </p>
                    ) : (
                        <ul className="flex flex-col divide-y">
                            {deliveries.map((delivery) => (
                                <DeliveryRow
                                    key={delivery.id}
                                    delivery={delivery}
                                    simulator={simulator}
                                />
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>
        </>
    );
}

function DeliveryRow({
    delivery,
    simulator,
}: {
    delivery: Delivery;
    simulator: boolean;
}) {
    const { t, locale } = useTranslations();
    const mark = (status: 'delivered' | 'failed') =>
        router.put(
            deliveryStatus.url(delivery.id),
            { status },
            { preserveScroll: true },
        );

    return (
        <li className="flex flex-wrap items-center gap-x-4 gap-y-1.5 py-3 text-body-sm first:pt-0 last:pb-0">
            <span className="font-mono tabular">
                <bdi dir="ltr">{delivery.phone}</bdi>
            </span>
            {delivery.purpose && (
                <span className="text-muted-foreground">
                    {t(`codes.purposes.${delivery.purpose}`)}
                </span>
            )}
            <span
                className={cn(
                    'rounded-full px-2.5 py-0.5 text-caption font-semibold',
                    DELIVERY_TONE[delivery.status],
                )}
            >
                {t(`inbox.status.${delivery.status}`)}
            </span>
            {delivery.to_email && (
                <span className="inline-flex items-center gap-1 text-caption text-muted-foreground">
                    <Mail className="size-3.5" />
                    {t('codes.deliveries.to_email')}
                </span>
            )}
            {delivery.at && (
                <span className="text-caption text-muted-foreground tabular">
                    {formatDate(delivery.at, locale)} ·{' '}
                    {formatTime(delivery.at, locale)}
                </span>
            )}
            {simulator && delivery.status === 'sent' && (
                <span className="ms-auto flex gap-1.5">
                    <SimulatorButton onClick={() => mark('delivered')}>
                        {t('codes.simulator.delivered')}
                    </SimulatorButton>
                    <SimulatorButton onClick={() => mark('failed')}>
                        {t('codes.simulator.failed')}
                    </SimulatorButton>
                </span>
            )}
            {delivery.error && (
                <p className="basis-full text-caption text-danger">
                    <bdi>{delivery.error}</bdi>
                </p>
            )}
        </li>
    );
}

/**
 * Meta writes the template in each language; this is close to it, so the
 * team knows what customers receive.
 */
function Preview({ minutes }: { minutes: number }) {
    const { t } = useTranslations();

    return (
        <figure className="flex flex-col gap-2">
            <figcaption className="text-caption text-muted-foreground">
                {t('codes.template.preview_label')}
            </figcaption>
            <div className="max-w-sm overflow-hidden rounded-2xl rounded-ss-sm border bg-background shadow-xs">
                <div className="flex flex-col gap-1.5 p-3">
                    <p className="text-body-sm">
                        {t('codes.template.preview_body', {
                            code: '482915',
                        })}
                    </p>
                    <p className="text-caption text-muted-foreground">
                        {t('codes.template.preview_footer', {
                            minutes: String(minutes),
                        })}
                    </p>
                </div>
                <div className="flex items-center justify-center gap-1.5 border-t py-2 text-body-sm font-semibold text-primary">
                    <Copy className="size-4" />
                    {t('codes.template.preview_button')}
                </div>
            </div>
        </figure>
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

function Fact({
    label,
    ok,
    children,
}: {
    label: string;
    ok: boolean;
    children: ReactNode;
}) {
    return (
        <li className="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
            {ok ? (
                <CircleCheck className="size-4 shrink-0 text-success" />
            ) : (
                <CircleX className="size-4 shrink-0 text-danger" />
            )}
            <span className="text-muted-foreground">{label}</span>
            <span className="ms-auto min-w-0 truncate font-semibold">
                {children}
            </span>
        </li>
    );
}

function SimulatorButton({
    onClick,
    children,
}: {
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <Button size="sm" variant="outline" onClick={onClick}>
            <FlaskConical />
            {children}
        </Button>
    );
}
