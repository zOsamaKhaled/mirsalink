import { Link, router } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';

import { AgentStatusDot } from '@/components/inbox/agent-status';

import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { useTranslations } from '@/hooks/use-translations';
import { logout } from '@/routes';
import { update as updateAvailability } from '@/routes/availability';
import { edit } from '@/routes/profile';
import type { Availability, User } from '@/types';

const AVAILABILITIES: Availability[] = ['available', 'busy', 'away'];

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const { t } = useTranslations();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuLabel className="text-caption font-normal text-muted-foreground">
                {t('availability.label')}
            </DropdownMenuLabel>
            <DropdownMenuRadioGroup
                value={user.availability}
                onValueChange={(availability) =>
                    router.patch(
                        updateAvailability.url(),
                        { availability },
                        { preserveScroll: true, preserveState: true },
                    )
                }
            >
                {AVAILABILITIES.map((availability) => (
                    <DropdownMenuRadioItem
                        key={availability}
                        value={availability}
                        onSelect={(event) => event.preventDefault()}
                        className="gap-2"
                    >
                        <AgentStatusDot
                            status={availability}
                            className="ring-0"
                        />
                        {t(`availability.${availability}`)}
                    </DropdownMenuRadioItem>
                ))}
            </DropdownMenuRadioGroup>
            <p className="px-2 pb-1.5 text-caption text-muted-foreground">
                {t('availability.hint')}
            </p>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="flex w-full cursor-pointer items-center gap-2"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings />
                        {t('user_menu.settings')}
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="flex w-full cursor-pointer items-center gap-2"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="rtl:-scale-x-100" />
                    {t('user_menu.logout')}
                </Link>
            </DropdownMenuItem>
        </>
    );
}
