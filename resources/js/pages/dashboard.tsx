import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { Check, Clock3, Inbox, CircleCheckBig } from 'lucide-react';
import type { ReactNode } from 'react';

import { ForwardArrow } from '@/components/direction-icons';
import { HandoverMark } from '@/components/inbox/handover-mark';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useDuration } from '@/hooks/use-duration';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { create as importContacts } from '@/routes/contacts/import';
import { index as inbox } from '@/routes/inbox';
import { index as reports } from '@/routes/reports';
import { index as routing } from '@/routes/routing';
import { index as teamMembers } from '@/routes/team/members';
import { index as whatsapp } from '@/routes/whatsapp';

/*
 * Home: today's figures (the team's for a supervisor, their own for an
 * agent), shown empty rather than as made-up zeros until the first
 * conversation, and the setup steps in the order the platform is built. A
 * step a supervisor can take now links to its page; the rest say "Soon".
 */

type SetupStep = {
    key: string;
    done: boolean;
    href?: ReturnType<typeof teamMembers>;
};

type Stats = {
    open: number;
    waiting: number;
    reply: number | null;
    closed: number;
    mine: boolean;
    started: boolean;
};

export default function Dashboard({
    stats,
    setup,
}: {
    stats: Stats;
    setup: {
        team: boolean;
        routing: boolean;
        contacts: boolean;
        whatsapp: boolean;
    };
}) {
    const { auth } = usePage().props;
    const { t } = useTranslations();
    const duration = useDuration();
    const shown = (value: string) => (stats.started ? value : null);
    const firstName = auth.user.name.split(' ')[0];

    const steps: SetupStep[] = [
        { key: 'account', done: true },
        {
            key: 'team',
            done: setup.team,
            href: auth.can.manageTeam ? teamMembers() : undefined,
        },
        {
            key: 'routing',
            done: setup.routing,
            href: auth.can.manageTeam ? routing() : undefined,
        },
        {
            key: 'contacts',
            done: setup.contacts,
            href: auth.can.manageTeam ? importContacts() : undefined,
        },
        {
            key: 'whatsapp',
            done: setup.whatsapp,
            href: auth.can.manageTeam ? whatsapp() : undefined,
        },
    ];

    setLayoutProps({
        breadcrumbs: [{ title: t('dashboard.title'), href: dashboard() }],
    });

    return (
        <>
            <Head title={t('dashboard.title')} />

            <div className="flex flex-col gap-10 px-4 py-8 sm:px-6">
                <header className="flex flex-col gap-2">
                    <h1 className="text-display font-extrabold">
                        {t('dashboard.greeting', { name: firstName })}
                    </h1>
                    <p className="max-w-[60ch] text-body text-muted-foreground">
                        {t('dashboard.subtitle')}
                    </p>
                </header>

                <section className="flex flex-col gap-3">
                    <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
                        <StatTile
                            icon={<Inbox className="size-4" />}
                            label={t(
                                stats.mine
                                    ? 'dashboard.stats.open_mine'
                                    : 'dashboard.stats.open',
                            )}
                            value={shown(String(stats.open))}
                            href={inbox()}
                        />
                        <StatTile
                            icon={<HandoverMark className="size-4" />}
                            label={t('dashboard.stats.waiting')}
                            value={shown(String(stats.waiting))}
                            href={inbox({ query: { tab: 'unassigned' } })}
                        />
                        <StatTile
                            icon={<Clock3 className="size-4" />}
                            label={t(
                                stats.mine
                                    ? 'dashboard.stats.reply_mine'
                                    : 'dashboard.stats.reply',
                            )}
                            value={shown(duration(stats.reply))}
                        />
                        <StatTile
                            icon={<CircleCheckBig className="size-4" />}
                            label={t(
                                stats.mine
                                    ? 'dashboard.stats.closed_mine'
                                    : 'dashboard.stats.closed_today',
                            )}
                            value={shown(String(stats.closed))}
                        />
                    </div>
                    {stats.started ? (
                        auth.can.manageTeam && (
                            <Link
                                href={reports()}
                                className="inline-flex items-center gap-1.5 self-start text-body-sm font-semibold text-primary hover:underline dark:text-tag-blue-foreground"
                            >
                                {t('dashboard.stats.reports')}
                                <ForwardArrow className="size-4" />
                            </Link>
                        )
                    ) : (
                        <p className="text-caption text-muted-foreground">
                            {t('dashboard.stats.empty')}
                        </p>
                    )}
                </section>

                {/* Setting the platform up is the supervisors' job. */}
                {auth.can.manageTeam && (
                    <section className="flex max-w-3xl flex-col gap-5 rounded-2xl border bg-card p-5 sm:p-6">
                        <div className="flex flex-col gap-1">
                            <h2 className="text-heading font-bold">
                                {t('dashboard.setup.title')}
                            </h2>
                            <p className="text-body-sm text-muted-foreground">
                                {t('dashboard.setup.description')}
                            </p>
                        </div>

                        <ol className="flex flex-col">
                            {steps.map((step, index) => (
                                <li
                                    key={step.key}
                                    className="flex items-start gap-4 border-t py-4 first:border-t-0 first:pt-0 last:pb-0"
                                >
                                    <span
                                        className={cn(
                                            'grid size-8 shrink-0 place-items-center rounded-full text-body-sm font-bold tabular',
                                            step.done
                                                ? 'bg-success-soft text-success'
                                                : 'bg-muted text-muted-foreground',
                                        )}
                                        aria-hidden
                                    >
                                        {step.done ? (
                                            <Check className="size-4" />
                                        ) : (
                                            index + 1
                                        )}
                                    </span>
                                    <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                        <h3 className="text-body font-bold">
                                            {t(
                                                `dashboard.setup.steps.${step.key}.title`,
                                            )}
                                        </h3>
                                        <p className="text-body-sm text-muted-foreground">
                                            {t(
                                                `dashboard.setup.steps.${step.key}.body`,
                                            )}
                                        </p>
                                    </div>
                                    {step.done ? (
                                        <Badge
                                            variant="success"
                                            className="rounded-full"
                                        >
                                            {t('dashboard.setup.done')}
                                        </Badge>
                                    ) : step.href ? (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link href={step.href}>
                                                {t('dashboard.setup.start')}
                                                <ForwardArrow />
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Badge
                                            variant="neutral"
                                            className="rounded-full"
                                        >
                                            {t('common.soon')}
                                        </Badge>
                                    )}
                                </li>
                            ))}
                        </ol>
                    </section>
                )}
            </div>
        </>
    );
}

function StatTile({
    icon,
    label,
    value,
    href,
}: {
    icon: ReactNode;
    label: string;
    /** Null before there is anything to count. */
    value: string | null;
    href?: ReturnType<typeof inbox>;
}) {
    const content = (
        <>
            <span className="flex items-center gap-2 text-body-sm text-muted-foreground">
                {icon}
                {label}
            </span>
            {value === null ? (
                <span
                    className="text-display leading-none font-extrabold text-muted-foreground/50"
                    aria-hidden
                >
                    —
                </span>
            ) : (
                <span className="text-display leading-none font-extrabold tabular">
                    {value}
                </span>
            )}
        </>
    );

    const tile = 'flex flex-col gap-3 rounded-xl border bg-card p-4';

    return href && value !== null ? (
        <Link
            href={href}
            className={cn(tile, 'transition-colors hover:bg-accent/50')}
        >
            {content}
        </Link>
    ) : (
        <div className={tile}>{content}</div>
    );
}
