import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { Check, Clock3, Inbox, CircleCheckBig } from 'lucide-react';
import type { ReactNode } from 'react';

import { ForwardArrow } from '@/components/direction-icons';
import { HandoverMark } from '@/components/inbox/handover-mark';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as teamMembers } from '@/routes/team/members';

/*
 * Home before there is anything to count: the figures the inbox will fill
 * in, shown empty rather than as made-up zeros, and the setup steps in the
 * order the platform is being built. A step a supervisor can take now
 * links to its page; the rest say "Soon" until their phase lands.
 */

type SetupStep = {
    key: string;
    done: boolean;
    href?: ReturnType<typeof teamMembers>;
};

export default function Dashboard({ setup }: { setup: { team: boolean } }) {
    const { auth } = usePage().props;
    const { t } = useTranslations();
    const firstName = auth.user.name.split(' ')[0];

    const steps: SetupStep[] = [
        { key: 'account', done: true },
        {
            key: 'team',
            done: setup.team,
            href: auth.can.manageTeam ? teamMembers() : undefined,
        },
        { key: 'routing', done: false },
        { key: 'contacts', done: false },
        { key: 'whatsapp', done: false },
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
                            label={t('dashboard.stats.open')}
                        />
                        <StatTile
                            icon={<HandoverMark className="size-4" />}
                            label={t('dashboard.stats.waiting')}
                        />
                        <StatTile
                            icon={<Clock3 className="size-4" />}
                            label={t('dashboard.stats.first_response')}
                        />
                        <StatTile
                            icon={<CircleCheckBig className="size-4" />}
                            label={t('dashboard.stats.closed_today')}
                        />
                    </div>
                    <p className="text-caption text-muted-foreground">
                        {t('dashboard.stats.empty')}
                    </p>
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

function StatTile({ icon, label }: { icon: ReactNode; label: string }) {
    return (
        <div className="flex flex-col gap-3 rounded-xl border bg-card p-4">
            <span className="flex items-center gap-2 text-body-sm text-muted-foreground">
                {icon}
                {label}
            </span>
            <span
                className="text-display leading-none font-extrabold text-muted-foreground/50"
                aria-hidden
            >
                —
            </span>
        </div>
    );
}
