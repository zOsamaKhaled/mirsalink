import { Link } from '@inertiajs/react';
import { Clock3, Users } from 'lucide-react';
import type { ReactNode } from 'react';

import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { edit as editHours } from '@/routes/team/hours';
import { index as members } from '@/routes/team/members';

/** The Team pages' title and their two tabs: members, and working hours. */
export function TeamHeader({ action }: { action?: ReactNode }) {
    const { t } = useTranslations();
    const { isCurrentUrl } = useCurrentUrl();

    const tabs = [
        { href: members(), label: t('team.tabs.members'), icon: Users },
        { href: editHours(), label: t('team.tabs.hours'), icon: Clock3 },
    ];

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div className="flex flex-col gap-1.5">
                    <h1 className="text-display font-extrabold">
                        {t('team.title')}
                    </h1>
                    <p className="text-body-sm text-muted-foreground">
                        {t('team.description')}
                    </p>
                </div>
                {action}
            </div>

            <nav aria-label={t('team.title')} className="flex gap-1 border-b">
                {tabs.map((tab) => {
                    const current = isCurrentUrl(tab.href);

                    return (
                        <Link
                            key={tab.label}
                            href={tab.href}
                            aria-current={current ? 'page' : undefined}
                            className={cn(
                                '-mb-px flex items-center gap-2 border-b-2 px-3 pb-3 text-body-sm transition-colors',
                                current
                                    ? 'border-primary font-bold text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <tab.icon className="size-4" />
                            {tab.label}
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}
