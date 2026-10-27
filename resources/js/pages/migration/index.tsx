import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import {
    Bot,
    Check,
    CircleCheck,
    CircleDashed,
    CircleX,
    Hourglass,
    UserCheck,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useDuration } from '@/hooks/use-duration';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate } from '@/lib/time';
import { cn } from '@/lib/utils';
import { index as assistantPage } from '@/routes/assistant';
import { index, prices, update } from '@/routes/migration';
import { update as updateStep } from '@/routes/migration/steps';

type Status = 'met' | 'not_met' | 'gathering' | 'manual';

type Criterion = { key: string; status: Status; value: string | null };

type Props = {
    migration: {
        respond_io_bot_active: boolean;
        baseline_reply_minutes: number | null;
        prices_checked_at: string | null;
        steps: Record<string, string>;
    };
    steps: string[];
    criteria: Criterion[];
    assistantMode: 'off' | 'shadow' | 'live';
    gateway: string;
    targets: {
        days: number;
        agreement: number;
        min_judged: number;
        delay_minutes: number;
    };
};

const STATUS: Record<Status, { icon: ReactNode; className: string }> = {
    met: {
        icon: <CircleCheck className="size-4" />,
        className: 'bg-success-soft text-success',
    },
    not_met: {
        icon: <CircleX className="size-4" />,
        className: 'bg-danger-soft text-danger',
    },
    gathering: {
        icon: <Hourglass className="size-4" />,
        className: 'bg-muted text-muted-foreground',
    },
    manual: {
        icon: <UserCheck className="size-4" />,
        className: 'bg-warning-soft text-warning',
    },
};

export default function Migration({
    migration,
    steps,
    criteria,
    targets,
}: Props) {
    const { t } = useTranslations();
    const met = criteria.filter(
        (criterion) => criterion.status === 'met',
    ).length;

    setLayoutProps({
        breadcrumbs: [{ title: t('migration.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('migration.title')} />

            <div className="flex flex-col gap-6 px-4 py-8 sm:px-6">
                <header className="flex max-w-3xl flex-col gap-1.5">
                    <h1 className="text-display font-extrabold">
                        {t('migration.title')}
                    </h1>
                    <p className="text-body-sm text-muted-foreground">
                        {t('migration.description')}
                    </p>
                </header>

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
                    <section className="flex flex-col gap-4 rounded-2xl border bg-card p-5">
                        <div className="flex flex-wrap items-end justify-between gap-2">
                            <div className="flex flex-col gap-1">
                                <h2 className="text-title font-bold">
                                    {t('migration.criteria.title')}
                                </h2>
                                <p className="text-body-sm text-muted-foreground">
                                    {t('migration.criteria.hint', {
                                        days: String(targets.days),
                                    })}
                                </p>
                            </div>
                            <span
                                className={cn(
                                    'rounded-full px-3 py-1 text-body-sm font-semibold tabular',
                                    met === criteria.length
                                        ? 'bg-success-soft text-success'
                                        : 'bg-muted',
                                )}
                            >
                                {met === criteria.length
                                    ? t('migration.criteria.ready', {
                                          days: String(targets.days),
                                      })
                                    : t('migration.criteria.progress', {
                                          count: String(met),
                                      })}
                            </span>
                        </div>
                        <ul className="flex flex-col divide-y">
                            {criteria.map((criterion) => (
                                <CriterionRow
                                    key={criterion.key}
                                    criterion={criterion}
                                    targets={targets}
                                />
                            ))}
                        </ul>
                    </section>

                    <div className="flex flex-col gap-6">
                        <SettingsPanel migration={migration} />
                        <StepsPanel steps={steps} done={migration.steps} />
                    </div>
                </div>
            </div>
        </>
    );
}

function CriterionRow({
    criterion,
    targets,
}: {
    criterion: Criterion;
    targets: Props['targets'];
}) {
    const { t, locale } = useTranslations();
    const duration = useDuration();
    const key = `migration.criteria.${criterion.key}`;

    const value = (() => {
        if (criterion.value === null) {
            return null;
        }

        switch (criterion.key) {
            case 'receiving':
            case 'reply_time':
                return duration(Number(criterion.value));
            case 'prices':
                return formatDate(criterion.value, locale);
            default:
                return criterion.value;
        }
    })();

    return (
        <li className="flex flex-col gap-2 py-4 first:pt-0 last:pb-0">
            <div className="flex items-start gap-3">
                <span
                    className={cn(
                        'inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-caption font-semibold',
                        STATUS[criterion.status].className,
                    )}
                >
                    {STATUS[criterion.status].icon}
                    {t(`migration.criteria.statuses.${criterion.status}`)}
                </span>
                <div className="flex min-w-0 flex-1 flex-col gap-1">
                    <h3 className="font-semibold">
                        {t(`${key}.title`, {
                            minutes: String(targets.delay_minutes),
                            target: String(targets.agreement),
                        })}
                    </h3>
                    <p className="text-body-sm text-muted-foreground">
                        {t(`${key}.body`, { min: String(targets.min_judged) })}
                    </p>
                    {value !== null && (
                        <p className="text-caption font-semibold tabular">
                            {t(`${key}.value`, { value })}
                        </p>
                    )}
                    {criterion.key === 'prices' && (
                        <div className="flex flex-wrap gap-2 pt-1">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        prices.url(),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Check />
                                {t('migration.criteria.prices.confirm')}
                            </Button>
                            <Button size="sm" variant="ghost" asChild>
                                <Link href={assistantPage()}>
                                    <Bot />
                                    {t('nav.assistant')}
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </li>
    );
}

function SettingsPanel({ migration }: { migration: Props['migration'] }) {
    const { t } = useTranslations();
    const form = useForm({
        respond_io_bot_active: migration.respond_io_bot_active,
        baseline_reply_minutes:
            migration.baseline_reply_minutes === null
                ? ''
                : String(migration.baseline_reply_minutes),
    });

    const save = (event?: FormEvent) => {
        event?.preventDefault();
        form.transform((data) => ({
            ...data,
            baseline_reply_minutes:
                data.baseline_reply_minutes === ''
                    ? null
                    : Number(data.baseline_reply_minutes),
        }));
        form.put(update.url(), { preserveScroll: true });
    };

    return (
        <form
            onSubmit={save}
            className="flex flex-col gap-5 rounded-2xl border bg-card p-5"
        >
            <div className="flex flex-col gap-3">
                <h2 className="text-title font-bold">
                    {t('migration.respond_io.title')}
                </h2>
                <label className="flex items-start gap-3 rounded-xl border p-3">
                    <Switch
                        checked={form.data.respond_io_bot_active}
                        onCheckedChange={(checked) =>
                            form.setData('respond_io_bot_active', checked)
                        }
                    />
                    <span className="flex flex-col gap-0.5">
                        <span className="text-body-sm font-semibold">
                            {t('migration.respond_io.label')}
                        </span>
                        <span className="text-caption text-muted-foreground">
                            {t('migration.respond_io.hint')}
                        </span>
                    </span>
                </label>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="baseline">
                    {t('migration.baseline.label')}
                </Label>
                <Input
                    id="baseline"
                    type="number"
                    min={1}
                    max={1440}
                    dir="ltr"
                    value={form.data.baseline_reply_minutes}
                    onChange={(event) =>
                        form.setData(
                            'baseline_reply_minutes',
                            event.target.value,
                        )
                    }
                    className="h-10 w-32 text-start tabular"
                />
                <p className="text-caption text-muted-foreground">
                    {t('migration.baseline.hint')}
                </p>
                <InputError message={form.errors.baseline_reply_minutes} />
            </div>
            <Button
                type="submit"
                className="self-start"
                disabled={form.processing || !form.isDirty}
            >
                {form.processing && <Spinner />}
                {t('migration.save')}
            </Button>
        </form>
    );
}

function StepsPanel({
    steps,
    done,
}: {
    steps: string[];
    done: Record<string, string>;
}) {
    const { t, locale } = useTranslations();

    const toggle = (step: string) =>
        router.put(
            updateStep.url(step),
            { done: !done[step] },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <section className="flex flex-col gap-4 rounded-2xl border bg-card p-5">
            <h2 className="text-title font-bold">
                {t('migration.steps.title')}
            </h2>
            <ol className="flex flex-col">
                {steps.map((step, position) => {
                    const at = done[step];

                    return (
                        <li
                            key={step}
                            className="flex items-start gap-3 border-t py-3 first:border-t-0 first:pt-0 last:pb-0"
                        >
                            <span
                                className={cn(
                                    'grid size-7 shrink-0 place-items-center rounded-full text-caption font-bold tabular',
                                    at
                                        ? 'bg-success-soft text-success'
                                        : 'bg-muted text-muted-foreground',
                                )}
                                aria-hidden
                            >
                                {at ? (
                                    <Check className="size-4" />
                                ) : (
                                    position + 1
                                )}
                            </span>
                            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span className="font-semibold">
                                    {t(`migration.steps.${step}.title`)}
                                </span>
                                <span className="text-body-sm text-muted-foreground">
                                    {t(`migration.steps.${step}.body`)}
                                </span>
                                {at && (
                                    <span className="text-caption text-success tabular">
                                        {t('migration.steps.done_on', {
                                            date: formatDate(at, locale),
                                        })}
                                    </span>
                                )}
                            </div>
                            <Button
                                size="sm"
                                variant={at ? 'ghost' : 'outline'}
                                onClick={() => toggle(step)}
                            >
                                {at ? (
                                    t('migration.steps.unmark')
                                ) : (
                                    <>
                                        <CircleDashed />
                                        {t('migration.steps.mark')}
                                    </>
                                )}
                            </Button>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}
