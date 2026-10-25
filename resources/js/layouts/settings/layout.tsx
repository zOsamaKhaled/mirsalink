import { Link } from '@inertiajs/react';
import { KeyRound, Palette, UserRound } from 'lucide-react';
import type { PropsWithChildren } from 'react';

import Heading from '@/components/heading';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslations } from '@/hooks/use-translations';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { t } = useTranslations();

    const items: NavItem[] = [
        { title: t('settings.nav.profile'), href: edit(), icon: UserRound },
        {
            title: t('settings.nav.security'),
            href: editSecurity(),
            icon: KeyRound,
        },
        {
            title: t('settings.nav.appearance'),
            href: editAppearance(),
            icon: Palette,
        },
    ];

    return (
        <div className="px-4 py-8 sm:px-6">
            <Heading
                title={t('settings.title')}
                description={t('settings.description')}
            />

            <div className="flex flex-col gap-8 lg:flex-row lg:gap-12">
                <nav
                    className="flex gap-1 overflow-x-auto lg:w-52 lg:shrink-0 lg:flex-col"
                    aria-label={t('settings.title')}
                >
                    {items.map((item) => {
                        const current = isCurrentOrParentUrl(item.href);

                        return (
                            <Link
                                key={toUrl(item.href)}
                                href={item.href}
                                aria-current={current ? 'page' : undefined}
                                className={cn(
                                    'flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-body-sm transition-colors',
                                    current
                                        ? 'bg-accent font-bold text-accent-foreground'
                                        : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground',
                                )}
                            >
                                {item.icon && <item.icon className="size-4" />}
                                {item.title}
                            </Link>
                        );
                    })}
                </nav>

                <div className="min-w-0 flex-1">
                    <section className="flex max-w-xl flex-col gap-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
