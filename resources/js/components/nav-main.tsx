import { Link } from '@inertiajs/react';

import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslations } from '@/hooks/use-translations';
import type { NavItem } from '@/types';

/** The current page is the one white row on the navy sidebar. */
const ACTIVE =
    'data-[active=true]:bg-sidebar-primary data-[active=true]:font-bold data-[active=true]:text-sidebar-primary-foreground';

export function NavMain({ label, items }: { label: string; items: NavItem[] }) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const { t } = useTranslations();

    return (
        <SidebarGroup className="px-2 py-1">
            <SidebarGroupLabel className="text-sidebar-foreground/60">
                {label}
            </SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) =>
                    item.soon ? (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton aria-disabled>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </SidebarMenuButton>
                            <SidebarMenuBadge className="rounded-full px-2 text-[0.6875rem] font-normal text-sidebar-foreground/60">
                                {t('common.soon')}
                            </SidebarMenuBadge>
                        </SidebarMenuItem>
                    ) : (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={
                                    item.section
                                        ? isCurrentOrParentUrl(item.section)
                                        : isCurrentUrl(item.href)
                                }
                                tooltip={{ children: item.title }}
                                className={ACTIVE}
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}
