import { Link } from '@inertiajs/react';
import {
    BarChart3,
    BookOpenText,
    Contact,
    FileText,
    Inbox,
    LayoutGrid,
    Megaphone,
    Tags,
    Users,
} from 'lucide-react';

import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTranslations } from '@/hooks/use-translations';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

/*
 * Every section the platform will have, in the order the plan builds them.
 * The ones not built yet show greyed with "Soon", so the team sees where
 * the platform is going; each turns into a link as its phase lands.
 */

export function AppSidebar() {
    const { t, dir } = useTranslations();

    const workspace: NavItem[] = [
        { title: t('nav.dashboard'), href: dashboard(), icon: LayoutGrid },
        { title: t('nav.inbox'), href: '#', icon: Inbox, soon: true },
        { title: t('nav.contacts'), href: '#', icon: Contact, soon: true },
        { title: t('nav.broadcasts'), href: '#', icon: Megaphone, soon: true },
        { title: t('nav.templates'), href: '#', icon: FileText, soon: true },
        {
            title: t('nav.knowledge'),
            href: '#',
            icon: BookOpenText,
            soon: true,
        },
        { title: t('nav.reports'), href: '#', icon: BarChart3, soon: true },
    ];

    const management: NavItem[] = [
        { title: t('nav.team'), href: '#', icon: Users, soon: true },
        { title: t('nav.routing'), href: '#', icon: Tags, soon: true },
    ];

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            side={dir === 'rtl' ? 'right' : 'left'}
        >
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="hover:bg-transparent active:bg-transparent"
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain label={t('nav.workspace')} items={workspace} />
                <NavMain label={t('nav.management')} items={management} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
