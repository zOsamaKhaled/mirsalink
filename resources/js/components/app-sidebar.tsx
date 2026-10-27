import { Link, usePage } from '@inertiajs/react';
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
import { index as broadcasts } from '@/routes/broadcasts';
import { index as contacts } from '@/routes/contacts';
import { index as inbox } from '@/routes/inbox';
import { index as knowledge } from '@/routes/knowledge';
import { index as reports } from '@/routes/reports';
import { index as routing } from '@/routes/routing';
import { index as teamMembers } from '@/routes/team/members';
import { index as templates } from '@/routes/templates';
import type { NavItem } from '@/types';

/*
 * Every section the platform will have, in the order the plan builds them.
 * The ones not built yet show greyed with "Soon", so the team sees where
 * the platform is going; each turns into a link as its phase lands.
 */

export function AppSidebar() {
    const { t, dir } = useTranslations();
    const { auth } = usePage().props;

    const workspace: NavItem[] = [
        { title: t('nav.dashboard'), href: dashboard(), icon: LayoutGrid },
        {
            title: t('nav.inbox'),
            href: inbox(),
            icon: Inbox,
            section: '/inbox',
        },
        {
            title: t('nav.contacts'),
            href: contacts(),
            icon: Contact,
            section: '/contacts',
        },
        // Campaigns are for supervisors.
        ...(auth.can.manageTeam
            ? [
                  {
                      title: t('nav.broadcasts'),
                      href: broadcasts(),
                      icon: Megaphone,
                      section: '/broadcasts',
                  },
              ]
            : []),
        {
            title: t('nav.templates'),
            href: templates(),
            icon: FileText,
            section: '/templates',
        },
        {
            title: t('nav.knowledge'),
            href: knowledge(),
            icon: BookOpenText,
            section: '/knowledge',
        },
        ...(auth.can.manageTeam
            ? [
                  {
                      title: t('nav.reports'),
                      href: reports(),
                      icon: BarChart3,
                      section: '/reports',
                  },
              ]
            : []),
    ];

    // Supervisors only: agents never see the management section.
    const management: NavItem[] = [
        {
            title: t('nav.team'),
            href: teamMembers(),
            icon: Users,
            section: '/team',
        },
        { title: t('nav.routing'), href: routing(), icon: Tags },
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
                {auth.can.manageTeam && (
                    <NavMain label={t('nav.management')} items={management} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
