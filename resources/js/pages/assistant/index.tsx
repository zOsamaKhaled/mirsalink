import {
    Head,
    Link,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/react';
import { Bot, CircleCheck, CircleX, Power, Sparkles } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';

import { CategoryTag } from '@/components/inbox/category-tag';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useDuration } from '@/hooks/use-duration';
import { useTranslations } from '@/hooks/use-translations';
import { formatPhone } from '@/lib/phone';
import { formatDate, formatTime } from '@/lib/time';
import { cn } from '@/lib/utils';
import { index, trial, update } from '@/routes/assistant';
import { show as conversation } from '@/routes/inbox';

type Mode = 'off' | 'shadow' | 'live';

type Summary = {
    turns: number;
    settled: number | null;
    handed_over: number | null;
    failed: number;
    agreement: number | null;
    judged: number;
    median_ms: number | null;
};

type Run = {
    id: number;
    mode: 'live' | 'shadow';
    conversation_id: number | null;
    customer: string | null;
    phone: string | null;
    message: string | null;
    status: 'ok' | 'failed';
    intent: string | null;
    tags: string[];
    team_tags: string[] | null;
    agrees: boolean | null;
    confidence: number | null;
    decision: string | null;
    reply: string | null;
    latency_ms: number | null;
    error: string | null;
    at: string;
};

type Trial = {
    message: string;
    status: 'ok' | 'failed';
    intent: string | null;
    tags: string[];
    confidence: number | null;
    decision: string | null;
    reply: string | null;
    sources: string[];
    latency_ms: number | null;
    error: string | null;
};

type Props = {
    settings: {
        mode: Mode;
        greeting: string;
        out_of_scope_reply: string;
        handover_reply: string;
    };
    brain: {
        driver: 'simulator' | 'ollama';
        model: string;
        embedding_model: string | null;
        healthy: boolean;
        threshold: number;
        timeout: number;
    };
    knowledge: { active: number; embedded: number | null };
    summary: { live: Summary; shadow: Summary };
    runs: Run[];
};

const MODES: Mode[] = ['off', 'shadow', 'live'];

export default function Assistant({
    settings,
    brain,
    knowledge,
    summary,
    runs,
}: Props) {
    const { t } = useTranslations();

    setLayoutProps({
        breadcrumbs: [{ title: t('assistant.title'), href: index() }],
    });

    // The switch saves at once: stopping the assistant can't wait for a form.
    const switchTo = (mode: Mode) =>
        router.put(
            update.url(),
            { ...settings, mode },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={t('assistant.title')} />

            <div className="flex flex-col gap-6 px-4 py-8 sm:px-6">
                <header className="flex max-w-3xl flex-col gap-1.5">
                    <h1 className="text-display font-extrabold">
                        {t('assistant.title')}
                    </h1>
                    <p className="text-body-sm text-muted-foreground">
                        {t('assistant.description')}
                    </p>
                </header>

                <section
                    className="grid gap-3 md:grid-cols-3"
                    aria-label={t('assistant.mode.label')}
                >
                    {MODES.map((mode) => {
                        const active = settings.mode === mode;

                        return (
                            <button
                                key={mode}
                                type="button"
                                aria-pressed={active}
                                onClick={() => !active && switchTo(mode)}
                                className={cn(
                                    'flex flex-col gap-1.5 rounded-2xl border p-4 text-start transition-colors',
                                    active
                                        ? mode === 'off'
                                            ? 'border-danger/40 bg-danger-soft'
                                            : 'border-primary bg-accent ring-2 ring-primary/30 dark:border-tag-blue-foreground'
                                        : 'bg-card hover:bg-accent/50',
                                )}
                            >
                                <span className="flex items-center gap-2 font-bold">
                                    {mode === 'off' ? (
                                        <Power className="size-4" />
                                    ) : mode === 'shadow' ? (
                                        <Sparkles className="size-4" />
                                    ) : (
                                        <Bot className="size-4" />
                                    )}
                                    {t(`assistant.mode.${mode}`)}
                                    {active && (
                                        <CircleCheck className="ms-auto size-4 text-success" />
                                    )}
                                </span>
                                <span className="text-body-sm text-muted-foreground">
                                    {t(`assistant.mode.hint.${mode}`)}
                                </span>
                            </button>
                        );
                    })}
                </section>

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <div className="flex flex-col gap-6">
                        <TrialBox />
                        <SummaryPanel summary={summary} />
                    </div>
                    <div className="flex flex-col gap-6">
                        <BrainPanel brain={brain} knowledge={knowledge} />
                        <RepliesForm settings={settings} />
                    </div>
                </div>

                <RunsTable runs={runs} />
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

function TrialBox() {
    const { t } = useTranslations();
    const result = usePage().flash.trial as Trial | undefined;
    const form = useForm({ message: result?.message ?? '' });

    const run = (event: FormEvent) => {
        event.preventDefault();
        form.post(trial.url(), { preserveScroll: true, preserveState: true });
    };

    return (
        <Panel
            title={t('assistant.trial.title')}
            hint={t('assistant.trial.hint')}
        >
            <form
                onSubmit={run}
                className="flex flex-col gap-2 sm:flex-row sm:items-start"
            >
                <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                    <Textarea
                        dir="auto"
                        rows={2}
                        value={form.data.message}
                        onChange={(event) =>
                            form.setData('message', event.target.value)
                        }
                        placeholder={t('assistant.trial.placeholder')}
                        aria-label={t('assistant.trial.title')}
                        className="field-sizing-content max-h-40 min-h-10 resize-none"
                    />
                    <InputError message={form.errors.message} />
                </div>
                <Button
                    type="submit"
                    disabled={
                        form.processing || form.data.message.trim() === ''
                    }
                >
                    {form.processing ? <Spinner /> : <Sparkles />}
                    {t('assistant.trial.run')}
                </Button>
            </form>

            {result && <TrialResult result={result} />}
        </Panel>
    );
}

function TrialResult({ result }: { result: Trial }) {
    const { t } = useTranslations();

    if (result.status === 'failed') {
        return (
            <p className="rounded-xl bg-danger-soft px-3 py-2.5 text-body-sm text-danger">
                {t('assistant.trial.failed', { error: result.error ?? '' })}
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-4 rounded-xl bg-sunken p-4">
            <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <Fact label={t('assistant.trial.intent')}>
                    {result.intent
                        ? t(`assistant.intents.${result.intent}`)
                        : '—'}
                </Fact>
                <Fact label={t('assistant.trial.confidence')}>
                    <span className="tabular">
                        {result.confidence === null
                            ? '—'
                            : `${Math.round(result.confidence * 100)}%`}
                    </span>
                </Fact>
                <Fact label={t('assistant.trial.decision')}>
                    <DecisionBadge decision={result.decision} />
                </Fact>
                <Fact label={t('assistant.trial.tags')}>
                    <TagList tags={result.tags} />
                </Fact>
            </dl>
            <div className="flex flex-col gap-1.5">
                <span className="text-caption text-muted-foreground">
                    {t('assistant.trial.reply')}
                </span>
                {result.reply && result.decision !== 'handed_over' ? (
                    <p
                        dir="auto"
                        className="max-w-xl self-end rounded-2xl rounded-se-md bg-bubble-bot px-3.5 py-2.5 text-body whitespace-pre-wrap text-bubble-bot-foreground"
                    >
                        {result.reply}
                    </p>
                ) : (
                    <p className="text-body-sm text-muted-foreground">
                        {result.reply ?? t('assistant.trial.no_reply')}
                    </p>
                )}
            </div>
            {result.sources.length > 0 && (
                <p className="text-caption text-muted-foreground">
                    {t('assistant.trial.sources')}: {result.sources.join(' · ')}
                </p>
            )}
        </div>
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

function TagList({ tags }: { tags: string[] }) {
    if (tags.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span className="flex flex-wrap gap-1">
            {tags.map((tag) => (
                <CategoryTag key={tag} tone="slate" byBot>
                    {tag}
                </CategoryTag>
            ))}
        </span>
    );
}

function DecisionBadge({ decision }: { decision: string | null }) {
    const { t } = useTranslations();

    if (!decision) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span
            className={cn(
                'inline-flex h-6 items-center rounded-full px-2.5 text-caption font-semibold',
                decision === 'handed_over'
                    ? 'bg-handover-soft text-handover-ink'
                    : 'bg-success-soft text-success',
            )}
        >
            {t(`assistant.decisions.${decision}`)}
        </span>
    );
}

function BrainPanel({
    brain,
    knowledge,
}: {
    brain: Props['brain'];
    knowledge: Props['knowledge'];
}) {
    const { t } = useTranslations();

    return (
        <Panel title={t('assistant.brain.title')}>
            <div className="flex flex-col gap-3 text-body-sm">
                <div className="flex items-center gap-2">
                    {brain.healthy ? (
                        <CircleCheck className="size-4 text-success" />
                    ) : (
                        <CircleX className="size-4 text-danger" />
                    )}
                    <span className="font-semibold">
                        {t(`assistant.brain.${brain.driver}`)}
                    </span>
                    <span
                        className={cn(
                            'ms-auto text-caption',
                            brain.healthy ? 'text-success' : 'text-danger',
                        )}
                    >
                        {brain.healthy
                            ? t('assistant.brain.healthy')
                            : t('assistant.brain.unhealthy')}
                    </span>
                </div>
                <bdi
                    dir="ltr"
                    className="font-mono text-caption text-muted-foreground"
                >
                    {brain.model}
                </bdi>
                {brain.embedding_model && (
                    <span className="text-caption text-muted-foreground">
                        {t('assistant.brain.embedding', {
                            model: brain.embedding_model,
                        })}
                    </span>
                )}
                <p className="text-caption text-muted-foreground">
                    {t('assistant.brain.rules', {
                        threshold: `${Math.round(brain.threshold * 100)}%`,
                        seconds: String(brain.timeout),
                    })}
                </p>
                <p className="border-t pt-3 text-caption text-muted-foreground tabular">
                    {knowledge.embedded === null
                        ? t('assistant.brain.knowledge', {
                              active: String(knowledge.active),
                          })
                        : t('assistant.brain.embedded', {
                              count: String(knowledge.embedded),
                              active: String(knowledge.active),
                          })}
                </p>
                {brain.driver === 'simulator' && (
                    <p className="rounded-xl bg-muted px-3 py-2 text-caption text-muted-foreground">
                        {t('assistant.brain.simulator_hint')}
                    </p>
                )}
            </div>
        </Panel>
    );
}

function RepliesForm({ settings }: { settings: Props['settings'] }) {
    const { t } = useTranslations();
    const form = useForm(settings);

    const save = (event: FormEvent) => {
        event.preventDefault();
        form.put(update.url(), { preserveScroll: true });
    };

    const field = (
        key: 'greeting' | 'out_of_scope_reply' | 'handover_reply',
        label: string,
        hint?: string,
    ) => (
        <div className="grid gap-1.5">
            <Label htmlFor={`assistant-${key}`}>{label}</Label>
            <Textarea
                id={`assistant-${key}`}
                dir="auto"
                rows={3}
                value={form.data[key]}
                onChange={(event) => form.setData(key, event.target.value)}
            />
            {hint && (
                <p className="text-caption text-muted-foreground">{hint}</p>
            )}
            <InputError message={form.errors[key]} />
        </div>
    );

    return (
        <Panel title={t('assistant.replies.title')}>
            <form onSubmit={save} className="flex flex-col gap-4">
                {field('greeting', t('assistant.replies.greeting'))}
                {field(
                    'out_of_scope_reply',
                    t('assistant.replies.out_of_scope'),
                )}
                {field(
                    'handover_reply',
                    t('assistant.replies.handover'),
                    t('assistant.replies.handover_hint'),
                )}
                <Button
                    type="submit"
                    className="self-start"
                    disabled={form.processing || !form.isDirty}
                >
                    {form.processing && <Spinner />}
                    {t('assistant.save')}
                </Button>
            </form>
        </Panel>
    );
}

function SummaryPanel({ summary }: { summary: Props['summary'] }) {
    const { t } = useTranslations();
    const duration = useDuration();
    const percent = (value: number | null) =>
        value === null ? '—' : `${value}%`;

    return (
        <Panel title={t('assistant.summary.title')}>
            <div className="grid gap-4 md:grid-cols-2">
                {(['live', 'shadow'] as const).map((mode) => {
                    const row = summary[mode];

                    return (
                        <div
                            key={mode}
                            className="flex flex-col gap-3 rounded-xl bg-sunken p-4"
                        >
                            <span className="text-body-sm font-semibold">
                                {t(`assistant.summary.${mode}`)}
                            </span>
                            <dl className="grid grid-cols-2 gap-3">
                                <Fact label={t('assistant.summary.turns')}>
                                    <span className="text-heading tabular">
                                        {row.turns}
                                    </span>
                                </Fact>
                                <Fact label={t('assistant.summary.settled')}>
                                    <span className="text-heading tabular">
                                        {percent(row.settled)}
                                    </span>
                                </Fact>
                                <Fact
                                    label={t('assistant.summary.handed_over')}
                                >
                                    <span className="tabular">
                                        {percent(row.handed_over)}
                                    </span>
                                </Fact>
                                <Fact label={t('assistant.summary.agreement')}>
                                    <span className="tabular">
                                        {percent(row.agreement)}
                                    </span>
                                    {row.judged > 0 && (
                                        <span className="block text-caption font-normal text-muted-foreground">
                                            {t('assistant.summary.judged', {
                                                count: String(row.judged),
                                            })}
                                        </span>
                                    )}
                                </Fact>
                                <Fact label={t('assistant.summary.failed')}>
                                    <span
                                        className={cn(
                                            'tabular',
                                            row.failed > 0 && 'text-danger',
                                        )}
                                    >
                                        {row.failed}
                                    </span>
                                </Fact>
                                <Fact label={t('assistant.summary.latency')}>
                                    <span className="tabular">
                                        {row.median_ms === null
                                            ? '—'
                                            : duration(
                                                  Math.max(
                                                      1,
                                                      Math.round(
                                                          row.median_ms / 1000,
                                                      ),
                                                  ),
                                              )}
                                    </span>
                                </Fact>
                            </dl>
                        </div>
                    );
                })}
            </div>
        </Panel>
    );
}

function RunsTable({ runs }: { runs: Run[] }) {
    const { t, locale } = useTranslations();

    return (
        <section className="flex flex-col overflow-hidden rounded-2xl border bg-card">
            <h2 className="p-4 pb-3 text-title font-bold">
                {t('assistant.runs.title')}
            </h2>
            {runs.length === 0 ? (
                <p className="px-4 pb-6 text-body-sm text-muted-foreground">
                    {t('assistant.runs.empty')}
                </p>
            ) : (
                <ul className="divide-y border-t">
                    {runs.map((run) => (
                        <li
                            key={run.id}
                            className="grid gap-3 p-4 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1.3fr)]"
                        >
                            <div className="flex min-w-0 flex-col gap-1">
                                <span className="flex flex-wrap items-center gap-2 text-caption text-muted-foreground">
                                    <span className="rounded-full bg-muted px-2 py-0.5">
                                        {t(`assistant.runs.mode.${run.mode}`)}
                                    </span>
                                    <span className="tabular">
                                        {formatDate(run.at, locale)}{' '}
                                        {formatTime(run.at, locale)}
                                    </span>
                                    {run.latency_ms !== null && (
                                        <span dir="ltr" className="tabular">
                                            {(run.latency_ms / 1000).toFixed(1)}{' '}
                                            s
                                        </span>
                                    )}
                                </span>
                                {run.customer ? (
                                    <span className="font-semibold">
                                        {run.customer}
                                    </span>
                                ) : (
                                    run.phone && (
                                        <bdi
                                            dir="ltr"
                                            className="self-start font-semibold tabular"
                                        >
                                            {formatPhone(run.phone)}
                                        </bdi>
                                    )
                                )}
                                <p
                                    dir="auto"
                                    className="line-clamp-2 text-body-sm"
                                >
                                    {run.message}
                                </p>
                                {run.conversation_id && (
                                    <Link
                                        href={conversation(run.conversation_id)}
                                        className="self-start text-caption font-semibold text-primary hover:underline dark:text-tag-blue-foreground"
                                    >
                                        {t('assistant.runs.open')}
                                    </Link>
                                )}
                            </div>
                            <div className="flex flex-col gap-2">
                                <span className="flex flex-wrap items-center gap-2">
                                    <DecisionBadge decision={run.decision} />
                                    {run.intent && (
                                        <span className="text-caption text-muted-foreground">
                                            {t(
                                                `assistant.intents.${run.intent}`,
                                            )}
                                            {run.confidence !== null &&
                                                ` · ${Math.round(run.confidence * 100)}%`}
                                        </span>
                                    )}
                                </span>
                                {run.status === 'failed' && (
                                    <span className="text-caption text-danger">
                                        {run.error}
                                    </span>
                                )}
                                {run.reply && (
                                    <p
                                        dir="auto"
                                        className="line-clamp-3 text-caption text-muted-foreground"
                                    >
                                        {run.reply}
                                    </p>
                                )}
                            </div>
                            <div className="flex flex-col gap-1.5 text-caption">
                                <span className="flex flex-wrap items-center gap-1.5">
                                    <span className="w-14 shrink-0 text-muted-foreground">
                                        {t('assistant.runs.suggested')}
                                    </span>
                                    <TagList tags={run.tags} />
                                </span>
                                <span className="flex flex-wrap items-center gap-1.5">
                                    <span className="w-14 shrink-0 text-muted-foreground">
                                        {t('assistant.runs.team')}
                                    </span>
                                    {run.team_tags === null ? (
                                        <span className="text-muted-foreground">
                                            {t('assistant.runs.pending')}
                                        </span>
                                    ) : (
                                        <span className="flex flex-wrap gap-1">
                                            {run.team_tags.length === 0
                                                ? '—'
                                                : run.team_tags.map((tag) => (
                                                      <CategoryTag
                                                          key={tag}
                                                          tone="slate"
                                                      >
                                                          {tag}
                                                      </CategoryTag>
                                                  ))}
                                        </span>
                                    )}
                                </span>
                                {run.agrees !== null && (
                                    <span
                                        className={cn(
                                            'inline-flex items-center gap-1 self-start rounded-full px-2 py-0.5 font-semibold',
                                            run.agrees
                                                ? 'bg-success-soft text-success'
                                                : 'bg-warning-soft text-warning',
                                        )}
                                    >
                                        {run.agrees ? (
                                            <CircleCheck className="size-3.5" />
                                        ) : (
                                            <CircleX className="size-3.5" />
                                        )}
                                        {run.agrees
                                            ? t('assistant.runs.agrees')
                                            : t('assistant.runs.differs')}
                                    </span>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
